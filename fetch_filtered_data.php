<?php
session_start();
include 'include/dbconnect.php';

// Base API URL
$url = $base_url . "station/new_history.php";

// Initialize parameters
$params = [
    'station_mobile' => $_GET['station_mobile'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
];

// Add 'station_id' if valid
if (!empty($_GET['station_id']) && $_GET['station_id'] !== 'all') {
    $params['station_id'] = $_GET['station_id']; // supports comma-separated values like: station_1,station_2
}

// Add 'charger_id' if valid
if (!empty($_GET['charger_id']) && $_GET['charger_id'] !== 'all') {
    $params['charger_id'] = $_GET['charger_id']; // supports comma-separated values
}

// Add 'cpo_name' if provided
if (!empty($_GET['cpo_name']) && $_GET['cpo_name'] !== 'all') {
    $params['cpo_name'] = $_GET['cpo_name'];
}

// Map gstInput to gst_status and pass to backend API
if (isset($_GET['gstInput']) && $_GET['gstInput'] !== 'all') {
    $gstInput = $_GET['gstInput'];
    if ($gstInput === 'Y' || $gstInput === 'N') {
        $params['gst_status'] = $gstInput;
    }
}

// Build query and call backend API
$query = http_build_query(array_filter($params));
$full_url = "$url?$query";
// echo $full_url;
$response_json = file_get_contents($full_url);

if ($response_json === FALSE) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch data']);
    exit;
}

// Decode response
$response = json_decode($response_json, true);

// Return final result
header('Content-Type: application/json');
echo json_encode($response);
