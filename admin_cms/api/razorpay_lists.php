<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

$start_date = $_GET['start_date'] ?? null;
$end_date   = $_GET['end_date'] ?? null;
$action     = $_REQUEST['action'] ?? null;  // works for GET/POST

if ($action == 'razorpay_lists') {
    // ---- LIST ----
    $query = "SELECT * from {$station_db}.view_daily_payments WHERE 1=1";

    if ($start_date && $end_date) {
        $query .= " AND DATE(CONVERT_TZ(payment_date, '+00:00', '+05:30')) 
                    BETWEEN '$start_date' AND '$end_date'";
    } else {
        $query .= " AND DATE(CONVERT_TZ(payment_date, '+00:00', '+05:30')) 
                    BETWEEN DATE_FORMAT(CONVERT_TZ(NOW(), '+00:00', '+05:30'), '%Y-%m-01') 
                    AND LAST_DAY(CONVERT_TZ(NOW(), '+00:00', '+05:30'))";
    }

    $query .= " GROUP BY payment_date ORDER BY payment_date DESC";

    $objResult = mysqli_query($connect, $query);
    $results = [];

    if ($objResult && mysqli_num_rows($objResult) > 0) {
        while ($row = mysqli_fetch_assoc($objResult)) {
            $results[] = $row;
        }
    }

    echo json_encode(!empty($results) ? [
        'status' => 'success',
        'data'   => $results
    ] : [
        'status' => 'error',
        'message' => 'No records found.'
    ]);
} elseif ($action == 'save_settlements') {
    // ---- UPDATE ----
    $amount      = $_POST['amount'] ?? null;
    $data_period = $_POST['data_period'] ?? null;

    if (!$amount || !$data_period) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing amount or date period.'
        ]);
        exit;
    }

    // Update all payments for this IST date (txn_date) to settlement_status = 'Y'
    $sql = "UPDATE razorpay_payments SET settlement_status = 'Y' WHERE DATE(CONVERT_TZ(created_at, '+00:00', '+05:30')) = ?";

    $stmt = $connect->prepare($sql);
    $stmt->bind_param("s", $data_period);

    if ($stmt->execute()) {
        echo json_encode([
            'status'  => 'success',
            'message' => 'Settlement updated successfully.'
        ]);
    } else {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Failed to update settlement.'
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid action.'
    ]);
}
