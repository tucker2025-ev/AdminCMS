<?php
header('Content-Type: application/json');
include '../include/dbconnect.php';

if (!isset($_GET['mac_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'mac_id is required'
    ]);
    exit;
}

$mac_id = $_GET['mac_id'];

try {
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://star.tuckermotors.com/FTCTlog/ftctapi2.php?mac=' . urlencode($mac_id),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $data = json_decode($response, true);
    if ($data && isset($data['data']['log_info']['latest_log_entry'])) {
        $latestLog = $data['data']['log_info']['latest_log_entry'];

        echo json_encode([
            'success' => true,
            'data' => [
                'log_info' => [
                    'latest_log_entry' => $latestLog
                ]
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No log data found for this MAC ID'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
