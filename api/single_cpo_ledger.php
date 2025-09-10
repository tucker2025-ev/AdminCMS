<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Parse JSON input
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

$cpo_id = $data['cpo_id'] ?? null;

if (!$cpo_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing cpo_id parameter.'
    ]);
    exit;
}

// Escape the input
$cpo_id = mysqli_real_escape_string($connect, $cpo_id);

// Query to fetch data
$query = "SELECT * FROM fca_cpo AS cpo LEFT JOIN service_list AS sl ON cpo.cpo_id = sl.cpo_id WHERE cpo.cpo_id = '$cpo_id' ORDER BY cpo.sno DESC";

$objResult = mysqli_query($connect, $query);
$results = [];

if ($objResult && mysqli_num_rows($objResult) > 0) {
    while ($row = mysqli_fetch_assoc($objResult)) {
        $grand_total = floatval($row['grand_total'] ?? 0);
        $paid_amount = floatval($row['paid_amount'] ?? 0);
        $remaining = floatval($row['remaining'] ?? 0);

        $row['grand_total'] = $grand_total;
        $row['paid_amount'] = $paid_amount;
        $row['remaining'] = $remaining;

        $row['pending'] = $grand_total - $paid_amount;

        $results[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No records found.'
    ]);
}
