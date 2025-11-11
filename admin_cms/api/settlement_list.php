<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Fetch monthly summary from SQL view
$query = "SELECT * FROM {$station_db}.view_monthly_summary ORDER BY month_key ASC";
$result = mysqli_query($connect, $query);

if (!$result) {
    echo json_encode([
        'status' => false,
        'message' => mysqli_error($connect)
    ]);
    exit;
}

$monthlyData = [];
while ($row = mysqli_fetch_assoc($result)) {
    $monthlyData[] = $row;
}

echo json_encode([
    'status' => 'success',
    'data' => [
        'monthly' => $monthlyData
    ]
]);
