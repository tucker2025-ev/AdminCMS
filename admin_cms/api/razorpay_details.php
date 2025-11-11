<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Get filters from request
$period = isset($_GET['period']) ? $_GET['period'] : null;

$query = "SELECT * , DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+05:30'), '%Y-%m-%d %H:%i:%s') AS entry_time FROM razorpay_payments WHERE 1=1";

// If filter applied
if ($period) {

    $query .= " AND DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) = '$period'";
}

$query .= " ORDER BY id DESC";

$objResult = mysqli_query($connect, $query);

$results = [];
if ($objResult && mysqli_num_rows($objResult) > 0) {
    while ($row = mysqli_fetch_assoc($objResult)) {
        $results[] = $row;
    }
}

//API Response
if (!empty($results)) {
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
