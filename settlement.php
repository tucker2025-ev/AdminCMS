<?php
// No changes to the PHP at the top of this file
session_start();
if ($_SESSION["user_mobile"] == '') {
    header('Location: index.php');
    exit;
}

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
                        <div class="filter-item"><select id="settlement-status-filter">
                                <option value="Pending">Pending</option>
                                <option value="Paid">Paid</option>
                            </select></div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>S.NO</th>
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
        // Global appState to store CPO settlement data
        let appState = {
            settlements: []
        };

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
            url: "api/cpo_list_months.php",
            method: "GET",
            dataType: "json",
            data: {
                start_date: formatDate(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1)), // 1st of last month
                end_date: formatDate(new Date()) // today
            },
            success: function(response) {
                console.log("API Response:", response);
                if (response.status !== true) return;

                const rows = [];
                const today = new Date();
                const currentYear = today.getFullYear();

                // Loop over each CPO in response.data
                Object.entries(response.data).forEach(([cpoName, cpoData]) => {
                    const gst_status = cpoData.gst_status; // CPO-level
                    const gstin = cpoData.gstin; // CPO-level
                    const cpoId = cpoData.cpo_id;

                    ["current_month", "previous_month"].forEach(monthType => {
                        if (!cpoData[monthType]) return;

                        Object.entries(cpoData[monthType]).forEach(([periodLabel, periodData]) => {
                            const status = (periodData.settlement_status === "Y" || periodData.settlement_status === "Paid") ? "Paid" : "Pending";

                            // Determine correct month name
                            let dateRef = new Date();
                            if (monthType === "previous_month") {
                                dateRef = new Date(dateRef.getFullYear(), dateRef.getMonth() - 1, 1);
                            }
                            const periodMonthName = dateRef.toLocaleString('default', {
                                month: 'long'
                            });

                            // Build standardized settlement id as string (no numeric-only expectation)
                            const settlementId = `${cpoId}-${monthType}-${periodLabel}`;
                            if (cpoName != '-') {
                                rows.push({
                                    id: settlementId,
                                    cpoId: cpoId,
                                    cpoName: cpoName,
                                    isGstRegistered: gst_status,
                                    gst_status: gst_status,
                                    gstin: gstin || null,
                                    settlement_status: periodData.settlement_status,
                                    total_units: Number(periodData.total_units) || 0,
                                    final_cost: Number(periodData.final_cost) || 0,
                                    unit_cost: Number(periodData.cost) || 0,
                                    grossRevenue: Number(periodData.unit_cost) || 0,
                                    gst: Number(periodData.gst_amount) || 0,
                                    period: `${periodLabel} ${periodMonthName}`,
                                    serviceFee: Number(periodData.service_fee) || 0,
                                    finalCost: (Number(periodData.cost) || 0) + (Number(periodData.gst_amount) || 0),
                                    status
                                });


                            }


                        });
                    });
                });

                appState.settlements = rows;
                renderSettlementsTable(); // Now rows are in appState
            },
            error: function(xhr, status, error) {
                console.error("AJAX error:", error);
            }
        });

        // Render settlements table with optional filters
        function renderSettlementsTable(filters = {}) {
            const {
                status = 'Pending', search = ''
            } = filters;

            const today = new Date();
            const currentDay = today.getDate();
            const currentMonth = today.getMonth(); // 0-based
            const currentYear = today.getFullYear();

            const filtered = appState.settlements.filter(s =>
                (status === 'All' || s.status === status) &&
                s.cpoName.toLowerCase().includes(search.toLowerCase())
            );

            let i = 1;

            const tableBodyHtml = filtered.map(s => {
                const cpoMeta = findCpo(s.cpoId) || {};
                const isGst = s.isGstRegistered;
                const gstOnRevenue = isGst ? Number(s.grossRevenue) * 0.18 : 0;
                const netPayable = calculateNetPayable(s);
                const cpoNameHtml = `${s.cpoName} ${s.gst_status == 'Y' ? '<span class="gst-tag">GST</span>' : ''}`;

                // Determine if action should be enabled (only for current month)
                let isActionEnabled = false;
                const periodParts = s.period.split(' '); // e.g., ["1–15", "September"]
                const periodRange = periodParts[0] || '';
                const periodMonthName = periodParts[1] || '';
                const periodMonth = new Date(`${periodMonthName} 1, ${currentYear}`).getMonth();

                if (periodMonth === currentMonth) {
                    if (periodRange.startsWith("1–15") || periodRange.startsWith("1-15")) {
                        isActionEnabled = currentDay > 15;
                    } else if (periodRange.startsWith("16–") || periodRange.startsWith("16-")) {
                        isActionEnabled = currentDay >= 16;
                    }
                }

                const isPaid = s.status.toLowerCase() === 'paid';
                // Use consistent data attributes:
                // - data-settlementid: settlement.id (string)
                // - data-cpoid: s.cpoId
                // - data-rowid: table row id (unique client-side)
                const clientRowId = `table_id${i}`;

                let actionHtml = '';
                if (!isPaid && (s.settlement_status === 'N' || s.status === 'Pending') && periodMonth === currentMonth) {
                    // Show actionable link (may be disabled visually if not allowed yet)
                    if (!isActionEnabled) {
                        actionHtml = `<a class="action-link disabled" style="pointer-events:none; opacity:0.4;" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>`;
                    } else {
                        actionHtml = `<a class="action-link" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>`;
                    }
                } else {
                    actionHtml = (s.settlement_status !== 'N' ? 'Paid' : `<a class="action-link" data-action="initiate-settlement-payout" data-settlementid="${s.id}" data-cpoid="${s.cpoId}" data-rowid="${clientRowId}">Deduct & Pay</a>`);
                }

                const html = `<tr data-rowid="${clientRowId}" data-cpoid="${s.cpoId}" data-settlementid="${s.id}">
                <td>${i}</td>
                <td>${cpoNameHtml}</td>
                <td>${s.period}</td>
                <td style="text-align:right;">${formatCurrency(s.grossRevenue)}</td>
                <td style="text-align:right;" class="outstanding-negative">- ${formatCurrency(s.serviceFee)}</td>
                <td style="text-align:right; color:var(--positive-balance);">${formatCurrency( s.gst_status == 'Y' ? s.gst : 0)}</td>
               <td style="text-align:right; font-weight:600;">${formatCurrency(s.gst_status === 'Y' ? (s.final_cost +  s.gst) : s.final_cost )}</td>
                <td><span class="status ${s.status.toLowerCase()}">${s.status}</span></td>
                <td>${actionHtml}</td>
            </tr>`;

                i++;
                return html;
            }).join('');

            document.getElementById('settlements-table-body').innerHTML = tableBodyHtml;
        }

        // Filter inputs event handler
        document.querySelector('.main-content').addEventListener('input', e => {
            if (e.target.matches('#settlement-search, #settlement-status-filter')) {
                renderSettlementsTable({
                    search: document.getElementById('settlement-search').value,
                    status: document.getElementById('settlement-status-filter').value
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
            console.log("Payout confirmed for ID:", settlementId);

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

                    // console.log({
                    //     settlement_id: settlementId,
                    //     cpo_id: cpoIds,
                    //     status: 'Paid',
                    //     deduction: deductionAmount,
                    //     period: settlement.period // 👈 add this
                    // });

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
                            period: settlement.period
                        },
                        success: function(response) {
                            // console.log('Backend updated:', response);
                            window.location.reload();
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

        // Render modal popup for payout confirmation by cpoId
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
                isGstRegistered: settlementsForCpo[0].isGstRegistered
            };

            const isGst = cpo.isGstRegistered;
            const gstOnRevenue = isGst ? Number(settlement.grossRevenue) * 0.18 : 0;
            const netSettlement = calculateNetPayable(settlement);
            const outstandingReceivable = getCpoOutstandingReceivable(cpoId);
            const deductionAmount = Math.min(netSettlement, outstandingReceivable);
            const finalPayout = netSettlement - deductionAmount;

            const gstHtml = isGst ? `<div><span>GST on Revenue (18%)</span> <span style="color:var(--positive-balance);">+ ${formatCurrency(gstOnRevenue)}</span></div>` : '';

            // modal content
            const modalHtml = `<h3>Confirm Settlement Payout</h3>
            <p>Review payout for CPO: <strong>${cpo.cpoName}</strong></p>
            <div class="payout-summary">
                <div><span>Gross Revenue</span> <span>${formatCurrency(settlement.unit_cost)}</span></div>
                <div><span>Service Fee</span> <span class="outstanding-negative">- ${formatCurrency(settlement.serviceFee)}</span></div>
                ${gstHtml}
                <div><span>Outstanding Fees to be Deducted</span> <span class="outstanding-negative">- ${formatCurrency(deductionAmount)}</span></div>
                <div class="final-payout"><span>Final Payout to CPO</span> <span>${formatCurrency(finalPayout)}</span></div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
                <button class="btn btn-primary" data-action="confirm-settlement-payout" data-cpoIds="${cpoId}" data-settlementid="${settlement.id}" data-deduction="${deductionAmount}" data-rowid="${rowid}">Confirm & Pay</button>
            </div>`;

            document.getElementById('modal-box').innerHTML = modalHtml;
        }

        // Initial page load
        showPage('settlements-page');
    </script>

    </script>
</body>

</html>