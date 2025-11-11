<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

// --- Current date ---
$current_date = date('d-m-Y');

// --- Get POST values ---
$month_name = $_POST['month_name'] ?? date('F');
$year = $_POST['year'] ?? date('Y');
$period = $_POST['period'] ?? '';

// --- Determine date range based on selected period ---
$month_num = date('m', strtotime("$month_name 1 $year"));
if ($period === 'Period I') {
    $start_date = "$year-$month_num-01";
    $end_date   = "$year-$month_num-15";
} elseif ($period === 'Period II') {
    $start_date = "$year-$month_num-16";
    $end_date   = date('Y-m-t', strtotime("$year-$month_num-01"));
} else { // All
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-t');
}

// --- Fetch station history ---

$url = "http://cms.tuckerio.bigtot.in/station/new_history.php?start_date=$start_date&end_date=$end_date";

$response = file_get_contents($url);
if ($response === false) {
    die("Failed to fetch station history. Check URL or server settings.");
}

$data = json_decode($response, true);
if (!$data || $data['status'] !== "true" || empty($data['Message'])) {
    die("No station data found.");
}

// --- Organize data by CPO ---
$cpos = [];
foreach ($data['Message'] as $tx) {
    $cpo_id = $tx['cpo_id'] ?? 'N/A';
    $station_id = $tx['station_id'] ?? 'N/A';

    $cpos[$cpo_id]['name'] = $tx['cpo_name'] ?? 'N/A';
    $cpos[$cpo_id]['stations'][$station_id]['name'] = $tx['station_name'] ?? 'N/A';
    $cpos[$cpo_id]['stations'][$station_id]['location'] = ($tx['station_city'] ?? '') . ', ' . ($tx['station_state'] ?? '');
    $cpos[$cpo_id]['stations'][$station_id]['mobile'] = $tx['station_mobile'] ?? 'N/A';

    $group_id = $tx['group_id'] ?? $tx['charger_id'] ?? 'Ungrouped';
    $cpos[$cpo_id]['stations'][$station_id]['groups'][$group_id][] = [
        'charger_id' => $tx['charger_id'] ?? 'N/A',
        'energy'     => (float)($tx['total_units'] ?? 0),
        'gross'      => (float)($tx['unit_cost'] ?? 0),
        'gst'        => (float)($tx['gst_amount'] ?? 0),
        'net'        => (float)($tx['final_cost'] ?? 0),
    ];
}

// --- Combine all CPO HTML for single PDF ---
$full_html = '';
foreach ($cpos as $cpo_id => $cpo) {

    $rows_html = '';
    $overall_energy = $overall_gross = $overall_gst = $overall_deduction = $overall_net = 0;

    foreach ($cpo['stations'] as $station_id => $station) {
        $rows_html .= '<h3>Station: ' . htmlspecialchars($station['name']) . " ({$station_id})</h3>";
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

    // --- Append each CPO HTML with page break ---
    $full_html .= '
    <div class="cpo-page">
        <div class="header">
            <h1>CPO Station & Charge Point Group Report</h1>
            <p>Generated on: ' . $current_date . '</p>
            <p><strong>CPO Name:</strong> ' . htmlspecialchars($cpo['name']) . '</p>
            <p><strong>Period:</strong> ' . htmlspecialchars(date('F Y')) . ' (' . htmlspecialchars($period) . ')</p>
        </div>
        ' . $rows_html . '
        <h2>Overall Grand Total</h2>
        <table>
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
    </div>
    <div style="page-break-after: always;"></div>';
}

// --- Full HTML ---
$html = '<html>
<head>
<meta charset="UTF-8">
<title>CPO Report</title>
<style>
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; margin: 20mm; }
table { width: 100%; border-collapse: collapse; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 8px; }
th { background: #e6f2ff; font-weight: bold; }
.text-right { text-align: right; }
</style>
</head>
<body>' . $full_html . '</body>
</html>';

// --- Generate single PDF ---
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// --- Stream single PDF ---
$filename = "CPO_All_Report_" . date('M_Y') . ".pdf";
$dompdf->stream($filename, ["Attachment" => true]);
