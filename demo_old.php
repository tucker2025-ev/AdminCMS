<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// --- Collect POST data safely ---
$station_id      = $_POST['station_id']      ?? 'N/A';
$cpo_name        = $_POST['cpo_name']        ?? 'N/A';
$cpo_id          = $_POST['cpo_id']          ?? 'N/A';
$station_mobile  = $_POST['station_mobile']  ?? 'N/A';
$month_name      = $_POST['month_name']      ?? 'N/A';
$half_month      = $_POST['half_month']      ?? 'N/A';
$year            = $_POST['year']            ?? date('Y');
$grossRevenue    = (float)str_replace(['₹', ','], '', $_POST['grossRevenue'] ?? 0);
$serviceFee      = (float)str_replace(['₹', ','], '', $_POST['serviceFee'] ?? 0);
$net_amount      = (float)str_replace(['₹', ','], '', $_POST['net_amount'] ?? 0);

$current_date = date('d-m-Y');

// --- Determine date range ---
list($start_day, $end_day) = explode('-', $half_month);
$month_number = date('m', strtotime($month_name));
$start_date = "$year-$month_number-" . str_pad($start_day, 2, '0', STR_PAD_LEFT);
$end_date   = "$year-$month_number-" . str_pad($end_day, 2, '0', STR_PAD_LEFT);

// --- Fetch station history ---
$url = "http://cms.tuckerio.bigtot.in/station/new_history.php?cpo_id=" . urlencode($cpo_id) . "&start_date=$start_date&end_date=$end_date";
$response = @file_get_contents($url);

$stations = [];

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && $data['status'] === "true" && !empty($data['Message'])) {
        foreach ($data['Message'] as $tx) {
            $sid = $tx['station_id'] ?? 'N/A';
            $stations[$sid]['name'] = $tx['station_name'] ?? 'N/A';
            $stations[$sid]['location'] = ($tx['station_city'] ?? '') . ', ' . ($tx['station_state'] ?? '');
            $stations[$sid]['mobile'] = $tx['station_mobile'] ?? $station_mobile;

            $group_id = $tx['group_id'] ?? $tx['charger_id'] ?? 'Ungrouped';
            $stations[$sid]['groups'][$group_id][] = [
                'charger_id' => $tx['charger_id'] ?? 'N/A',
                'energy'     => (float)($tx['total_units'] ?? 0),
                'gross'      => (float)($tx['unit_cost'] ?? 0),
                'gst'        => (float)($tx['gst_amount'] ?? 0),
                'net'        => (float)($tx['final_cost'] ?? 0),
            ];
        }
    }
}

// --- Initialize overall totals ---
$overall_energy = $overall_gross = $overall_gst = $overall_deduction = $overall_net = 0;

