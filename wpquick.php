<?PHP
print "<pre>";



print "Downloading latest wordpress zip\n";
$DownloadFolder = "./wordpress_download_temp";
if(file_exists($DownloadFolder)){
  print "Temp folder exists, removing it\n";
  rrmdir($DownloadFolder);
}

download_extract("https://wordpress.org/latest.zip", $DownloadFolder);

if(file_exists($DownloadFolder."/wp-content")){
  print "Folder wp-content already exists, removing it from temp folder\n";
  rrmdir($DownloadFolder."/wordpress/wp-content");
}

print "Moving files from sub solder to parent folder\n";
recurse_move($DownloadFolder."/wordpress","./");

print "Removeing empty wordpress sub directory\n";
rrmdir($DownloadFolder);

//plugins
get_plugin("worker");
get_plugin("stops-core-theme-and-plugin-updates");
get_plugin("maintenance");
get_plugin("w3-total-cache");
get_plugin("simple-history");
get_plugin("wordfence");
get_plugin("ga-in");


print "Removing wpsetup.php (this script)\n";
unlink("wpsetup.php");

print "Done: <a href=\"index.php\">Open Wordpress Setup</a>\n";
print "</pre>";

function get_plugin($plugin){
  download_extract("https://downloads.wordpress.org/plugin/".$plugin.".zip", "./wp-content/plugins");

}

function download_extract($url, $destination){
  $zipFile = __FILE__.".zip";
  $zipResource = fopen($zipFile, "w");

  print "Downloading: ".$url."... ";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_FAILONERROR, true);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  curl_setopt($ch, CURLOPT_BINARYTRANSFER,true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
  curl_setopt($ch, CURLOPT_FILE, $zipResource);
  $page = curl_exec($ch);
  if(!$page) {
    echo "Error :- ".curl_error($ch);
  }
  curl_close($ch);

  $zip = new ZipArchive;
  if($zip->open($zipFile) != "true"){ echo "\nError: Can't open zip\n"; }

  print "Extracting zip... ";
  $zip->extractTo($destination);
  $zip->close();

  print "Removing zip... ";
  unlink($zipFile);

  print "Done\n";
}

function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (filetype($dir."/".$object) == "dir")
           rrmdir($dir."/".$object);
        else unlink   ($dir."/".$object);
      }
    }
    reset($objects);
    rmdir($dir);
  }
 }

function recurse_move($src,$dst) {
  $dir = opendir($src);
  @mkdir($dst);
  while(false !== ( $file = readdir($dir)) ) {
    if (( $file != '.' ) && ( $file != '..' )) {
      if ( is_dir($src . '/' . $file) ) {
        recurse_move($src . '/' . $file,$dst . '/' . $file);
        rmdir($src . '/' . $file);
      }
      else {
        if (copy($src . '/' . $file,$dst . '/' . $file)){
          unlink($src . '/' . $file);
        }
      }
    }
  }
  closedir($dir);
}

?>
