<?php
session_start();
// if (empty($_SESSION["citymart_cpo"])) {
//     header('Location: index.php');
//     exit;
// }

include 'include/dbconnect.php';
include 'api/check_active_status.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['station_id'])) {
    $_SESSION["demo_station_id"] = $_POST['station_id'];
    $station_id = $_SESSION["demo_station_id"];
    $_SESSION["demo_station_mobile"] = $_POST['station_mobile'];
    $station_mobile = $_SESSION["demo_station_mobile"];
}

if (!$_POST['station_mobile']) {
    $summary['total_units']  = 0;
    $summary['unit_cost']    = 0;
    $summary['gst']          = 0;
    $summary['total_cost']   = 0;
    $summary['total_rate']   = 0;
    $summary['rate']         = 0;
    $gstStatusCounts[$tx['gst_status']] = 0;
    $summary['final_cost']  = 0;
    $summary['gst_amount']  = 0;
    $summary['final_cost'] = 0;
    $summary['gst_status'] = 0;
}
// Default summary
$summary = [
    'total_units' => 0,
    'unit_cost' => 0,
    'gst' => 0,
    'total_cost' => 0,
    'total_rate' => 0,
    'final_cost' => 0
];

$messages = [];
$data_loaded = false;
$start_day = $end_day = $month_name = '[N/A]';

$settlement_status = isset($_POST['status']) ? htmlspecialchars($_POST['status']) : 'Pending';

// Check POST data
if (isset($_POST['month_name'], $_POST['half_month'], $_POST['total_costs'])) {
    $month_name = htmlspecialchars($_POST['month_name']);
    $half_month = htmlspecialchars($_POST['half_month']);
    $total_costs = htmlspecialchars($_POST['total_costs']);

    list($start_day, $end_day) = explode('-', $half_month);
    $month_number = date('m', strtotime($month_name));
    $year = date("Y");

    $start_date = "$year-$month_number-" . str_pad($start_day, 2, '0', STR_PAD_LEFT);
    $end_date   = "$year-$month_number-" . str_pad($end_day, 2, '0', STR_PAD_LEFT);
    $response = false;
    if ($station_mobile) {
        $url = "http://cms.tuckerio.bigtot.in/station/new_history.php?station_mobile=" . urlencode($station_mobile) . "&start_date=$start_date&end_date=$end_date";
        $response = @file_get_contents($url);
    } else {
        $url = "http://cms.tuckerio.bigtot.in/station/new_history.php?station_id=" . urlencode($station_id) . "&start_date=$start_date&end_date=$end_date";
        $response = @file_get_contents($url);
    }

    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === "true" && isset($data['Message'])) {
            $messages = $data['Message'];
            foreach ($messages as $tx) {
                $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $tx['start_time']);
                if ($startTime) {
                    $day = (int)$startTime->format('d');
                    if ($day >= (int)$start_day && $day <= (int)$end_day) {
                        $summary['total_units']  += round((float)($tx['total_units'] ?? 0), 2);
                        $summary['unit_cost']    += round((float)($tx['unit_cost'] ?? 0), 2);
                        $summary['gst']          += round((float)($tx['gst_amount'] ?? 0), 2);
                        $summary['total_cost']   += round((float)($tx['total_cost'] ?? 0), 2);
                        $summary['total_rate']   += round((float)($tx['unitfare'] ?? 0), 2);
                        $summary['rate']         += round((float)($tx['rate'] ?? 0), 2);

                        $gstStatusCounts[$tx['gst_status']] = ($gstStatusCounts[$tx['gst_status']] ?? 0) + 1;

                        $summary['final_cost']  += round((float)($tx['final_cost'] ?? 0), 2);
                        $summary['gst_amount']  += round(((float)($tx['unit_cost'] ?? 0) * 18 / 100), 2);
                    }
                }
            }
            if ($gstStatusCounts) {
                $summary['gst_status'] = array_search(max($gstStatusCounts), $gstStatusCounts);
            }
            $total_service_fee = $summary['unit_cost'] - $summary['final_cost'];

            if ($summary['gst_status'] == 'Y') {
                $summary['final_cost'] = $summary['final_cost'] + $summary['gst_amount'];
            }

            $data_loaded = true;
        }
    } else {
        $summary['total_units']  = 0;
        $summary['unit_cost']    = 0;
        $summary['gst']          = 0;
        $summary['total_cost']   = 0;
        $summary['total_rate']   = 0;
        $summary['rate']         = 0;
        $gstStatusCounts[$tx['gst_status']] = 0;
        $summary['final_cost']  = 0;
        $summary['gst_amount']  = 0;
        $summary['final_cost'] = 0;
        $summary['gst_status'] = 0;
    }
}

