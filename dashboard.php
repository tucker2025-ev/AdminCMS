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
			<div id="dashboard-page" class="page-content">
				<header class="main-header">
					<h2>Dashboard</h2>
				</header>
				<div class="content-row">
					<div class="content-column-large">
						<div class="card" id="dashboard-kpis-container"></div>
						<div class="card">
							<div class="card-header">
								<h3>Pending Settlements</h3><a href="settlement.php" class="action-link" data-page="settlements-page">View All &rarr;</a>
							</div>
							<table class="data-table">
								<thead>
									<tr>
										<th>CPO Name</th>
										<th>Period</th>
										<th>Net Amount</th>
										<th>Status</th>
									</tr>
								</thead>
								<tbody id="dashboard-pending-settlements"></tbody>
							</table>
						</div>
					</div>
					<div class="content-column-small">
						<div class="card">
							<div class="card-header">
								<h3>Monthly Revenue vs Settled</h3>
							</div>
							<div style="position: relative; height: 300px;"><canvas id="financialChart"></canvas></div>
						</div>
					</div>
				</div>
			</div>

			<div id="modal-overlay" class="modal-overlay">
				<div id="modal-box" class="modal-box"></div>
			</div>
			<div id="toast" class="toast-notification"></div>
	</div>
	<script>
		const MOCK_SYSTEM_DATE = new Date('2025-08-31');

		function showPage(pageId, options = {}) {
			document.querySelectorAll('.page-content').forEach(p => p.classList.remove('active'));
			document.getElementById(pageId)?.classList.add('active');
			document.querySelectorAll('.nav-item').forEach(l => l.classList.remove('active'));
			const navLink = document.querySelector(`[data-page="${pageId}"]`) || document.querySelector(`[data-page="${options.parentPage}"]`);
			navLink?.parentElement.classList.add('active');
			const pageRenderers = {
				'dashboard-page': renderDashboard
			};
			if (pageRenderers[pageId]) pageRenderers[pageId]();
		}
		showPage('dashboard-page');

		function renderDashboard() {
			const $container = $('#dashboard-kpis-container');
			const formatCurrency = (num) =>
				`₹ ${parseFloat(num).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

			const getMonthYear = (date) => {
				return date.toLocaleString('default', {
					month: 'long'
				}) + ' ' + date.getFullYear();
			};

			const parseMonthYear = (monthYearStr) => {
				// Converts "August 2025" → Date object (1st of that month)
				return new Date(Date.parse(`1 ${monthYearStr}`));
			};

			const now = MOCK_SYSTEM_DATE;
			const thisMonthYear = getMonthYear(now);
			const prevMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
			const prevMonthYear = getMonthYear(prevMonthDate);

			$.ajax({
				url: 'api/settlement_list.php',
				method: 'GET',
				dataType: 'json',
				success: function(response) {
					console.log(response);
					if (response.status !== "success") {
						console.error("API returned non-success status");
						return;
					}

					const monthlyData = response.data.monthly || [];

					// Sort descending by month date
					monthlyData.sort((a, b) => parseMonthYear(b.month_name) - parseMonthYear(a.month_name));

					const currentMonthData = monthlyData.find(m => m.month_name === thisMonthYear);

					let html = '';

					if (currentMonthData) {
						html += `<div class="dashboard-single-kpi"><h3 style="cursor:pointer;" data-action="filter-settlements" data-status="Pending">
                    Net Amount to be Settled (Previous Month)
                </h3><p style="color:var(--primary-red);">${formatCurrency(currentMonthData.unit_cost)}</p></div>`;
					}

					html += `<table class="data-table"><thead><tr>
                <th>Period</th>
                <th style="text-align:right;">Razorpay Collected Amount</th>
                <th style="text-align:right;">Total Amount Settled</th>
                <th style="text-align:right;">Revenue Balance</th>
                <th style="text-align:right;">CPO Service Fee</th>
                <th style="text-align:right;">Razorpay Fee</th>
            </tr></thead><tbody>`;
					monthlyData.forEach((month) => {
						let label = month.month_name;
						// if (month.month_name === thisMonthYear) {
						// 	label = `${month.month_name}`;
						// } else if (month.month_name === prevMonthYear) {
						// 	label = `${month.month_name}`;
						// }

						const profit = month.razorpay_amount - month.total_cost;

						html += `<tr><td>${label}</td>
        <td style="text-align:right;">${formatCurrency(month.razorpay_amount)}</td>
        <td style="text-align:right;">${formatCurrency(month.total_cost)}</td>
        <td style="text-align:right; color:var(--positive-balance);">${formatCurrency(profit)}</td>
        <td style="text-align:right;">${formatCurrency(month.total_rate)}</td>
		<td style="text-align:right;">${formatCurrency(month.razorpay_amount * 0.0236)}</td>
    </tr>`;
					});


					html += '</tbody></table>';
					$container.html(html);

					// Prepare chart data from monthlyData for last 6 months relative to MOCK_SYSTEM_DATE
					const chartData = getMonthlyChartDataFromResponse(monthlyData, MOCK_SYSTEM_DATE);

					const ctx = document.getElementById('financialChart').getContext('2d');
					if (window.financialChartInstance) window.financialChartInstance.destroy();
					window.financialChartInstance = new Chart(ctx, {
						type: 'bar',
						data: chartData,
						options: {
							responsive: true,
							maintainAspectRatio: false,
							scales: {
								y: {
									beginAtZero: true,
									ticks: {
										callback: v => `₹${(v / 1000).toFixed(0)}k`
									}
								}
							}
						}
					});
				},
				error: function(error) {
					console.error("AJAX error:", error);
				}
			});
		}

		/**
		 * Extract last 6 months data from monthlyData and prepare labels and datasets for chart
		 * @param {Array} monthlyData - Array of months from API response
		 * @param {Date} referenceDate - Date to calculate last 6 months from
		 * @returns Chart.js data object
		 */
		function getMonthlyChartDataFromResponse(monthlyData, referenceDate) {
			const labels = [];
			const finalCostData = [];
			const totalCostData = [];

			const monthNamesShort = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

			// Helper to find data for a specific month-year string
			const findMonthData = (monthYear) =>
				monthlyData.find(m => m.month_name === monthYear);

			for (let i = 5; i >= 0; i--) {
				const d = new Date(referenceDate.getFullYear(), referenceDate.getMonth() - i, 1);
				const monthName = monthNamesShort[d.getMonth()];
				const year = d.getFullYear();
				const monthYearStr = `${d.toLocaleString('default', { month: 'long' })} ${year}`;

				labels.push(monthName);

				const monthData = findMonthData(monthYearStr);

				finalCostData.push(monthData ? monthData.razorpay_amount : 0);
				totalCostData.push(monthData ? monthData.unit_cost : 0);
			}

			return {
				labels,
				datasets: [{
						label: 'Razorpay Collections (Final Cost)',
						data: finalCostData,
						backgroundColor: 'rgba(54, 162, 235, 0.7)'
					},
					{
						label: 'Total Settled',
						data: totalCostData,
						backgroundColor: 'rgba(75, 192, 192, 0.7)'
					}
				]
			};
		}

		let allSettlements = []; // to store all data
		function renderSettlements(data) {
			let html = '';
			data.forEach(item => {
				const totalCost = Number(item.total_cost) || 0;
				const gst = Number(item.total_rate ?? item.total_rate) || 0; // support both keys

				const netAmount = totalCost - gst;
				const formattedNetAmount = netAmount.toFixed(2);

				html += `
            <tr>
                <td>${item.cpo_name}</td>
                <td>${item.period ?? '-'}</td>
                <td style="text-align:right; font-weight:600;">${formattedNetAmount}</td>
                <td>
                    ${
                        item.status 
                          ? `<span class="status ${item.status.toLowerCase()}">${item.status}</span>` 
                          : '<span class="status pending">Pending</span>'
                    }
                </td>
            </tr>`;
			});
			$('#dashboard-pending-settlements').html(html);
		}


		function formatDate(date) {
			let y = date.getFullYear();
			let m = String(date.getMonth() + 1).padStart(2, "0");
			let d = String(date.getDate()).padStart(2, "0");
			return `${y}-${m}-${d}`;
		}

		$.ajax({
			url: 'api/cpo_list_months.php',
			method: 'GET',
			dataType: 'json',
			data: {
				start_date: formatDate(new Date(new Date().getFullYear(), new Date().getMonth() - 1, 1)), // 1st of last month
				end_date: formatDate(new Date()) // today
			},
			success: function(response) {
				if (!response.status) {
					console.error("API returned non-success status");
					return;
				}

				// --- Get current month name ---
				const now = new Date();
				const monthName = now.toLocaleString('default', {
					month: 'long'
				});
				const year = now.getFullYear();

				// Convert object -> array (only current month entries)
				allSettlements = Object.entries(response.data).map(([cpoName, cpoData]) => {
					let current = cpoData.current_month?.["1-15"] ?? {};
					return {
						cpo_name: cpoName,
						period: `${monthName} ${year} (1-15)`, // ✅ dynamic month name
						total_cost: current.total_cost || 0,
						total_rate: current.gst_amount || 0, // assuming GST = deduction
						status: current.settlement_status === "Y" ? "Settled" : "Pending"
					};
				});

				// Show only first 2 records
				renderSettlements(allSettlements.slice(0, 2));
			}
		});



		// Handle click on "View All →"
		// $('a.action-link[data-page="settlements-page"]').on('click', function(e) {
		// 	e.preventDefault();
		// 	renderSettlements(allSettlements); // render all data on click
		// });
	</script>
</body>

</html>