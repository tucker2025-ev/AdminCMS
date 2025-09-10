<?php
include 'include/dbconnect.php';
include 'include/steve_connection.php';

session_start();
if (empty($_SESSION["user_mobile"])) {
    header("location:index.php");
    exit();
}

// Query the billings_accounts table
$result = mysqli_query($connect, "SELECT * FROM billings_accounts ORDER BY billing_id DESC");
if (!$result) {
    die("Query failed: " . mysqli_error($connect));
}
$totalOutstanding = 0;
$totalPaid = 0;
$invoice_Count = 0; // initialize as integer

$invoiceRows = '';
while ($row = mysqli_fetch_assoc($result)) {
    $status_text = ($row['status'] === 'Y') ? 'Paid' : 'Pending';

    if ($status_text === 'Pending') {
        $totalOutstanding += $row['amount'];
        $invoice_Count++;
    } else {
        $totalPaid += $row['amount'];
    }

    $statusClass = strtolower($status_text);
    $formattedAmount = number_format($row['amount'], 2);
    $invoice_id = htmlspecialchars($row['invoice_id']);
    $entry_date = htmlspecialchars($row['entry_date']);
    $description = htmlspecialchars($row['description']);

    $invoiceRows .= "
        <tr>
            <td>{$invoice_id}</td>
            <td>{$entry_date}</td>
            <td>{$description}</td>
            <td><span class='status status-{$statusClass}'>{$status_text}</span></td>
            <td style='text-align: right;'>\${$formattedAmount}</td>
            <td style='text-align: center;'>
                <button class='action-btn' title='View Details'>
                    <svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2.5'>
                        <path d='M5 12h14M12 5l7 7-7 7'/>
                    </svg>  
                    <span>Upcoming</span>
                </button>
            </td>
        </tr>
    ";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoices</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles/billingaccounts.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="icon" type="image/x-icon" href="images/favicon.ico">
</head>

<body>
    <div class="dashboard-container">
        <?php include "left.php"; ?>

        <main class="main-content"><br>
            <h1 class="page-title" style="text-align: center;">Coming Soon</h1>
            <div class="main-wrapper" style="display: none;">
                <header class="top-header">
                    <h1 class="page-title">Billing Report</h1>
                    <div class="user-menu">
                        <svg
                            class="icon"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2">
                            <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9" />
                            <path d="M13.73 21a2 2 0 01-3.46 0" />
                        </svg>
                        <div class="user-avatar"></div>
                    </div>
                </header>

                <main>
                    <div class="summary-container">
                        <div class="stat-card is-hero">
                            <div class="stat-icon">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2">
                                    <path
                                        d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
                                </svg>
                            </div>
                            <div class="stat-info">
                                <p class="label">Total Outstanding</p>
                                <p class="value" id="total-outstanding">$0.00</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="stat-info">
                                <p class="label">Invoices Pending</p>
                                <p class="value" id="invoices-pending">0</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14" />
                                    <path d="M22 4L12 14.01l-3-3" />
                                </svg>
                            </div>
                            <div class="stat-info">
                                <p class="label">Paid This Year</p>
                                <p class="value" id="total-paid">$0.00</p>
                            </div>
                        </div>
                    </div>

                    <div class="history-card">
                        <div class="card-header">
                            <h2 class="card-title">Invoice History</h2>
                        </div>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th style="text-align: right">Amount</th>
                                        <th style="text-align: center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="invoice-table-body"> <?= $invoiceRows ?></tbody>
                            </table>
                        </div>
                    </div>
                </main>
            </div>
        </main>
    </div>

    <script>
        $(document).ready(function() {
            let totalOutstanding = <?= $totalOutstanding ?>;
            let invoiceCount = <?= $invoice_Count ?>;
            let totalPaid = <?= $totalPaid ?>;

            $('#total-outstanding').text(`$${totalOutstanding.toFixed(2)}`);
            $('#invoices-pending').text(invoiceCount);
            $('#total-paid').text(`$${totalPaid.toFixed(2)}`);
        });
    </script>
</body>

</html>