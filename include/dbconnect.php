<?php
$cms_referred = "tucker";
$cms_color = "#3d56d8";

error_reporting(0);

date_default_timezone_set("Asia/Kolkata");

ini_set('max_execution_time', 450);
set_time_limit(300);

/*	$host_name =  "103.83.81.25";
	$host_user =  "bigtot_cms_user";
	$host_pass =  "yvT8KJESGT@o";
	$host_db   = "bigtot_cms"; */

// $host_name = "15.207.37.132";
 $host_name = "13.233.175.29";
$host_user = "cloud";
$host_pass = "TUCKER_ser_sql";
$host_db = "bigtot_cms";

$base_url = "http://cms.tuckerio.bigtot.in/";

$connect = mysqli_connect($host_name, $host_user, $host_pass, $host_db) or die($connect);
// if($connect)
// {
// 	echo "Success";
// }
// else
// {
// 	echo "Error";
// }

$root_dir = "https://" . $_SERVER['HTTP_HOST'] . "/";
define('ROOT_DIR', $root_dir);

if (!function_exists('writeLog')) {
    function writeLog($message, $logDir = 'logs/', $maxSizeMB = 10)
    {
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $date = date('Y-m-d');
        $baseFilename = "$logDir/log-$date";
        $ext = '.log';

        $index = 0;
        do {
            $filename = $baseFilename . ($index === 0 ? '' : "-$index") . $ext;
            $index++;
        } while (file_exists($filename) && filesize($filename) > ($maxSizeMB * 1024 * 1024));

        $logMessage = "[" . date('Y-m-d H:i:s') . "] $message" . PHP_EOL;
        file_put_contents($filename, $logMessage, FILE_APPEND);
    }
}
?>