<?php
include '../include/dbconnect.php';
// --- Collect POST data safely ---
$station_id = $_POST['station_id'] ?? 'N/A';
$cpo_name = $_POST['cpo_name'] ?? 'N/A';
$cpo_id = $_POST['cpo_id'] ?? 'N/A';
$station_mobile = $_POST['station_mobile'] ?? 'N/A'; // This might be for the specific station, but overall CPO mobile is usually separate
$month_name = $_POST['month_name'] ?? 'N/A';
$half_month = $_POST['half_month'] ?? 'N/A';
$year = $_POST['year'] ?? date('Y');
$grossRevenue = (float)str_replace(['₹', ','], '', $_POST['grossRevenue'] ?? 0);
$serviceFee = (float)str_replace(['₹', ','], '', $_POST['serviceFee'] ?? 0);
$net_amount = (float)str_replace(['₹', ','], '', $_POST['net_amount'] ?? 0);

$current_date = date('d-m-Y');

// --- Determine date range ---
$start_date = '';
$end_date = '';
if ($month_name !== 'N/A' && $half_month !== 'N/A') {
    list($start_day, $end_day) = explode('-', $half_month);
    $month_number = date('m', strtotime($month_name));
    $start_date = "$year-$month_number-" . str_pad($start_day, 2, '0', STR_PAD_LEFT);
    $end_date = "$year-$month_number-" . str_pad($end_day, 2, '0', STR_PAD_LEFT);
}

// --- Fetch station history ---
$url = "http://cms.tuckerio.bigtot.in/station/new_history.php?cpo_id=" . urlencode($cpo_id) . "&start_date=$start_date&end_date=$end_date";
$response = @file_get_contents($url);
$stations_data = []; // Renamed to avoid conflict with $stations for rendering
$api_fetch_success = false;

if ($response !== false) {
    $data = json_decode($response, true);
    if ($data && $data['status'] === "true" && !empty($data['Message'])) {
        $api_fetch_success = true;

        if (isset($data['Message'][0])) {
            $first_tx = $data['Message'][0];
            $cpo_city = $first_tx['cpo_city'] ?? $cpo_city; // Use already defined or N/A
            $cpo_state = $first_tx['cpo_state'] ?? $cpo_state;
            $cpo_mobile = $first_tx['cpo_mobile'] ?? $cpo_mobile;
            $invoice_gstno = $first_tx['invoice_gstno'] ?? $invoice_gstno;
            $gst_status = $first_tx['gst_status'] ?? 'N';
        }

        foreach ($data['Message'] as $tx) {
            $sid = $tx['station_id'] ?? 'UNKNOWN_STATION';
            if (!isset($stations_data[$sid])) {
                $stations_data[$sid] = [
                    'name' => $tx['station_name'] ?? 'N/A',
                    'location' => ($tx['station_city'] ?? '') . ', ' . ($tx['station_state'] ?? ''),
                    'mobile' => $tx['station_mobile'] ?? 'N/A',
                    'groups' => [], // This will hold filtered charge points
                    'station_total_energy' => 0,
                    'station_total_gross' => 0,
                    'station_total_gst' => 0,
                    'station_total_deduction' => 0,
                    'station_total_net' => 0,
                    'station_total_service_fee' => 0, //Add this field
                ];
            }

            // --- Collect data safely ---
            $charger_id  = $tx['charger_id'] ?? 'N/A';
            $total_units = (float)($tx['total_units'] ?? 0);
            $unit_fare   = (float)($tx['unitfare'] ?? 0);
            $unit_cost   = (float)($tx['unit_cost'] ?? 0);
            $gst_amount  = (float)($tx['gst_amount'] ?? 0);
            $deduction   = (float)($tx['deduction_amount'] ?? 0);
            $final_cost  = (float)($tx['final_cost'] ?? 0);

            // --- Step 1: Get matching tariff value ---
            $tariff_amount = 0;

            // --- Make sure $unit_fare is numeric ---
            $tariffQuery = "SELECT amount FROM unit_fare_tariff WHERE min_unit <= $unit_fare AND (max_unit >= $unit_fare OR max_unit IS NULL) AND status = 'Y' LIMIT 1";
            $result = $connect->query($tariffQuery);

            if ($result && $tariff = $result->fetch_assoc()) {
                $tariff_amount = (float)$tariff['amount'];
            }

            // --- Step 2: Compute service fee ---
            $service_fee = round($total_units * $tariff_amount, 2);

            // --- Accumulate station totals ---
            $stations_data[$sid]['station_total_energy']      += $total_units;
            $stations_data[$sid]['station_total_gross']       += $unit_cost;
            $stations_data[$sid]['station_total_gst']         += $gst_amount;
            $stations_data[$sid]['station_total_deduction']   += $deduction;
            $stations_data[$sid]['station_total_service_fee'] += $service_fee;

            if ($gst_status == 'Y') {
                $stations_data[$sid]['station_total_net'] += $final_cost + $gst_amount;
            } else {
                $stations_data[$sid]['station_total_net'] += $final_cost;
            }

            // --- Accumulate charger group ---
            if (!isset($stations_data[$sid]['groups'][$charger_id])) {
                $stations_data[$sid]['groups'][$charger_id] = [
                    'charger_id' => $charger_id,
                    'energy' => 0,
                    'gross' => 0,
                    'gst' => 0,
                    'deduction' => 0,
                    'net' => 0,
                    'service_fee' => 0,
                ];
            }

            $stations_data[$sid]['groups'][$charger_id]['energy'] += $total_units;
            $stations_data[$sid]['groups'][$charger_id]['gross'] += $unit_cost;
            $stations_data[$sid]['groups'][$charger_id]['gst'] += $gst_amount;
            $stations_data[$sid]['groups'][$charger_id]['deduction'] += $deduction;
            $stations_data[$sid]['groups'][$charger_id]['service_fee'] += $service_fee;

            if ($gst_status == 'Y') {
                $stations_data[$sid]['groups'][$charger_id]['net'] += $final_cost + $gst_amount;
            } else {
                $stations_data[$sid]['groups'][$charger_id]['net'] += $final_cost;
            }
        }
    }
}

