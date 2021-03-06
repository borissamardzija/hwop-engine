<?php

class WikiBuilder {

  private $header = <<<EOHEADER
<!DOCTYPE html>
<html lang="hr">
  <head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="/css/style.css" />
    <title>%%title%%</title>
  </head>
  <body class="markup">
    <div class="markup-container">
EOHEADER;

  private $footer = <<<EOFOOTER
    </div>
  </body>
</html>
EOFOOTER;

  private $src, $dst, $opt, $files, $cache;

  private $msgs = array(
    "buildCache" => "scaning destination folder for existing markups.",
    "prepareDstFolder" => "rm -rf destination and clone source to destination.",
    "discoverFiles" => "searching for all .wiki files and reading in.",
    "buildWiki" => "build everything."
  );

  /**
   * src and dst are folders.
   */
  public function __construct($src, $dst, $opt) { $this->src = $src;
    $this->dst = $dst;
    $this->opt = $opt;
    $this->initialize();
    $this->engine = new HWOPMarkupEngine();
    foreach ($this->msgs as $method => $msg) {
      $t = microtime(1);
      $this->$method();
      printf("%s done in %.2lf.\n", $method, microtime(1) - $t);
    }
  }

  private function initialize() {
    if ($this->opt['header']) {
      $this->header = file_get_contents($this->opt['header']);
    }
  }

  private function buildCache() {
    $files = shell_exec("find $this->dst -type f -name index.html");
    $files = array_filter(explode("\n", trim($files)));
    foreach ($files as $file) {
      $src = substr($file, 0, -strlen("index.html")) . "source.txt";
      $this->cache[ md5(file_get_contents($src)) ] = file_get_contents($file);
    }
  }

  private function prepareDstFolder() {
    system("rm -rf $this->dst");
    system("cp -r $this->src $this->dst");
  }

  private function discoverFiles() {
    $files = shell_exec("find $this->dst -type f -name \*.wiki");
    $this->files = array_filter(explode("\n", trim($files)));
  }

  private function wikiToHtml($wiki, $in_file, $desc_file) {
    if (isset($this->cache[md5($wiki)])) {
      return $this->cache[md5($wiki)];
    }
    $result = $this->engine->fullRender($wiki, array());
    $meta = $result['metadata'];
    $title = $this->getTitleFromHeaders($meta['headers.toc'], $in_file);
    $html = $this->makeHtml($result, $title);
    echo $desc_file . ": ok.\n";
    return $html;
  }

  private function buildWiki() {
    foreach ($this->files as $in_file) {
      assert(substr($in_file, -5) == ".wiki");
      $out_file = substr($in_file, 0, strlen($in_file) - 5);
      system("mkdir -p $out_file");
      $out_dir = $out_file;
      $out_file .= "/index.html";
      $content = file_get_contents($in_file);
      system("mv $in_file $out_dir/source.txt");
      $desc_file = "  " . substr($in_file, strlen($this->dst));
      try {
        $html = $this->wikiToHtml($content, $in_file, $desc_file);
      } catch (Exception $ex) {
        echo $desc_file . ": " . $ex->getMessage(), "\n";
        continue;
      }
      file_put_contents($out_file, $html);
    }
  }

  private function makeHtml($result, $title) {
    $html = $result['html'];
    $header = str_replace("%%title%%", $title, $this->header);
    return $header . $html . $this->footer;
  }

  private function getTitleFromHeaders($headers, $default) {
    $headers = json_decode($headers, 1);
    foreach ($headers as $name => $pair) {
      list ($level, $label) = $pair;
      if ($level == 1) {
        return $label;
      }
    }
    return $default;
  }

};

