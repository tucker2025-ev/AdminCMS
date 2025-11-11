<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

$response = ["status" => false, "data" => []];

//Validate date input
if (empty($start_date) || empty($end_date)) {
    echo json_encode([
        "status"  => false,
        "message" => "Missing start_date or end_date"
    ], JSON_PRETTY_PRINT);
    exit;
}

//Fetch main transaction data
$sql = "SELECT * FROM view_summary_details 
        WHERE DATE(start_time) BETWEEN ? AND ? 
        ORDER BY trans_id DESC";
$stmt = $connect->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => false, "error" => $connect->error]);
    exit;
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {

    //Unit fare rate lookup
    $unit_fare = (float)$row["unit_fare"];
    $rate = 0;
    $sqlRate = "SELECT amount 
                FROM unit_fare_tariff 
                WHERE min_unit <= ? 
                  AND (max_unit >= ? OR max_unit IS NULL) 
                  AND status = 'Y' 
                LIMIT 1";
    $stmtRate = $connect->prepare($sqlRate);
    if ($stmtRate) {
        $stmtRate->bind_param("dd", $unit_fare, $unit_fare);
        $stmtRate->execute();
        $rateRes = $stmtRate->get_result();
        if ($rateRow = $rateRes->fetch_assoc()) {
            $rate = (float)$rateRow['amount'];
        }
        $stmtRate->close();
    }

    //Cost calculations (fixed logic: add rate instead of subtracting)
    $total_units = (float)$row["total_units"];
    $unit_cost   = (float)$row["unit_cost"];
    $total_rate  = $total_units * $rate;
    $final_cost  = $unit_cost + $total_rate;  // ✅ corrected

    //Duration (handle NULL stop_time)
    $start_time = new DateTime($row["start_time"]);
    $stop_time  = !empty($row["stop_time"]) ? new DateTime($row["stop_time"]) : new DateTime();
    $duration   = $start_time->diff($stop_time);
    $total_minutes = ($duration->h * 60) + $duration->i + ($duration->s / 60);

    //Build transaction record
    $transactions[] = [
        "transaction_id"   => $row["trans_id"],
        "cpo_id"           => $row["cpo_id"],
        "cpo_name"         => $row["cpo_name"],
        "station_id"       => $row["station_id"],
        "station_mobile"   => $row["station_mobile"],
        "total_units"   => $row["total_units"],
        "unit_fare"   => $row["unit_fare"],
        "gst_status"       => $row["gst_status"],
        "settlement_status" => $row["settlement_status"],
        "units_consumed"   => round($total_units, 3),
        "unit_cost"        => round($unit_cost, 2),
        "rate"             => $rate,
        "final_cost"       => round($final_cost, 2),
        "total_cost"       => round((float)$row["total_cost"], 2),
        "start_time"       => $start_time->format("Y-m-d H:i:s"),
        "stop_time"        => $stop_time->format("Y-m-d H:i:s"),
    ];
}
$stmt->close();

// print_r($transactions);
// If no transactions found
if (empty($transactions)) {
    echo json_encode([
        "status"  => false,
        "message" => "No transactions found"
    ], JSON_PRETTY_PRINT);
    exit;
}

//Grouping by CPO with half-month buckets
$grouped = [];
$today   = new DateTime();
$currentMonth = (int)$today->format('m');
$currentYear  = (int)$today->format('Y');
$prevMonthObj = (clone $today)->modify('first day of last month');
$prevMonth = (int)$prevMonthObj->format('m');
$prevYear  = (int)$prevMonthObj->format('Y');

// Helper
function maxSettlementStatus($a, $b)
{
    return (strtoupper($a) === 'N' || strtoupper($b) === 'N') ? 'N' : 'Y';
}

// Process transactions
foreach ($transactions as $tx) {
    $txDate = DateTime::createFromFormat('Y-m-d H:i:s', $tx['start_time']);
    if (!$txDate) continue;

    $day   = (int)$txDate->format('d');
    $month = (int)$txDate->format('m');
    $year  = (int)$txDate->format('Y');
    $cpo   = $tx['cpo_name'];

    $gst  = isset($tx['total_units'], $tx['unit_fare']) ? round($tx['total_units'] * $tx['unit_fare'] * 0.18, 2) : 0;

    // Init if needed
    if (!isset($grouped[$cpo])) {
        $grouped[$cpo] = [
            'cpo_id'         => $tx['cpo_id'],
            'station_id'     => $tx['station_id'],
            'station_mobile' => $tx['station_mobile'],
            'gst_status'     => $tx['gst_status'],
            'current_month'  => [
                '1-15'   => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y'],
                '16-end' => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y']
            ],
            'previous_month' => [
                '1-15'   => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y'],
                '16-end' => ['units' => 0, 'cost' => 0, 'gst_amount' => 0, 'service_fee' => 0, 'unit_cost' => 0, 'final_cost' => 0, 'settlement_status' => 'Y']
            ],
            // placeholders for invoices
            'invoice_list'   => [],
            'total_fees'     => 0,
            'total_paid'     => 0,
            'pending'        => 0
        ];
    }

    // Which bucket?
    if ($year === $currentYear && $month === $currentMonth) {
        $bucket = ($day <= 15) ? '1-15' : '16-end';
        $bucketRef = &$grouped[$cpo]['current_month'][$bucket];
    } elseif ($year === $prevYear && $month === $prevMonth) {
        $bucket = ($day <= 15) ? '1-15' : '16-end';
        $bucketRef = &$grouped[$cpo]['previous_month'][$bucket];
    } else {
        continue;
    }

    // Add values
    $bucketRef['units']          += $tx['units_consumed'];
    $bucketRef['cost']           += $tx['total_cost'];
    $bucketRef['gst_amount']     +=  $gst;
    $bucketRef['unit_cost']      += $tx['unit_cost'];
    $bucketRef['final_cost']     += $tx['final_cost'];
    $bucketRef['service_fee']    += ($tx['final_cost'] - $tx['unit_cost']); // ✅ fixed
    $bucketRef['settlement_status'] = maxSettlementStatus($bucketRef['settlement_status'], $tx['settlement_status']);
    unset($bucketRef);
}

//Fetch invoices per CPO
$sqlInv = "SELECT cpo_id, invoice_id, list_id, set_amount, total_fees, total_paid, pending
           FROM invoice_summary";
$resInv = $connect->query($sqlInv);
$invoicesByCpo = [];
if ($resInv) {
    while ($row = $resInv->fetch_assoc()) {
        $invoicesByCpo[$row['cpo_id']][] = [
            'invoice_id' => $row['invoice_id'],
            'list_id'    => $row['list_id'],
            'set_amount' => (float)$row['set_amount'],
            'total_fees' => (float)$row['total_fees'],
            'total_paid' => (float)$row['total_paid'],
            'pending'    => (float)$row['pending']
        ];
    }
}

//Merge invoices into grouped data
foreach ($grouped as $cpoName => &$data) {
    $cpoId = $data['cpo_id'];
    if (isset($invoicesByCpo[$cpoId])) {
        $data['invoice_list'] = $invoicesByCpo[$cpoId];
        $data['total_fees']   = array_sum(array_column($invoicesByCpo[$cpoId], 'total_fees'));
        $data['total_paid']   = array_sum(array_column($invoicesByCpo[$cpoId], 'total_paid'));
        $data['pending']      = array_sum(array_column($invoicesByCpo[$cpoId], 'pending'));
    }
}
unset($data);

//Final response
$response['status'] = true;
$response['data']   = $grouped;

echo json_encode($response, JSON_PRETTY_PRINT);
