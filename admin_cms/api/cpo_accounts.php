<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");

header('Content-Type: application/json');

include '../include/dbconnect.php';

// Step 1: Get all CPOs and the sum of their service financials
$query = "SELECT fca_stations.station_name,cpo.gst_status,cpo.cpo_id,cpo.cpo_name, SUM(sl.grand_total) AS total_fees,SUM(sl.paid_amount) AS total_paid,SUM(sl.remaining) AS remaining FROM fca_cpo AS cpo LEFT JOIN $station_db.service_list AS sl ON cpo.cpo_id = sl.cpo_id LEFT JOIN fca_stations ON cpo.cpo_id = fca_stations.cpo_id GROUP BY cpo.cpo_id ORDER BY cpo.sno DESC";
// echo $query ;
$objResult = mysqli_query($connect, $query);

$results = [];

if ($objResult && mysqli_num_rows($objResult) > 0) {
    while ($row = mysqli_fetch_assoc($objResult)) {
        // Optional: convert nulls to 0 if needed
        $row['gst_status'] = $row['gst_status'] ?? '';
        $row['total_fees'] = $row['total_fees'] ?? 0;
        $row['total_paid'] = $row['total_paid'] ?? 0;
        $row['pending'] = $row['total_fees'] - $row['remaining'] ?? 0;
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