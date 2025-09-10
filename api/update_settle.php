<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);

date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Validate required POST params
if (!isset($_POST['settlement_id']) || !isset($_POST['status']) || !isset($_POST['cpo_id']) || !isset($_POST['period'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$settlement_id = $_POST['settlement_id'];
$cpo_id        = $_POST['cpo_id'];
$status        = $_POST['status'];
$deduction     = isset($_POST['deduction']) ? floatval($_POST['deduction']) : 0;
$period        = trim($_POST['period']);

// 🔹 Parse the period into date range
$parts = explode(" ", $period);
if (count($parts) < 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid period format.']);
    exit;
}

$range      = strtolower(trim($parts[0]));  // "1-15" or "16-end"
$monthName  = $parts[1];
$year       = date("Y");

// Map month name → number
$monthNum = date("m", strtotime($monthName . " " . $year));

// Determine start & end days properly
if ($range === "1-15") {
    $startDay = 1;
    $endDay   = 15;
} elseif ($range === "16-end") {
    $startDay = 16;
    $endDay   = cal_days_in_month(CAL_GREGORIAN, intval($monthNum), intval($year));
} else {
    echo json_encode(['success' => false, 'message' => 'Unknown range format: ' . $range]);
    exit;
}

// Build datetime range
$startDate = "$year-$monthNum-" . str_pad($startDay, 2, "0", STR_PAD_LEFT) . " 00:00:00";
$endDate   = "$year-$monthNum-" . str_pad($endDay, 2, "0", STR_PAD_LEFT) . " 23:59:59";

// 🔹 Get all station_ids for the given CPO
$query = "SELECT station_id FROM fca_stations WHERE cpo_id = '$cpo_id' ORDER BY sno DESC";
$result = $connect->query($query);

$response = [];
$allUpdated = false;

if ($result && $result->num_rows > 0) {
    $allUpdated = true;

    while ($row = $result->fetch_assoc()) {
        $station_id = $row['station_id'];

        $update_sql = "UPDATE summary_report SET settlement_status = 'Y' WHERE station_id = '$station_id' AND start_time BETWEEN '$startDate' AND '$endDate'";

        if (!$connect->query($update_sql)) {
            $allUpdated = false;
            break;
        }
    }

    if ($allUpdated) {
        $response = [
            'success'       => true,
            'message'       => "Settlement updated for $period ($startDate → $endDate).",
            'settlement_id' => $settlement_id,
            'cpo_id'        => $cpo_id,
            'status'        => $status,
            'deduction'     => $deduction,
            'update_sql'    => $update_sql,
            'query'         => $query
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Failed to update some records.',
            'error'   => $connect->error
        ];
    }
} else {
    $response = ['success' => false, 'message' => 'No stations found for this CPO ID.'];
}

// 🔹 Debug info (remove in production)
$response['debug'] = [
    'normalized_cpo_id' => $cpo_id,
    'query'             => $query,
    'start'             => $startDate,
    'end'               => $endDate
];

echo json_encode($response);
$connect->close();
