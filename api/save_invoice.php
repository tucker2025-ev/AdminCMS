<?php
header('Content-Type: application/json');
include '../include/dbconnect.php';

// Decode JSON from raw input
$input = file_get_contents('php://input');
$data = json_decode($input, true);
// Check if valid JSON and action exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($data['action'])) {
    $action = $data['action'];
    if ($action === 'save_invoice') {
        // Collecting data
        $cpo_id = $data['cpo_id'];
        $action_form = $data['action_form'];
        $invoice_id = $data['invoice_id'];
        $fee_date = $data['invoice_date'];
        $due_date = $data['due_date'];
        $line_items = $data['line_items'];
        $grand_total = $data['grand_total'];

        $rate = 0;
        $sub_total = 0;
        $gst = 0;

        $status = 'Y';

        $success = true;
        $errors = [];


        if (!empty($data['file_data'])) {
            $base64File = $data['file_data'];
            $decodedFile = base64_decode($base64File);

            $uploadDir = __DIR__ . '/../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $newFileName = date('Ymd_His') . '.pdf';
            $uploadPath = $uploadDir . $newFileName;

            // Save file
            file_put_contents($uploadPath, $decodedFile);

            // Compress with Ghostscript
            $compressedFile = $uploadDir . 'compressed_' . $newFileName;
            $gsCommand = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook -dNOPAUSE -dQUIET -dBATCH -sOutputFile='$compressedFile' '$uploadPath'";
            shell_exec($gsCommand);

            // Replace original with compressed
            rename($compressedFile, $uploadPath);
            $description = "Upload pdf";
            $quantity = "1";


            // Insert new item
            $stmt = $connect->prepare("INSERT INTO service_list 
(cpo_id, invoice_id, fee_date, due_date, description, quantity, rate, sub_total, gst, grand_total, status, entry_time, actions, remaining) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)");

            $stmt->bind_param("sssssiiiiisss", $cpo_id, $invoice_id, $fee_date, $due_date, $description, $quantity, $rate, $sub_total, $gst, $grand_total, $status, $action_form, $grand_total);

            // echo $cpo_id, $invoice_id, $fee_date, $due_date, $description, $quantity, $rate, $sub_total, $gst, $grand_total, $status, $action_form, $grand_total;
            if (!$stmt->execute()) {
                $success = false;
                $errors[] = $stmt->error;
            }
        }

        foreach ($line_items as $item) {
            $list_id = isset($item['list_id']) && $item['list_id'] !== '' ? intval($item['list_id']) : null;
            $description = $item['description'];
            $quantity = $item['quantity'];
            $rate = $item['price'];

            $sub_total = $quantity * $rate;
            $gst = $sub_total * 0.18;
            $grand_total = $sub_total + $gst;

            if ($list_id) {
                // 🔄 Update existing entry
                $stmt = $connect->prepare("UPDATE service_list SET 
            description = ?, quantity = ?, rate = ?, sub_total = ?, gst = ?, grand_total = ?, 
            fee_date = ?, due_date = ?, actions = ?, entry_time = CURRENT_TIMESTAMP
            WHERE list_id = ?");
                $stmt->bind_param(
                    "siiiidsssi",
                    $description,
                    $quantity,
                    $rate,
                    $sub_total,
                    $gst,
                    $grand_total,
                    $fee_date,
                    $due_date,
                    $action_form,
                    $list_id
                );
            } else {
                // Insert new item
                $stmt = $connect->prepare("INSERT INTO service_list 
(cpo_id, invoice_id, fee_date, due_date, description, quantity, rate, sub_total, gst, grand_total, status, entry_time, actions, remaining) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)");

                $stmt->bind_param("sssssiiiiisss", $cpo_id, $invoice_id, $fee_date, $due_date, $description, $quantity, $rate, $sub_total, $gst, $grand_total, $status, $action_form, $grand_total);
            }

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
    } elseif ($action === 'get_invoiceid') {
        // Fetch total number of existing invoices
        $result = $connect->query("SELECT COUNT(*) AS count FROM service_list");
        $row = $result->fetch_assoc();
        $count = (int)$row['count'];

        // Generate new invoice ID
        $new_id = $count + 1;
        $invoice_id = "INV-ID-" . str_pad($new_id, 3, "0", STR_PAD_LEFT);

        echo json_encode(["success" => true, "invoice_id" => $invoice_id]);
    } elseif ($action === 'draft_invoice_list') {
        $result = $connect->query("SELECT * FROM fca_cpo LEFT JOIN service_list ON fca_cpo.cpo_id = service_list.cpo_id WHERE actions = 'draft' ORDER BY list_id DESC");

        $drafts = [];
        while ($row = $result->fetch_assoc()) {
            $drafts[] = $row;
        }

        echo json_encode(["success" => true, "data" => $drafts]);
        exit;
    } elseif ($action === 'invoice_list') {
        $result = $connect->query("SELECT * FROM fca_cpo LEFT JOIN service_list ON fca_cpo.cpo_id = service_list.cpo_id WHERE actions = 'save' ORDER BY list_id DESC");

        $drafts = [];
        while ($row = $result->fetch_assoc()) {
            $drafts[] = $row;
        }

        echo json_encode(["success" => true, "data" => $drafts]);
        exit;
    } else {
        echo json_encode(["success" => false, "error" => "Invalid action"]);
    }

    $connect->close();
} else {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
}
