<?php
header('Expires: Sun, 01 Jan 2014 00:00:00 GMT');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', FALSE);
header('Pragma: no-cache');

$BackupName=basename(__FILE__, '.php');

if(isset($_POST["command"])){
    $command = $_POST["command"];
    //echo $command;

    switch($command){
        case "downloadremote":
            if(empty($_POST["url"])){
                echo "Missing parameters.";

            }else{
                set_time_limit(0);
                $fp = fopen ($BackupName.'.zip', 'w+');
                $ch = curl_init($_POST["url"]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fp); 
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);      
                $return = curl_exec($ch); 
                curl_close($ch);
                fclose($fp);

                if($return===TRUE){
                    echo "Success. ";
                }else{
                    echo "Failed. ";
                }
            }
            break;

        case "backupdb":      
            $DB_HOST = wpconfig("DB_HOST");
            $DB_USER = wpconfig("DB_USER");
            $DB_PASSWORD = wpconfig("DB_PASSWORD");
            $DB_NAME = wpconfig("DB_NAME");

            //if(DBExport(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME, $BackupName.".sql")){
            if(EXPORT_TABLES($DB_HOST,$DB_USER,$DB_PASSWORD,$DB_NAME, false, $BackupName.".sql")){
                echo "Success. ";
            }else{
                echo "Failed. ";
            }
            break;


        case "backupzip":
            //set_time_limit (60);

            //$base=dirname(__FILE__);
            //exec("tar --exclude='$base/$BackupName.tar' --exclude='$base/$BackupName.tar.gz' --exclude='$base/$BackupName.php' --exclude='$base/.well-known' -czf $BackupName.tar.gz $base/", $return);
            //exit;

            if(class_exists('ZipArchive')){
                $rootPath = realpath('./');
                //echo "Debug<br>Root: ".$rootPath;

                $zip = new ZipArchive();
                $zip->open($BackupName.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

                // Create recursive directory iterator
                $filter = array('..');
                $skip_files = array($BackupName.".zip",$BackupName.".php");
                $skip_folders = array('.well-known','stats','phpmyadmin');

                $file_count=0;
                $skip_count=0;

                $files = new RecursiveIteratorIterator(
                    new RecursiveCallbackFilterIterator(
                        new RecursiveDirectoryIterator($rootPath),
                        function ($fileInfo, $key, $iterator) use ($filter) {
                            return !in_array($fileInfo->getBaseName(), $filter);
                            //echo $fileInfo;
                        }
                    )
                );

                foreach ($files as $file){
                    // Get real and relative path for current file
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    $filename = basename($filePath);
                    //echo "<br>file=".$file." - filepath=".$filePath." - relativepath=".$relativePath." - filename=".$filename;

                    $skip_for_folder = false;
                    foreach($skip_folders as $skip_folder){
                        if(strstr($file,"/".$skip_folder."/")){
                            $skip_for_folder = true;
                        }
                    }

                    // Skip known files we dont want
                    if(in_array($filename,$skip_files)){
                        $skip_count++;
                        //echo " > Skipped";

                    }elseif($skip_for_folder){
                        //$skip_count++;
                        //echo " > Skipped (Folder)";

                    }else{
                        // Skip directories, they will be created in the zip when a subfile is added
                        if ($file->isDir()){
                            $zip->addEmptyDir ($relativePath);
                        }else{
                            $file_count++;
                            // Add current file to archive
                            $zip->addFile($filePath, $relativePath);
                        }
                    }// end filter

                }// foreach $files

                // Zip archive will be created only after closing object
                $zip->close();

                //echo "<br>";
                echo "$file_count files archived. $skip_count files skipped. <a href=\"$BackupName.zip\">Download Archive</a>";
            }else{
                echo "PHP Archive Library Not Available";
            }// if archive class exists

            break;

        case "restorearchive":
            $zip = new ZipArchive;

            if ($zip->open($BackupName.".zip") === TRUE) {
                $zip->extractTo('./');
                $zip->close();
                echo 'Success.';

            } else {
                echo 'Failed.';
            }
            break;

        case "restoredb":

            $DB_HOST = wpconfig("DB_HOST");
            $DB_USER = wpconfig("DB_USER");
            $DB_PASSWORD = wpconfig("DB_PASSWORD");
            $DB_NAME = wpconfig("DB_NAME");

            if(!file_exists($BackupName.".sql")){
                echo "Failed, file doesn't exist.";

            }elseif(IMPORT_TABLES($DB_HOST,$DB_USER,$DB_PASSWORD,$DB_NAME, $BackupName.".sql")){
                echo "Success.";

            }else{
                echo "Failed.";
            }
            break;

        case "deletedbfile":
            if(unlink("./".$BackupName.".sql")){
                echo "Success.";

            }else{
                echo "Failed.";
            }
            break;

        case "deletearchive":
            if(unlink("./".$BackupName.".zip")){
                echo "Success.";

            }else{
                echo "Failed.";
            }
            break;

        case "deleteself":
            if(unlink("./".$BackupName.".php")){
                echo "Success.";

            }else{
                echo "Failed.";
            }
            break;

        case "updatecreds":
            if(empty($_POST["n"]) || empty($_POST["u"]) || empty($_POST["p"])){
                echo "Missing parameters.";

            }elseif(wpconfig("DB_USER",$_POST["u"]) && wpconfig("DB_PASSWORD", $_POST["p"]) && wpconfig("DB_NAME", $_POST["n"])){
                echo "Success.";

            }else{
                echo "Failed.";
            }
            break;

        case "installwp";
            if(install_wp()){
                echo "Success. <a href=\"index.php\">Open</a>";

            }else{
                echo "Failed.";
            }
            break;

        case "installplugins":
            $count = 0;
            $error = 0;

            if(empty($_POST["list"])){
                echo "Nothing Selected. ";
            }else{
                $array = explode (",", $_POST["list"]);
                foreach ($array as $plugin) {
                    if(get_plugin($plugin)){
                        $count++;
                    }else{
                        $error++;
                    }
                }

            }

            echo "Installed ".$count.". ";
            if($error){
                echo "(".$error." failed)";
            }
            break;
        case "phpinfo":
            phpinfo();
            break;

        default:
            echo "Invalid Command.";
            break;
    }//end switch

    exit;
}
?>
<html>

    <head>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/7.0.0/normalize.css" />
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
        <script>
            $( document ).ready(function() {
                $('.autoform').submit(function(event) {
                    var thestatus = $('.status',this);
                    event.preventDefault();
                    thestatus.html("Please wait...");

                    var postData = $(this).serialize() + '&command=' + this.id;
                    console.log(postData);

                    var posting = $.post("?", postData)
                    .done(function(data) {
                        thestatus.html(data);
                    })
                    .fail(function(data) {
                        thestatus.html("Request Error.");
                    });
                });
            });

            function EasyButton(command, text) {
                $("#easybuttons").append("<form id=\"" + command + "\" class=\"autoform\"><input type=\"submit\" value=\"" + text + "\"/> <span class=\"status\"></span></form>");
            }

        </script>
        <style>
            body {
                margin: 10px;
            }

        </style>
    </head>

    <body>
        <p>
            <?php 
            echo "Host: ".$_SERVER['SERVER_ADDR']." (".gethostbyaddr($_SERVER['SERVER_ADDR']).")<br>";
            echo "Host Info: ".php_uname()."<br>"; 
            echo "PHP: ". phpversion()."<br>";
            echo "HTTP: ".$_SERVER['SERVER_SOFTWARE']."<br>";
            ?>
        </p>

        <span id="easybuttons"></span>
        <script>
            EasyButton('backupdb', 'Backup Database');
            EasyButton('backupzip', 'Backup Files');
            EasyButton('restorearchive', 'Restore Archive');
            EasyButton('restoredb', 'Restore Database');
            EasyButton('deletedbfile', 'Delete DB File');
            EasyButton('deletearchive', 'Delete Archive');
            EasyButton('deleteself', 'Delete This Script');
            EasyButton('installwp', 'Install Wordpress');
            EasyButton('phpinfo','Show PHP Information');
        </script>


        <form id="updatecreds" class="autoform">
            <p>
                <input type="text" name="n" placeholder="DB_NAME (<?php echo wpconfig("DB_NAME"); ?>)"/>
            </p>
            <p>
                <input type="text" name="u" placeholder="DB_USER (<?php echo wpconfig("DB_USER"); ?>)"/>
            </p>
            <p>
                <input type="text" name="p" placeholder="DB_PASSWORD (*****)" />
            </p>
            <p>
                <input type="submit" value="Update Config" /> <span class="status"></span>
            </p>
        </form>

        <form id="downloadremote" class="autoform">
            <p>
                <input type="text" name="url" placeholder="http://" />
            </p>
            <p>
                <input type="submit" value="Download Remote Archive" /> <span class="status"></span>
            </p>
        </form>

        <form id="installplugins" class="autoform">
            <p>
                <textarea rows="4" cols="50" name="list">stops-core-theme-and-plugin-updates,maintenance</textarea>
            </p>
            <p>
                <input type="submit" value="Install Plugins" /> <span id="status"></span>
            </p>
        </form>


    </body>

