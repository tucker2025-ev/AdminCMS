<?php
include 'include/dbconnect.php';
include 'include/steve_connection.php';
$currentPage = basename($_SERVER['PHP_SELF']); // Get current page name
?>

<nav class="sidebar">
	<div>
		<div class="sidebar-header">
			<h1>TUCKER <span>CMS</span></h1>
		</div>
		<ul class="nav">
			<li class="nav-item <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>"><a href="dashboard.php" data-page="dashboard-page"><svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
					</svg>Dashboard</a></li>
			<li class="nav-item <?= ($currentPage == 'history.php') ? 'active' : '' ?>"><a href="history.php" data-page="customer-invoices-page"><svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"></path>
					</svg>Customer Invoices</a></li>
			<li class="nav-item <?= ($currentPage == 'settlement.php') ? 'active' : '' ?>"><a href="settlement.php" data-page="settlements-page"><svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
					</svg>Settlements</a></li>
			<li class="nav-item <?= ($currentPage == 'invocing.php') ? 'active' : '' ?>"><a href="invocing.php" data-page="invoicing-page"><svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
					</svg>Invoicing</a></li>
			<li class="nav-item <?= ($currentPage == 'bills.php') ? 'active' : '' ?>"><a href="bills.php" data-page="bills-page"><svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
					</svg>Bills</a></li>
			<li class="nav-item <?= ($currentPage == 'cpo_accounts.php') ? 'active' : '' ?>"><a href="cpo_accounts.php" data-page="cpo-accounts-page"><svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
					</svg>CPO Accounts</a></li>

			<li class="nav-item <?= ($currentPage == 'logout.php') ? 'active' : '' ?>"><a href="logout.php" data-page="logout-page"><svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 11-4 0v-1m0-8v1a2 2 0 104 0V7" />
					</svg>
					Logout</a></li>

		</ul>
	</div>
</nav>