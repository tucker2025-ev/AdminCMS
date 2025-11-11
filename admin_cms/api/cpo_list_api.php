<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Step 1: Get all CPOs and their station count
$query = "
    SELECT cpo.*, COUNT(st.cpo_id) AS total_stations 
    FROM fca_cpo AS cpo 
    LEFT JOIN fca_stations AS st ON cpo.cpo_id = st.cpo_id 
    GROUP BY cpo.cpo_id 
    ORDER BY cpo.sno DESC";

$objResult = mysqli_query($connect, $query);

$results = [];

if ($objResult && mysqli_num_rows($objResult) > 0) {
    while ($cpo = mysqli_fetch_assoc($objResult)) {
        $results[] = $cpo;
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