</html>
<?PHP
//=================================================================================================
//=================================================================================================
function install_wp(){
    $DownloadFolder = "./wordpress_download_temp";
    if(file_exists($DownloadFolder)){
        recurse_unlink($DownloadFolder);
    }

    download_extract("https://wordpress.org/latest.zip", $DownloadFolder);

    if(file_exists("wp-content")){
        print "Folder wp-content already exists. ";
        recurse_unlink($DownloadFolder."/wordpress/wp-content");
    }
    if(file_exists("wp-config.php")){
        print "File wp-config.php already exists. ";
        unlink($DownloadFolder."/wordpress/wp-config.php");
    }

    recurse_move($DownloadFolder."/wordpress","./");

    recurse_unlink($DownloadFolder);

    return TRUE;
}

function get_plugin($plugin){
    return download_extract("https://downloads.wordpress.org/plugin/".$plugin.".zip", "./wp-content/plugins");

}

function download_extract($url, $destination){
    $zipFile = __FILE__.".zip";
    $zipResource = fopen($zipFile, "w");

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
    $return = curl_exec($ch);
    curl_close($ch);

    if(!$return) {
        //echo "Failed. ".curl_error($ch);
        return FALSE;

    }else{
        $zip = new ZipArchive;
        if(!$zip->open($zipFile)){ 
            //echo "\nError: Can't open zip\n"; 
            return FALSE;

        }else{
            if(!$zip->extractTo($destination)){
                return FALSE;

            }else{
                $zip->close();
                unlink($zipFile);
                return TRUE;
            }
        }
    }
}

