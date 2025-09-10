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
                                <th>CPO Name</th>
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
                document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
                document.getElementById(pageId)?.classList.add('active');
                document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
                const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
                navLink?.parentElement.classList.add('active');
                const pageRenderers = {
                    'cpo-accounts-page': renderCpoAccounts
                };
                if (pageRenderers[pageId]) pageRenderers[pageId]();
            }

            function renderCpoAccounts() {
                $('#cpo-ledger-page').css('display', 'none');
                $('#cpo-accounts-page').css('display', '');
                document.getElementById('cpo-ledger-page').classList.remove('active');
                document.getElementById('cpo-accounts-page').classList.add('active');
                $.ajax({
                    url: 'api/cpo_accounts.php',
                    method: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: "invoice_list"
                    }),
                    success: function(response) {
                        if (response.status === "success" && Array.isArray(response.data)) {
                            const invoiceData = response.data;
                            // Build the table rows HTML
                            const tableBodyHtml = invoiceData.map(item => {
                                const gstTag = item.gst_status === 'Y' ? `<span class="gst-tag">GST</span>` : '';
                                return `<tr>
                            <td>${item.cpo_name} ${gstTag}</td>
                            <td>₹ ${item.total_fees || 0}</td>
                            <td>₹ ${item.total_paid || 0}</td>
                            <td>₹ ${item.remaining || 0}</td>
                           <td><a class="action-link" onclick="renderCpoLedger('${item.cpo_id}', '${item.cpo_name}')" data-cpo-id="${item.cpo_id}">View Statement</a></td></tr>`;
                            }).join('');
                            // Insert rows into table body
                            document.getElementById('cpo-accounts-body').innerHTML = tableBodyHtml;

                        } else {
                            console.error("Invalid response from server");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                    }
                });
            }

            showPage('cpo-accounts-page');
        });


        function renderCpoLedger(cpoId, cpoName) {
            $('#cpo-accounts-page').css('display', 'none');
            $('#cpo-ledger-page').css('display', '');
            document.getElementById('cpo-accounts-page').classList.remove('active');
            document.getElementById('cpo-ledger-page').classList.add('active');
            document.getElementById('cpo-ledger-header').innerHTML = `<h2>Account Statement <span style="font-weight:400; color:var(--text-light)">for ${cpoName}</span></h2>`;
            $.ajax({
                url: 'api/single_cpo_ledger.php',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    cpo_id: cpoId
                }),
                success: function(response) {
                    if (response.status === "success" && Array.isArray(response.data)) {
                        const invoiceData = response.data;

                        const tableBodyHtml = invoiceData.map(item => {
                            if (item.invoice_id != null) {
                                creditRow = '';
                                const amount = item.grand_total || 0;
                                const paid = item.paid_amount || 0;
                                const remaining = amount - paid;
                                // Row 1: Debit (Receivable) — in red
                                const debitRow = `<tr>
                    <td>${item.entry_time}</td>
                    <td>${item.description}</td>
                    <td>${item.invoice_id}</td>
                    <td style="color: red;">₹ ${amount.toFixed(2)}</td>
                    <td>-</td>
                    <td style="color: red;">₹ ${amount.toFixed(2)}</td>
                </tr>`;

                                if ((item.grand_total != item.remaining) && (item.grand_total == item.paid_amount)) {
                                    // Row 2: Credit (Received) — in green
                                    creditRow = `<tr>
                    <td>${item.entry_time}</td>
                    <td>${item.description}</td>
                    <td>${item.invoice_id}</td>
                    <td>-</td>
                    <td style="color: green;">₹ ${paid.toFixed(2)}</td>
                    <td style="color: green;">₹ 0.00</td>
                </tr>`;
                                }


                                return debitRow + creditRow;
                            }

                            return ''; // Skip if no invoice_id
                        }).join('');

                        document.getElementById('cpo-ledger-body').innerHTML = tableBodyHtml;
                    } else {
                        console.error("Invalid response from server");
                    }
                }

            });

        }
    </script>


</body>

</html>