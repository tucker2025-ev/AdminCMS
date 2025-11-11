<?php
session_start();
date_default_timezone_set("Asia/Kolkata");

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include '../include/dbconnect.php';

if (!isset($_POST['settlement_id'], $_POST['status'], $_POST['cpo_id'], $_POST['period'], $_POST['invoice_id'], $_POST['list_id'], $_POST['set_amount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$settlement_id = $_POST['settlement_id'];
$cpo_id        = $_POST['cpo_id'];
$status        = $_POST['status'];
$deduction     = isset($_POST['deduction']) ? floatval($_POST['deduction']) : 0;
$period        = trim($_POST['period']);
$set_amount    = trim($_POST['set_amount']);
$list_id       = trim($_POST['list_id']);
$invoice_id    = trim($_POST['invoice_id']);

$set_amounts = explode(',', $set_amount);
$list_ids    = explode(',', $list_id);
$invoice_ids = explode(',', $invoice_id);

if (count($set_amounts) !== count($list_ids) || count($list_ids) !== count($invoice_ids)) {
    echo json_encode(['success' => false, 'message' => 'Mismatch in number of set amounts, list IDs, and invoice IDs.']);
    exit;
}

$parts = explode(" ", $period);
if (count($parts) < 2) {
    echo json_encode(['success' => false, 'message' => 'Invalid period format.']);
    exit;
}

$range     = strtolower(trim($parts[0]));
$monthName = $parts[1];
$year      = date("Y");
$monthNum  = date("m", strtotime($monthName . " " . $year));

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

$startDate = "$year-$monthNum-" . str_pad($startDay, 2, "0", STR_PAD_LEFT) . " 00:00:00";
$endDate   = "$year-$monthNum-" . str_pad($endDay, 2, "0", STR_PAD_LEFT) . " 23:59:59";

// Get all stations for CPO
$query = "SELECT station_id FROM fca_stations WHERE cpo_id = '$cpo_id' ORDER BY sno DESC";
$result = $connect->query($query);
$response = [];
$allUpdated = true;

if ($result && $result->num_rows > 0) {
    for ($i = 0; $i < count($list_ids); $i++) {
        $setAmt          = floatval($set_amounts[$i]);
        $currentListId   = intval($list_ids[$i]);
        $currentInvoiceId = $connect->real_escape_string($invoice_ids[$i]);

        // Get current remaining
        $sel_query = "SELECT remaining FROM $station_db.service_list WHERE list_id = $currentListId AND invoice_id = '$currentInvoiceId'";
        $sel_res = $connect->query($sel_query);
        $row = $sel_res->fetch_assoc();
        $remaining = isset($row['remaining']) ? floatval($row['remaining']) - $setAmt : 0;

        // Update service_list
        $update_query = "UPDATE  $station_db.service_list SET paid_amount = paid_amount + $setAmt, remaining = $remaining, last_updated_date = CURRENT_TIMESTAMP,set_amount = $setAmt WHERE list_id = $currentListId AND invoice_id = '$currentInvoiceId'
        ";
        // echo  $update_query;
        if (!$connect->query($update_query)) {
            $allUpdated = false;
            $response['error'][] = $connect->error;
        }
    }

    // Update summary_report for all stations
    while ($row = $result->fetch_assoc()) {
        $station_id = $row['station_id']; // Keep as string
        $update_sql = "UPDATE summary_report SET settlement_status = 'Y' WHERE station_id = '$station_id' AND start_time BETWEEN '$startDate' AND '$endDate'";

        if (!$connect->query($update_sql)) {
            $allUpdated = false;
            $response['error'][] = $connect->error;
        }
    }


    if ($allUpdated) {
        $response['success'] = true;
        $response['message'] = "Settlement updated for $period ($startDate → $endDate).";
    } else {
        $response['success'] = false;
        $response['message'] = 'Some updates failed.';
    }
} else {
    $response['success'] = false;
    $response['message'] = 'No stations found for this CPO ID.';
}

$response['debug'] = [
    'cpo_id' => $cpo_id,
    'settlement_id' => $settlement_id,
    'invoice_ids' => $invoice_ids,
    'list_ids' => $list_ids,
    'set_amounts' => $set_amounts,
    'start_date' => $startDate,
    'end_date' => $endDate
];

echo json_encode($response);
$connect->close();
