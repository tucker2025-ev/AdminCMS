<?php
// No changes to the PHP at the top of this file
session_start();
// ✅ Safe check before accessing the variable
if (!isset($_SESSION["user_mobile"]) || $_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit();
}

include 'include/dbconnect.php';
$_SESSION["period"] = htmlspecialchars($_POST['period']);

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
    .view-btn {
        padding: 4px 10px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        color: #fff;
    }

    .btn-captured {
        background-color: green;
    }

    .btn-failed {
        background-color: red;
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
                    <h2>Razorpay History Details</h2>
                </header>
                <div class="card">
                    <!-- Summary Boxes -->
                    <div class="summary-boxes">
                        <div class="summary-box">
                            <p class="label">Total Captured Amount</p>
                            <p class="value" id="total-captured">₹0.00</p>
                        </div>
                        <div class="summary-box">
                            <p class="label">Total Failed Amount</p>
                            <p class="value" id="total-failed">₹0.00</p>
                        </div>
                    </div>
                    <div class="filter-bar" id="customer-invoices-filters">
                        <div class="filter-actions"><button class="action-btn primary"
                                id="DownloadBtn">Download</button></div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SNO</th>
                                <th>Entry Time</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Amount ( ₹ )</th>
                                <th>Razorpay Fee ( ₹ )</th>
                                <th>Settlement Amount ( ₹ )</th>
                                <th>Status</th>
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

    var period = '<?= $_SESSION["period"] ?>';
    console.log(period)
    // Function to fetch data (optionally with date range)
    function loadData() {
        let url = 'api/razorpay_details.php';
        if (period) {
            url += `?period=${period}`;
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

        let totalCaptured = 0;
        let totalFailed = 0;

        data.forEach((item, index) => {
            const amount = parseFloat(item.amount || 0);

            if (item.status?.toLowerCase() === 'captured') {
                totalCaptured += amount;
            } else if (item.status?.toLowerCase() === 'failed') {
                totalFailed += amount;
            }

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${item.entry_time.toLocaleString() || ''}</td>
                <td>${item.email || ''}</td>
                <td>${item.contact || ''}</td>
                <td>${amount.toFixed(2)}</td>
                <td>${(parseFloat(item.amount) * 0.0236).toFixed(2)}</td>
<td>${(parseFloat(item.amount) - (parseFloat(item.amount) * 0.0236)).toFixed(2)}</td>
                <td>
                    <button class="view-btn ${
                        item.status?.toLowerCase() === 'captured' ? 'btn-captured' : 'btn-failed'
                    }">${item.status || ''}</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Update summary totals
        document.getElementById('total-captured').textContent = `₹${totalCaptured.toFixed(2)}`;
        document.getElementById('total-failed').textContent = `₹${totalFailed.toFixed(2)}`;
    }

    // On page load: load today's data
    document.addEventListener('DOMContentLoaded', () => {
        loadData();
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