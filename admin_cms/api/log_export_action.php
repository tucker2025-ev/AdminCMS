<?php
session_start();
header('Content-Type: application/json');

// Include files with correct paths
@include __DIR__ . '/../include/dbconnect.php';
@include __DIR__ . '/check_active_status.php';

// Input data
$export_type = $_POST['export_type'] ?? 'Unknown';
$page_name = basename($_POST['page_name'] ?? $_SERVER['PHP_SELF']);
$comment = "User exported data as $export_type";

$request_data = substr(json_encode($_POST), 0, 500);
$response_data = json_encode(["result" => "logged"]);

// Insert query
$log_query = "INSERT INTO api_process_log (
    ip_address, station_id, mobile_id, status, comment, request_data, response_data, page_name
) VALUES (
    '" . $_SERVER['REMOTE_ADDR'] . "',
    '" . ($_SESSION["demo_station_id"] ?? '') . "',
    '" . ($_SESSION["demo_station_mobile"] ?? '') . "',
    'Y',
    '" . mysqli_real_escape_string($station_connect, $comment) . "',
    '" . mysqli_real_escape_string($station_connect, $request_data) . "',
    '" . mysqli_real_escape_string($station_connect, $response_data) . "',
    '" . mysqli_real_escape_string($station_connect, $page_name) . "'
)";
// Execute query
if (!mysqli_query($station_connect, $log_query)) {
    echo json_encode(['error' => 'Log insert failed', 'details' => mysqli_error($station_connect)]);
    exit;
}

echo json_encode(['result' => 'Logged']);
