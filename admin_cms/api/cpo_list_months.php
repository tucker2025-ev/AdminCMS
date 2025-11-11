<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Base API URL
$url = $base_url . "station/new_history.php";
$params = array(
    'start_date' => isset($_GET['start_date']) ? $_GET['start_date'] : '',
    'end_date'   => isset($_GET['end_date']) ? $_GET['end_date'] : ''
);

$full_url = $url . "?" . http_build_query(array_filter($params));
$response_json = file_get_contents($full_url);
if ($response_json === FALSE) {
    http_response_code(500);
    echo json_encode(array('error' => 'Failed to fetch data'));
    exit;
}

$response     = json_decode($response_json, true);
$transactions = isset($response['Message']) ? $response['Message'] : array();

// Collect unique CPOs
$cpoNames = array();
foreach ($transactions as $tx) {
    $cpoNames[] = isset($tx['cpo_name']) ? $tx['cpo_name'] : '-';
}
$cpoNames = array_unique($cpoNames);

// Escape CPO names for SQL
$cpoEscaped = array();
foreach ($cpoNames as $name) {
    $cpoEscaped[] = "'" . mysqli_real_escape_string($connect, $name) . "'";
}
$cpoPlaceholders = implode(",", $cpoEscaped);

// Fetch aggregated service data
$query = "SELECT cpo.gst_status, cpo.cpo_id, cpo.cpo_name,
                 sl.grand_total AS total_fees,
                 sl.paid_amount AS total_paid,
                 sl.remaining AS remaining,
                 sl.invoice_id AS invoice_id,
                 sl.list_id AS list_id,
                 sl.set_amount AS set_amount
          FROM fca_cpo AS cpo
          LEFT JOIN $station_db.service_list AS sl ON cpo.cpo_id = sl.cpo_id
          WHERE cpo.cpo_name IN ($cpoPlaceholders) and sl.grand_total != paid_amount
          ORDER BY cpo.sno DESC";
$objResult = mysqli_query($connect, $query);
$cpoAggregates = array();
if ($objResult && mysqli_num_rows($objResult) > 0) {
    while ($row = mysqli_fetch_assoc($objResult)) {
        $row['gst_status'] = $row['gst_status'] ?? '';
        $row['total_fees'] = $row['total_fees'] ?? 0;
        $row['total_paid'] = $row['total_paid'] ?? 0;
        $row['pending']    = $row['total_fees'] - $row['total_paid'];

        $cpo = $row['cpo_name'];

        // Initialize if not exists
        if (!isset($cpoAggregates[$cpo])) {
            $cpoAggregates[$cpo] = [
                'gst_status'  => $row['gst_status'],
                'total_fees'  => 0,
                'total_paid'  => 0,
                'pending'     => 0,
                'invoice_list' => []
            ];
        }

        // Add to totals
        $cpoAggregates[$cpo]['total_fees'] += $row['total_fees'];
        $cpoAggregates[$cpo]['total_paid'] += $row['total_paid'];
        $cpoAggregates[$cpo]['pending']    += $row['pending'];

        // Push invoice row
        $cpoAggregates[$cpo]['invoice_list'][] = [
            'invoice_id'  => $row['invoice_id'],
            'list_id'     => $row['list_id'],
            'set_amount'  => $row['set_amount'],
            'total_fees'  => $row['total_fees'],
            'total_paid'  => $row['total_paid'],
            'pending'     => $row['pending']
        ];
    }
}


// Initialize variables for processing
$grouped = array();
$cpoGstStatusCounts = array();
$today = new DateTime();
$currentMonth = (int)$today->format('m');
$currentYear  = (int)$today->format('Y');
$prevMonth    = (clone $today)->modify('first day of last month');
$prevMonthNum = (int)$prevMonth->format('m');
$prevYear     = (int)$prevMonth->format('Y');
$today_day    = (int)$today->format('d');
$show_current_buckets = ($today_day <= 15) ? array('1-15') : array('1-15', '16-end');

// Helper
function maxSettlementStatus($current, $newStatus)
{
    return (strtoupper($current) === 'N' || strtoupper($newStatus) === 'N') ? 'N' : 'Y';
}

