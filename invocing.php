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
<style>
    .form-grid.two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        /* Two equal columns */
        gap: 15px;
        /* Space between columns */
        align-items: center;
        /* Align inputs vertically */
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        box-sizing: border-box;
    }

    #form-items-container {
        margin-top: 24px;
        border-top: 1px solid var(--border-color);
        padding-top: 24px;
    }

    #upload-form input,
    #upload-form select {
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        font-size: 14px;
        width: 100%;
    }
</style>

<body>
    <div class="container">
        <?php include "left.php"; ?>
        <main class="main-content">
            <div id="invoicing-page" class="page-content">
                <header class="main-header">
                    <h2>Create & Manage Invoices</h2>
                </header>
                <div class="content-row">
                    <div class="content-column-large">

                        <!-- <div class="card" id="invoicing-form-card">
                            <form id="invoice-form">
                                <div class="card-header">
                                    <h3>Create Manual Invoice</h3>
                                </div>
                                <div class="form-grid">
                                    <div class="form-item"><label for="cpo-select">Bill To (CPO)</label><select id="cpo-select" required>
                                        </select></div>
                                    <div class="form-item"><label>Invoice ID</label><input type="text" id="invoice-id" value="" disabled></div>
                                    <div class="form-item"><label>Fee Date</label><input type="date" id="invoice-date" required></div>
                                    <div class="form-item"><label>Due Date</label><input type="date" id="due-date" required></div>
                                </div>
                                <div id="line-items-container"></div><button type="button" class="btn btn-secondary" data-action="add-item-btn" style="padding: 8px 16px;">+ Add Line Item</button>
                                <div class="invoice-summary">
                                    <div class="summary-box">
                                        <div class="summary-row"><span>Subtotal</span><span id="summary-subtotal">₹ 0.00</span></div>
                                        <div class="summary-row"><span>GST (18%)</span><span id="summary-gst">₹ 0.00</span></div>
                                        <div class="summary-row total"><span>Grand Total</span><span id="summary-total">₹ 0.00</span></div>
                                    </div>
                                </div>
                                <div class="invoice-actions"><button type="button" class="btn btn-secondary" data-action="save-draft">Save as Draft</button><button type="button" class="btn btn-primary" data-action="handle-invoice-form" id="generate_fee">Generate Fee</button></div>
                            </form>
                        </div>

                        <div class="card" id="upload-form-card">
                            <form id="upload-form" enctype="multipart/form-data" method="post">
                                <div class="card-header">
                                    <h3>Create Upload Invoice</h3>
                                </div>
                                <div class="form-grid two-columns">
                                    <div class="form-group">
                                        <input type="file" name="invoice_pdf" id="invoice_pdf" accept="application/pdf">
                                    </div>
                                    <div class="form-group">
                                        <input type="text" id="voucher_no" name="voucher_no" placeholder="Enter Voucher No">
                                    </div>
                                </div>
                                <br>
                                <div class="form">
                                    <div class="form-group">
                                        <input type="text" id="grand_total" name="grand_total" placeholder="Enter Grand Total" readonly>
                                    </div>
                                </div>
                                <br>
                                <div class="form-grid" style="text-align: center;">
                                    <button type="button" class="btn btn-primary" id="uploadBtn">Upload & Extract</button>
                                </div>
                                <div id="form-items-container"></div>
                                <div class="invoice-actions">
                                    <button type="button" class="btn btn-secondary" data-action="save-draft">Save as Draft</button>
                                    <button type="button" class="btn btn-primary" data-action="handle-invoice-form" id="generate_fee">Generate Fee</button>
                                </div>
                            </form>
                        </div> -->
                        <div class="card" id="invoice-form-card">
                            <div class="card-header">
                                <h3>Create Invoice</h3>
                                <div class="mode-switch">
                                    <button type="button" class="btn btn-secondary" id="manual-mode-btn">Manual Entry</button>
                                    <button type="button" class="btn btn-secondary" id="upload-mode-btn">Upload PDF</button>
                                </div>
                            </div>

                            <form id="invoice-form" enctype="multipart/form-data" method="post">
                                <!-- Common Invoice Info -->
                                <div class="form-grid">
                                    <div class="form-item"><label for="cpo-select">Bill To (CPO)</label>
                                        <select id="cpo-select" required></select>
                                    </div>
                                    <div class="form-item"><label>Invoice ID</label>
                                        <input type="text" id="invoice-id" value="" disabled>
                                    </div>
                                    <div class="form-item"><label>Fee Date</label>
                                        <input type="date" id="invoice-date" required>
                                    </div>
                                    <div class="form-item"><label>Due Date</label>
                                        <input type="date" id="due-date" required>
                                    </div>
                                </div>

                                <!-- Manual Entry Mode -->
                                <div id="manual-entry-section" style="display:none;">
                                    <div id="line-items-container"></div>
                                    <button type="button" class="btn btn-secondary" data-action="add-item-btn" style="padding: 8px 16px;">+ Add Line Item</button>
                                </div>

                                <!-- Upload Mode -->
                                <div id="upload-entry-section" style="display:none;">
                                    <div class="form-grid two-columns">
                                        <div class="form-group">
                                            <input type="file" name="invoice_pdf" id="invoice_pdf" accept="application/pdf">
                                        </div>
                                        <div class="form-group">
                                            <input type="text" id="voucher_no" name="voucher_no" placeholder="Enter Voucher No">
                                        </div>
                                    </div>
                                    <!-- <br> -->
                                    <div class="form">
                                        <div class="form-group">
                                            <input type="text" id="grand_total" name="grand_total" placeholder="Enter Grand Total" readonly>
                                        </div>
                                    </div>
                                    <br>
                                    <div class="form" style="text-align: center;">
                                        <button type="button" class="btn btn-primary" id="uploadBtn">Upload & Extract</button>
                                    </div>
                                    <div id="form-items-container"></div>
                                </div>

                                <!-- Common Invoice Summary -->
                                <div class="invoice-summary">
                                    <div class="summary-box">
                                        <div class="summary-row"><span>Subtotal</span><span id="summary-subtotal">₹ 0.00</span></div>
                                        <div class="summary-row"><span>GST (18%)</span><span id="summary-gst">₹ 0.00</span></div>
                                        <div class="summary-row total"><span>Grand Total</span><span id="summary-total">₹ 0.00</span></div>
                                    </div>
                                </div>

                                <!-- Common Actions -->
                                <div class="invoice-actions">
                                    <!-- Add mode tracking as hidden -->
                                    <input type="hidden" id="invoice-mode" value="manual">
                                    <button type="button" class="btn btn-secondary" data-action="save-draft">Save as Draft</button>
                                    <button type="button" class="btn btn-primary" data-action="handle-invoice-form" id="generate_fee">Generate Fee</button>
                                </div>
                            </form>
                        </div>

                    </div>
                    <div class="content-column-small" id="invoicing-right-column"></div>
                </div>
            </div>
            <div id="modal-overlay" class="modal-overlay">
                <div id="modal-box" class="modal-box"></div>
            </div>
            <div id="toast" class="toast-notification"></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.10.111/pdf.min.js"></script>

    <script>
        document.getElementById('uploadBtn').addEventListener('click', () => {
            const fileInput = document.getElementById('invoice_pdf');
            const voucherInput = document.getElementById('voucher_no');
            const uploadBtn = document.getElementById('uploadBtn');

            if (!fileInput.files.length) return alert('Please select a PDF file.');
            if (!voucherInput.value.trim()) {
                voucherInput.style.border = "2px solid red";
                return alert('Please enter Voucher No.');
            }
            voucherInput.style.border = "";

            const formData = new FormData();
            formData.append("invoice_pdf", fileInput.files[0]);
            formData.append("voucher_no", voucherInput.value);

            fetch('api/extract.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) return alert(data.error);
                    const total = parseFloat(data.grand_total || 0).toFixed(2);
                    document.getElementById('grand_total').value = total;
                    $('#summary-subtotal, #summary-total').text(`₹ ${total}`);
                    uploadBtn.style.display = 'none';
                })
                .catch(console.error);
        });

        // Global appState to store CPO settlement data
        let appState = {
            settlements: []
        };
        let currentSimData = [];
        $.ajax({
            url: 'api/cpo_list.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status !== "success") {
                    console.error("API returned non-success status");
                    return;
                }

                // Assuming your <select> has id="cpo-select"
                const $select = $('#cpo-select');
                $select.empty(); // clear existing options
                $select.append(`<option value="">-- Select a CPO --</option>`);
                // Loop through the data and create <option> elements
                response.data.forEach(item => {
                    const option = $('<option></option>')
                        .val(item.cpo_id)
                        .text(item.cpo_name);
                    $select.append(option);
                });

                // If you want to store the settlements in appState as plain data (not DOM elements):
                appState.settlements = response.data;
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });


        $.ajax({
            url: 'api/monthly_recharge_bill.php',
            method: 'POST',
            dataType: 'json',
            data: JSON.stringify({
                action: "monthly_bill_list"
            }),
            success: function(response) {
                // console.log(response)
                renderSimBillingPreview(response.data);
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });
        const formatCurrency = (num) => `₹ ${num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

        function renderSimBillingPreview(rec) {
            currentSimData = rec;
            const withinAllowedDays = new Date().getDate() <= 5;

            $('#sim-billing-month').text(`For ${new Date().toLocaleString('default', { month: 'long', year: 'numeric' })}`);

            const $previewContainer = $('#sim-billing-preview-container');
            const $generateBtn = $('#generate-sim-button');

            if ((rec.length > 0)) {

                const itemsHtml = rec.map(cpo => `<div class="list-item"><span class="name">${cpo.device_name}</span><span>${cpo.recharge_date}</span><span class="amount">${formatCurrency(cpo.recharge_amount * 1.18)}</span></div>`).join('');
                $previewContainer.html(itemsHtml);
                $generateBtn.prop('disabled', false).html(`Generate Invoices for <span id="sim-billing-count">${rec.length}</span> CPOs`);

                if (!withinAllowedDays) {
                    $generateBtn.prop('disabled', true).text(new Date().getDate() > 3 ? 'Invoice Generation Closed' : 'All Invoices Generated');
                }
            } else {
                $previewContainer.html('<p style="text-align:center; color:var(--text-light); padding: 40px 0;">All SIM invoices for this month have been generated or are currently locked.</p>');
                $generateBtn.prop('disabled', true).text(new Date().getDate() > 3 ? 'Invoice Generation Closed' : 'All Invoices Generated');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const MOCK_SYSTEM_DATE = new Date('2025-08-31');
            const SIM_CHARGE_AMOUNT = 90.00;

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
                    case 'confirm-settlement-payout':
                        const settlement = appState.settlements.find(s => s.id === id);
                        let deductionAmount = parseFloat(target.dataset.deduction);
                        if (!settlement || isNaN(deductionAmount)) return;
                        if (deductionAmount > 0) {
                            const unpaidBills = appState.serviceFees.filter(f => f.cpoId === settlement.cpoId && getFeeStatus(f).text !== 'Paid' && f.status !== 'Draft').sort((a, b) => new Date(a.date) - new Date(b.date));
                            for (const bill of unpaidBills) {
                                if (deductionAmount <= 0) break;
                                const paidOnBill = getTotalPaidForFee(bill.id);
                                const remainingOnBill = bill.total - paidOnBill;
                                const paymentAmount = Math.min(deductionAmount, remainingOnBill);
                                appState.payments.push({
                                    id: `P${String(appState.payments.length + 1).padStart(3, '0')}`,
                                    feeId: bill.id,
                                    cpoId: settlement.cpoId,
                                    date: MOCK_SYSTEM_DATE.toISOString().split('T')[0],
                                    amount: paymentAmount,
                                    method: `Deduction from ${settlement.id}`
                                });
                                deductionAmount -= paymentAmount;
                            }
                        }
                        settlement.status = 'Paid';
                        settlement.deduction = parseFloat(target.dataset.deduction);
                        showToast(`Settlement ${settlement.id} processed successfully.`);
                        document.getElementById('modal-overlay').style.display = 'none';
                        renderDashboard();
                        if (document.getElementById('cpo-ledger-page').classList.contains('active'));
                        break;
                    case 'confirm-record-payment':
                        e.preventDefault();
                        const paymentForm = document.getElementById('payment-form');
                        const fee = appState.serviceFees.find(f => f.id === id);
                        const amount = parseFloat(paymentForm.querySelector('#payment-amount').value);
                        const date = paymentForm.querySelector('#payment-date').value;
                        if (amount > 0 && date && fee) {
                            appState.payments.push({
                                id: `P${String(appState.payments.length + 1).padStart(3, '0')}`,
                                feeId: id,
                                cpoId: fee.cpoId,
                                date: date,
                                amount: amount,
                                method: 'Direct Payment'
                            });
                            showToast(`Payment of ${formatCurrency(amount)} recorded for bill #${id}.`);
                            document.getElementById('modal-overlay').style.display = 'none';
                            if (document.getElementById('cpo-ledger-page').classList.contains('active') && document.getElementById('cpo-ledger-header').innerText.includes(findCpoName(fee.cpoId)));
                        } else {
                            showToast("Invalid payment amount or date.", false);
                        }
                        break;
                    case 'edit-draft': {
                        const draft = appState.serviceFees.find(b => b.id === id);
                        if (draft) showPage('invoicing-page', {
                            draft
                        });
                        break;
                    }
                    case 'add-item-btn':
                        createLineItem();
                        break;
                    case 'remove-line-item':
                        e.target.closest('.line-item').remove();
                        updateInvoiceTotals();
                        break;
                    case 'close-modal':
                        document.getElementById('modal-overlay').style.display = 'none';
                        break;
                }
            });

            // Add button click event to submit data to PHP
            function generateSimInvoices() {
                if (!currentSimData.length) {
                    alert('No data to generate invoices.');
                    return;
                }

                const postData = {
                    action: 'save_monthly_bill',
                    action_form: 'generate-sim-invoices',
                    cpo_items: currentSimData.map(cpo => ({
                        cpo_id: cpo.cpo_id,
                        price: cpo.recharge_amount
                    }))
                };

                $.ajax({
                    url: 'api/monthly_recharge_bill.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(postData),
                    success: function(response) {
                        if (response.success) {
                            $('#sim-billing-preview-container').html('<p>Invoices generated successfully!</p>');
                            $('#generate-sim-button').prop('disabled', true).text('Invoices Generated');
                        } else {
                            $('#sim-billing-preview-container').html('<p>Error: ' + (response.errors ? response.errors.join(', ') : response.error) + '</p>');
                        }
                    },
                    error: function() {
                        $('#sim-billing-preview-container').html('<p>Request failed. Please try again.</p>');
                    }
                });
            };

            function renderInvoicingPage(draftToLoad = null) {

                // Render the new tabbed component (right column)
                const rightColumnHtml = `<div class="card" style="padding: 0 24px 24px 24px;">
            <div class="tab-nav">
                <button class="tab-link active" data-tab="sim-billing-tab">Automated Billing</button>
                <button class="tab-link" data-tab="drafts-tab">Drafts (<span id="draft-count">0</span>)</button>
            </div>
            <div id="sim-billing-tab" class="tab-pane active">
                <p style="color:var(--text-light); margin: 16px 0 0 0;">Preview and generate monthly SIM charges. (<span id="sim-billing-month"></span>)</p>
                <div id="sim-billing-preview-container" class="scrollable-list-container"></div>
                <div class="invoice-actions" style="margin-top: 20px; justify-content:center;">
                    <button id="generate-sim-button" class="btn btn-primary" data-action="generate-sim-invoices">Generate Invoices for <span id="sim-billing-count">0</span> CPOs</button>
                </div>
            </div>
            <div id="drafts-tab" class="tab-pane">
                 <p style="color:var(--text-light); margin: 16px 0 0 0;">Select a draft to continue editing or to finalize.</p>
                <div id="draft-fees-list-container" class="scrollable-list-container"></div>
            </div>
        </div>`;
                document.getElementById('invoicing-right-column').innerHTML = rightColumnHtml;

                if (draftToLoad) {
                    cpoSelect.value = draftToLoad.cpoId;
                    document.getElementById('invoice-date').value = draftToLoad.date;
                    document.getElementById('invoice-id').value = draftToLoad.id;
                    const container = document.getElementById('line-items-container');
                    container.innerHTML = '';
                    draftToLoad.lineItems.forEach(item => createLineItem(item));
                    updateInvoiceTotals();
                    showToast(`Loaded Draft ${draftToLoad.id}`);
                } else {
                    document.getElementById('invoice-date').value = MOCK_SYSTEM_DATE.toISOString().split('T')[0];
                    const dueDate = new Date(MOCK_SYSTEM_DATE);
                    dueDate.setDate(dueDate.getDate() + 15);
                    document.getElementById('due-date').value = dueDate.toISOString().split('T')[0];
                    createLineItem();
                }

                // renderSimBillingPreview();
                renderDrafts();
            }

            function createLineItem(item = {
                desc: '',
                qty: 1,
                price: ''
            }) {
                const container = document.getElementById('line-items-container');
                if (!container) return;
                const div = document.createElement('div');
                div.className = 'line-item';
                div.innerHTML = `<div class="desc"><input type="text" placeholder="Service description" value="${item.desc}"></div><div class="qty"><input type="number" value="${item.qty}" min="1"></div><div class="price"><input type="number" placeholder="0.00" step="0.01" value="${item.price}"></div><div class="total">₹ 0.00</div><div class="remove-btn" data-action="remove-line-item">&times;</div>`;
                container.appendChild(div);
                updateInvoiceTotals();
            }

            function updateInvoiceTotals() {
                const container = document.getElementById('line-items-container');
                if (!container) return;
                let subtotal = 0;
                container.querySelectorAll('.line-item').forEach(row => {
                    const qty = parseFloat(row.querySelector('.qty input').value) || 0;
                    const price = parseFloat(row.querySelector('.price input').value) || 0;
                    const lineTotal = qty * price;
                    row.querySelector('.total').textContent = formatCurrency(lineTotal);
                    subtotal += lineTotal;
                });
                const gst = subtotal * 0.18;
                document.getElementById('summary-subtotal').textContent = formatCurrency(subtotal);
                document.getElementById('summary-gst').textContent = formatCurrency(gst);
                document.getElementById('summary-total').textContent = formatCurrency(subtotal + gst);
            }


            $(document).on('click', '.action-link[data-action="edit-draft"]', function() {
                const invoiceId = $(this).data('invoice_id');
                const invoiceItems = allDraftData.filter(item => item.invoice_id === invoiceId);

                if (invoiceItems.length > 0) {
                    const firstItem = invoiceItems[0];

                    $('#cpo-select').val(firstItem.cpo_id);
                    $('#invoice-id').val(firstItem.invoice_id);
                    $('#invoice-date').val(firstItem.fee_date.split(' ')[0]);
                    $('#due-date').val(firstItem.due_date.split(' ')[0]);

                    const lineItemsContainer = $('#line-items-container');
                    lineItemsContainer.empty();

                    invoiceItems.forEach(item => {
                        const lineItemHTML = `
        <div class="line-item" data-list_id="${item.list_id}">
            <div class="desc">
                <input type="text" placeholder="Service description" value="${item.description || ''}">
            </div>
            <div class="qty">
                <input type="number" value="${item.quantity || 1}" min="1">
            </div>
            <div class="price">
                <input type="number" placeholder="0.00" step="0.01" value="${item.rate || 0}">
            </div>
            <div class="total">₹ 0.00</div>
            <div class="remove-btn" data-action="remove-line-item">×</div>
        </div>
    `;
                        lineItemsContainer.append(lineItemHTML);
                    });


                    recalculateInvoiceTotals();
                }
            });

            // Recalculate totals on any input change in line items
            $('#line-items-container').on('input', 'input', function() {
                recalculateInvoiceTotals();
            });

            function recalculateInvoiceTotals() {
                let subtotal = 0;

                $('#line-items-container .line-item').each(function() {
                    const qty = parseFloat($(this).find('.qty input').val()) || 0;
                    const price = parseFloat($(this).find('.price input').val()) || 0;
                    const total = qty * price;
                    subtotal += total;
                    $(this).find('.total').text(`₹ ${total.toFixed(2)}`);
                });

                const gst = subtotal * 0.18; // 18% GST
                const grandTotal = subtotal + gst;

                $('#summary-subtotal').text(`₹ ${subtotal.toFixed(2)}`);
                $('#summary-gst').text(`₹ ${gst.toFixed(2)}`);
                $('#summary-total').text(`₹ ${grandTotal.toFixed(2)}`);
            }


            let allDraftData = []; // Global cache for drafts

            function renderDrafts() {
                $.ajax({
                    url: 'api/save_invoice.php',
                    method: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: "draft_invoice_list"
                    }),
                    success: function(response) {
                        if (response.success) {
                            allDraftData = response.data; // Store for later use

                            const grouped = {};
                            response.data.forEach(item => {
                                const invoiceId = item.invoice_id;
                                if (!grouped[invoiceId]) {
                                    grouped[invoiceId] = {
                                        invoice_id: invoiceId,
                                        cpo_name: item.cpo_name,
                                        cpo_id: item.cpo_id,
                                        items: []
                                    };
                                }
                                grouped[invoiceId].items.push(item);
                            });

                            const groupedDrafts = Object.values(grouped);
                            document.getElementById('draft-count').textContent = groupedDrafts.length;

                            const container = document.getElementById('draft-fees-list-container');
                            if (groupedDrafts.length > 0) {
                                container.innerHTML = groupedDrafts.map(d => {
                                    const grandTotal = d.items.reduce((sum, item) => sum + Number(item.grand_total), 0);
                                    return `
                            <div class="list-item">
                                <div>
                                    <div class="name">${d.cpo_name}</div>
                                    <div class="amount" style="font-size:12px; font-weight:500;">
                                        ₹${grandTotal}
                                    </div>
                                </div>
                                <a class="action-link" 
                                   data-action="edit-draft"
                                   data-invoice_id="${d.invoice_id}" 
                                   data-cpoid="${d.cpo_id}"
                                   data-list_ids="${d.list_id}"
                                >Edit</a>
                            </div>
                        `;
                                }).join('');
                            } else {
                                container.innerHTML = '<p style="text-align:center; color:var(--text-light); padding:40px 0;">No drafts found.</p>';
                            }
                        } else {
                            console.error("Error from server:", response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX error:", error);
                    }
                });
            }


            // Show page and render accordingly
            function showPage(pageId, options = {}) {
                document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
                document.getElementById(pageId)?.classList.add('active');

                document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
                const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
                if (navLink?.parentElement) {
                    navLink.parentElement.classList.add('active');
                }

                const pageRenderers = {
                    'invoicing-page': () => renderInvoicingPage(options.draft),
                };
                if (pageRenderers[pageId]) pageRenderers[pageId]();
            }
            showPage('invoicing-page');
        });

        $('[data-action="handle-invoice-form"]').on('click', function(e) {
            e.preventDefault();
            submitInvoice('save_invoice', 'save');
        });

        $('[data-action="save-draft"]').on('click', function(e) {
            e.preventDefault();
            submitInvoice('save_invoice', 'draft'); // You can change actionType if needed (e.g., 'save_draft')
        });

        $.ajax({
            url: 'api/save_invoice.php',
            method: 'POST',
            dataType: 'json',
            contentType: 'application/json',
            data: JSON.stringify({
                action: "get_invoiceid"
            }),
            success: function(response) {
                if (response.success) {
                    $('#invoice-id').val(response.invoice_id);
                } else {
                    console.error("Error from server:", response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });

        let currentMode = 'manual'; // Default mode


        //     // Show toast message function
        const showToast = (message, isSuccess = true) => {
            const t = document.getElementById('toast');
            t.textContent = message;
            t.style.backgroundColor = isSuccess ? 'var(--paid-bg)' : 'var(--pending-bg)';
            t.style.color = isSuccess ? 'var(--paid-text)' : 'var(--pending-text)';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        };

        function resetAll() {
            document.getElementById('line-items-container').innerHTML = '';
            document.getElementById('invoice_pdf').value = '';
            document.getElementById('voucher_no').value = '';
            document.getElementById('grand_total').value = '';
            document.getElementById('summary-subtotal').textContent = '₹ 0.00';
            document.getElementById('summary-gst').textContent = '₹ 0.00';
            document.getElementById('summary-total').textContent = '₹ 0.00';
        }

        // Switch mode buttons
        document.getElementById('manual-mode-btn').onclick = () => {
            resetAll();
            currentMode = 'manual';
            document.getElementById('manual-entry-section').style.display = 'block';
            document.getElementById('upload-entry-section').style.display = 'none';
            document.getElementById('invoice-mode').value = 'manual';
        };

        document.getElementById('upload-mode-btn').onclick = () => {
            resetAll();
            currentMode = 'upload';
            document.getElementById('manual-entry-section').style.display = 'none';
            document.getElementById('upload-entry-section').style.display = 'block';
            document.getElementById('invoice-mode').value = 'upload';
        };

        // Add new line item row
        function addLineItemRow() {
            const container = document.getElementById('line-items-container');
            const row = document.createElement('div');
            row.className = 'line-item';
            row.innerHTML = `
        <div class="desc"><input type="text" placeholder="Description"></div>
        <div class="qty"><input type="number" placeholder="Qty" min="1"></div>
        <div class="price"><input type="number" placeholder="Price" min="0"></div>
        <button type="button" class="remove-item">X</button>
    `;
            row.querySelector('.remove-item').onclick = () => row.remove();
            container.appendChild(row);
        }

        // Calculate summary for manual mode
        function updateSummary() {
            let subtotal = 0;
            document.querySelectorAll('#line-items-container .line-item').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty input').value) || 0;
                const price = parseFloat(row.querySelector('.price input').value) || 0;
                subtotal += qty * price;
            });

            const gst = subtotal * 0.18; // 18% GST example
            const total = subtotal + gst;

            document.getElementById('summary-subtotal').textContent = `₹ ${subtotal.toFixed(2)}`;
            document.getElementById('summary-gst').textContent = `₹ ${gst.toFixed(2)}`;
            document.getElementById('summary-total').textContent = `₹ ${total.toFixed(2)}`;
        }

        // Attach listeners for dynamic calculation
        document.addEventListener('input', (e) => {
            if (e.target.closest('#line-items-container')) {
                updateSummary();
            }
        });

        // Submit Invoice
        function submitInvoice(actionType, action_form) {
            const cpoSelect = document.getElementById('cpo-select');
            if (!cpoSelect.value.trim()) {
                showToast("Please select a CPO.", false);
                return;
            }

            const invoiceDate = document.getElementById('invoice-date').value;
            const dueDate = document.getElementById('due-date').value;
            if (!invoiceDate || !dueDate) {
                showToast("Please select both invoice and due dates.", false);
                return;
            }

            let lineItems = [];

            // Manual Mode
            if (currentMode === 'manual') {
                const itemsContainer = document.getElementById('line-items-container');
                itemsContainer.querySelectorAll('.line-item').forEach(row => {
                    const desc = row.querySelector('.desc input').value.trim();
                    const qty = parseFloat(row.querySelector('.qty input').value);
                    const price = parseFloat(row.querySelector('.price input').value);
                    if (desc && qty > 0 && !isNaN(price) && price >= 0) {
                        lineItems.push({
                            description: desc,
                            quantity: qty,
                            price: price
                        });
                    }
                });
                if (lineItems.length === 0) {
                    showToast("Please add at least one valid line item.", false);
                    return;
                }
            }

            // Build common data
            const baseData = {
                action_form: action_form,
                action: actionType,
                cpo_id: cpoSelect.value,
                invoice_id: document.getElementById('invoice-id').value,
                invoice_date: invoiceDate,
                due_date: dueDate,
                mode: currentMode,
                line_items: lineItems
            };

            // Manual Mode → Send as JSON directly
            if (currentMode === 'manual') {
                $.ajax({
                    url: 'api/save_invoice.php',
                    method: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify(baseData),
                    success: function(response) {
                        if (response.success) {
                            showToast(`Invoice ${action_form} successfully!`);
                            $('#generate_fee').prop('disabled', true);
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            showToast(response.error || "Failed to save invoice.", false);
                        }
                    },
                    error: function() {
                        showToast("Failed to save invoice.", false);
                    }
                });
            }

            // Upload Mode → Convert file to Base64 and send JSON
            else if (currentMode === 'upload') {
                const fileInput = document.getElementById('invoice_pdf');
                const voucherInput = document.getElementById('voucher_no');
                const grandTotal = document.getElementById('grand_total').value;

                if (fileInput.files.length === 0) {
                    showToast("Please upload a PDF file.", false);
                    return;
                }
                if (voucherInput.value.trim() === "") {
                    voucherInput.style.border = "2px solid red";
                    showToast("Please enter Voucher No.", false);
                    return;
                } else {
                    voucherInput.style.border = "";
                }
                if (!grandTotal || parseFloat(grandTotal) <= 0) {
                    showToast("Grand total is missing. Please extract from PDF.", false);
                    return;
                }

                const file = fileInput.files[0];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const base64File = e.target.result.split(',')[1];

                    const uploadData = {
                        ...baseData,
                        voucher_no: voucherInput.value,
                        grand_total: grandTotal,
                        file_name: file.name,
                        file_type: file.type,
                        file_data: base64File
                    };

                    $.ajax({
                        url: 'api/save_invoice.php',
                        method: 'POST',
                        dataType: 'json',
                        contentType: 'application/json',
                        data: JSON.stringify(uploadData),
                        success: function(response) {
                            if (response.success) {
                                showToast(`Invoice ${action_form} successfully!`);
                                $('#generate_fee').prop('disabled', true);
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                showToast(response.error || "Failed to save invoice.", false);
                            }
                        },
                        error: function() {
                            showToast("Failed to save invoice.", false);
                        }
                    });
                };
                reader.readAsDataURL(file);
            }
        }
    </script>

</body>

</html>