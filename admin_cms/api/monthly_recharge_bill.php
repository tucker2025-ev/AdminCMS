<?php
header('Content-Type: application/json');
include '../include/dbconnect.php';

// Decode JSON from raw input
$input = file_get_contents('php://input');
$data = json_decode($input, true);
// Check if valid JSON and action exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['action'])) {
    $action = $data['action'];
    if ($action === 'save_monthly_bill') {
        $cpo_items = $data['cpo_items']; // expecting array of items [{cpo_id, price}, ...]
        $action_form = 'save';
        $due_date = date('Y-m-d', strtotime('+1 month'));
        $status = 'Y';
        $success = true;
        $errors = [];

        $description = "Monthly Recharge For SIM";
        $quantity = 1;

        foreach ($cpo_items as $item) {
            $cpo_id = $item['cpo_id'];
            $rate = $item['price'];

            $sub_total = $quantity * $rate;
            $gst = round($sub_total * 0.18, 2);
            $grand_total = round($sub_total + $gst, 2);

            // Get current count to generate invoice id
            $result = $station_connect->query("SELECT COUNT(*) AS count FROM service_list");
            $row = $result->fetch_assoc();
            $count = (int)$row['count'];

            $new_id = $count + 1;
            $invoice_id = "INV-ID-" . str_pad($new_id, 3, "0", STR_PAD_LEFT);

            // If fee_date is needed, define it here (example: today)
            $fee_date = date('Y-m-d');

            $stmt = $station_connect->prepare("INSERT INTO service_list (cpo_id, invoice_id, fee_date, due_date, description, quantity, rate, sub_total, gst, grand_total, status, entry_time, actions, remaining) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)");

            $stmt->bind_param("sssssiiddsiss", $cpo_id, $invoice_id, $fee_date, $due_date, $description, $quantity, $rate, $sub_total, $gst, $grand_total, $status, $action_form, $grand_total);

            if (!$stmt->execute()) {
                $success = false;
                $errors[] = $stmt->error;
            }

            $stmt->close();
        }

        if ($success) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "errors" => $errors]);
        }
    } elseif ($action === 'monthly_bill_list') {
        $result = $station_connect->query("SELECT * FROM recharge_bill_monthly WHERE status = 'Y' ORDER BY id DESC");
        $bill_list = [];
        while ($row = $result->fetch_assoc()) {
            $bill_list[] = $row;
        }

        echo json_encode(["success" => true, "data" => $bill_list]);
        exit;
    } else {
        echo json_encode(["success" => false, "error" => "Invalid action"]);
    }

    $station_connect->close();
} else {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
}