// Process transactions
foreach ($transactions as $tx) {
    $txDate = DateTime::createFromFormat('Y-m-d H:i:s', $tx['start_time']);
    if (!$txDate) continue;

    $day   = (int)$txDate->format('d');
    $month = (int)$txDate->format('m');
    $year  = (int)$txDate->format('Y');

    $cpo        = isset($tx['cpo_name']) ? $tx['cpo_name'] : '-';
    $cpo_id     = isset($tx['cpo_id']) ? $tx['cpo_id'] : 0;
    $station_id = isset($tx['station_id']) ? $tx['station_id'] : '-';
    $station_mobile = isset($tx['station_mobile']) ? $tx['station_mobile'] : '-';
    $gstin      = isset($tx['cpo_gstin']) ? $tx['cpo_gstin'] : '';
    $gst_status = isset($tx['gst_status']) ? strtoupper($tx['gst_status']) : 'N';
    $status     = isset($tx['settlement_status']) ? $tx['settlement_status'] : 'Y';
    $units      = isset($tx['units_consumed']) ? (float)$tx['units_consumed'] : 0;
    $cost       = isset($tx['total_cost']) ? (float)$tx['total_cost'] : 0;
    $unit_cost  = isset($tx['unit_cost']) ? round((float)$tx['unit_cost'], 2) : 0;
    $final_cost = isset($tx['final_cost']) ? round((float)$tx['final_cost'], 2) : 0;
    $gst        = isset($tx['total_units'], $tx['unitfare']) ? round($tx['total_units'] * $tx['unitfare'] * 0.18, 2) : 0;
    $service_fee_txt = $unit_cost - $final_cost;

    // Track GST
    if (!isset($cpoGstStatusCounts[$cpo])) $cpoGstStatusCounts[$cpo] = array();
    $cpoGstStatusCounts[$cpo][$gst_status] = isset($cpoGstStatusCounts[$cpo][$gst_status]) ? $cpoGstStatusCounts[$cpo][$gst_status] + 1 : 1;

    // Initialize grouped
    if (!isset($grouped[$cpo])) {
        $agg = isset($cpoAggregates[$cpo]) ? $cpoAggregates[$cpo] : array();
        $grouped[$cpo] = array(
            'cpo_id' => $cpo_id,
            'station_id' => $station_id,
            'station_mobile' => $station_mobile,
            'gstin' => $gstin,
            'gst_status' => $agg['gst_status'] ?? $gst_status,
            'invoice_list' => $agg['invoice_list'] ?? [],  // ✅ Added here
            'current_month' => array(),
            'previous_month' => array(
                '1-15' => array('units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y'),
                '16-end' => array('units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y')
            )
        );

        foreach ($show_current_buckets as $b) {
            $grouped[$cpo]['current_month'][$b] = array('units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y');
        }
    }

    // Determine bucket
    if ($year === $currentYear && $month === $currentMonth) {
        $bucket = ($day <= 15) ? '1-15' : '16-end';
        $bucket_ref = &$grouped[$cpo]['current_month'][$bucket];
    } elseif ($year === $prevYear && $month === $prevMonthNum) {
        $bucket = ($day <= 15) ? '1-15' : '16-end';
        $bucket_ref = &$grouped[$cpo]['previous_month'][$bucket];
    } else continue;

    $bucket_ref['units'] += $units;
    $bucket_ref['cost'] += $cost;
    $bucket_ref['gst_amount'] += $gst;
    $bucket_ref['unit_cost'] += $unit_cost;
    $bucket_ref['service_fee'] += $service_fee_txt;
    $bucket_ref['final_cost'] += $final_cost;
    $bucket_ref['settlement_status'] = maxSettlementStatus($bucket_ref['settlement_status'], $status);
    unset($bucket_ref);
}

// Post-process GST majority
foreach ($grouped as $cpo => &$months) {
    $counts = isset($cpoGstStatusCounts[$cpo]) ? $cpoGstStatusCounts[$cpo] : array();
    $countY = isset($counts['Y']) ? $counts['Y'] : 0;
    $countN = isset($counts['N']) ? $counts['N'] : 0;
    $majorityGst = ($countY > $countN) ? 'Y' : 'N';
    $months['gst_status'] = $majorityGst;

    foreach (array('current_month', 'previous_month') as $mt) {
        if (!isset($months[$mt])) continue;
        foreach ($months[$mt] as $range => &$val) {
            if ($majorityGst === 'Y') $val['final_cost'] += $val['gst_amount'];
            $val['total_units'] = $val['units'];
            $val['total_cost'] = $val['cost'];
        }
        unset($val);
    }
}
unset($months);

// Output
echo json_encode(array('status' => true, 'data' => $grouped), JSON_PRETTY_PRINT);
