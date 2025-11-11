<?php
// No changes to the PHP at the top of this file
session_start();
if (!isset($_SESSION["user_mobile"]) || $_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit();
}
include 'include/dbconnect.php';
// $stationIds = explode(',', $_SESSION["station_ids"]);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tucker CMS - Settlement</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>
<style>
    /* --- NEW: BADGE STYLES --- */
    .badge {
        display: inline-block;
        padding: 4px 8px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        border-radius: 0.25rem;
        margin-left: 8px;
    }

    .badge-gst {
        color: #1d643b;
        background-color: #d1f7e0;
    }

    .badge-non-gst {
        color: #586069;
        background-color: #e8eaed;
    }

    .filter-bar {
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 20px;
    }

    /* Summary Boxes */
    .summary-boxes {
        display: flex;
        gap: 20px;
        margin-bottom: 24px;
    }

    .summary-box {
        flex: 1;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 16px;
    }

    .summary-box .label {
        font-size: 14px;
        color: #03070aff;
        margin: 0 0 8px 0;
    }

    .summary-box .value {
        font-size: 22px;
        font-weight: 600;
        color: #0d141aff;
        margin: 0;
    }

    .action-btn.active-button {
        background-color: #4CAF50;
        border-radius: 4px;
        /* Green */
        color: white;
        font-weight: bold;
        border: 1px solid #3e8e41;
    }

    .action-btn {
        margin-top: 22px;
        border-radius: 4px;
        padding: 6px 12px;
        background-color: #e53935;
        color: white;
        border: 1px solid #f1ededff;
        cursor: pointer;
    }

    .action-btn:hover {
        background-color: #e53935;
        border-radius: 4px;
    }

    .loader-container {
        position: fixed;
        /* Stick to viewport */
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        /* Optional: semi-transparent bg */
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        /* Ensure it's on top */
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 5px solid #ccc;
        border-top-color: #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .loader-container span {
        margin-top: 10px;
        font-size: 16px;
        color: #333;
    }
</style>
<?php

$url = $base_url . "station/new_history.php";
$response = file_get_contents($url);
$data = json_decode($response, true);
?>

