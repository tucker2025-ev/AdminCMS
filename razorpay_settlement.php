<?php
// No changes to the PHP at the top of this file
session_start();
// ✅ Safe check before accessing the variable
if (!isset($_SESSION["user_mobile"]) || $_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit();
}

include 'include/dbconnect.php';
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
    .data-table th,
    .data-table td {
        text-align: center;
    }

    .amount-input {
        width: 100px;
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .btn-status {
        padding: 4px 10px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        color: #fff;
        background-color: green;
    }


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

<body>
    <div class="loader-container" id="loader">
        <div class="spinner"></div>
        <span>Loading...Please Wait...</span>
    </div>
    <div class="container">
        <?php include "left.php"; ?>
        <main class="main-content">
            <div id="razorpay-history-page" class="page-content">
                <header class="main-header">
                    <h2>Razorpay History</h2>
                </header>
                <div class="card">
                    <div class="filter-bar" id="customer-invoices-filters">
                        <div class="filter-item"><label for="ci-date-start">Start Date</label><input type="date" id="ci-date-start"></div>
                        <div class="filter-item"><label for="ci-date-end">End Date</label><input type="date" id="ci-date-end"></div>
                        <div class="filter-actions"><button class="action-btn"
                                id="clearFiltersBtn">Clear</button><button class="action-btn primary"
                                id="applyFiltersBtn">Apply</button></div>

                        <div class="filter-actions"><button class="action-btn primary"
                                id="DownloadBtn">Download</button></div>
                        <br>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SNO</th>
                                <th>Entry Time</th>
                                <th>Captured Amount ( ₹ )</th>
                                <th>Razorpay Fee ( ₹ )</th>
                                <th>Settlement Amount ( ₹ )</th>
                                <th>Manual Amount ( ₹ )</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="razorpay-settlements"></tbody>
                    </table>
                </div>
            </div>
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
        showPage('razorpay-history-page');
    });

    // Function to fetch data (optionally with date range)
    function loadData(startDate = '', endDate = '') {
        let url = 'api/razorpay_lists.php?action=razorpay_lists';
        if (startDate && endDate) {
            url += `&start_date=${startDate}&end_date=${endDate}`;
        }

        fetch(url)
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    renderTable(res.data, res.monthly);
                } else {
                    document.getElementById('razorpay-settlements').innerHTML =
                        '<tr><td colspan="4">No records found</td></tr>';
                }
            })
            .catch(err => console.error(err));
    }
    // Render table and summaries
    function renderTable(data, monthly = null) {
        const tbody = document.getElementById('razorpay-settlements');
        tbody.innerHTML = '';

        const feeRate = 0.0236;
        const CURRENT_DATE = new Date().toISOString().split('T')[0]; // today's date (yyyy-mm-dd)

        data.forEach((item, index) => {
            const tr = document.createElement('tr');

            // Action cell logic
            let actionCell = "";

            if (item.settlement_status === 'N' && item.payment_date === CURRENT_DATE) {
                actionCell = `<span style="font-weight:600; color:#666;">N/A</span>`;
            } else if (item.settlement_status === 'N') {
                // Case: unsettled but not today → allow input
                actionCell = `
                <input type="number" class="amount-input"
                    id="settlement-amount-${index + 1}" value=""
                    placeholder="Enter amount">
                <button class="action-btn amount-btn"
                    id="date-${index + 1}"
                    data-period="${item.payment_date}"
                    data-setamount="${(parseFloat(item.successful_amount) - (parseFloat(item.successful_amount) * feeRate)).toFixed(2)}">
                    Enter
                </button>`;
            } else if (item.settlement_status === 'Y') {
                // Case: already settled
                actionCell = `<span style="font-weight:600; color:#666;">${(parseFloat(item.successful_amount) - (parseFloat(item.successful_amount) * feeRate)).toFixed(2)}</span>`;
            } else {
                // Case: already settled
                actionCell = `<span style="font-weight:600; color:#666;">N/A</span>`;
            }

            tr.innerHTML = `
            <td>${index + 1}</td>
            <td>${item.payment_date}</td>
            <td>${parseFloat(item.successful_amount).toFixed(2)}</td>
            <td>${(parseFloat(item.successful_amount) * feeRate).toFixed(2)}</td>
            <td>${(parseFloat(item.successful_amount) - (parseFloat(item.successful_amount) * feeRate)).toFixed(2)}</td>
            <td>${actionCell}</td>
            <td>
                <form method="POST" action="razorpay_details.php" id="form-${index+1}">
                    <input type="hidden" name="period" value="${item.payment_date}">
                    <button type="submit" class="view-btn btn-status">View</button>
                </form>
            </td>
        `;

            tbody.appendChild(tr);
        });
    }

    // Save Settlement
    $(document).on('click', '.amount-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const $row = $btn.closest('tr');
        const amount = $row.find('.amount-input').val();
        const data_period = $btn.data('period'); // fixed
        const setamount = $btn.data('setamount'); // fixed


        if (!amount || !data_period) {
            alert('Missing required information.');
            return;
        } else if (setamount != amount) {
            alert('Mismatch the settlement amount');
            return;
        }

        $btn.prop('disabled', true);

        fetch('api/razorpay_lists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `amount=${amount}&data_period=${data_period}&action=save_settlements`
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // Instead of reload, update row:
                    $row.find('.amount-input').remove();
                    $btn.replaceWith(`<span style="font-weight:600; color:#666;">Saved</span>`);
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    alert("Error: " + data.message);
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                $btn.prop('disabled', false);
            });
    });

    //On page load: load today's data
    document.addEventListener('DOMContentLoaded', () => {
        loadData();
    });

    //Apply Date Filters (calls API again)
    document.getElementById('applyFiltersBtn').addEventListener('click', () => {
        const startDateVal = document.getElementById('ci-date-start').value;
        const endDateVal = document.getElementById('ci-date-end').value;
        if (startDateVal && endDateVal) {
            loadData(startDateVal, endDateVal);
        } else {
            alert("Please select both Start and End dates.");
        }
    });

    //Clear Filters (reloads today's data)
    document.getElementById('clearFiltersBtn').addEventListener('click', () => {
        document.getElementById('ci-date-start').value = '';
        document.getElementById('ci-date-end').value = '';
        loadData(); // reload today's by default
    });

    //Download Excel
    $('#DownloadBtn').on('click', function() {
        const rows = [];
        const headers = [];
        $('.data-table thead th').each(function() {
            headers.push($(this).text().trim());
        });
        rows.push(headers);

        $('#razorpay-settlements tr:visible').each(function() {
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
        const worksheet = XLSX.utils.aoa_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Filtered Data");
        XLSX.writeFile(workbook, "Razorpay_History.xlsx");
    });
</script>