// --- Initialize overall totals ---
$overall_energy = 0;
$overall_gross = 0;
$overall_gst = 0;
$overall_service_fee = 0;
$overall_net = 0;
$Bank_fee = '5.9';
foreach ($stations_data as $station) {
    $overall_energy += $station['station_total_energy'];
    $overall_gross += $station['station_total_gross'];
    $overall_gst += $station['station_total_gst'];
    $overall_service_fee += $station['station_total_service_fee'];
    $overall_net += $station['station_total_net'];
}

// If API didn't provide revenue data, use POST values
if (!$api_fetch_success || ($overall_gross == 0 && $overall_net == 0)) {
    $overall_gross = $grossRevenue;
}
if ($gst_status == 'Y') {
    $overall_net = ($grossRevenue - $overall_service_fee - $Bank_fee) + $overall_gst;
} else {
    $overall_net = $grossRevenue - $overall_service_fee - $Bank_fee;
}

// --- Make sure $unit_fare is numeric ---
$amc_cost_query = "SELECT SUM(set_amount) AS total_amount FROM $station_db.service_list WHERE cpo_id = '$cpo_id'";
$result_amc_cost = $connect->query($amc_cost_query);

if ($result_amc_cost && $row_amc_cost = $result_amc_cost->fetch_assoc()) {
    $AMC_amount = (float)$row_amc_cost['total_amount'];
} else {
    $AMC_amount = 0; // fallback if no result
}
list($start_day, $end_day) = explode('-', $half_month);
$weekday = date('l', strtotime("$year $month_name $start_day"));
$formatted_period = "$weekday, {$start_day}th to {$end_day}th $month_name $year";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        body {
            font-family: "Roboto", sans-serif;
            background-color: #e9e9e9;
            color: #333;
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .download-btn {
            margin-bottom: 20px;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: bold;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .download-btn:hover {
            background-color: #45a049;
        }

        .invoice-wrapper {
            position: relative;
            width: 1100px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .invoice-container {
            background-color: #fcfcfc;
            padding: 30px 40px;
            position: relative;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background-color: #e62e41;
            margin: -30px -40px 0 -40px;
            padding: 20px 40px;
        }

        .header-left {
            max-width: 60%;
        }

        .logo-section {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }

        /* .logo-dots {
            display: grid;
            grid-template-columns: repeat(5, 6px);
            grid-gap: 5px;
            margin-right: 25px;
            margin-top: 10px;
        }

        .logo-dots::before {
            content: "";
            display: block;
            grid-column: span 5;
            height: 0;
            padding-bottom: calc(4 * (6px + 5px) - 5px);
            background-image: radial-gradient(circle, #333 55%, transparent 55%);
            background-size: 11px 11px;
            background-repeat: repeat;
        } */

        .invoice-title h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: 1.5px;
        }

        .invoice-title p {
            margin: 5px 0 0;
            font-size: 14px;
            font-weight: bold;
            color: #555;
        }

        .contact-company {
            width: auto;
        }

        .contact-company h3 {
            color: #fff;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .contact-info {
            display: flex;
            font-size: 15px;
            color: #fff;
        }

        .contact-info p {
            margin: 0;
            padding-right: 25px;
        }

        .contact-info strong {
            display: block;
            margin-bottom: 4px;
            color: #ffffff;
            position: relative;
            padding-left: 12px;
        }

        th {
            background: #e62e41;
        }

        .header-right {
            text-align: right;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            padding-top: 10px;
        }

        .company-brand {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 20px;
        }

        .brand-text {
            margin-right: 20px;
        }

        .brand-title {
            font-size: 20px;
            font-weight: bold;
        }

        .brand-tagline {
            font-size: 11px;
            color: #888;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            background-color: #4caf50;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            position: relative;
            z-index: 1;
            border: 4px solid #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
            transform: rotate(-15deg);
        }

        .brand-logo span {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            text-transform: uppercase;
        }

        .invoice-dates p {
            margin: 10px 0 0;
            font-size: 12px;
            color: #fcfcfc;
        }

        .invoice-dates strong {
            font-weight: bold;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        .billing-details {
            display: flex;
            justify-content: space-between;
            padding: 20px 0;
            margin-top: 20px;
        }

        .section-title {
            font-weight: bold;
            color: #555;
            margin-bottom: 15px;
            font-size: 13px;
        }

        .client-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .client-detail,
        .payment-method p {
            font-size: 14px;
            margin: 5px 0;
            line-height: 1.6;
        }

        .invoice-items table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-items thead {
            background-color: #333;
            color: #fff;
        }

        .invoice-items th {
            padding: 15px;
            text-align: left;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .invoice-items th.sno-header {
            width: 50px;
            text-align: center;
        }

        .invoice-items td {
            padding: 15px;
            border-bottom: 1px solid #f2f2f2;
            font-size: 15px;
            vertical-align: top;
        }

        .invoice-items td.sno-data {
            text-align: center;
            font-weight: bold;
        }

        .invoice-items tr.totals-row td,
        .invoice-items tr.grand-total-row td {
            border-bottom: 1px solid #f2f2f2;
            font-weight: bold;
        }

        .invoice-items tr.grand-total-row td {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 16px;
        }

        .invoice-items td p {
            font-size: 12px;
            color: #888;
            margin: 5px 0 0;
        }

        .invoice-items td strong {
            font-weight: bold;
        }

        .invoice-items th:nth-child(n + 4),
        .invoice-items td:nth-child(n + 4) {
            text-align: right;
        }

        .invoice-items .station-name {
            font-weight: bold;
        }

        .invoice-items .charge-point-name {
            padding-left: 25px;
        }

        .invoice-summary {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 30px;
        }

        .total-balance-title {
            margin: 0;
            font-size: 11px;
            color: #555;
            font-weight: bold;
        }

        .total-balance-amount {
            margin: 5px 0 0;
            font-size: 14px;
            font-weight: bold;
            max-width: 500px !important;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .total-balance-amount .in-words {
            display: block;
            margin-top: 5px;
            font-weight: normal;
            max-width: 100px !important;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .amount-in-words {
            font-size: 13px;
            font-style: italic;
            color: #555;
            margin-top: 8px;
        }

        .totals-section {
            background-color: #f5f5f5;
            margin-top: -83px !important;
            width: 300px;
        }

        .totals p {
            display: flex;
            justify-content: space-between;
            margin: 0;
            padding: 12px 15px;
            font-size: 15px;
            background-color: #f5f5f5;
        }

        .final-total p {
            background-color: #e62e41;
            background-image: linear-gradient(45deg,
                    rgba(255, 255, 255, 0.05) 25%,
                    transparent 25%,
                    transparent 75%,
                    rgba(255, 255, 255, 0.05) 75%),
                linear-gradient(-45deg,
                    rgba(255, 255, 255, 0.05) 25%,
                    transparent 25%,
                    transparent 75%,
                    rgba(255, 255, 255, 0.05) 75%);
            color: #fff;
            font-weight: bold;
            font-size: 18px;
            padding: 15px;
        }

        .notes-section {
            margin-top: 20px;
            padding: 0;
        }

        .notes-section h3 {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .notes-section p {
            font-size: 12px;
            color: #888;
            line-height: 1.6;
        }

        .invoice-logo {
            max-width: 210px;
            height: auto;
        }

        header {
            background-color: #e62e41;
            background-image: linear-gradient(45deg,
                    rgba(255, 255, 255, 0.05) 25%,
                    transparent 25%,
                    transparent 75%,
                    rgba(255, 255, 255, 0.05) 75%),
                linear-gradient(-45deg,
                    rgba(255, 255, 255, 0.05) 25%,
                    transparent 25%,
                    transparent 75%,
                    rgba(255, 255, 255, 0.05) 75%);
            background-size: 30px 30px;

            border-radius: 0px 0px 30px 30px;
        }

        table {
            border: 0.5px solid #f0f0f0;
            border-radius: 10px 10px 0 0 !important;
        }

        .watermark {
            position: absolute;
            top: 56%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
            opacity: 0.1;
            pointer-events: none;
            width: 600px;
            height: 600px;
            background: url("https://tuckermotors.com/assets/logo-marketing.png") no-repeat center center;
            background-size: contain;
        }
    </style>
</head>

<body>
    <button id="download-btn" class="download-btn">Download PDF</button>

    <div class="invoice-wrapper" id="invoice">
        <div class="invoice-container">
            <div class="watermark"></div>
            <header>
                <div class="header-left">
                    <div class="logo-section">
                        <div class="invoice-title">
                            <img src="images/LOGO.png" alt="Company Logo" class="invoice-logo" />
                        </div>
                    </div>
                    <div class="invoice-dates">
                        <p>
                            <strong>Generated on</strong><span><?= $current_date ?></span>
                        </p>
                        <p>
                            <strong>Period Date</strong><span><?= $formatted_period ?></span>
                        </p>
                    </div>
                </div>
                <div class="header-right" style="color: #fff">
                    <div class="company-brand">
                        <div class="invoice-title">
                            <h3>Tucker Motors Private Limited</h3>
                        </div>
                    </div>
                    <div class="contact-company">
                        <br />
                        <br />

                        <div class="contact-info">
                            <p><strong>PHONE</strong> (+91) 8220075825</p>
                            <p><strong>EMAIL</strong> info@tuckermotors.com</p>
                            <p>
                                <strong>ADDRESS</strong> 159, C1/1, Kamarajar Salai,Madurai -
                                625 009
                            </p>
                        </div>
                    </div>
                </div>
            </header>
            <div class="brand-title" style="text-align: center !important; margin-top: 30px">
                CPO Station Charge Point Group Report
            </div>
            <div class="brand-tagline"></div>
            <section class="billing-details">
                <div class="invoice-to">
                    <p class="client-name"><?= htmlspecialchars($cpo_name) ?></p>
                    <p class="client-detail"><?= htmlspecialchars($cpo_city) ?>,<?= htmlspecialchars($cpo_state) ?></p>
                    <p class="client-detail">(+91) <?= htmlspecialchars($cpo_mobile) ?></p>
                    <p class="client-detail"><?= htmlspecialchars($invoice_gstno) ?></p>
                </div>
                <div class="payment-method">
                    <br />
                    <p class="section-title">Revenue (Period Totals)</p>
                    <p>Gross Revenue : ₹<?= number_format($overall_gross, 2) ?></p>
                    <p>Service Fee : ₹<?= number_format($overall_service_fee, 2) ?></p>
                    <?php if ($gst_status == 'Y') { ?>
                        <p>Deduction (GST): ₹<?= number_format($overall_gst, 2) ?></p>
                    <?php }
                    if (!empty($AMC_amount) && isset($half_month) && in_array($half_month, ['16-30', '16-31'])) { ?>
                        <p>AMC Cost: ₹<?= number_format($AMC_amount, 2) ?></p>
                    <?php }
                    $settlement_amount = ($AMC_amount && in_array($half_month, ['16-30', '16-31'])) ? $overall_net - $AMC_amount : $overall_net;
                    ?>
                    <p>Bank Fee : ₹5.9</p>
                    <p>Settlement (Net Amount): ₹<?= number_format($settlement_amount, 2) ?></p>
                </div>
            </section>

            <section class="invoice-items">
                <table>
                    <thead>
                        <tr>
                            <th class="sno-header">S.No</th>
                            <th>Station / Location</th>
                            <th>Charge Point</th>
                            <th>Energy (kWh)</th>
                            <th>Gross (₹)</th>
                            <th>GST (₹)</th>
                            <th>Deduction (₹)</th>
                            <th>Net (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $station_counter = 0;
                        foreach ($stations_data as $sid => $station) :
                            // Filter out charge points that are all zeros for display purposes
                            $charge_points_to_display = array_filter($station['groups'], function ($cp) {
                                return $cp['energy'] > 0 || $cp['gross'] > 0 || $cp['gst'] > 0 || $cp['deduction'] > 0 || $cp['net'] > 0;
                            });

                            // Only display station if it has at least one charge point with non-zero values
                            if (!empty($charge_points_to_display)) {
                                $station_counter++;
                                $is_first_charge_point = true;
                                $rowspan_count = count($charge_points_to_display); // +1 for the station total row

                                foreach ($charge_points_to_display as $group_id => $charge_point) :
                        ?>
                                    <tr>
                                        <?php if ($is_first_charge_point) : ?>
                                            <td class="sno-data" rowspan="<?= $rowspan_count ?>"><?= $station_counter ?></td>
                                            <td class="station-name" rowspan="<?= $rowspan_count ?>">
                                                <?= htmlspecialchars($station['location']) ?> – <?= htmlspecialchars($station['name']) ?> (<?= htmlspecialchars($sid) ?>)
                                            </td>
                                        <?php endif; ?>
                                        <td class="charge-point-name"><?= htmlspecialchars($charge_point['charger_id']) ?></td>
                                        <td><?= number_format($charge_point['energy'], 2) ?></td>
                                        <td><?= number_format($charge_point['gross'], 2) ?></td>
                                        <td> <?= ($gst_status == 'Y') ? number_format($charge_point['gst'], 2) : '0.00' ?></td>
                                        <td><?= number_format($charge_point['service_fee'], 2) ?></td>
                                        <td><?= number_format($charge_point['net'], 2) ?></td>
                                    </tr>
                                <?php
                                    $is_first_charge_point = false;
                                endforeach;
                                ?>

                            <?php
                            } ?>

                            <?php endforeach;
                        $station_counter = 0;
                        foreach ($stations_data as $sid => $station) :
                            // Filter out charge points that are all zeros for display purposes
                            $charge_points_to_display = array_filter($station['groups'], function ($cp) {
                                return $cp['energy'] > 0 || $cp['gross'] > 0 || $cp['gst'] > 0 || $cp['deduction'] > 0 || $cp['net'] > 0;
                            });

                            // Only display station if it has at least one charge point with non-zero values
                            if (!empty($charge_points_to_display)) {
                                $station_counter++;
                                $is_first_charge_point = true;
                                $rowspan_count = count($charge_points_to_display); // +1 for the station total row 
                            ?>

                                <tr class="totals-row">
                                    <td colspan="2">◆ Station Total <?= $station_counter ?></td>
                                    <td></td>
                                    <td><?= number_format($station['station_total_energy'], 2) ?></td>
                                    <td><?= number_format($station['station_total_gross'], 2) ?></td>
                                    <td> <?= ($gst_status == 'Y') ? number_format($station['station_total_gst'], 2) : '0.00' ?></td>
                                    <td><?= number_format($station['station_total_service_fee'], 2) ?></td>
                                    <td><?= number_format($station['station_total_net'], 2) ?></td>
                                </tr>

                                <?php
                                $is_first_charge_point = false;
                                ?>

                            <?php
                            } ?>

                        <?php endforeach;

                        // Display a message if no data or no non-zero data
                        if ($station_counter === 0) : ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 20px;">No transaction data with non-zero values found for the selected period.</td>
                            </tr>
                        <?php endif; ?>

                        <tr class="grand-total-row">
                            <td colspan="3">◈ Grand Total (All Stations)</td>
                            <td colspan="4"><?= number_format($overall_energy, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="invoice-summary">
                <div class="balance-due">
                    <p class="total-balance-title">Net Amount (in words) :</p>
                    <p class="total-balance-amount" id="total-amount-numerical">
                        <!-- Content will be set by JavaScript -->
                    </p>
                </div>
                <div class="totals-section">
                    <div class="totals">
                        <p><span>Gross Total :</span><span>₹ <?= number_format($overall_gross, 2) ?></span></p>
                        <?php if ($gst_status == 'Y') { ?>
                            <p><span>Deduction (GST) :</span><span>₹ <?= number_format($overall_gst, 2) ?></span></p>
                        <?php } ?>
                        <p><span>Service Fee :</span><span>₹ <?= number_format($overall_service_fee, 2) ?></span></p>

                        <?php if (!empty($AMC_amount) && isset($half_month) && in_array($half_month, ['16-30', '16-31'])) { ?>
                            <p><span>AMC Cost: </span><span>₹ <?= number_format($AMC_amount, 2) ?></span></p>
                        <?php } ?>
                        <p><span>Bank Fee :</span><span>₹5.9</span></p>
                    </div>
                    <div class="final-total">
                        <p><span>Net Amount :</span><span id="final-net-amount"> ₹ <?= number_format($settlement_amount, 2) ?></span></p>
                    </div>
                </div>
            </section>

            <section class="notes-section">
                <h3>Notes</h3>
                <p>
                    You can view and download the full transaction report at <a href="https://station.cms.tuckermotors.com">Click here</a>
                </p>
            </section>
        </div>
    </div>

    <script>
        window.addEventListener("load", function() {
            const downloadBtn = document.getElementById("download-btn");
            if (!downloadBtn) {
                console.error("Download button not found!");
                return;
            }

            downloadBtn.addEventListener("click", function() {
                const invoiceElement = document.getElementById("invoice");

                if (
                    typeof html2canvas === "undefined" ||
                    typeof jspdf === "undefined"
                ) {
                    alert("Could not generate PDF. Required libraries did not load.");
                    return;
                }

                const {
                    jsPDF
                } = window.jspdf;

                // Added allowTaint and useCORS to handle images and fonts better
                html2canvas(invoiceElement, {
                        scale: 3,
                        useCORS: true,
                        allowTaint: true,
                    })
                    .then((canvas) => {
                        const imgData = canvas.toDataURL("image/png");
                        const pdf = new jsPDF({
                            orientation: "portrait",
                            unit: "mm",
                            format: "a3",
                        });
                        const pdfWidth = pdf.internal.pageSize.getWidth();
                        const pdfHeight = pdf.internal.pageSize.getHeight();
                        const canvasAspectRatio = canvas.width / canvas.height;
                        let finalPdfWidth = pdfWidth;
                        let finalPdfHeight = finalPdfWidth / canvasAspectRatio;

                        if (finalPdfHeight > pdfHeight) {
                            finalPdfHeight = pdfHeight;
                            finalPdfWidth = finalPdfHeight * canvasAspectRatio;
                        }
                        const x = (pdfWidth - finalPdfWidth) / 2;
                        const y = 0;
                        pdf.addImage(imgData, "PNG", x, y, finalPdfWidth, finalPdfHeight);
                        let CPO_id = '<?= $cpo_id ?>';
                        pdf.save(CPO_id + "-cpo-report.pdf");
                    })
                    .catch(function(error) {
                        // This will now print the detailed error object to the console
                        console.error("html2canvas failed:", error);
                        alert(
                            "Failed to generate PDF. See console for details (Press F12 to open)."
                        );
                    });
            });

            // Number to words function
            try {
                function numberToWordsINR(num) {
                    if (num === null || isNaN(num)) return "";
                    let amount = num.toString();
                    let [integerPart, decimalPart] = amount.split(".");
                    const ones = [
                        "",
                        "One",
                        "Two",
                        "Three",
                        "Four",
                        "Five",
                        "Six",
                        "Seven",
                        "Eight",
                        "Nine",
                    ];
                    const teens = [
                        "Ten",
                        "Eleven",
                        "Twelve",
                        "Thirteen",
                        "Fourteen",
                        "Fifteen",
                        "Sixteen",
                        "Seventeen",
                        "Eighteen",
                        "Nineteen",
                    ];
                    const tens = [
                        "",
                        "",
                        "Twenty",
                        "Thirty",
                        "Forty",
                        "Fifty",
                        "Sixty",
                        "Seventy",
                        "Eighty",
                        "Ninety",
                    ];

                    const convertLessThanThousand = (nStr) => {
                        let n = parseInt(nStr, 10);
                        if (n === 0) return "";
                        if (n < 10) return ones[n];
                        if (n < 20) return teens[n - 10];
                        if (n < 100)
                            return (
                                tens[Math.floor(n / 10)] +
                                (n % 10 !== 0 ? " " : "") +
                                ones[n % 10]
                            );
                        return (
                            ones[Math.floor(n / 100)] +
                            " Hundred" +
                            (n % 100 !== 0 ? " " : "") +
                            convertLessThanThousand((n % 100).toString())
                        );
                    };

                    if (integerPart === "0") {
                        return "Zero Rupees";
                    }
                    let words = "";
                    let tempInteger = integerPart;
                    if (tempInteger.length > 7) {
                        words +=
                            convertLessThanThousand(tempInteger.slice(0, -7)) + " Crore ";
                        tempInteger = tempInteger.slice(-7);
                    }
                    if (tempInteger.length > 5) {
                        words +=
                            convertLessThanThousand(tempInteger.slice(0, -5)) + " Lakh ";
                        tempInteger = tempInteger.slice(-5);
                    }
                    if (tempInteger.length > 3) {
                        words +=
                            convertLessThanThousand(tempInteger.slice(0, -3)) +
                            " Thousand ";
                        tempInteger = tempInteger.slice(-3);
                    }
                    words += convertLessThanThousand(tempInteger);

                    let result = words.trim() + " Rupees";
                    if (decimalPart) {
                        let paise = decimalPart.padEnd(2, "0").substring(0, 2);
                        if (parseInt(paise, 10) > 0) {
                            result += " and " + convertLessThanThousand(paise) + " Paise";
                        }
                    }
                    return result.replace(/\s+/g, " ").trim() + " Only.";
                }

                const amountElement = document.getElementById(
                    "total-amount-numerical"
                );
                const finalAmountElement = document.querySelector(
                    ".final-total span:last-child"
                );
                const numericString = finalAmountElement.innerText.replace(
                    /[^0-9.]/g,
                    ""
                );
                const amountValue = parseFloat(numericString);

                if (!isNaN(amountValue)) {
                    amountElement.innerText = numberToWordsINR(amountValue);
                }
            } catch (e) {
                console.error("Error converting number to words:", e);
            }
        });
    </script>

    
</body>

</html>