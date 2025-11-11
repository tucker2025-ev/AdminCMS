<?php
session_start();
include 'include/dbconnect.php';

$station_id = $_POST['station_id'] ?? 'all';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

$station_mobile = $_SESSION["demo_station_mobile"];

// Build URL depending on selection
if ($station_id == 'all') {
    $url = "http://cms.tuckerio.bigtot.in/station/new_history.php?station_mobile=" . urlencode($station_mobile) . "&start_date=$start_date&end_date=$end_date";
} else {
    $url = "http://cms.tuckerio.bigtot.in/station/new_history.php?station_mobile=" . urlencode($station_mobile) . "&station_id=" . urlencode($station_id) . "&start_date=$start_date&end_date=$end_date";
}
// Fetch data
$response = @file_get_contents($url);
$data = json_decode($response, true);
$messages = $data['Message'] ?? [];

// Always output <td> for all 11 columns
if (!empty($messages)) {
    $row_num = 1;
    foreach ($messages as $msg) {

        $transaction_id = htmlspecialchars($msg['transaction_id'] ?? '');
        $charger_id = htmlspecialchars($msg['charger_id'] ?? '');
        $con_qr_code = htmlspecialchars($msg['con_qr_code'] ?? '');
        $total_units = round($msg['total_units'] ?? 0, 2);
        $energy_rate = round($msg['unitfare'] ?? 0, 2);
        $service_fee_rate = round($msg['rate'] ?? 0, 2);
        $gross_amount = round($msg['unit_cost'] ?? 0, 2);
        $net_amount = round($msg['final_cost'] ?? 0, 2);
        $GST_amount = round($total_units * $energy_rate * 0.18, 2);
        $service_fee_deduction = round($gross_amount - $net_amount, 2);

        $start = !empty($msg['start_time']) ? date("d M Y, H:i", strtotime($msg['start_time'])) : '-';
        $end = !empty($msg['stop_timestamp'] ?? $msg['stop_time']) ? date("d M Y, H:i", strtotime($msg['stop_timestamp'] ?? $msg['stop_time'])) : '-';
        $invoice_url = "http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/ist.php?transid=" . urlencode($transaction_id);

        echo "<tr class='clickable-row' data-invoice-url='$invoice_url'>
                <td>{$row_num}</td>
                <td>{$transaction_id}</td>
                <td>{$charger_id}<br><small style='color:#6c757d;'>{$con_qr_code}</small></td>
                <td>{$start}<br>{$end}</td>
                <td>{$total_units}</td>
                <td>" . number_format($energy_rate, 2) . " / " . number_format($service_fee_rate, 2) . "</td>
                <td>" . number_format($gross_amount, 2) . "</td>
                <td>" . number_format($GST_amount, 2) . "</td>
                <td>" . number_format($service_fee_deduction, 2) . "</td>
                <td>" . number_format($net_amount + $GST_amount, 2) . "</td>
                <td style='font-weight:600;'>
                    <span class='tooltip-container'>
                        <i class='fas fa-info-circle info-icon'></i>
                        <span class='tooltip-text' style='width:220px; margin-left:-110px; text-align:left; line-height:1.6;'>
                            <div style='display:flex; justify-content:space-between;'><span>Gross Amount:</span> <span>₹ " . number_format($gross_amount, 2) . "</span></div>
                            <div style='display:flex; justify-content:space-between; color:#ffadad;'><span>Deduction Fee:</span> <span>- ₹ " . number_format($service_fee_deduction, 2) . "</span></div>
                            <div style='display:flex; justify-content:space-between; color:#83e99dff;'><span>GST Amount:</span> <span>+ ₹ " . number_format($GST_amount, 2) . "</span></div>
                            <hr style='border-color:#555; margin:4px 0;'>
                            <div style='display:flex; justify-content:space-between; font-weight:bold;'><span>Net Total:</span> <span>₹ " . number_format($net_amount + $GST_amount, 2) . "</span></div>
                        </span>
                    </span>
                </td>
            </tr>";
        $row_num++;
    }
} else {
    echo "<tr><td colspan='11' style='text-align:center; padding:2rem;'>No transaction data available for this station.</td></tr>";
}
?>