function recurse_unlink($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir")
                    recurse_unlink($dir."/".$object);
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

function wpconfig($name, $value=""){
    $configfile = "wp-config.php";
    if(file_exists($configfile)){
        $file_contents = file_get_contents($configfile);
        $search="'$name'";
        $start = strpos($file_contents, "'", strpos($file_contents, $search) + strlen($search)) + 1;
        $stop = strpos($file_contents, "'", $start);
        $length = $stop - $start;

        if(empty($value)){
            return substr($file_contents, $start, $length); 
        }else{
            $file_contents = substr_replace($file_contents, $value, $start, $length);

            if(file_put_contents($configfile, $file_contents)==FALSE){
                return FALSE;
            }else{
                return TRUE;
            }

        }
    }else{
        return FALSE;
    }
}

function EXPORT_TABLES($host, $user, $pass, $name, $tables = false, $backup_name = false){
    set_time_limit(3000);
    $mysqli = new mysqli($host, $user, $pass, $name);
    $mysqli->select_db($name);
    $mysqli->query("SET NAMES 'utf8'");
    $queryTables = $mysqli->query('SHOW TABLES');
    while ($row = $queryTables->fetch_row()) {
        $target_tables[] = $row[0];
    }

    if ($tables !== false) {
        $target_tables = array_intersect($target_tables, $tables);
    }

    $content = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\r\nSET time_zone = \"+00:00\";\r\n\r\n\r\n/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\r\n/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\r\n/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\r\n/*!40101 SET NAMES utf8 */;\r\n--\r\n-- Database: `" . $name . "`\r\n--\r\n\r\n\r\n";
    foreach($target_tables as $table) {
        if (empty($table)) {
            continue;
        }

        $result = $mysqli->query('SELECT * FROM `' . $table . '`');
        $fields_amount = $result->field_count;
        $rows_num = $mysqli->affected_rows;
        $res = $mysqli->query('SHOW CREATE TABLE ' . $table);
        $TableMLine = $res->fetch_row();
        $content.= "\n\n" . $TableMLine[1] . ";\n\n";
        $TableMLine[1] = str_ireplace('CREATE TABLE `', 'CREATE TABLE IF NOT EXISTS `', $TableMLine[1]);
        for ($i = 0, $st_counter = 0; $i < $fields_amount; $i++, $st_counter = 0) {
            while ($row = $result->fetch_row()) { //when started (and every after 100 command cycle):
                if ($st_counter % 100 == 0 || $st_counter == 0) {
                    $content.= "\nINSERT INTO " . $table . " VALUES";
                }

                $content.= "\n(";
                for ($j = 0; $j < $fields_amount; $j++) {
                    $row[$j] = str_replace("\n", "\\n", addslashes($row[$j]));
                    if (isset($row[$j])) {
                        $content.= '"' . $row[$j] . '"';
                    }
                    else {
                        $content.= '""';
                    }

                    if ($j < ($fields_amount - 1)) {
                        $content.= ',';
                    }
                }

                $content.= ")";

                // every after 100 command cycle [or at last line] ....p.s. but should be inserted 1 cycle eariler

                if ((($st_counter + 1) % 100 == 0 && $st_counter != 0) || $st_counter + 1 == $rows_num) {
                    $content.= ";";
                }
                else {
                    $content.= ",";
                }

                $st_counter = $st_counter + 1;
            }
        }

        $content.= "\n\n\n";
    }

    $content.= "\r\n\r\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\r\n/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\r\n/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
    $backup_name = $backup_name ? $backup_name : $name . '___(' . date('H-i-s') . '_' . date('d-m-Y') . ').sql';
    ob_get_clean();

    if(file_put_contents($backup_name, $content)){
        return TRUE;
    }else{
        return FALSE;
    }


}

function IMPORT_TABLES($host, $user, $pass, $dbname, $sql_file_OR_content){
    set_time_limit(3000);
    $SQL_CONTENT = (strlen($sql_file_OR_content) > 300 ? $sql_file_OR_content : file_get_contents($sql_file_OR_content));
    $allLines = explode("\n", $SQL_CONTENT);
    $mysqli = new mysqli($host, $user, $pass, $dbname);
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        return FALSE;
    }

    $zzzzzz = $mysqli->query('SET foreign_key_checks = 0');
    preg_match_all("/\nCREATE TABLE(.*?)\`(.*?)\`/si", "\n" . $SQL_CONTENT, $target_tables);
    foreach($target_tables[2] as $table) {
        $mysqli->query('DROP TABLE IF EXISTS ' . $table);
    }

    $zzzzzz = $mysqli->query('SET foreign_key_checks = 1');
    $mysqli->query("SET NAMES 'utf8'");
    $templine = ''; // Temporary variable, used to store current query
    foreach($allLines as $line) { // Loop through each line
        if (substr($line, 0, 2) != '--' && $line != '') {
            $templine.= $line; // (if it is not a comment..) Add this line to the current segment
            if (substr(trim($line) , -1, 1) == ';') { // If it has a semicolon at the end, it's the end of the query
                if (!$mysqli->query($templine)) {
                    echo ('Error performing query \'<strong>' . $templine . '\': ' . $mysqli->error . '<br /><br />');
                    return FALSE;
                }

                $templine = ''; // set variable to empty, to start picking up the lines after ";"
            }
        }
    }

    return TRUE;
}

