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
            <div id="bills-page" class="page-content">
                <header class="main-header">
                    <h2>CPO Bills</h2>
                </header>
                <div class="card">
                    <div class="filter-bar">
                        <div class="filter-item" style="flex-grow:1;"><input type="text" id="bill-search" placeholder="Search by CPO name..."></div>
                        <div class="filter-item">
                            <select id="bill-status-filter">
                                <option value="All">All Statuses</option>
                                <option value="Unpaid">Unpaid</option>
                                <option value="Partially Paid">Partially Paid</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Bill ID</th>
                                <th>CPO Name</th>
                                <th>Date</th>
                                <th style="text-align:right;">Amount</th>
                                <th style="text-align:right;">Paid</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="bills-table-body"></tbody>
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

            const formatCurrency = (num) => `₹ ${num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const findCpoName = (cpoId) => findCpo(cpoId)?.name || 'Unknown';
            const showToast = (message, isSuccess = true) => {
                const t = document.getElementById('toast');
                t.textContent = message;
                t.style.backgroundColor = isSuccess ? 'var(--paid-bg)' : 'var(--pending-bg)';
                t.style.color = isSuccess ? 'var(--paid-text)' : 'var(--pending-text)';
                t.classList.add('show');
                setTimeout(() => t.classList.remove('show'), 3000);
            };


            const calculateNetPayable = (settlement) => {
                const cpo = findCpo(settlement.cpoId);
                let netPayable = settlement.grossRevenue - settlement.serviceFee;
                if (cpo && cpo.isGstRegistered) {
                    netPayable += settlement.grossRevenue * 0.18;
                }
                return netPayable;
            };

            function showPage(pageId, options = {}) {
                document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
                document.getElementById(pageId)?.classList.add('active');
                document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
                const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
                navLink?.parentElement.classList.add('active');
                const pageRenderers = {
                    'bills-page': () => renderBillsPage(),
                };
                if (pageRenderers[pageId]) pageRenderers[pageId]();
            }

            function renderPaymentModal(bill) {
                document.getElementById('modal-overlay').style.display = 'flex';
                const remaining = bill.grand_total - bill.paid_amount;
                document.getElementById('modal-box').innerHTML = `
        <form id="payment-form">
            <h3>Record Payment for Bill #${bill.invoice_id}</h3>
            <p>
                Total: ₹ ${bill.grand_total} |
                Paid: ₹ ${bill.paid_amount} |
                Remaining: ₹ <span class="outstanding-negative">${remaining}</span>
            </p>
            <div class="form-item">
                <label for="payment-amount">Payment Amount</label>
                <input type="number" id="payment-amount" step="0.01" max="${remaining.toFixed(2)}" value="${remaining.toFixed(2)}" required >
            </div>
            <div class="form-item">
                <label for="payment-date">Payment Date</label>
                <input type="date" id="payment-date" value="${new Date().toISOString().split('T')[0]}" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                <button type="submit" class="btn btn-primary" data-action="confirm-record-payment" id="confirm-payment-btn" data-id="${bill.list_id}">Save Payment</button>
            </div>
        </form>`;
            }

            document.querySelector('body').addEventListener('click', e => {
                const target = e.target.closest('[data-page], [data-action], [data-tab]');
                if (!target) return;
                const {
                    page,
                    action,
                    id,
                    tab
                } = target.dataset;
                if (page) {
                    showPage(page);
                    return;
                }
                if (tab) {
                    document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                    target.classList.add('active');
                    document.getElementById(tab).classList.add('active');
                    return;
                }

                switch (action) {
                    case 'generate-sim-invoices':
                        generateSimInvoices();
                        break;
                    case 'initiate-settlement-payout':
                        renderSettlementPayoutModal(id);
                        break;
                    case 'show-record-payment-modal':
                        renderPaymentModal(id);
                        break;
                    case 'handle-invoice-form':
                        handleInvoicingForm(e);
                        break;
                    case 'save-draft':
                        handleInvoicingForm(e, 'Draft');
                        break;
                    case 'filter-settlements':
                        showPage('settlements-page', {
                            filters: {
                                status: target.dataset.status
                            }
                        });
                        break;
                    case 'view-ledger':
                        showPage('cpo-ledger-page', {
                            cpoId: target.dataset.cpoId,
                            parentPage: 'cpo-accounts-page'
                        });
                        break;
                    case 'close-modal':
                        document.getElementById('modal-overlay').style.display = 'none';
                        break;
                }
            });

            document.querySelector('.main-content').addEventListener('input', e => {
                if (e.target.matches('#settlement-search, #settlement-status-filter')) {
                    renderSettlementsTable({
                        search: document.getElementById('settlement-search').value,
                        status: document.getElementById('settlement-status-filter').value
                    });
                }
            });


            $(document).on('click', '.action-link[data-action="show-record-payment-modal"]', function() {
                const bill = JSON.parse(this.dataset.bill);
                renderPaymentModal(bill);
            });

            showPage('bills-page');
        });

        function formatCurrency(amount) {
            return '₹' + parseFloat(amount).toFixed(2);
        }

        function findCpoName(cpoId, data) {
            const match = data.find(d => d.cpo_id === cpoId);
            return match ? match.cpo_name : 'Unknown';
        }

        function getFeeStatus(bill) {
            // const totalPaid = getTotalPaidForFee(bill.id);
            const totalPaid = bill.paid_amount;
            const grand_total = bill.grand_total;
            const remaining = bill.remaining;

            if (parseFloat(totalPaid) >= parseFloat(grand_total)) {
                return {
                    text: 'Paid',
                    class: 'paid'
                };
            } else if (totalPaid == 0) {
                return {
                    text: 'Unpaid',
                    class: 'unpaid'
                };
            } else if (grand_total > totalPaid) {
                return {
                    text: 'Partially Paid',
                    class: 'partially-paid'
                }
            } else {
                return {
                    text: 'Unpaid',
                    class: 'unpaid'
                };
            }
        }

        $(document).ready(function() {
            function filterTable() {
                let search = $('#bill-search').val().toLowerCase();
                let status = $('#bill-status-filter').val();

                $('#bills-table-body tr').each(function() {
                    let cpoName = $(this).find('td:eq(1)').text().toLowerCase();
                    let billStatus = $(this).find('td:eq(5)').text().trim();

                    let matchesSearch = cpoName.includes(search);
                    let matchesStatus = (status === "All") || (billStatus === status);

                    if (matchesSearch && matchesStatus) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }

            // Trigger filter on input and select change
            $('#bill-search').on('input', filterTable);
            $('#bill-status-filter').on('change', filterTable);
        });

        function renderBillsPage() {
            // Fetch data and render
            $.ajax({
                url: 'api/save_invoice.php',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    action: "invoice_list"
                }),
                success: function(response) {
                    if (response.success && Array.isArray(response.data)) {
                        const invoiceData = response.data;
                        const tableBodyHtml = invoiceData.map(b => {
                            const statusInfo = getFeeStatus(b);
                            const actionHtml = statusInfo.text !== 'Paid' ?
                                `<a class="action-link" data-action="show-record-payment-modal" data-id="${b.list_id}" data-bill='${JSON.stringify(b)}'>Record Payment</a>` : '-';
                            return `<tr>
                    <td>${b.invoice_id}</td>
                    <td>${b.cpo_name}</td>  
                    <td>${new Date(b.fee_date).toLocaleDateString('en-CA')}</td>
                    <td style="text-align:right;">${formatCurrency(b.grand_total)}</td>
                    <td style="text-align:right;">${formatCurrency(b.paid_amount)}</td>
                    <td><span class="status ${statusInfo.class}">${statusInfo.text}</span></td>
                    <td>${actionHtml}</td>
                </tr>`;
                        }).join('');

                        document.getElementById('bills-table-body').innerHTML = tableBodyHtml;

                    } else {
                        console.error("Invalid response from server");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", error);
                }
            });
        }
        const showToast = (message, isSuccess = true) => {
            const t = document.getElementById('toast');
            t.textContent = message;
            t.style.backgroundColor = isSuccess ? 'var(--paid-bg)' : 'var(--pending-bg)';
            t.style.color = isSuccess ? 'var(--paid-text)' : 'var(--pending-text)';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        };

        // Payment For Invoice 
        $(document).on('click', '#confirm-payment-btn', function(e) {
            e.preventDefault();
            const listId = $(this).data('id');
            const invoiceIdText = $('h3').text();
            const invoiceIdMatch = invoiceIdText.match(/#(\S+)/);
            const invoiceId = invoiceIdMatch ? invoiceIdMatch[1] : null;
            const paymentAmount = parseFloat($('#payment-amount').val());
            console.log(paymentAmount)
            const paymentDate = $('#payment-date').val();

            // Validation
            if (!invoiceId) {
                showToast("Invoice ID not found.", false);
                return;
            }

            if (isNaN(paymentAmount) || paymentAmount <= 0) {
                showToast("Please enter a valid payment amount.", false);
                return;
            }
            if (!paymentDate) {
                showToast("Please select a payment date.", false);
                return;
            }

            // Prepare data
            const paymentData = {
                list_id: listId,
                invoice_id: invoiceId,
                amount: paymentAmount,
                date: paymentDate,
                action: 'record_payment'
            };
            // Send AJAX request
            $.ajax({
                url: 'api/record_payment.php',
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(paymentData),
                success: function(response) {
                    console.log("Payment recorded successfully:", response);
                    showToast("Payment recorded successfully.");
                    $('#modal-overlay').hide();
                    window.location.reload();
                },
                error: function(xhr, status, error) {
                    console.error("Payment error:", error);
                    showToast("Failed to record payment.", false);
                }
            });
        });
    </script>


</body>

</html>