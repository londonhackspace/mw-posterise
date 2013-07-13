<?php

if ($_SERVER['PATH_INFO'] == '' or $_SERVER['PATH_INFO'] == '/') {
  header("HTTP/1.0 400 Bad Request");
  die("Bad request");
}
$page = ltrim($_SERVER['PATH_INFO'], '/');

$wiki = 'http://wiki.london.hackspace.org.uk/view';

function get_page_url($page) {
  global $wiki;
  $page = str_replace(" ", "_", $page);
  return "$wiki/$page";
}

function wiki_to_tex($text) {
  $descriptorspec = array(
     0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w")
  );
  $proc = proc_open("pandoc -f mediawiki -t latex", $descriptorspec, $pipes);
  fwrite($pipes[0], $text);
  fclose($pipes[0]);
  $result = stream_get_contents($pipes[1]);
  $stderr = stream_get_contents($pipes[2]);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $retval = proc_close($proc);
  if ($retval > 0)
    die("pandoc exited with code $retval: $stderr");
  return $result;
}

$url = get_page_url($page);
$tex = file_get_contents('./include/head.tex');
$contents = file_get_contents("$url?action=raw");

if ($contents === false) {
  header("HTTP/1.0 404 Not Found");
  die("Page not found");
}

$tex .= wiki_to_tex($contents);
$tex_url = str_replace("_", "\_", $url);
$tex .= "\\vspace*{\\fill}\n\\editlink{" . $tex_url . "}\n\\end{document}\n";

$out_dir = tempnam(sys_get_temp_dir(), "mw-posterise");
unlink($out_dir);
mkdir($out_dir);

copy("./include/background.pdf", "$out_dir/background.pdf");

chdir($out_dir);
$prefix = "out";
$outfile = "$out_dir/$prefix.tex";
file_put_contents($outfile, $tex);

exec("xelatex -interaction=nonstopmode $prefix.tex", $output, $retval);

if ($retval > 0) {
  cleanup($out_dir);
  die("Error in running xelatex: " . implode($output, "\n"));
}

function cleanup($dir){ 
  $files = glob("$dir/*", GLOB_MARK);
  foreach ($files as $file) {
    unlink($file);
  }
  rmdir($dir);
}

header("Content-Type: application/pdf");

echo file_get_contents("$out_dir/$prefix.pdf");

cleanup($out_dir);
