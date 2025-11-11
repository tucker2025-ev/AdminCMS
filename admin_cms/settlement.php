<?php
session_start();

// ✅ Safe check before accessing the variable
if (!isset($_SESSION["user_mobile"]) || $_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit();
}

// Clear demo session variables if logged in
$_SESSION["demo_station_id"] = '';
$_SESSION["demo_station_mobile"] = '';
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
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<style>
    .action-btn {
        margin-top: 1px;
        border-radius: 4px;
        padding: 6px 12px;
        background-color: #e53935;
        color: white;
        border: 1px solid #f1ededff;
        cursor: pointer;
    }

    .modal-box {
        max-height: 85vh !important;
        /* limit modal height */
        overflow-y: auto !important;
        /* enables vertical scroll inside modal */
    }

    .invoice-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    .invoice-table th,
    .invoice-table td {
        border: 1px solid #ccc;
        padding: 6px 10px;
        text-align: left;
    }

    .invoice-table th {
        background: #f5f5f5;
    }

    .clickable-row {
        cursor: pointer;
    }

    .amount-input {
        width: 100px;
        padding: 8px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .btn-status {
        /*padding: 4px 10px;*/
        margin-top: 20px;
        margin-right: 10px;
        border: none;
        border-radius: 4px;
        font-weight: bold;
        color: #fff;
        background-color: green;
    }

    .emi-checkbox-group {
        margin: 10px 0;
    }

    .emi-label {
        display: block;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .emi-options {
        display: flex;
        gap: 20px;
        /* space between checkboxes */
        align-items: center;
        flex-wrap: wrap;
    }

    .emi-options label {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .emi-outstanding {
        margin: 10px 0;
        display: flex;
        justify-content: space-between;
    }
</style>

<body>
    <div class="container">
        <?php include "left.php"; ?>
        <main class="main-content">
            <div id="settlements-page" class="page-content">
                <header class="main-header">
                    <h2>CPO Settlements</h2>
                </header>
                <div class="card">
                    <div class="filter-bar">
                        <div class="filter-item" style="flex-grow:1;"><input type="text" id="settlement-search" placeholder="Search by CPO name..."></div>
                        <div class="filter-item">
                            <select id="settlement-status-filter">
                                <option value="Pending">Pending</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                        <div class="filter-item">
                            <select id="settlement-period-filter">
                                <option value="All">All Periods</option>
                                <option value="Period I">Period I (1–15)</option>
                                <option value="Period II">Period II (16–End)</option>
                            </select>
                        </div>

                        <div class="filter-item">
                            <form method="post" action="download_pdf.php" id="pdfForm">
                                <input type="hidden" name="month_name" value="<?php echo date('F'); ?>">
                                <input type="hidden" name="year" value="<?php echo date('Y'); ?>">
                                <input type="hidden" name="period" id="selectedPeriod" value="">

                                <button type="submit" class="action-btn primary">Download PDF</button>
                            </form>
                        </div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Download</th>
                                <th>S.NO</th>
                                <th>CPO ID</th>
                                <th>CPO Name</th>
                                <th>Period</th>
                                <th style="text-align:right;">Gross Revenue</th>
                                <th style="text-align:right;">Service Fee</th>
                                <th style="text-align:right;">GST on Revenue</th>
                                <th style="text-align:right;">Net Payable</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="settlements-table-body"></tbody>
                    </table>
                </div>
            </div>


            <div id="modal-overlay" class="modal-overlay">
                <div id="modal-box" class="modal-box"></div>
            </div>

            <div id="toast" class="toast-notification"></div>
    </div>

    <script>
        document.getElementById('settlement-period-filter').addEventListener('change', function() {
            document.getElementById('selectedPeriod').value = this.value;
        });
        // Global appState to store CPO settlement data
        let appState = {
            settlements: []
        };

        let send_invoice_id = [];
        let send_list_id = [];
        let send_setamount = [];


        // Helper: Format currency in INR
        function formatCurrency(amount) {
            return '₹' + (Number(amount) || 0).toFixed(2);
        }

        // Helper: Get full CPO meta (first settlement record for that cpoId)
        function findCpo(cpoId) {
            if (!cpoId) return null;
            return appState.settlements.find(s => String(s.cpoId) === String(cpoId)) || null;
        }

        // Calculate net payable amount considering GST and fees
        function calculateNetPayable(settlement) {
            if (!settlement) return 0;
            const unitCost = Number(settlement.unit_cost) || 0;
            const serviceFee = Number(settlement.serviceFee) || 0;
            const cpo = findCpo(settlement.cpoId);
            const isGst = cpo && cpo.isGstRegistered;
            const gst = isGst ? unitCost * 0.18 : 0;
            // net payable = unitCost - serviceFee + gst
            return unitCost - serviceFee + gst;
        }

        // Show page and render accordingly
        function showPage(pageId, options = {}) {
            document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
            document.getElementById(pageId)?.classList.add('active');

            document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
            const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
            if (navLink?.parentElement) navLink.parentElement.classList.add('active');

            const pageRenderers = {
                'settlements-page': () => renderSettlementsTable(),
            };
            if (pageRenderers[pageId]) pageRenderers[pageId]();
        }

        function formatDate(date) {
            let y = date.getFullYear();
            let m = String(date.getMonth() + 1).padStart(2, "0");
            let d = String(date.getDate()).padStart(2, "0");
            return `${y}-${m}-${d}`;
        }

        // Load settlements via AJAX
        $.ajax({
            url: "api/testing.php",
            // url: "api/cpo_list_months.php",
            method: "GET",
            dataType: "json",
            data: {
                start_date: formatDate(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1)), // 1st of last month
                end_date: formatDate(new Date()) // today
            },
            success: function(response) {
                // console.log("API Response:", response);
                if (response.status !== true) return;

                const rows = [];
                const today = new Date();

                response.data.forEach(item => {
                    const cpoName = item.cpo_name;
                    const cpoId = item.cpo_id;
                    const station_id = item.station_id;
                    const station_mobile = item.station_mobile;
                    const station_name = item.station_name;

                    const gst_status = item.gst_status;
                    const settlement_status = item.settlement_status;

                    // period label (1-15 / 16-end)
                    const periodLabel = item.half_month_bucket;
                    const periodMonthName = item.month_period;
                    const year = today.getFullYear();

                    // standard settlement id
                    const settlementId = `${cpoId}-${periodMonthName}-${periodLabel}`;

                    if (cpoName !== '-') {
                        rows.push({
                            id: settlementId,
                            cpoId: cpoId,
                            station_id: station_id,
                            station_mobile: station_mobile,
                            station_name: station_name,
                            cpoName: cpoName,
                            isGstRegistered: gst_status,
                            gst_status: gst_status,
                            settlement_status: settlement_status,
                            total_units: Number(item.total_units) || 0,
                            final_cost: Number(item.total_final_cost) || 0,
                            unit_cost: Number(item.total_unit_cost) || 0,
                            grossRevenue: Number(item.total_unit_cost) || 0,
                            gst: Number(item.total_gst_amount) || 0,
                            period: `${periodLabel} ${periodMonthName} ${year}`,
                            serviceFee: Number(item.total_service_fee) || 0,
                            finalCost: (Number(item.total_unit_cost) || 0) + (Number(item.total_gst_amount) || 0),
                            status: (settlement_status === "Y" || settlement_status === "Paid") ? "Paid" : "Pending",
                            pending: Number(item.pending) || 0,
                            invoice_list: item.invoice_list || [],
                            month_period: item.month_period
                        });
                    }
                });

                appState.settlements = rows.sort((a, b) =>
                    a.cpoName.localeCompare(b.cpoName, 'en', {
                        sensitivity: 'base'
                    })
                );

                renderSettlementsTable(); // Now rows are in appState
            },

            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });

        // Render settlements table with optional filters
        function renderSettlementsTable(filters = {}) {
            const {
                status = 'Pending',
                    search = '',
                    period = 'All'
            } = filters;

            const today = new Date();
            const currentDay = today.getDate();
            const currentMonth = today.getMonth(); // 0-based
            const currentYear = today.getFullYear();

            // const filtered = appState.settlements.filter(s =>
            //     (status === 'All' || s.status === status) &&
            //     s.cpoName.toLowerCase().includes(search.toLowerCase())
            // );

            const filtered = appState.settlements.filter(s => {
                // --- Status filter ---
                const statusMatch = status === 'All' || s.status === status;

                // --- Search filter ---
                const searchMatch = s.cpoName.toLowerCase().includes(search.toLowerCase());

                // --- Period filter ---
                const halfMonth = s.period.split(' ')[0] || '';
                let periodValue = '';
                if (halfMonth.startsWith("1–") || halfMonth.startsWith("1-")) {
                    periodValue = 'Period I';
                } else if (halfMonth.startsWith("16") || halfMonth.startsWith("16–") || halfMonth.startsWith("16-")) {
                    periodValue = 'Period II';
                }
                const periodMatch = period === 'All' || periodValue === period;

                return statusMatch && searchMatch && periodMatch;
            });

            // Sort by period: Period I first, Period II second
            filtered.sort((a, b) => {
                const getPeriodValue = (s) => {
                    const halfMonth = s.period.split(' ')[0];
                    return halfMonth.startsWith("1–15") || halfMonth.startsWith("1-15") ? 1 : 2;
                };
                return getPeriodValue(a) - getPeriodValue(b);
            });

            let i = 1;
            const tableBodyHtml = filtered.map((s, index) => {
                const cpoMeta = findCpo(s.cpoId) || {};
                const isGst = s.isGstRegistered;
                const gstOnRevenue = isGst ? Number(s.grossRevenue) * 0.18 : 0;
                const netPayable = calculateNetPayable(s);
                const cpoNameHtml = `${s.cpoName} ${s.gst_status === 'Y' ? '<span class="gst-tag">GST</span>' : ''}`;

                // Parse period
                const periodParts = s.period.split(' '); // ["1–15", "September", "2025"]
                const halfMonth = periodParts[0] || '';
                const monthName = periodParts[1] || '';
                const year = periodParts[2] || new Date().getFullYear();

                // Determine last day of the period
                let lastDayOfPeriod = 0;
                let period_value = '';
                if (halfMonth.startsWith("1–") || halfMonth.startsWith("1-")) {
                    lastDayOfPeriod = 15;
                    period_value = `Period I (${s.month_period})`;
                } else if (halfMonth.startsWith("16") || halfMonth.startsWith("16–") || halfMonth.startsWith("16-")) {
                    const monthIndex = new Date(`${monthName} 1, ${year}`).getMonth();
                    lastDayOfPeriod = new Date(year, monthIndex + 1, 0).getDate();
                    period_value = `Period II (${s.month_period})`;
                }

                const today = new Date();
                const periodMonth = new Date(`${monthName} 1, ${year}`).getMonth();

                // Enable action if today is past last day of period
                const isActionEnabled = today.getMonth() === periodMonth && today.getDate() > lastDayOfPeriod;

                const isPaid = s.status.toLowerCase() === 'paid';
                const clientRowId = `table_id${index + 1}`;
                const currentMonth = new Date().toLocaleString('default', {
                    month: 'long'
                });

                let actionHtml = '';
                if (s.settlement_status === 'N' && s.month_period === currentMonth) {
                    actionHtml = !isActionEnabled ?
                        `<a class="action-link disabled" style="pointer-events:none; opacity:0.4;" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>` :
                        `<a class="action-link" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>`;
                } else {
                    actionHtml = s.settlement_status !== 'N' ? 'Paid' : `<a class="action-link" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>`;
                }

                const halfMonthValue = halfMonth.startsWith("16") ?
                    `16-${lastDayOfPeriod}` :
                    "1-15";

                return `
<tr data-rowid="${clientRowId}" data-cpoid="${s.cpoId}" data-settlementid="${s.id}" class="clickable-row">
<td>
        <form method="POST" action="demo.php" id="download-form-${s.id}" target="_blank" class="pdf-download-form">
            <input type="hidden" name="station_id" value="${s.station_id}">
            <input type="hidden" name="cpo_name" value="${s.cpoName}">
            <input type="hidden" name="cpo_id" value="${s.cpoId}">
            <input type="hidden" name="station_mobile" value="${s.station_mobile}">
            <input type="hidden" name="month_name" value="${monthName}">
            <input type="hidden" name="half_month" value="${halfMonthValue}">
            <input type="hidden" name="year" value="${year}">
            <input type="hidden" name="status" value="${s.status}">
            <input type="hidden" name="grossRevenue" value="${s.grossRevenue}">
            <input type="hidden" name="serviceFee" value="${s.serviceFee}">
            <input type="hidden" name="gstAmount" value="${formatCurrency(s.gst_status === 'Y' ? s.gst : 0)}">
            <input type="hidden" name="net_amount" value="${formatCurrency(s.gst_status === 'Y' ? (s.grossRevenue - s.serviceFee + s.gst) : (s.grossRevenue - s.serviceFee))}">
        </form>
        <i class="fas fa-download" style="cursor:pointer; color:#007bff;" title="Download PDF"
           onclick="document.getElementById('download-form-${s.id}').submit()"></i>
    </td>
    <td>${index + 1}</td>
    <td>${s.cpoId}</td>
    <td>${cpoNameHtml}</td>
    <td>${period_value}</td>
    <td style="text-align:right;">${formatCurrency(s.grossRevenue)}</td>
    <td style="text-align:right;" class="outstanding-negative">- ${formatCurrency(s.serviceFee)}</td>
    <td style="text-align:right; color:var(--positive-balance);">${formatCurrency(s.gst_status === 'Y' ? s.gst : 0)}</td>
   <td style="text-align:right; font-weight:600;">
  ${formatCurrency(
    (s.grossRevenue > 0)
      ? (s.gst_status === 'Y'
          ? (s.grossRevenue - s.serviceFee + s.gst - 5.9)
          : (s.grossRevenue - s.serviceFee - 5.9))
      : 0
  )}
</td>

    <td><span class="status ${s.status.toLowerCase()}">${s.status}</span></td>
    <td>
        <form method="POST" action="settlement_details.php" id="form-${s.id}" class="settlement-details-form">
            <input type="hidden" name="station_id" value="${s.station_id}">
            <input type="hidden" name="station_mobile" value="${s.station_mobile}">
            <input type="hidden" name="month_name" value="${monthName}">
            <input type="hidden" name="half_month" value="${halfMonthValue}">
            <input type="hidden" name="year" value="${year}">
            <input type="hidden" name="status" value="${s.status}">
            <input type="hidden" name="total_costs" value="${s.final_cost}">
        </form>
        ${actionHtml}
    </td>
</tr>`;
            }).join('');


            document.getElementById('settlements-table-body').innerHTML = tableBodyHtml;
        }

        // Delegate row clicks after rendering
        // document.addEventListener("click", function(e) {
        //     const row = e.target.closest(".clickable-row");
        //     if (!row) return;

        //     // Prevent form submit if action link clicked
        //     if (e.target.closest(".action-link")) return;

        //     const form = row.querySelector("form");
        //     if (form) form.submit();
        // });
        document.addEventListener("click", function(e) {
            const row = e.target.closest(".clickable-row");
            if (!row) return;

            // Prevent row click if clicking action links or PDF icon
            if (e.target.closest(".action-link") || e.target.closest(".pdf-download-form") || e.target.closest("i.fa-download")) return;

            // Submit the settlement details form (not PDF form)
            const form = row.querySelector("form.settlement-details-form");
            if (form) form.submit();
        });




        // Filter inputs event handler
        document.querySelector('.main-content').addEventListener('input', e => {
            if (e.target.matches('#settlement-search, #settlement-status-filter, #settlement-period-filter')) {
                renderSettlementsTable({
                    search: document.getElementById('settlement-search').value,
                    status: document.getElementById('settlement-status-filter').value,
                    period: document.getElementById('settlement-period-filter').value
                });
            }
        });

        // Event delegation for buttons/links with data-action attribute
        document.body.addEventListener('click', function(e) {
            const action = e.target.getAttribute('data-action');
            const settlementIdAttr = e.target.getAttribute('data-settlementid'); // string ID
            const cpoIdAttr = e.target.getAttribute('data-cpoid');
            const rowidAttr = e.target.getAttribute('data-rowid');

            switch (action) {
                case 'initiate-settlement-payout':
                    // settlementIdAttr is string like "cpo_18-previous_month-16-end"
                    if (!cpoIdAttr) {
                        console.warn('No CPO id passed for payout initiation');
                        return;
                    }
                    // Pass cpoId, rowid, and optionally settlementId so modal can pick that settlement
                    renderSettlementPayoutModalByCpoId(cpoIdAttr, rowidAttr, settlementIdAttr);
                    break;

                case 'close-modal':
                    document.querySelector('.modal-overlay').style.display = 'none';
                    break;

                case 'confirm-settlement-payout':
                    // When confirming, read the data attributes from the confirm button
                    const confirmBtn = e.target;
                    const confirmSettlementId = confirmBtn.getAttribute('data-settlementid');
                    const confirmDeductionStr = confirmBtn.getAttribute('data-deduction') || '0';
                    const confirmCpoIds = confirmBtn.getAttribute('data-cpoIds');
                    const confirmRowId = confirmBtn.getAttribute('data-rowid');


                    if (!confirmSettlementId || !confirmCpoIds) {
                        console.warn('Missing data on confirm button');
                        return;
                    }

                    const deduction = Number(confirmDeductionStr) || 0;
                    handleSettlementPayout(confirmSettlementId, deduction, confirmRowId, confirmCpoIds);
                    break;
            }
        });

        // Dummy function to get outstanding receivable for a CPO settlement - replace with real logic or API
        function getCpoOutstandingReceivable(cpoId) {
            // Here you could implement AJAX or other logic to fetch actual outstanding receivable
            return 1000; // dummy value
        }

        // Handle settlement payout - uses string settlementId
        function handleSettlementPayout(settlementId, deductionAmount, rowid, cpoIds) {
            // console.log("Payout confirmed for ID:", settlementId, deductionAmount, rowid, cpoIds);

            let updated = false;
            appState.settlements.forEach(settlement => {
                if (String(settlement.id) === String(settlementId) && settlement.status === 'Pending') {
                    settlement.status = 'Paid';
                    updated = true;

                    const $row = $('tr[data-rowid="' + rowid + '"]');
                    if ($row.length) {
                        $row.find('span.status').removeClass('pending').addClass('paid').text('Paid');
                        $row.find('td:last-child').html('Paid');
                    }
                    // send update to backend
                    $.ajax({
                        url: 'api/update_settle.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            settlement_id: settlementId,
                            cpo_id: cpoIds,
                            status: 'Paid',
                            deduction: deductionAmount,
                            set_amount: send_setamount.join(','), // optional: comma-separated
                            period: settlement.period,
                            invoice_id: send_invoice_id.join(','), // comma-separated if backend expects string
                            list_id: send_list_id.join(',') // same here
                        },
                        success: function(response) {
                            alert(response.message)
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', error);
                        }
                    });

                }
            });

            if (!updated) {
                console.warn('No settlement was updated. Check if settlementId and status match (maybe already Paid).');
            }

            document.getElementById('modal-overlay').style.display = 'none';
        }

        // signature: (cpoId, rowid, settlementId)
        function renderSettlementPayoutModalByCpoId(cpoId, rowid, settlementId) {

            const settlementsForCpo = appState.settlements.filter(s => String(s.cpoId) === String(cpoId));
            if (settlementsForCpo.length === 0) return alert('No settlements found for this CPO.');

            // If settlementId provided, try to pick it; otherwise pick latest Pending or first
            let settlement = null;
            if (settlementId) {
                settlement = settlementsForCpo.find(s => String(s.id) === String(settlementId));
            }
            if (!settlement) {
                settlement = settlementsForCpo.find(s => s.status === 'Pending') || settlementsForCpo[0];
            }

            document.getElementById('modal-overlay').style.display = 'flex';

            // Get CPO meta (first record)
            const cpo = findCpo(cpoId) || {
                cpoName: settlementsForCpo[0].cpoName,
                isGstRegistered: settlementsForCpo[0].isGstRegistered,
                pending: cpoSettlement.pending,
                final_cost: cpoSettlement.final_cost,
                gst_amount: cpoSettlement.gst

            };
            const isGst = cpo.isGstRegistered;
            let deductionAmount = 0;
            // console.log(settlement.grossRevenue)
            // const gstOnRevenue = isGst ? Number(settlement.grossRevenue) * 0.18 : 0;
            const netSettlement = calculateNetPayable(settlement);
            const outstandingReceivable = getCpoOutstandingReceivable(cpoId);
            let finalPayout = settlement.grossRevenue - settlement.serviceFee;
            const gstAmount = Number(settlement.gst) || 0;

            // Add GST row only if applicable
            const gstHtml = (isGst === 'Y') ? `<div><span>GST on Revenue (18%)</span><span style="color:var(--positive-balance);">+ ${formatCurrency(gstAmount)}</span></div>` : '';

            // Add GST to payout if applicable
            if (isGst === 'Y') {
                finalPayout += gstAmount;
            }

            let invoiceHtml = '';
            let totalPending = '';
            if (settlement.invoice_list && settlement.invoice_list.length > 0) {
                // Calculate total pending
                totalPending = settlement.invoice_list.reduce((sum, inv) => {
                    return sum + parseFloat(inv.pending || 0);
                }, 0);
                invoiceHtml = `
    <br><h4>Invoice List</h4>
  <input type="hidden" id="initial-final-payout" value="${finalPayout}">
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Invoice ID</th>
                <th>Total Amount</th>
                <th>Pending Amount</th>
                <th>Set Partial Amount (EMI)</th>
                <th>EMI Amount</th>
            </tr>
        </thead>
        <tbody>
            ${settlement.invoice_list.map(inv => `
                <tr>
                    <td>${inv.invoice_id}</td>
                    <td>${inv.total_fees}</td>
                    <td>${formatCurrency(inv.pending)}</td>
                  <td>
  <div class="emi-options">
    <label>
      <input type="checkbox" id="6_months-${inv.list_id}" 
             onclick="emi_function(this, ${inv.pending}, ${totalPending},${inv.list_id})"> 6 Months
    </label>
    <label>
      <input type="checkbox" id="12_months-${inv.list_id}" 
             onclick="emi_function(this, ${inv.pending}, ${totalPending},${inv.list_id})"> 12 Months
    </label>
    <label>
      <input type="checkbox" id="set_manual-${inv.list_id}" 
             onclick="emi_function(this, ${inv.pending}, ${totalPending},${inv.list_id})"> Reminder
    </label>
  </div>
</td>
 <td>
  <input type="number" class="amount-input" 
         id="settlement-amount-${inv.list_id}" 
         placeholder="Enter amount to deduct" min="0" max="${inv.pending}">
  <input type="hidden" id="final_amount-${inv.list_id}" value="${finalPayout}">
  <input type="hidden" id="invoice_id-${inv.list_id}" value="${inv.invoice_id}">
  <input type="hidden" id="list_id-${inv.list_id}" value="${inv.list_id}">
  <br>
  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<button class="btn btn-status amount-btn" id="amount-btn-${inv.list_id}" data-listid="${inv.list_id}">Enter</button>
</td>
                </tr>
            `).join('')}
        </tbody>
    </table>`;
            }

            // Determine period type from settlement.period
            let periodParts = settlement.period.split(' '); // ["16-end", "September", "2025"] or ["1-15", "September", "2025"]
            let halfMonth = periodParts[0] || '';
            let periodType = (halfMonth.startsWith("16")) ? "Period II" : "Period I";

            // Only show deduction for Period II
            let deductionHtml = '';
            let emiHtml = '';
            if (periodType === "Period II") {
                deductionAmount = totalPending;
                emiHtml = `<div class="emi-outstanding"><span>Total Outstanding Amount</span><span id="total_pending">- ${formatCurrency(totalPending)}</span></div>
                
                <div class="emi-outstanding"><span>Outstanding Fees to be Deducted</span><span class="outstanding-negative" id="full-deduction">- 0.00</span></div>`;

            }


            // modal content
            let modalHtml = `<h3>Confirm Settlement Payout</h3><p>Review payout for CPO: <strong>${cpo.cpoName}</strong></p><div class="payout-summary"><div><span>Gross Revenue</span> <span>${formatCurrency(settlement.grossRevenue)}</span></div><div><span>Service Fee</span> <span class="outstanding-negative">- ${formatCurrency(settlement.serviceFee)}</span></div>${gstHtml}${invoiceHtml}${emiHtml}<div class="final-payout"><span>Final Payout to CPO</span> <span id="final-payout">${formatCurrency(finalPayout)}</span></div></div><div class="modal-actions"><button class="btn btn-secondary" data-action="close-modal">Cancel</button><button class="btn btn-primary" data-action="confirm-settlement-payout" data-cpoIds="${cpoId}" data-settlementid="${settlement.id}" data-deduction="${deductionAmount}" data-rowid="${rowid}" id="confirm-btn">Confirm & Pay</button></div>`;

            // Set HTML
            document.getElementById('modal-box').innerHTML = modalHtml;
        }

        function emi_function(el, pending, totalPending, listId) {
            let months = 0;

            if (el.id === `6_months-${listId}` && el.checked) {
                months = 6;
                $(`#12_months-${listId}`).prop('checked', false);
                $(`#set_manual-${listId}`).prop('checked', false);

            } else if (el.id === `12_months-${listId}` && el.checked) {
                months = 12;
                $(`#6_months-${listId}`).prop('checked', false);
                $(`#set_manual-${listId}`).prop('checked', false);

            } else { // Manual / unchecked
                $(`#6_months-${listId}`).prop('checked', false);
                $(`#12_months-${listId}`).prop('checked', false);
                $(`#settlement-amount-${listId}`).val('');
                updateTotalDeduction();
                return;
            }

            // Calculate EMI amount
            let emiAmount = parseFloat((pending / months).toFixed(2));

            //Check if EMI amount exceeds pending
            if (emiAmount > pending) {
                alert(`Calculated EMI amount ₹${emiAmount} exceeds pending amount ₹${pending}.`);
                $(`#settlement-amount-${listId}`).val('');
                $(`#${el.id}`).prop('checked', false);
                return;
            }

            $(`#settlement-amount-${listId}`).val(emiAmount);
            updateTotalDeduction();
        }


        function updateTotalDeduction() {
            let total = 0;
            $('.amount-input').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            // Update total deduction
            $('#full-deduction').html(total.toFixed(2));
            // Ensure both sides are numbers before subtracting
            let initialPayout = parseFloat($('#initial-final-payout').val()) || 0;
            let finalPayout = initialPayout - total;
            // Update final payout
            $('#final-payout').html(finalPayout.toFixed(2));
        }



        $(document).on('click', '.amount-btn', function() {
            let $row = $(this).closest('tr');
            let $checkedBoxes = $row.find('input[type="checkbox"]:checked');

            // Check if any checkbox is selected
            if ($checkedBoxes.length === 0) {
                alert("Please select at least one checkbox before entering the amount.");
                return;
            }

            let $deductInput = $row.find('.amount-input');
            let deduct_amt = parseFloat($deductInput.val()) || 0;

            // Check if amount is zero or empty
            if (deduct_amt <= 0) {
                alert("Please enter a valid amount greater than 0.");
                $deductInput.focus();
                return;
            }

            let pending_amt = parseFloat($row.find('td:nth-child(2)').text()) || 0; // pending column
            if (deduct_amt > pending_amt) {
                alert(`Entered amount ₹${deduct_amt} exceeds pending amount ₹${pending_amt}.`);
                $deductInput.val('');
                $row.find('input[type="checkbox"]').prop('checked', false);
                return;
            }


            // let final_payout = parseFloat($('#final-payout').text().replace(/[^0-9.-]+/g, "")) || 0;
            let final_payout = parseFloat($('#initial-final-payout').val()) || 0;

            let listId = $(this).data('listid');
            let $button = $(`#amount-btn-${listId}`);
            if (deduct_amt <= final_payout) {
                const newFinal = (final_payout - deduct_amt).toFixed(2);
                $('#final-payout').text(newFinal);

                let invoiceId = $row.find('input[id^="invoice_id-"]').val() || '';
                let hiddenListId = $row.find('input[id^="list_id-"]').val() || '';
                let set_amount = deduct_amt;


                let index = send_invoice_id.indexOf(invoiceId);
                if (index !== -1) {
                    // Replace the old amount with the new one
                    send_setamount[index] = set_amount;
                }
                $button.prop('disabled', true);

            } else {
                alert("Deducted amount is greater than the available payout.");
                $deductInput.val('');
                $row.find('input[type="checkbox"]').prop('checked', false);
                updateTotalDeduction();
            }

        });

        $(document).on('change', 'input[type="checkbox"]', function() {
            let $row = $(this).closest('tr');
            let $button = $row.find('.amount-btn');
            let listId = $button.data('listid');

            // Enable button
            $button.prop('disabled', false);

            updateTotalDeduction();

            let invoiceId = $row.find('input[id^="invoice_id-"]').val() || '';
            let hiddenListId = $row.find('input[id^="list_id-"]').val() || '';
            let set_amount = parseFloat($row.find('.amount-input').val()) || 0;

            // Remove only the first occurrence of this invoice
            let index = send_invoice_id.indexOf(invoiceId);
            if (index !== -1) {
                send_invoice_id.splice(index, 1);
                send_list_id.splice(index, 1);
                send_setamount.splice(index, 1);
            }
            // If checked, push new values
            if ($(this).is(':checked')) {
                send_invoice_id.push(invoiceId);
                send_list_id.push(hiddenListId);
                send_setamount.push(set_amount);
            }
        });

        // Initial page load
        showPage('settlements-page');
    </script>

    </script>
</body>

</html>