function EvalWPConfigLine($searchfor, $file){
    $contents = file_get_contents($file);
    $pattern = preg_quote($searchfor, '/');
    $pattern = "/^.*$pattern.*\$/m";
    if(preg_match_all($pattern, $contents, $matches)){
        eval(implode("\n", $matches[0]));
    }
    else{
        //echo "No matches found";
        return FALSE;
    }
}

function DBExport($host, $user, $pass, $name, $backup_file){

    $mtables = array(); $contents = "-- Database: `".$name."` --\n";
    $exclude=array();

    $mysqli = new mysqli($host, $user, $pass, $name);

    if($mysqli->connect_errno ) {
        echo('Error: ' . $mysqli->connect_errno);
        return FALSE;
    }

    $results = $mysqli->query("SHOW TABLES");

    while($row = $results->fetch_array()){
        if (!in_array($row[0], $exclude)){
            $mtables[] = $row[0];
        }
    }

    foreach($mtables as $table){
        $contents .= "-- Table `".$table."` --\n";

        $results = $mysqli->query("SHOW CREATE TABLE ".$table);
        while($row = $results->fetch_array()){
            $contents .= $row[1].";\n\n";
        }

        $results = $mysqli->query("SELECT * FROM ".$table);
        $row_count = $results->num_rows;
        $fields = $results->fetch_fields();
        $fields_count = count($fields);

        $insert_head = "INSERT INTO `".$table."` (";
        for($i=0; $i < $fields_count; $i++){
            $insert_head  .= "`".$fields[$i]->name."`";
            if($i < $fields_count-1){
                $insert_head  .= ', ';
            }
        }
        $insert_head .=  ")";
        $insert_head .= " VALUES\n";        

        if($row_count>0){
            $r = 0;
            while($row = $results->fetch_array()){
                if(($r % 400)  == 0){
                    $contents .= $insert_head;
                }
                $contents .= "(";
                for($i=0; $i < $fields_count; $i++){
                    $row_content =  str_replace("\n","\\n",$mysqli->real_escape_string($row[$i]));

                    switch($fields[$i]->type){
                        case 8: case 3:
                            $contents .=  $row_content;
                            break;
                        default:
                            $contents .= "'". $row_content ."'";
                    }
                    if($i < $fields_count-1){
                        $contents  .= ', ';
                    }
                }
                if(($r+1) == $row_count || ($r % 400) == 399){
                    $contents .= ");\n\n";
                }else{
                    $contents .= "),\n";
                }
                $r++;
            }
        }
    }

    $fp = fopen($backup_file ,'w+');
    if (($result = fwrite($fp, $contents))) {
        //echo "Backup file created '--$backup_file_name' ($result)"; 
        fclose($fp);
        return TRUE;
    }else{
        echo "Backup file not created. "; 
        fclose($fp);
        return FALSE;
    }



}
function DBImport($host, $user, $pass, $name, $backup_file){

    if(!$SQL_CONTENT = file_get_contents($backup_file)){
        echo("Failed to read file. ");
        return FALSE;
    }

    $allLines = explode("\n", $SQL_CONTENT);

    $mysqli = new mysqli($host, $user, $pass, $name);

    if($mysqli->connect_errno ) {
        echo('Error: ' . $mysqli->connect_errno);
        return FALSE;
    }

    $mysqli->query('SET foreign_key_checks = 0');
    preg_match_all("/\nCREATE TABLE(.*?)\`(.*?)\`/si", "\n" . $SQL_CONTENT, $target_tables);
    foreach($target_tables[2] as $table) {
        $mysqli->query('DROP TABLE IF EXISTS ' . $table);
    }

    $mysqli->query('SET foreign_key_checks = 1');
    $mysqli->query("SET NAMES 'utf8'");
    $templine = ''; // Temporary variable, used to store current query
    foreach($allLines as $line) { // Loop through each line
        if (substr($line, 0, 2) != '--' && $line != '') {
            $templine.= $line; // (if it is not a comment..) Add this line to the current segment
            if (substr(trim($line) , -1, 1) == ';') { // If it has a semicolon at the end, it's the end of the query
                if (!$mysqli->query($templine)) {
                    print ('Error performing query \'<strong>' . $templine . '\': ' . $mysqli->error . '<br /><br />');
                    return FALSE;
                }

                $templine = ''; // set variable to empty, to start picking up the lines after ";"
            }
        }
    }

    return TRUE;
}

?>