// Styles & Status Classes
switch ($settlement_status) {
    case 'Processed':
        $overview_card_style = 'background: linear-gradient(135deg, #198754, #28a745); color: white;';
        $status_badge_class = 'processed';
        $status_badge_text = 'Processed';
        $status_badge_icon = 'fa-check-circle';
        $total_row_class_modifier = 'processed';
        $net_value_class_modifier = 'net-processed';
        $table_header_class = 'thead-processed';
        break;

    case 'Processing':
        $overview_card_style = 'background: linear-gradient(135deg, #d47f7cff, #e7d1c4ff); color: white;';
        $status_badge_class = 'processing';
        $status_badge_text = 'Processing';
        $status_badge_icon = 'fa-solid fa-hourglass-half';
        $total_row_class_modifier = 'processing';
        $net_value_class_modifier = 'net-processing';
        $table_header_class = 'thead-processing';
        break;

    default:
        $overview_card_style = 'background: linear-gradient(145deg, #f8b007ff, #f0ece0ff); font-weight:800;';
        $status_badge_class = 'pending';
        $status_badge_text = 'Pending';
        $status_badge_icon = 'fa-clock';
        $total_row_class_modifier = 'pending';
        $net_value_class_modifier = 'net-pending';
        $table_header_class = 'thead-pending';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tucker CMS - Settlement</title>
    <!-- <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/style.css">
    <link rel="stylesheet" href="assets/styles/settlement_details.css">
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script> -->

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <link rel="stylesheet" href="assets/styles/settlement_details.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<style>
    .table-responsive {
        overflow-x: auto;
    }
</style>

<body>
    <div class="loader-container" id="loader">
        <div class="spinner"></div>
        <span>Loading Details...</span>
    </div>

    <div class="container">
        <?php include "left.php"; ?>

        <main class="main-content">
            <header class="header">
                <h1 class="header-title">Settlement Details</h1>
                <i class="fas fa-bell notification-bell"></i>
            </header>

            <a href="settlement.php" class="go-back-link"><i class="fas fa-arrow-left"></i> Go Back to Settlements</a>

            <div class="top-cards-grid">
                <div class="card overview-card" style="<?= $overview_card_style ?>">
                    <span class="status-badge <?= $status_badge_class ?>"><i class="far <?= $status_badge_icon ?>"></i> <?= $status_badge_text ?></span>
                    <span class="amount">₹ <?= number_format(max((float)$summary['final_cost'] - 5.9, 0), 2) ?></span>
                    <div class="date-range">For Period: <?= "$start_day to $end_day $month_name" ?></div>
                </div>
                <div class="card breakdown-card">
                    <h2 class="breakdown-title">Settlement Breakdown</h2>
                    <div class="breakdown-row">
                        <span class="breakdown-label">Total Unit Cost</span>
                        <span class="breakdown-value">₹ <?= number_format((float)$summary['unit_cost'], 2) ?></span>
                    </div>
                    <div class="breakdown-row">
                        <span class="breakdown-label">Deduction for Service Fee</span>
                        <span class="breakdown-value deduction">- ₹ <?= number_format((float)$total_service_fee, 2) ?></span>
                    </div>
                    <?php if ((float)$summary['final_cost'] > 0) { ?>
                        <div class="breakdown-row">
                            <span class="breakdown-label">Deduction for Bank Fee</span>
                            <span class="breakdown-value deduction">- ₹5.9</span>
                        </div>
                    <?php } ?>

                    <?php if ($summary['gst_status'] == 'Y') { ?>
                        <div class="breakdown-row">
                            <span class="breakdown-label">GST Amount</span>
                            <span class="breakdown-value addition">+ ₹ <?= number_format((float)$summary['gst_amount'], 2) ?></span>
                        </div>
                    <?php  } ?>
                    <div class="breakdown-row total <?= $total_row_class_modifier ?>">
                        <span class="breakdown-label">Net Settlement Amount</span>
                        <span class="breakdown-value net <?= $net_value_class_modifier ?>">
                            ₹ <?= number_format(max((float)$summary['final_cost'] - 5.9, 0), 2) ?>
                        </span>

                    </div>
                </div>
            </div>

            <div class="card transactions-card">
                <div class="card-header">
                    <h2 class="card-title">Detailed Transactions (<?= count($messages) ?>)</h2>
                    <div class="table-controls">
                        <div class="station-filter" style="margin-bottom: 1rem;">
                            <label for="station_id">Filter by Station:</label>
                            <select name="station_id" id="station_id" class="form-control selectpicker" style="padding: 8px 12px;border: 1px solid #ccc; border-radius: 6px;font-size: 14px;min-width: 220px;transition: border-color 0.3s, box-shadow 0.3s;"
                                data-live-search="true" data-width="100%" required>
                                <option value="all"> Select All Stations</option>
                                <?php
                                $result = mysqli_query($connect, "SELECT * FROM fca_stations WHERE station_mobile = '{$station_mobile}'");
                                while ($row = mysqli_fetch_array($result)) {
                                    $station_id_db = $row['station_id'] . " [ " . $row['station_city'] . " ] ";
                                    $station_value = $row['station_id'];

                                    $selected = ($station_id_db == ($station_id ?? '')) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $station_value; ?>" <?php echo $selected; ?>>
                                        <?php echo $station_id_db; ?>
                                    </option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="transactionsTable" class="display nowrap" style="width:100%;">
                        <thead class="<?= $table_header_class ?>">
                            <!-- ===== MODIFIED: Removed "Energy Rate" header ===== -->
                            <tr>
                                <th>S.No.</th>
                                <th>Trans ID</th>
                                <th>Charger </th>
                                <th>Start / Stop Time</th>
                                <th>Energy (kWh)</th>
                                <th>Unit Cost / Per kW fee</th>
                                <th>Gross Amount (₹)</th>
                                <th>GST Amount</th>
                                <th>Deduction Fee</th>
                                <th>Net Amount (₹)</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($data_loaded && !empty($messages)) : ?>
                                <?php
                                $row_num = 1;
                                foreach ($messages as $msg) :
                                    // Round to 2 digits for calculations
                                    $total_units       = isset($msg['total_units']) ? round((float)$msg['total_units'], 2) : 0;
                                    $service_fee_rate  = isset($msg['rate']) ? round((float)$msg['rate'], 2) : 0;
                                    $energy_rate       = isset($msg['unitfare']) ? round((float)$msg['unitfare'], 2) : 0;
                                    $gross_amount      = isset($msg['unit_cost']) ? round((float)$msg['unit_cost'], 2) : 0;
                                    $net_amount        = isset($msg['final_cost']) ? round((float)$msg['final_cost'], 2) : 0;

                                    $service_fee_deduction = round($gross_amount - $net_amount, 2);
                                    $GST_amount = round(($gross_amount * 18 / 100), 2);
                                    //$GST_amount = round(($gross_amount * 18 / 100), 2);

                                    $start = date("d M Y, H:i", strtotime($msg['start_time']));
                                    $end   = date("d M Y, H:i", strtotime($msg['stop_timestamp'] ?? $msg['stop_time'] ?? ''));
                                    $invoice_url = "http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/ist.php?transid=" . urlencode($msg['transaction_id']);
                                    // $net_amount = $net_amount + $GST_amount; 
                                ?>

                                    <tr class="clickable-row" data-invoice-url="<?= $invoice_url ?>">
                                        <td><?= $row_num++ ?></td>
                                        <td><?= htmlspecialchars($msg['transaction_id']) ?></td>
                                        <td><?= htmlspecialchars($msg['charger_id']) ?></td>
                                        <td><?= $start ?><br><?= $end ?></td>
                                        <td><?= $total_units ?></td>
                                        <td><?= number_format($energy_rate, 2) ?> / <?= number_format($service_fee_rate, 2) ?></td>
                                        <td><?= number_format($gross_amount, 2) ?></td>

                                        <td><?= $GST_amount ?></td>
                                        <td><?= number_format($service_fee_deduction, 2) ?></td>
                                        <td>
                                            <?= number_format(($summary['gst_status'] == 'Y' ? $net_amount + $GST_amount : $net_amount), 2) ?>
                                        </td>

                                        <td style="font-weight: 600;">
                                            <span class="tooltip-container">
                                                <i class="fas fa-info-circle info-icon"></i>
                                                <span class="tooltip-text" style="width: 220px; margin-left: -110px; text-align: left; line-height: 1.6;">
                                                    <div style="display: flex; justify-content: space-between;"><span>Gross Amount:</span> <span>₹ <?= number_format($gross_amount, 2) ?></span></div>
                                                    <div style="display: flex; justify-content: space-between; color: #ffadad;"><span>Deduction Fee:</span> <span>- ₹ <?= number_format($service_fee_deduction, 2) ?></span></div>
                                                    <?php if ($summary['gst_status'] == 'Y') {
                                                        $net_amount = $net_amount + $GST_amount; ?>
                                                        <div style="display: flex; justify-content: space-between; color: #83e99dff;"><span>GST Amount:</span> <span>+ ₹ <?= number_format($GST_amount, 2) ?></span></div>
                                                    <?php } ?>
                                                    <hr style="border-color: #555; margin: 4px 0;">
                                                    <div style="display: flex; justify-content: space-between; font-weight: bold;"><span>Net Total:</span><span>₹ <?= number_format($net_amount, 2) ?></span></div>
                                                </span>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>


                            <?php endif; ?>

                        </tbody>

                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
        $(document).ready(function() {
            $('#loader').css('display', 'flex');
            // console.log("Header count:", $('#transactionsTable thead th').length);
            // let firstRowCells = $('#transactionsTable tbody tr:first td');
            // console.log("First row cell count:", firstRowCells.length);
            var table = $('#transactionsTable').DataTable({
                dom: 'Bfrtp',
                buttons: [{
                    extend: 'collection',
                    text: '<i class="fas fa-download"></i> Download',
                    buttons: [{
                            extend: 'csv',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            exportOptions: {
                                columns: ':not(:last-child)'
                            },
                            action: function(e, dt, button, config) {
                                logExportAction('CSV');
                                $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                            }
                        },
                        {
                            extend: 'excel',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            exportOptions: {
                                columns: ':not(:last-child)'
                            },
                            action: function(e, dt, button, config) {
                                logExportAction('Excel');
                                $.fn.dataTable.ext.buttons.excelHtml5.action.call(this, e, dt, button, config);
                            }
                        },
                        {
                            extend: 'pdf',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            exportOptions: {
                                columns: ':not(:last-child)'
                            },
                            action: function(e, dt, button, config) {
                                logExportAction('PDF');
                                $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Print',
                            exportOptions: {
                                columns: ':not(:last-child)'
                            },
                            action: function(e, dt, button, config) {
                                logExportAction('Print');
                                $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                            }
                        }
                    ]
                }],
                columns: [
                    null, null, null, null, null,
                    null, null, null, null, null, null
                ],
                pageLength: 8,
                language: {
                    search: "",
                    searchPlaceholder: "Search transactions...",
                    emptyTable: "No transaction data available."
                },
                responsive: true,
                initComplete: function(settings, json) {
                    $('.dt-buttons').appendTo('.table-controls');
                    $('.dataTables_filter').appendTo('.table-controls');
                    $('#loader').hide();
                },
                drawCallback: function(settings) {
                    var api = this.api();
                    $('#loader').hide(); // Always hide after render

                    var pageInfo = api.page.info();
                    if (pageInfo.pages <= 1) {
                        $('.dataTables_paginate').hide();
                    } else {
                        $('.dataTables_paginate').show();
                    }
                }
            });


            if (!$.fn.DataTable.isDataTable('#transactionsTable') || table.rows().count() === 0) {
                $('#loader').hide();
            }

            // Event listener for clickable rows to open invoices
            $('#transactionsTable tbody').on('click', 'tr.clickable-row', function(e) {
                // Prevent click on tooltip from triggering the row click
                if ($(e.target).closest('.tooltip-container').length) {
                    return;
                }

                const invoiceUrl = $(this).data('invoice-url');
                if (invoiceUrl) {
                    window.open(invoiceUrl, '_blank').focus();
                }
            });


            function logExportAction(type) {
                $.ajax({
                    url: 'api/log_export_action.php',
                    method: 'POST',
                    data: {
                        export_type: type,
                        page_name: window.location.pathname
                    },
                    success: function(res) {
                        console.log("Export logged:", res);
                    }
                });
            }
            $('#station_id').on('change', function() {
                let station_id = $(this).val();
                let start_date = '<?= $start_date ?>';
                let end_date = '<?= $end_date ?>';

                // Show temporary loading row
                table.clear().draw();
                $('#transactionsTable tbody').html('<tr><td colspan="11" style="text-align:center;">Loading...</td></tr>');

                $.ajax({
                    url: 'fetch_transactions.php',
                    type: 'POST',
                    data: {
                        station_id: station_id,
                        start_date: start_date,
                        end_date: end_date
                    },
                    success: function(response) {
                        let $rows = $(response);

                        table.clear(); // Clear existing rows

                        if ($rows.length === 1 && $rows.find('td[colspan]').length) {
                            // No data row — append manually without triggering DataTable draw
                            $('#transactionsTable tbody').html(response);
                        } else {
                            // Add real rows
                            table.rows.add($rows).draw();
                        }

                        // Always hide loader
                        $('#loader').hide();
                    },
                    error: function() {
                        table.clear().draw();
                        $('#transactionsTable tbody').html(
                            '<tr><td colspan="11" style="text-align:center;">Failed to load data</td></tr>'
                        );

                        // Always hide loader
                        $('#loader').hide();
                    }
                });
            });

        });
    </script>
</body>

</html>