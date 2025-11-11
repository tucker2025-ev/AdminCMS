<?php
header('Content-Type: application/json');
if (isset($_FILES['invoice_pdf'])) {
    $file = $_FILES['invoice_pdf']['tmp_name'];
    $voucherNo = isset($_POST['voucher_no']) ? trim($_POST['voucher_no']) : '';

    // Convert PDF to text using pdftotext
    $text = shell_exec("pdftotext '$file' -");

    // Split lines and clean empty spaces
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));

    $grandTotal = null;
    $amountInWords = '';

    //Extract all numeric amounts
    $amounts = [];
    foreach ($lines as $line) {
        if (preg_match('/₹?\s?([\d,]+\.\d{2})/', $line, $match)) {
            $amounts[] = str_replace(',', '', $match[1]);
        }
    }

    //Get last numeric amount as Grand Total
    if (!empty($amounts)) {
        $grandTotal = end($amounts);
    }

    //Find amount in words (INR ... Only)
    for ($i = 0; $i < count($lines); $i++) {
        if (stripos($lines[$i], 'INR') === 0) {
            $amountInWords = $lines[$i];
            if (isset($lines[$i + 1]) && stripos($lines[$i + 1], 'Only') !== false) {
                $amountInWords .= ' ' . $lines[$i + 1];
            }
            break; // Stop after finding first INR
        }
    }

    //Prepare JSON response
    if ($grandTotal || $amountInWords) {
        echo json_encode([
            'voucher_no' => $voucherNo,
            'grand_total' => $grandTotal ? number_format((float)$grandTotal, 2, '.', '') : null,
            'amount_in_words' => $amountInWords
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['error' => 'Grand Total or Amount in words not found'], JSON_PRETTY_PRINT);
    }
} else {
    echo json_encode(['error' => 'No file uploaded'], JSON_PRETTY_PRINT);
}