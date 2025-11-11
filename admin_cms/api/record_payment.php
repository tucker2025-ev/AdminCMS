<?php
header('Content-Type: application/json');
include '../include/dbconnect.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

$list_id = $data['list_id'] ?? null;
$invoice_id = $data['invoice_id'] ?? null;
$amount = floatval($data['amount'] ?? 0);
$date = $data['date'] ?? null;

if (!$list_id || !$invoice_id || $amount <= 0 || !$date) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid payment data."]);
    exit;
}

// Use prepared statements safely
$stmt = $station_connect->prepare("SELECT paid_amount, remaining FROM service_list WHERE list_id = ? AND invoice_id = ?");
$stmt->bind_param("is", $list_id, $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Service list item not found."]);
    exit;
}

$row = $result->fetch_assoc();
$new_paid = $row['paid_amount'] + $amount;
$new_remaining = $row['remaining'] - $amount;
if ($new_remaining < 0) {
    http_response_code(400);
    echo json_encode(["error" => "Payment exceeds remaining amount."]);
    exit;
}

$update = $station_connect->prepare("UPDATE service_list SET paid_amount = ?, remaining = ?, last_updated_date = ? WHERE list_id = ? AND invoice_id = ?");
$update->bind_param("ddsis", $new_paid, $new_remaining, $date, $list_id, $invoice_id);
$update->execute();

if ($update->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Payment recorded."]);
} else {
    echo json_encode(["status" => "warning", "message" => "No changes made."]);
}
