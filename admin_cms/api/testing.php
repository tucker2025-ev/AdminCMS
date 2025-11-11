<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// --- Fetch CPO summary ---
$query = "SELECT station_id,station_name,station_mobile,cpo_name,cpo_id, half_month_bucket, month_period, gst_status, settlement_status,
                 SUM(units) AS total_units,
                 SUM(unit_cost) AS total_unit_cost,
                 SUM(rate_amount) AS total_rate_amount,
                 SUM(final_cost) AS total_final_cost,
                 SUM(service_fee) AS total_service_fee,
                 SUM(gst_amount) AS total_gst_amount
          FROM {$station_db}.view_cpo_summary
          WHERE cpo_id LIKE 'T%'
          GROUP BY cpo_name, month_period,half_month_bucket";

$result = mysqli_query($connect, $query);
if (!$result) {
    echo json_encode(['status' => false, 'message' => mysqli_error($connect)]);
    exit;
}

$summaryRows = mysqli_fetch_all($result, MYSQLI_ASSOC);

// collect cpo names from summary
$cpoNames = [];
if (!empty($summaryRows)) {
    foreach ($summaryRows as $r) {
        $cpoNames[] = $r['cpo_name'];
    }
}

// --- Fetch invoices for the CPOS found in summary ---
$cpoAggregates = [];
if (!empty($cpoNames)) {
    $escaped = [];
    foreach ($cpoNames as $n) {
        $escaped[] = "'" . mysqli_real_escape_string($connect, $n) . "'";
    }
    $inList = implode(',', $escaped);

    $query = "SELECT cpo.gst_status, cpo.cpo_name,
                     sl.grand_total, sl.paid_amount, sl.remaining,
                     sl.invoice_id, sl.list_id, sl.set_amount
              FROM fca_cpo AS cpo
              LEFT JOIN {$station_db}.service_list AS sl ON cpo.cpo_id = sl.cpo_id
              WHERE cpo.cpo_name IN ($inList) AND sl.grand_total != sl.paid_amount AND cpo.cpo_reg_name != 'Private Personal' AND cpo.cpo_id LIKE 'T%'
              ORDER BY cpo.sno DESC";
    // echo $query;
    $objResult = mysqli_query($connect, $query);
    if ($objResult) {
        while ($row = mysqli_fetch_assoc($objResult)) {
            $cpo = $row['cpo_name'];
            $grand_total = isset($row['grand_total']) ? $row['grand_total'] : 0;
            $paid_amount  = isset($row['paid_amount'])  ? $row['paid_amount']  : 0;
            $pending      = $grand_total - $paid_amount;

            if (!isset($cpoAggregates[$cpo])) {
                $cpoAggregates[$cpo] = [
                    'gst_status'   => isset($row['gst_status']) ? $row['gst_status'] : '',
                    'invoice_list' => []
                ];
            }

            $cpoAggregates[$cpo]['invoice_list'][] = [
                'invoice_id' => isset($row['invoice_id']) ? $row['invoice_id'] : '',
                'list_id'    => isset($row['list_id'])    ? $row['list_id']    : '',
                'set_amount' => isset($row['set_amount']) ? $row['set_amount'] : 0,
                'total_fees' => $grand_total,
                'total_paid' => $paid_amount,
                'pending'    => $pending
            ];
        }
    }
}

// --- Build final response array ---
$data = [];
foreach ($summaryRows as $row) {
    $cpo = $row['cpo_name'] ?? '-';
    $data[] = [
        'cpo_name'          => $cpo,
        'cpo_id'          => $row['cpo_id'] ?? '-',
        'station_id'        => $row['station_id'] ?? '-',
        'station_name'        => $row['station_name'] ?? '-',
        'station_mobile'    => $row['station_mobile'] ?? '-',
        'half_month_bucket' => isset($row['half_month_bucket']) ? $row['half_month_bucket'] : '',
        'month_period'      => isset($row['month_period']) ? $row['month_period'] : '',
        'gst_status'        => isset($row['gst_status']) ? $row['gst_status'] : 'N',
        'settlement_status' => isset($row['settlement_status']) ? $row['settlement_status'] : '',
        'total_units'       => isset($row['total_units']) ? round((float)$row['total_units'], 3) : 0,
        'total_unit_cost'   => isset($row['total_unit_cost']) ? round((float)$row['total_unit_cost'], 2) : 0,
        'total_rate_amount' => isset($row['total_rate_amount']) ? round((float)$row['total_rate_amount'], 2) : 0,
        'total_final_cost'  => isset($row['total_final_cost']) ? round((float)$row['total_final_cost'], 2) : 0,
        'total_service_fee' => isset($row['total_service_fee']) ? round((float)$row['total_service_fee'], 2) : 0,
        'total_gst_amount'  => isset($row['total_gst_amount']) ? round((float)$row['total_gst_amount'], 2) : 0,
        'invoice_list'      => isset($cpoAggregates[$cpo]['invoice_list']) ? $cpoAggregates[$cpo]['invoice_list'] : []
    ];
}

// --- Output ---
echo json_encode(['status' => true, 'data' => $data], JSON_PRETTY_PRINT);
