<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set("Asia/Kolkata");
header('Content-Type: application/json');

include '../include/dbconnect.php';

// Step 1: Get all CPOs and their station count
$query = "
    SELECT cpo.*, COUNT(st.cpo_id) AS total_stations 
    FROM fca_cpo AS cpo 
    LEFT JOIN fca_stations AS st ON cpo.cpo_id = st.cpo_id 
    GROUP BY cpo.cpo_id 
    ORDER BY cpo.sno DESC";

$objResult = mysqli_query($connect, $query);

// Step 2: Get station IDs grouped by CPO
$grp_query = "SELECT cpo_id, GROUP_CONCAT(station_id) AS station_ids FROM fca_stations GROUP BY cpo_id";
$grp_objResult = mysqli_query($connect, $grp_query);

$cpo_station_map = [];
while ($row = mysqli_fetch_assoc($grp_objResult)) {
    $cpo_station_map[$row['cpo_id']] = explode(',', $row['station_ids']);
}

$results = [];

if (mysqli_num_rows($objResult) > 0) {
    while ($cpo = mysqli_fetch_assoc($objResult)) {
        $cpo_id = $cpo['cpo_id'];

        if (!isset($cpo_station_map[$cpo_id]) || empty($cpo_station_map[$cpo_id])) {
            $cpo['summary'] = null;
            $results[] = $cpo;
            continue;
        }

        $station_ids = $cpo_station_map[$cpo_id];
        $placeholders = implode(',', array_fill(0, count($station_ids), '?'));
        // Step 3: Prepare and run query to get summary data
        $sql = "SELECT unit_fare,total_units, unit_cost,gst, total_cost FROM summary_report WHERE station_id IN ($placeholders) AND start_time >= CURDATE() - INTERVAL 30 DAY AND start_time < CURDATE()";

        //  AND start_time IS NOT NULL
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connect->error);
        }

        // Bind station_id values
        $types = str_repeat('s', count($station_ids));
        $bind_params = [];
        $bind_params[] = &$types;
        foreach ($station_ids as $key => $value) {
            $bind_params[] = &$station_ids[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_params);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("get_result failed: " . $stmt->error);
        }

        // Step 4: Calculate aggregation
        $sum_total_units = 0;
        $sum_unit_cost   = 0;
        $sum_gst         = 0;
        $sum_total_cost  = 0;
        $sum_total_rate  = 0;
        $sum_final_cost  = 0;

        while ($row = $result->fetch_assoc()) {
            $unit_fare    = (float)$row["unit_fare"];
            $total_units  = (float)$row["total_units"];
            $unit_cost    = (float)$row["unit_cost"];
            $gst          = (float)$row["gst"];
            $total_cost   = (float)$row["total_cost"];

            // Get rate from table
            $sql = "SELECT amount FROM unit_fare_tariff 
            WHERE min_unit <= ? AND (max_unit >= ? OR max_unit IS NULL) 
            AND status = 'Y' LIMIT 1";

            $stmt = $connect->prepare($sql);
            $stmt->bind_param("dd", $unit_fare, $unit_fare);
            $stmt->execute();
            $rate_result = $stmt->get_result();

            if ($rate_row = $rate_result->fetch_assoc()) {
                $rate = (float)$rate_row['amount'];
            } else {
                $rate = 0; // default for unit_fare < 15
            }

            $total_rate = $total_units * $rate;
            $final_cost = $unit_cost - $total_rate;

            $sum_total_units += $total_units;
            $sum_unit_cost   += $unit_cost;
            $sum_gst         += $gst;
            $sum_total_cost  += $total_cost;
            $sum_total_rate  += $total_rate;
            $sum_final_cost  += $final_cost;
        }


        $total_rate = $total_units * $rate;
        $final_cost = $unit_cost - $total_rate;

        $sum_total_units += $total_units;
        $sum_unit_cost   += $unit_cost;
        $sum_gst         += $gst;
        $sum_total_cost  += $total_cost;
        $sum_total_rate  += $total_rate;

        $cpo['total_rate'] =  $total_rate;
        $cpo['final_cost'] =  $final_cost;
        $cpo['total_units'] =  $sum_total_units;
        $cpo['unit_cost'] =  $sum_unit_cost;
        $cpo['gst'] =  $sum_gst;
        $cpo['total_cost'] =  $sum_total_cost;
        $cpo['total_rate'] =  $sum_total_rate;
        $cpo['final_cost'] =  $sum_final_cost;

        $results[] = $cpo;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No records found.'
    ]);
}
