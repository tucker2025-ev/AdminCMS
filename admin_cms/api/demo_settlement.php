<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';


// Input params from AJAX
$start_date = $_GET['start_date'] ?? '';
$end_date  = $_GET['end_date'] ?? '';

// Main Query from the View
$query = "SELECT * FROM v_transaction_summary  WHERE DATE(start_time) BETWEEN '$start_date' AND '$end_date' ORDER BY trans_id DESC";

$result = mysqli_query($station_connect, $query);

// Decode response
$response     = json_decode($response_json, true);
$transactions = $response['Message'] ?? [];

// Prepare structure
$grouped = [];
$cpoGstStatusCounts = [];

// Date references
$today        = new DateTime();
$currentMonth = (int)$today->format('m');
$currentYear  = (int)$today->format('Y');

$prevMonth    = (clone $today)->modify('first day of last month');
$prevMonthNum = (int)$prevMonth->format('m');
$prevYear     = (int)$prevMonth->format('Y');

// Determine current month buckets to show
$today_day = (int)$today->format('d');
$show_current_buckets = ($today_day <= 15) ? ['1-15'] : ['1-15', '16-end'];

// Helper function for max settlement status
function maxSettlementStatus($current, $newStatus)
{
    return (strtoupper($current) === 'N' || strtoupper($newStatus) === 'N') ? 'N' : 'Y';
}

foreach ($transactions as $tx) {
    $txDate = DateTime::createFromFormat('Y-m-d H:i:s', $tx['start_time']);
    if (!$txDate) continue;

    $day   = (int)$txDate->format('d');
    $month = (int)$txDate->format('m');
    $year  = (int)$txDate->format('Y');

    $cpo_id     = $tx['cpo_id'] ?? 0;
    $station_id     = $tx['station_id'] ?? '-';
    $station_mobile     = $tx['station_mobile'] ?? '-';


    $cpo        = $tx['cpo_name'] ?? '-';
    $gstin      = $tx['cpo_gstin'] ?? '';
    $gst_status = strtoupper($tx['gst_status'] ?? 'N');

    $status = $tx['settlement_status'] ?? 'Y';
    $units  = (float)($tx['units_consumed'] ?? 0);
    $cost   = (float)($tx['total_cost'] ?? 0);
    $unit_cost   = round((float)($tx['unit_cost'] ?? 0), 2);
    $final_cost   = round((float)($tx['final_cost'] ?? 0), 2);

    $gst   = round(floatval($tx['total_units'] ?? 0) * floatval($tx['unitfare'] ?? 0) * 18 / 100, 2);
    $service_fee_txt = round($tx['unit_cost'] ?? 0, 2) - round($tx['final_cost'] ?? 0, 2);

    // Track GST status per CPO
    if (!isset($cpoGstStatusCounts[$cpo])) {
        $cpoGstStatusCounts[$cpo] = [];
    }
    $cpoGstStatusCounts[$cpo][$gst_status] = ($cpoGstStatusCounts[$cpo][$gst_status] ?? 0) + 1;

    // Initialize CPO structure if not exists
    if (!isset($grouped[$cpo])) {
        $grouped[$cpo] = [
            'cpo_id'       => $cpo_id,
            'station_id'       => $station_id,
            'station_mobile'       => $station_mobile,
            'gstin'        => $gstin,
            'gst_status'   => $gst_status,
            'current_month' => [],
            'previous_month' => [
                '1-15'   => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y'],
                '16-end' => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y']
            ]
        ];

        // Current month buckets
        foreach ($show_current_buckets as $bucket_name) {
            $grouped[$cpo]['current_month'][$bucket_name] = [
                'units' => 0,
                'cost' => 0,
                'gst_amount' => 0,
                'service_fee' => 0,
                'unit_cost' => 0,
                'final_cost' => 0,
                'settlement_status' => 'N'
            ];
        }
    }

    // Determine which bucket
    $bucket = null;
    if ($year === $currentYear && $month === $currentMonth) {
        if ($day <= 15 && in_array('1-15', $show_current_buckets)) {
            $bucket = &$grouped[$cpo]['current_month']['1-15'];
        } elseif ($day > 15 && in_array('16-end', $show_current_buckets)) {
            $bucket = &$grouped[$cpo]['current_month']['16-end'];
        } else {
            continue;
        }
    } elseif ($year === $prevYear && $month === $prevMonthNum) {
        $range = ($day <= 15) ? '1-15' : '16-end';
        $bucket = &$grouped[$cpo]['previous_month'][$range];
    } else {
        continue;
    }

    // Aggregate values
    $bucket['units']      += $units;
    $bucket['cost']       += $cost;
    $bucket['gst_amount'] += $gst;
    $bucket['unit_cost'] += $unit_cost;
    $bucket['service_fee'] += $service_fee_txt;
    $bucket['final_cost'] += $final_cost;
    $bucket['settlement_status'] = maxSettlementStatus($bucket['settlement_status'], $status);
    unset($bucket);
}

// Post-process: apply GST per CPO and calculate total units/cost
// Determine majority GST status per CPO
foreach ($grouped as $cpo => &$months) {
    $majorityGstStatus = 'N'; // default

    if (isset($cpoGstStatusCounts[$cpo])) {
        $counts = $cpoGstStatusCounts[$cpo];
        $countY = $counts['Y'] ?? 0;
        $countN = $counts['N'] ?? 0;
        $majorityGstStatus = ($countY > $countN) ? 'Y' : 'N';
    }

    $months['gst_status'] = $majorityGstStatus;

    // Apply GST only if majority is 'Y'
    foreach (['current_month', 'previous_month'] as $monthType) {
        if (!isset($months[$monthType])) continue;
        foreach ($months[$monthType] as $range => &$values) {
            if ($majorityGstStatus === 'Y') {
                $values['final_cost'] += $values['gst_amount'];
            }
            $values['total_units'] = $values['units'];
            $values['total_cost']  = $values['cost'];
        }
        unset($values);
    }
}
unset($months);


unset($months);

// Output JSON
echo json_encode([
    'status' => true,
    'data'   => $grouped
], JSON_PRETTY_PRINT);
