<?php
session_start();

// Include DB connection if needed
include "include/dbconnect.php";

// Clear session variables
$_SESSION["user_mobile"] = '';
$_SESSION["sno"] = '';
$_SESSION["station_id"] = '';

unset($_SESSION["user_mobile"]);
unset($_SESSION["sno"]);
unset($_SESSION["station_id"]);
session_unset(); // Optional
// session_destroy(); // Optional

// Auto-detect base URL (HTTP or HTTPS + host + path)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST']; // localhost or domain
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\'); // current folder path
$base_url = $protocol . $host . $path . '/';

// Redirect to index.php in same folder
header("Location: " . $base_url . "index.php");
exit;