<body>
    <div class="loader-container" id="loader">
        <div class="spinner"></div>
        <span>Loading...Please Wait...</span>
    </div>
    <div class="container">
        <?php include "left.php"; ?>
        <main class="main-content">
            <div id="customer-invoices-page" class="page-content">
                <header class="main-header">
                    <h2>Customer Invoice History</h2>
                </header>
                <div class="card">
                    <div class="filter-bar" id="customer-invoices-filters">
                        <div class="filter-item"><label for="ci-date-start">Start Date</label><input type="date" id="ci-date-start"></div>
                        <div class="filter-item"><label for="ci-date-end">End Date</label><input type="date" id="ci-date-end"></div>
                        <div class="filter-item"><label for="ci-cpo-filter">CPO Name</label><select id="ci-cpo-filter">
                                <option value="All">All CPOs</option>
                            </select></div>
                        <div class="filter-item"><label for="ci-station-search">Station ID</label><input type="text" id="ci-station-search" placeholder="Search station..."></div>
                        <div class="filter-item"><label for="ci-charger-search">Charger Point</label><input type="text" id="ci-charger-search" placeholder="Search charger ID..."></div>
                        <div class="filter-item"><label for="ci-cpo-filter">GST Status</label> <select id="gstInput">
                                <option value="all"> Select GST Status</option>
                                <option value="Y">With GST</option>
                                <option value="N">Without GST</option>
                            </select></div>

                        <div class="filter-actions"><button class="action-btn"
                                id="clearFiltersBtn">Clear</button><button class="action-btn primary"
                                id="applyFiltersBtn">Apply</button></div>

                        <div class="filter-actions"><button class="action-btn primary"
                                id="DownloadBtn">Download</button></div>
                        <br>
                    </div>


                    <!-- Summary Boxes -->
                    <div class="summary-boxes">
                        <div class="summary-box">
                            <p class="label">Total from GST Invoices</p>
                            <p class="value" id="total-gst-invoices">₹0.00</p>
                        </div>
                        <div class="summary-box">
                            <p class="label">Total from Non-GST Invoices</p>
                            <p class="value" id="total-non-gst-invoices">₹0.00</p>
                        </div>
                    </div>


                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Date</th>
                                <th>CPO Name</th>
                                <th>Station Name</th>
                                <th>Station</th>
                                <th>Chargepoint</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="customer-invoices-table-body">

                            <?php
                            $totalGST_Amount = 0;
                            $totalnon_GST_Amount = 0;
                            if ($data && $data['status'] == "true" && isset($data['Message'])) {
                                // 🔹 Sort alphabetically by CPO Name (A-Z)
                                usort($data['Message'], function ($a, $b) {
                                    return strcmp(strtolower($a['cpo_name'] ?? ''), strtolower($b['cpo_name'] ?? ''));
                                });
                                foreach ($data['Message'] as $item) {

                                    $transactionId = $item['transaction_id'] ?? '-';
                                    $name = (!empty(trim($item['name']))) ? $item['name'] : '-';
                                    $cpo_name = (!empty(trim($item['cpo_name']))) ? $item['cpo_name'] : '-';
                                    $station_name = (!empty(trim($item['station_name']))) ? $item['station_name'] : '-';

                                    $mobile = (!empty(trim($item['mobile']))) ? $item['mobile'] : '-';
                                    $chargerId = (!empty(trim($item['charger_id']))) ? $item['charger_id'] : '-';
                                    $con_qr_code = (!empty(trim($item['con_qr_code']))) ? $item['con_qr_code'] : '-';
                                    $connector = (!empty(trim($item['connector_id']))) ? $item['connector_id'] : '-';
                                    $duration = (!empty(trim($item['time_consumed']))) ? $item['time_consumed'] : '-';
                                    $station_id = (!empty(trim($item['station_id']))) ? $item['station_id'] : '-';

                                    $start = !empty($item['start_time']) ? date("d M Y H:i:s", strtotime($item['start_time'])) : '-';
                                    $end = !empty($item['stop_timestamp']) ? date("d M Y H:i:s", strtotime($item['stop_timestamp'])) : '-';

                                    $energy = isset($item['units_consumed']) ? $item['units_consumed'] . " kWh" : '-';
                                    $totalCost = isset($item['total_cost']) ? "₹" . $item['total_cost'] : '₹0';

                                    $date = !empty($item['stop_timestamp']) ? date("Y-m-d", strtotime($item['stop_timestamp'])) : '-';

                                    $status = (!empty($item['stop_reason']) && $item['stop_reason'] == "Remote") ? "completed" : "failed";

                                    $gstin = $item['gstin'] ?? '';
                                    trim($gstin) ? $totalGST_Amount += (float) str_replace('₹', '', $item['total_cost'] ?? 0) : $totalnon_GST_Amount += (float) str_replace('₹', '', $item['total_cost'] ?? 0);

                                    $gstBadge = !empty(trim($gstin)) ? '<span class="badge badge-gst">GST</span>' : '<span class="badge badge-non-gst">Non-GST</span>'; ?>

                                    <tr data-charger="<?= $chargerId ?>" data-status="<?= $status ?>" data-date="<?= $date ?>">

                                        <td>
                                            <div class="transaction-cell">
                                                <span class="table-value">#<?= $transactionId . $gstBadge ?> </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="user-info-cell">
                                                <?= htmlspecialchars($start) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($cpo_name) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($station_name) ?>
                                        </td>
                                        <td class="duration-cell">
                                            <?= $station_id ?>
                                        </td>
                                        <td class="duration-cell">
                                            <?= $chargerId ?>
                                        </td>
                                        <td>
                                            <div class="table-value"><?= $totalCost ?></div>
                                        </td>
                                        <td><a href="http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/ist.php?transid=<?= $transactionId ?>" class="action-link" target="_blank">View</a></td>
                                    </tr>
                            <?php
                                }
                            } else {
                                $totalGST_Amount = 0;
                                $totalnon_GST_Amount = 0;
                                echo "<tr><td colspan='8' style='text-align: center;'>No transaction data found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="modal-overlay" class="modal-overlay">
                <div id="modal-box" class="modal-box"></div>
            </div>
            <div id="toast" class="toast-notification"></div>
    </div>
</body>


</html>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function showPage(pageId, options = {}) {
            document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
            document.getElementById(pageId)?.classList.add('active');
            document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
            const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
            navLink?.parentElement.classList.add('active');
        }

        showPage('customer-invoices-page');
    });

    $(document).ready(function() {
        const totalGST = `<?= "₹" . number_format($totalGST_Amount, 2) ?>`;
        const totalNonGST = `<?= "₹" . number_format($totalnon_GST_Amount, 2) ?>`;
        document.getElementById('total-gst-invoices').textContent = totalGST;
        document.getElementById('total-non-gst-invoices').textContent = totalNonGST;

        let lastAppliedStartDate = null;
        let lastAppliedEndDate = null;


        // Enable Apply button when any filter changes
        $('#ci-date-start, #ci-date-end, #ci-cpo-filter, #ci-station-search, #ci-charger-search, #gstInput').on('input change', function() {
            $('#applyFiltersBtn').prop('disabled', false);
        });

        $('#applyFiltersBtn').on('click', function() {
            $(this).prop('disabled', true);
            // Add "active" style to the Apply button
            $(this).addClass('active-button');
            $('#clearFiltersBtn').removeClass('active-button');

            filterTable();
        });

        $('#clearFiltersBtn').on('click', function() {
            // Reset all fields
            $('#ci-date-start').val('');
            $('#ci-date-end').val('');
            $('#ci-cpo-filter').val('');
            $('#ci-station-search').val('');
            $('#ci-charger-search').val('');
            $('#gstInput').val('all');

            // Add "active" style to Clear button
            $(this).addClass('active-button');
            $('#applyFiltersBtn').removeClass('active-button');

            filterTable(); // Optionally re-run to reset visible rows
        });

        function filterTable() {
            const startDateVal = $('#ci-date-start').val();
            const endDateVal = $('#ci-date-end').val();
            const cpo = $('#ci-cpo-filter').val().toLowerCase();
            const stationSearch = $('#ci-station-search').val().toLowerCase();
            const chargerSearch = $('#ci-charger-search').val().toLowerCase();
            const gstInput = $('#gstInput').val().toLowerCase();

            let params = [];

            function shouldShowRow(row) {
                const rowCPO = row.find('td:nth-child(3)').text().toLowerCase();
                const rowStation = row.find('td:nth-child(4)').text().toLowerCase();
                const rowCharger = row.find('td:nth-child(5)').text().toLowerCase();

                const bgColor = window.getComputedStyle(row[0]).backgroundColor;
                const isGreen = bgColor.includes('105, 164, 127'); // GST row check

                let visible = true;
                if (cpo && cpo !== 'all' && !rowCPO.includes(cpo)) visible = false;
                if (stationSearch && !rowStation.includes(stationSearch)) visible = false;
                if (chargerSearch && !rowCharger.includes(chargerSearch)) visible = false;

                // GST filter logic
                if (gstInput === 'y' && !isGreen) visible = false;
                else if (gstInput === 'n' && isGreen) visible = false;

                return visible;
            }

            if (cpo === '' && stationSearch === '' && chargerSearch === '') {
                $('#customer-invoices-table-body tr').each(function() {
                    const row = $(this);
                    row.toggle(shouldShowRow(row));
                });
            }

            // Fetch new data
            lastAppliedStartDate = startDateVal;
            lastAppliedEndDate = endDateVal;

            document.getElementById('loader').style.display = 'flex';
            if (startDateVal) params.push(`start_date=${startDateVal}`);
            if (endDateVal) params.push(`end_date=${endDateVal}`);
            if (cpo && cpo !== 'all') params.push(`cpo_name=${encodeURIComponent(cpo)}`);
            if (stationSearch) params.push(`station_id=${encodeURIComponent(stationSearch)}`);
            if (chargerSearch) params.push(`charger_id=${encodeURIComponent(chargerSearch)}`);
            if (gstInput && gstInput !== 'all') params.push(`gstInput=${gstInput.toUpperCase()}`);


            let ajaxURL = 'fetch_filtered_data.php';
            if (params.length > 0) {
                ajaxURL += '?' + params.join('&');
            }

            $.ajax({
                url: ajaxURL,
                method: 'GET',
                dataType: 'json',
                success: function(json) {
                    document.getElementById('loader').style.display = 'none';
                    // $('#applyFiltersBtn').prop('disabled', false);
                    if (json.status !== 'true') {
                        $('#customer-invoices-table-body').empty();
                        $('#total-gst-invoices').text(`₹0.00`);
                        $('#total-non-gst-invoices').text(`₹0.00`);

                        return;
                    }

                    const rows = json.Message || [];
                    //Sort rows A → Z by cpo_name
                    rows.sort((a, b) => (a.cpo_name || '').localeCompare(b.cpo_name || ''));
                    const $tbody = $('#customer-invoices-table-body');
                    $tbody.empty();
                    let totalGST_Amount = 0;
                    let totalnon_GST_Amount = 0;
                    rows.forEach(data => {
                        const tr = $('<tr></tr>');
                        const gstBadge = data.gstin && data.gstin.trim() !== '' ?
                            '<span class="badge badge-gst">GST</span>' :
                            '<span class="badge badge-non-gst">Non-GST</span>';

                        const cost = parseFloat(data.total_cost) || 0;

                        if (data.gstin && data.gstin.trim() !== '') {
                            totalGST_Amount += cost;
                        } else {
                            totalnon_GST_Amount += cost;
                        }

                        tr.append(`<td>#${data.transaction_id} ${gstBadge}</td>`);
                        tr.append(`<td>${data.start_time ? new Date(data.start_time).toLocaleString() : '-'}</td>`);
                        tr.append(`<td>${data.cpo_name || '-'}</td>`);
                        tr.append(`<td>${data.station_name || '-'}</td>`);
                        tr.append(`<td>${data.station_id || '-'}</td>`);
                        tr.append(`<td>${data.charger_id || '-'}</td>`);
                        tr.append(`<td>₹${cost.toFixed(2)}</td>`);
                        tr.append(`<td><a href="http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/ist.php?transid=${data.transaction_id}" target="_blank" class="action-link">View</a></td>`);
                        $tbody.append(tr);
                    });

                    // Update the totals in the DOM
                    $('#total-gst-invoices').text(`₹${totalGST_Amount.toFixed(2)}`);
                    $('#total-non-gst-invoices').text(`₹${totalnon_GST_Amount.toFixed(2)}`);


                },
                error: function(xhr, status, error) {
                    document.getElementById('loader').style.display = 'none';
                    // $('#applyFiltersBtn').prop('disabled', false);
                    console.error('AJAX error:', error);
                }
            });

            // Final pass — always filter everything again to ensure consistency
            $('#customer-invoices-table-body tr').each(function() {
                const row = $(this);
                row.toggle(shouldShowRow(row));
            });
        }
    });
    $.ajax({
        url: 'api/cpo_list_api.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response || response.status !== "success") {
                console.error("API returned non-success status");
                return;
            }

            const $select = $('#ci-cpo-filter');
            $select.empty(); // Clear existing options
            $select.append('<option value="">-- Select a CPO --</option>');

            // Sort A → Z by cpo_name, trimming spaces
            const sortedData = response.data
                .filter(item => item.cpo_name && item.cpo_name.trim() !== "")
                .sort((a, b) => a.cpo_name.trim().localeCompare(b.cpo_name.trim(), 'en', {
                    sensitivity: 'base'
                }));

            // Add options
            sortedData.forEach(item => {
                $select.append(
                    $('<option></option>')
                    //.val(item.cpo_id) // use ID as value
                    .val(item.cpo_name.trim())
                    .text(item.cpo_name.trim())
                );
            });
        },
        error: function(xhr, status, error) {
            console.error("AJAX error:", error);
        }
    });


    $('#DownloadBtn').on('click', function() {
        const rows = [];
        // Add table headers
        const headers = [];
        $('.data-table thead th').each(function() {
            headers.push($(this).text().trim());
        });
        rows.push(headers);

        // Add only visible rows
        $('#customer-invoices-table-body tr:visible').each(function() {
            const row = [];
            $(this).find('td').each(function() {
                row.push($(this).text().trim());
            });
            rows.push(row);
        });

        if (rows.length <= 1) {
            alert("No data to download.");
            return;
        }

        // Create worksheet and workbook
        const worksheet = XLSX.utils.aoa_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Filtered Data");

        // Download Excel file
        XLSX.writeFile(workbook, "Customer_History.xlsx");
    });
</script>