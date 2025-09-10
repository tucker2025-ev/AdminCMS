<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';
        // SUM(unit_cost + gst) AS total_cost,
// SQL: Summary data for the past 3 months (including current month)
$sql = "
    SELECT 
       DATE_FORMAT(start_time, '%Y-%m') AS month_key,
        DATE_FORMAT(start_time, '%M %Y') AS month_name,
        SUM(unit_fare) AS total_unit_fare,
        SUM(total_units) AS total_units,
        SUM(unit_cost) AS unit_cost,
        SUM(gst) AS gst,
        SUM(total_cost) AS total_cost
    FROM summary_report 
    WHERE start_time >= DATE_FORMAT(CURDATE() - INTERVAL 2 MONTH, '%Y-%m-01') 
      AND start_time <= LAST_DAY(CURDATE())
    GROUP BY month_key, month_name
    ORDER BY month_key ASC;";

// Initialize months array for current and previous 2 months
$months = [];
$current = new DateTime('first day of this month');
$current->setTime(0, 0);
for ($i = 2; $i >= 0; $i--) {
    $m = clone $current;
    $m->modify("-$i month");
    $month_key = $m->format('Y-m');
    $month_name = $m->format('F Y');
    $months[$month_key] = [
        'month_name'      => $month_name,
        'total_unit_fare' => 0,
        'total_units'     => 0,
        'unit_cost'       => 0,
        'gst'             => 0,
        'total_cost'      => 0,
        'total_rate'      => 0,
        'final_cost'      => 0
    ];
}

// Execute query
$stmt = $connect->prepare($sql);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed']);
    exit;
}

if (!$stmt->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'SQL execution failed']);
    exit;
}

$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $month_key = $row['month_key'];

    $unit_fare   = (float)$row["total_unit_fare"];
    $total_units = (float)$row["total_units"];
    $unit_cost   = (float)$row["unit_cost"];
    $gst         = (float)$row["gst"];
    $total_cost  = (float)$row["total_cost"];

    // Determine rate based on unit_fare
    if ($unit_fare < 20) {
        $rate = 0.75;
    } elseif ($unit_fare <= 24) {
        $rate = 0.85;
    } else {
        $rate = 1.00;
    }

    $total_rate = $total_units * $rate;
    $final_cost = $unit_cost - $total_rate;

    if (isset($months[$month_key])) {
        $months[$month_key] = [
            'month_name'      => $row['month_name'],
            'total_unit_fare' => $unit_fare,
            'total_units'     => $total_units,
            'unit_cost'       => $unit_cost,
            'gst'             => $gst,
            'total_cost'      => $total_cost,
            'total_rate'      => $total_rate,
            'final_cost'      => $final_cost
        ];
    }
}

// Aggregate totals
$sum_total_units = 0;
$sum_unit_cost   = 0;
$sum_gst         = 0;
$sum_total_cost  = 0;
$sum_total_rate  = 0;
$sum_final_cost  = 0;

foreach ($months as $m) {
    $sum_total_units += $m['total_units'];
    $sum_unit_cost   += $m['unit_cost'];
    $sum_gst         += $m['gst'];
    $sum_total_cost  += $m['total_cost'];
    $sum_total_rate  += $m['total_rate'];
    $sum_final_cost  += $m['final_cost'];
}
// Step 1: Query Razorpay monthly totals (past 3 months)
$sql_razorpay = "
    SELECT 
    DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '+05:30'), '%Y-%m') AS month_key,
    SUM(amount) AS total_razorpay_amount
FROM razorpay_payments
WHERE status = 'captured'
  AND CONVERT_TZ(created_at, '+00:00', '+05:30') >= DATE_FORMAT(CONVERT_TZ(NOW(), '+00:00', '+05:30') - INTERVAL 2 MONTH, '%Y-%m-01')
  AND CONVERT_TZ(created_at, '+00:00', '+05:30') <= LAST_DAY(CONVERT_TZ(NOW(), '+00:00', '+05:30'))
GROUP BY month_key
ORDER BY month_key ASC;";

$stmt_razorpay = $connect->prepare($sql_razorpay);
if (!$stmt_razorpay) {
    echo json_encode(['status' => 'error', 'message' => 'Razorpay SQL prepare failed']);
    exit;
}

if (!$stmt_razorpay->execute()) {
    echo json_encode(['status' => 'error', 'message' => 'Razorpay SQL execution failed']);
    exit;
}

$result_razorpay = $stmt_razorpay->get_result();

// Initialize razorpay totals for all months as zero
foreach ($months as $key => &$month) {
    $month['razorpay_amount'] = 0;
}
unset($month); // break reference

// Step 2: Update months array with razorpay amounts
while ($row = $result_razorpay->fetch_assoc()) {
    $month_key = $row['month_key'];
    $amount = (float)$row['total_razorpay_amount'];

    if (isset($months[$month_key])) {
        $months[$month_key]['razorpay_amount'] = $amount;
    }
}

// Step 3: Calculate Razorpay total across all months for summary
$sum_razorpay_amount = 0;
foreach ($months as $m) {
    $sum_razorpay_amount += $m['razorpay_amount'];
}

// Step 4: Add razorpay_amount to summary output
echo json_encode([
    'status' => 'success',
    'data'   => [
        'monthly' => array_values($months),
        'summary' => [
            'total_units'     => $sum_total_units,
            'unit_cost'       => $sum_unit_cost,
            'gst'             => $sum_gst,
            'total_cost'      => $sum_total_cost,
            'total_rate'      => $sum_total_rate,
            'final_cost'      => $sum_final_cost,
            'razorpay_amount' => $sum_razorpay_amount
        ]
    ]
]);
