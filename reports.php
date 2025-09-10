<?php
session_start();
if ($_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit;
}
include 'include/dbconnect.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tucker CMS - History</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/history.css">
    <!-- jQuery (Required) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Include Select2 CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>


</head>
<style>
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal;
        margin-bottom: 50px;
        /* Or use a custom value */
    }

    .select2-container--default .select2-selection--single {
        background-color: #fff;
        border: 1px solid #aaa;
        border-radius: 4px;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        font-family: var(--font-family);
        color: var(--text-dark);
        padding: 20px;
    }

    .select2-search__field {
        background-color: #fff;
        border: 1px solid #aaa;
        border-radius: 4px;
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 14px;
        font-family: var(--font-family);
        color: var(--text-dark);
        padding: 20px;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_processing,
    .dataTables_wrapper .dataTables_paginate {
        margin-top: 20px !important;
        margin-left: 10px !important;
        margin-right: 20px;
    }
</style>
<?php
$url = $base_url . "station/new_history.php";
$response = file_get_contents($url);
$data = json_decode($response, true);
$perPage = 10; // Number of rows per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$startIndex = ($page - 1) * $perPage;

?>

<body>
    <div class="loader-container" id="loader">
        <div class="spinner"></div>
        <span>Loading...</span>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include "left.php"; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="page-header">
                <h2>Session History</h2>
                <div class="header-actions">
                    <div class="filters-wrapper">
                        <button class="action-btn" id="filterBtn"><svg xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                            </svg>Filters</button>
                        <div class="filter-panel" id="filterPanel">
                            <h3>Filter Sessions</h3>
                            <div class="form-grid">
                                <div class="form-group"><label for="dateRange">Date Range</label>
                                    <div class="date-range"><input type="date" id="dateStart"
                                            value="<?php echo date('d-m-Y'); ?>"><span>to</span><input type="date"
                                            id="dateEnd" value="<?php echo date('d-m-Y'); ?>"></div>
                                </div>
                                <?php if ($result_data && mysqli_num_rows($result_data) > 0) { ?>
                                    <div class="form-group"> <label for="Stations">Stations</label>
                                        <select name="station_id" id="station_id" class="form-control selectpicker"
                                            data-live-search="true" data-width="100%" required>
                                            <option value="all"> Select All Stations</option>
                                            <?php
                                            $result = mysqli_query($connect, "SELECT * FROM fca_stations WHERE station_id = '{$_SESSION['station_id']}' OR parent_id = '{$_SESSION["sno"]}'");
                                            while ($row = mysqli_fetch_array($result)) {
                                                $station_id_db = $row['station_id'];
                                                $selected = ($station_id_db == ($station_id ?? '')) ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $station_id_db; ?>" <?php echo $selected; ?>>
                                                    <?php echo $station_id_db; ?>
                                                </option>
                                            <?php
                                            }
                                            ?>
                                        </select>
                                    </div>

                                <?php } ?>
                                <div class="form-group"> <label for="chargerIdInput">Charger ID</label>
                                    <select id="chargerIdInput">
                                        <option value="">-- Select Charger ID --</option>
                                        <?php
                                        if (!empty($data_CP['Message'])) {
                                            foreach ($data_CP['Message'] as $station) {
                                                foreach ($station['Detail'] as $charger) {
                                                    $charger_id = htmlspecialchars($charger['charger_id']);
                                        ?>
                                                    <option value="<?= $charger_id ?>">
                                                        <?= $charger_id ?>
                                                    </option>
                                        <?php
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group"> <label for="gstInput">GST Status</label>
                                    <select id="gstInput">
                                        <option value="all"> Select GST Status</option>
                                        <option value="Y">With GST</option>
                                        <option value="N">Without GST</option>
                                    </select>
                                </div>

                            </div>
                            <div class="filter-actions"><button class="action-btn"
                                    id="clearFiltersBtn">Clear</button><button class="action-btn primary"
                                    id="applyFiltersBtn">Apply</button></div>
                        </div>
                    </div>
                    <button class="action-btn primary" onclick="report()"><svg xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>Download Report</button>
                </div>
            </header>
            <div class="history-card card">
                <table id="historyTable" class="display history-table">
                    <thead>
                        <tr>
                            <th>Transaction</th>
                            <th>User</th>
                            <th>Charger</th>
                            <th>Duration</th>
                            <th>Energy</th>
                            <th>Total Cost</th>
                            <th>View Details</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php
                        $chargerIdarray = [];

                        if ($data && $data['status'] == "true" && isset($data['Message'])) {
                            foreach ($data['Message'] as $item) {
                                $transactionId = $item['transaction_id'];
                                $name = $item['name'];
                                $mobile = $item['mobile'];
                                $chargerId = $item['charger_id'];
                                $chargerIdarray[] = $chargerId;
                                $con_qr_code = $item['con_qr_code'];
                                $connector = $item['connector_id'];
                                $duration = $item['time_consumed'];
                                $start = date("d M Y H:i:s", strtotime($item['start_time']));
                                $end = date("d M Y H:i:s", strtotime($item['stop_timestamp']));
                                $energy = $item['units_consumed'] . " kWh";
                                $totalCost = "₹" . number_format((float) $item['total_cost'], 2);
                                $date = date("Y-m-d", strtotime($item['stop_timestamp']));
                                $status = ($item['stop_reason'] == "Remote") ? "completed" : "failed";
                                $gstin = $item['gstin'];
                                $background = !empty($gstin) ? 'background-color: #69a47f94;' : '';
                        ?>
                                <tr data-charger="<?= $chargerId ?>" data-status="<?= $status ?>" data-date="<?= $date ?>" style="<?= $background ?>">
                                    <td>
                                        <div class="transaction-cell">
                                            <span class="table-value">#<?= $transactionId ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info-cell">
                                            <strong><?= htmlspecialchars($name) ?></strong><small><?= $mobile ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info-cell"><strong><?= $chargerId ?></strong><small>Connector
                                                <?= $connector ?></small><br><small>QR Code
                                                <?= $con_qr_code ?></small></div>
                                    </td>
                                    <td class="duration-cell">
                                        <span class="table-value"><?= $duration ?></span><br>
                                        <span><strong>Start:</strong> <?= $start ?></span><br>
                                        <span><strong>Stop:</strong> <?= $end ?></span>
                                        <div class="duration-tooltip">
                                            <span><strong>Start:</strong> <?= $start ?></span>
                                            <span><strong>Stop:</strong> <?= $end ?></span>
                                        </div>
                                    </td>
                                    <td><?= $energy ?></td>
                                    <td>
                                        <div class="table-value"><?= $totalCost ?></div>
                                    </td>
                                    <td>
                                        <button class="btn action-btn history-btn" data-bs-toggle="modal"
                                            data-bs-target="#historyModal"
                                            data-history='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>'>
                                            <i class="fas fa-history me-1"></i>History
                                        </button>
                                    </td>
                                    <td> <a
                                            href="http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/inv.php?transid=<?= $transactionId ?>" target="_blank">
                                            <h5><i class="far fa-file-alt"></i></h5>
                                        </a></td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='8'>No transaction data found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>

    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg"> <!-- large modal for table -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activity History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="historyDetailsTable">
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            const $firstCellText = $('#historyTable tbody tr:first td:first').text().trim();

            if ($firstCellText !== 'No transaction data found.') {
                $('#historyTable').DataTable({
                    pageLength: 10,
                    order: [
                        [0, 'desc']
                    ] // Default sort by first column descending
                });
            }
        });

        var stationId = '';

        $(document).ready(function() {
            let selected_charger = "<?= $charger ?>"; // must be quoted!
            // Initial load
            loadChargers($('#station_id').val());
            // On station change
            $('#station_id').on('change', function() {
                stationId = $(this).val();
                selected_charger = "all"; // Clear or keep as needed
                loadChargers(stationId);
            });

            function loadChargers(stationId) {
                $.ajax({
                    url: 'get_chargers.php',
                    type: 'GET',
                    dataType: 'json',
                    data: {
                        station_id: stationId
                    },
                    success: function(data) {
                        var options = '<option value="all">Select All Chargers</option>';
                        $.each(data, function(index, charger) {
                            var isSelected = (charger.charger_id === selected_charger) ? ' selected' : '';
                            options += '<option value="' + charger.charger_id + '"' + isSelected + '>' +
                                charger.charger_id + ' [ ' + charger.charger_qr_code + ' ]</option>';
                        });

                        $('#chargerIdInput').html(options);
                        // $('.selectpicker').selectpicker('refresh');
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', error);
                    }
                });
            }
            // $('#charger').selectpicker();
        });

        var Chargepoint = '';
        document.getElementById('chargerIdInput').addEventListener('change', function() {
            Chargepoint = this.value;
            console.log("Selected Charger ID:", Chargepoint);
        });

        function report() {
            var fromdate = document.getElementById('dateStart').value;
            var todate = document.getElementById('dateEnd').value;
            window.location.href = "history_excel.php?fromdate=" + fromdate + "&todate=" + todate + "&Chargepoint=" + Chargepoint;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const filterBtn = document.getElementById('filterBtn');
            const filterPanel = document.getElementById('filterPanel');
            const applyFiltersBtn = document.getElementById('applyFiltersBtn');
            const clearFiltersBtn = document.getElementById('clearFiltersBtn');
            const tableRows = document.querySelectorAll('#historyTableBody tr');

            if (filterBtn && filterPanel) {
                filterBtn.addEventListener('click', (event) => {
                    event.stopPropagation();
                    filterPanel.classList.toggle('visible');
                });
                document.addEventListener('click', (event) => {
                    if (!filterPanel.contains(event.target) && !filterBtn.contains(event.target)) {
                        filterPanel.classList.remove('visible');
                    }
                });
            }
            let dataTableInstance;

            function applyFilters() {
                document.getElementById('loader').style.display = 'flex';
                $('#applyFiltersBtn').prop('disabled', true);
                $('#filterPanel').removeClass('visible');

                const dateStart = document.getElementById('dateStart').value;
                const dateEnd = document.getElementById('dateEnd').value;
                const chargerId = document.getElementById('chargerIdInput').value;
                const status = document.getElementById('sessionStatusInput')?.value || '';
                const gstInput = document.getElementById('gstInput')?.value || '';

                let params = [];

                if (chargerId) params.push(`charger_id=${encodeURIComponent(chargerId)}`);
                if (dateStart) params.push(`start_date=${encodeURIComponent(dateStart)}`);
                if (dateEnd) params.push(`end_date=${encodeURIComponent(dateEnd)}`);
                if (gstInput) params.push(`gstInput=${encodeURIComponent(gstInput)}`);
                if (status && status !== 'all') params.push(`status=${encodeURIComponent(status)}`);

                const queryString = params.length > 0 ? `?${params.join('&')}` : '';
                const ajaxURL = `fetch_filtered_data.php${queryString}`;

                // Destroy old DataTable instance if exists
                if ($.fn.DataTable.isDataTable('#historyTable')) {
                    $('#historyTable').DataTable().clear().destroy();
                }

                dataTableInstance = $('#historyTable').DataTable({
                    ajax: {
                        url: ajaxURL,
                        dataSrc: function(json) {
                            document.getElementById('loader').style.display = 'none';
                            $('#applyFiltersBtn').prop('disabled', false);
                            return json.status === 'true' ? json.Message : [];
                        }
                    },
                    columns: [{
                            data: 'transaction_id',
                            render: data => `#${data}`
                        },
                        {
                            data: null,
                            render: d => `<strong>${d.name || '-'}</strong><br><small>${d.mobile || '-'}</small>`
                        },
                        {
                            data: null,
                            render: d => `<strong>${d.charger_id}</strong><br><small>Connector ${d.connector_id}</small><br><small>QR ${d.con_qr_code}</small>`
                        },
                        {
                            data: null,
                            render: d => {
                                const start = new Date(d.start_time).toLocaleString();
                                const stop = new Date(d.stop_timestamp).toLocaleString();
                                return `${d.time_consumed}<br><small><b>Start:</b> ${start}</small><br><small><b>Stop:</b> ${stop}</small>`;
                            }
                        },
                        {
                            data: 'units_consumed',
                            render: d => `${d} kWh`
                        },
                        {
                            data: 'total_cost',
                            render: d => `₹${parseFloat(d).toFixed(2)}`
                        },
                        {
                            data: null,
                            render: d => {
                                const historyData = JSON.stringify(d).replace(/"/g, '&quot;');
                                return `<button class="btn btn-sm action-btn history-btn" data-bs-toggle="modal" data-bs-target="#historyModal" data-history="${historyData}"><i class="fas fa-history me-1"></i>History</button>`;
                            }
                        },
                        {
                            data: 'transaction_id',
                            render: d => `<a href="http://cms.tuckerio.bigtot.in/flutter/FlutterInvoice/inv.php?transid=${d}" target="_blank"><i class="far fa-file-alt"></i></a>`
                        }
                    ],
                    pageLength: 10,
                    // Add this to apply background color conditionally
                    createdRow: function(row, data, dataIndex) {
                        if (data.gstin) {
                            $(row).css('background-color', '#69a47f94');
                        }
                    }
                });
            }

            if (applyFiltersBtn) applyFiltersBtn.addEventListener('click', applyFilters);
            if (clearFiltersBtn) {
                clearFiltersBtn.addEventListener('click', () => {
                    const today = new Date().toISOString().split('T')[0];
                    document.getElementById('dateStart').value = document.getElementById('dateEnd').value = today;
                    document.getElementById('chargerIdInput').value = '';
                    //document.getElementById('sessionStatusInput').value = 'all';
                    tableRows.forEach(row => {
                        row.style.display = '';
                    });
                    filterPanel.classList.remove('visible');
                    window.location.reload();
                });
            }
        });
        //Modal table Format to diplay the all details 
        document.addEventListener('DOMContentLoaded', function() {
            const historyModal = document.getElementById('historyModal');

            historyModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const data = JSON.parse(button.getAttribute('data-history'));

                const tableBody = document.querySelector('#historyDetailsTable tbody');
                if (!tableBody) return;

                tableBody.innerHTML = ''; // Clear previous content

                // Helper: handles empty/null + adds prefix/suffix
                const formatValue = (val, suffix = '', prefix = '') => {
                    return val !== null && val !== undefined && val !== '' ? `${prefix}${val}${suffix}` : '-';
                };

                const rows = [
                    ['Transaction ID', formatValue(data.transaction_id), 'Connector ID', formatValue(data.connector_id)],
                    ['User Name', formatValue(data.name), 'Mobile', formatValue(data.mobile)],
                    ['Email', formatValue(data.email), 'Charger ID', formatValue(data.charger_id)],
                    ['Station Name', formatValue(data.station_name), 'Station City', formatValue(data.station_city)],
                    ['Start Time', formatValue(data.start_time), 'Stop Time', formatValue(data.stop_timestamp)],
                    ['Units Consumed', formatValue(data.units_consumed, ' kWh'), 'Total Cost', formatValue(data.total_cost, '', '₹')],
                    ['Base Fare', formatValue(data.base_fare, '', '₹'), 'Unit Cost', formatValue(data.unit_cost, '', '₹')],
                    ['GST', formatValue(data.gst_amount, '', '₹'), 'Razorpay Charges', formatValue(data.razorpay_amount, '', '₹')],
                    ['Voltage', formatValue(data.voltage, ' V'), 'Current', formatValue(data.current, ' A')],
                    ['Power', formatValue(data.power, ' W'), 'Duration', formatValue(data.time_consumed)],
                    ['Start SOC', formatValue(data.start_soc), 'Stop SOC', formatValue(data.stop_soc)],
                    ['Stop Reason', formatValue(data.stop_reason), 'Connector Type', formatValue(data.con_type)]
                ];

                rows.forEach(([key1, val1, key2, val2]) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<th>${key1}</th><td>${val1}</td><th>${key2}</th><td>${val2}</td>`;
                    tableBody.appendChild(row);
                });
            });
        });

        sessionStorage.removeItem('active_tab');
        $(document).ready(function() {
            // Ensure select2 applies correctly
            $('#chargerIdInput').select2({
                placeholder: "-- Select Charger ID --",
                allowClear: true,
                width: '100%' // Force it to full width to avoid dropdown closing issues
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const filterBtn = document.getElementById('filterBtn');
            const filterPanel = document.getElementById('filterPanel');
            const filterWrapper = document.querySelector('.filters-wrapper');

            // Show/hide filter panel when button is clicked
            filterBtn.addEventListener('click', function(e) {
                e.stopPropagation(); // prevent event bubbling
                if (filterPanel.style.display === 'none' || filterPanel.style.display === '') {
                    filterPanel.style.display = 'block';
                } else {
                    filterPanel.style.display = 'none';
                }
            });

            // Close panel when clicking outside (excluding Select2 dropdown)
            document.addEventListener('click', function(event) {
                const select2Container = document.querySelector('.select2-container');
                const select2Search = document.querySelector('.select2-search__field');

                const clickedInsideFilter = filterWrapper.contains(event.target);
                const clickedInsideSelect2 = select2Container && select2Container.contains(event.target);
                const clickedSearch = select2Search && select2Search === event.target;

                if (!clickedInsideFilter && !clickedInsideSelect2 && !clickedSearch) {
                    filterPanel.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>