$rows_html = '';
foreach ($stations as $sid => $station) {
    $rows_html .= '<h3>Station: ' . htmlspecialchars($station['name']) . " ({$sid})</h3>";
    $rows_html .= '<p><strong>Mobile:</strong> ' . htmlspecialchars($station['mobile']) . '</p>';
    $rows_html .= '<p><strong>Location:</strong> ' . htmlspecialchars($station['location']) . '</p>';

    $rows_html .= '<table>
        <thead>
            <tr>
                <th>Charge Point</th>
                <th>Energy (kWh)</th>
                <th class="text-right">Gross</th>
                <th class="text-right">GST</th>
                <th class="text-right">Deduction</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
        <tbody>';

    $station_energy = $station_gross = $station_gst = $station_deduction = $station_net = 0;

    foreach ($station['groups'] as $group_name => $points) {
        $rows_html .= '<tr><td colspan="6" style="background:#d9edf7;font-weight:bold;">' . htmlspecialchars($group_name) . '</td></tr>';

        $group_energy = $group_gross = $group_gst = $group_deduction = $group_net = 0;

        foreach ($points as $cp) {
            $energy = $cp['energy'];
            $gross = $cp['gross'];
            $gst = $cp['gst'];
            $net = $cp['net'];
            $deduction = round($gross - $net, 2);

            $group_energy += $energy;
            $group_gross += $gross;
            $group_gst += $gst;
            $group_deduction += $deduction;
            $group_net += $net;
        }

        // --- Group subtotal ---
        $rows_html .= '<tr style="background:#f9f9f9;font-weight:bold;">
            <td>Subtotal</td>
            <td>' . number_format($group_energy, 2) . '</td>
            <td class="text-right">₹' . number_format($group_gross, 2) . '</td>
            <td class="text-right">₹' . number_format($group_gst, 2) . '</td>
            <td class="text-right">₹' . number_format($group_deduction, 2) . '</td>
            <td class="text-right">₹' . number_format($group_net, 2) . '</td>
        </tr>';

        $station_energy += $group_energy;
        $station_gross += $group_gross;
        $station_gst += $group_gst;
        $station_deduction += $group_deduction;
        $station_net += $group_net;
    }

    // --- Station total ---
    $rows_html .= '<tr style="background:#e6ffe6;font-weight:bold;">
        <td>Station Total</td>
        <td>' . number_format($station_energy, 2) . '</td>
        <td class="text-right">₹' . number_format($station_gross, 2) . '</td>
        <td class="text-right">₹' . number_format($station_gst, 2) . '</td>
        <td class="text-right">₹' . number_format($station_deduction, 2) . '</td>
        <td class="text-right">₹' . number_format($station_net, 2) . '</td>
    </tr></tbody></table><br>';

    $overall_energy += $station_energy;
    $overall_gross += $station_gross;
    $overall_gst += $station_gst;
    $overall_deduction += $station_deduction;
    $overall_net += $station_net;
}

// --- Build HTML for PDF ---
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>CPO Station Report</title>
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #333; margin: 20mm; }
.header { text-align: center; margin-bottom: 20px; }
.header h1 { color: #0056b3; margin-bottom: 5px; }
.cpo-details { margin-bottom: 20px; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 8px; }
th { background: #e6f2ff; font-weight: bold; }
tfoot td { font-weight: bold; background: #f1f1f1; }
.text-right { text-align: right; }
</style>
</head>
<body>

<div class="header">
    <h1>CPO Station & Charge Point Group Report</h1>
    <p>Generated on: ' . $current_date . '</p>
</div>

<div class="cpo-details">
    <p><strong>CPO Name:</strong> ' . htmlspecialchars($cpo_name) . '</p>
    <p><strong>Period:</strong> ' . htmlspecialchars($month_name) . ' (' . htmlspecialchars($half_month) . '), ' . htmlspecialchars($year) . '</p>
    <p><strong>Gross Revenue:</strong> ₹' . number_format($grossRevenue, 2) . '</p>
    <p><strong>Service Fee:</strong> ₹' . number_format($serviceFee, 2) . '</p>
    <p><strong>Settlement (Net Amount):</strong> ₹' . number_format($net_amount, 2) . '</p>
</div>

' . $rows_html . '

<h2>Overall Grand Total</h2>
<table>
  <thead>
            <tr>
                <th>Name</th>
                <th>Energy (kWh)</th>
                <th class="text-right">Gross</th>
                <th class="text-right">GST</th>
                <th class="text-right">Deduction</th>
                <th class="text-right">Net</th>
            </tr>
        </thead>
    <tbody>
        <tr style="background:#ffd9b3;font-weight:bold;">
            <td>Grand Total</td>
            <td>' . number_format($overall_energy, 2) . '</td>
            <td class="text-right">₹' . number_format($overall_gross, 2) . '</td>
            <td class="text-right">₹' . number_format($overall_gst, 2) . '</td>
            <td class="text-right">₹' . number_format($overall_deduction, 2) . '</td>
            <td class="text-right">₹' . number_format($overall_net, 2) . '</td>
        </tr>
    </tbody>
</table>

</body>
</html>';

// --- Generate PDF ---
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// --- Stream (download) the PDF ---
$filename = "CPO_Group_Report_{$station_id}_{$month_name}_{$year}.pdf";
$dompdf->stream($filename, ["Attachment" => true]);
exit;
