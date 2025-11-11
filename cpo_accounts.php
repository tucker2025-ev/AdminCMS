<?php
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
    <title>Tucker CMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/styles/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>

<body>
    <div class="container">
        <?php include "left.php"; ?>
        <main class="main-content">
            <div id="cpo-accounts-page" class="page-content">
                <header class="main-header">
                    <h2>CPO Accounts</h2>
                </header>
                <div class="card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SNO</th>
                                <th>CPO Name</th>
                                <th>Station Name</th>
                                <th>Total Fees</th>
                                <th>Total Paid</th>
                                <th>Outstanding Receivable</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cpo-accounts-body"></tbody>
                    </table>
                </div>
            </div>
            <div id="cpo-ledger-page" class="page-content">
                <header class="main-header">
                    <div id="cpo-ledger-header"></div>
                </header>
                <div class="card">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>SNO</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Transaction ID</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Receivable Balance</th>
                            </tr>
                        </thead>
                        <tbody id="cpo-ledger-body"></tbody>
                    </table>
                </div>
            </div>

            <div id="modal-overlay" class="modal-overlay">
                <div id="modal-box" class="modal-box"></div>
            </div>
            <div id="toast" class="toast-notification"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            function showPage(pageId, options = {}) {
                // Hide all pages
                document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
                // Show selected page
                const pageEl = document.getElementById(pageId);
                if (pageEl) pageEl.classList.add('active');

                // Update nav
                document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
                const navLink = document.querySelector(`[data-page="${pageId}"]`) ||
                    document.querySelector(`[data-page="${options.parentPage}"]`);
                if (navLink?.parentElement) navLink.parentElement.classList.add('active');

                // Render page if renderer exists
                const pageRenderers = {
                    'cpo-accounts-page': renderCpoAccounts
                };
                if (pageRenderers[pageId]) pageRenderers[pageId]();
            }

            // ==============================
            // Render CPO ACCOUNTS PAGE
            // ==============================
            function renderCpoAccounts() {
                $('#cpo-ledger-page').hide();
                $('#cpo-accounts-page').show();
                $('#cpo-ledger-page').removeClass('active');
                $('#cpo-accounts-page').addClass('active');

                $.ajax({
                    url: 'api/cpo_accounts.php',
                    method: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: "invoice_list"
                    }),
                    success: function(response) {
                        if (response.status !== "success" || !Array.isArray(response.data)) {
                            console.error("Invalid response from server");
                            return;
                        }

                        //Filter and sort alphabetically (A → Z)
                        const invoiceData = response.data
                            .filter(item => item.cpo_name && item.cpo_name.trim() !== "")
                            .sort((a, b) => a.cpo_name.trim().localeCompare(b.cpo_name.trim(), 'en', {
                                sensitivity: 'base'
                            }));

                        let tableBodyHtml = '';

                        if (invoiceData.length === 0) {
                            tableBodyHtml = `<tr><td colspan="7" style="text-align:center; color:#777;">No data available to set</td></tr>`;
                        } else {
                            tableBodyHtml = invoiceData.map((item, index) => {
                                const gstTag = item.gst_status === 'Y' ? `<span class="gst-tag">GST</span>` : '';
                                return `<tr>
                                <td>${index + 1}</td>
                                <td>${item.cpo_name.trim()} ${gstTag}</td>
                                <td>${item.station_name ?? 'N/A'}</td>
                                <td>₹ ${item.total_fees || 0}</td>
                                <td>₹ ${item.total_paid || 0}</td>
                                <td>₹ ${item.remaining || 0}</td>
                                <td>
                                    <a class="action-link" onclick="renderCpoLedger('${item.cpo_id}', '${item.cpo_name.trim()}')" data-cpo-id="${item.cpo_id}">View Statement</a>
                                </td>
                            </tr>`;
                            }).join('');
                        }

                        document.getElementById('cpo-accounts-body').innerHTML = tableBodyHtml;
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                    }
                });
            }

            // Initial load
            showPage('cpo-accounts-page');
        });

        // ==============================
        // Render INDIVIDUAL CPO LEDGER PAGE
        // ==============================
        function renderCpoLedger(cpoId, cpoName) {
            $('#cpo-accounts-page').hide();
            $('#cpo-ledger-page').show();
            $('#cpo-accounts-page').removeClass('active');
            $('#cpo-ledger-page').addClass('active');

            document.getElementById('cpo-ledger-header').innerHTML =
                `<h2>Account Statement <span style="font-weight:400; color:var(--text-light)">for ${cpoName}</span></h2>`;

            $.ajax({
                url: 'api/single_cpo_ledger.php',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    cpo_id: cpoId
                }),
                success: function(response) {
                    if (response.status !== "success" || !Array.isArray(response.data)) {
                        console.error("Invalid response from server");
                        return;
                    }

                    const invoiceData = response.data;
                    const validRows = invoiceData.filter(item => item.invoice_id);

                    let tableBodyHtml = '';

                    if (validRows.length === 0) {
                        tableBodyHtml = `<tr><td colspan="7" style="text-align:center; color:#777;">No data available to set</td></tr>`;
                    } else {
                        let sno = 1;
                        tableBodyHtml = validRows.map(item => {
                            const amount = parseFloat(item.grand_total) || 0;
                            const paid = parseFloat(item.paid_amount) || 0;
                            const remaining = amount - paid;

                            let debitRow = `<tr>
                            <td>${sno++}</td>
                            <td>${item.entry_time || 'N/A'}</td>
                            <td>${item.description || 'N/A'}</td>
                            <td>${item.invoice_id}</td>
                            <td style="color: red;">₹ ${amount.toFixed(2)}</td>
                            <td>-</td>
                            <td style="color: red;">₹ ${amount.toFixed(2)}</td>
                        </tr>`;

                            let creditRow = '';
                            if (paid > 0) {
                                creditRow = `<tr>
                                <td>${sno++}</td>
                                <td>${item.entry_time || 'N/A'}</td>
                                <td>${item.description || 'N/A'}</td>
                                <td>${item.invoice_id}</td>
                                <td>-</td>
                                <td style="color: green;">₹ ${paid.toFixed(2)}</td>
                                <td style="color: green;">₹ 0.00</td>
                            </tr>`;
                            }

                            return debitRow + creditRow;
                        }).join('');
                    }

                    document.getElementById('cpo-ledger-body').innerHTML = tableBodyHtml;
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                }
            });
        }
    </script>

</body>

</html>