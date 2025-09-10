<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

include '../include/dbconnect.php';

// Step 1: Get all CPOs and their station count
$query = "SELECT cpo.*, COUNT(DISTINCT st.station_id) AS total_stations,GROUP_CONCAT(DISTINCT st.station_id) AS station_ids,COUNT(DISTINCT cp.charger_id) AS total_chargepoints, GROUP_CONCAT(DISTINCT cp.charger_id) AS chargepoints FROM fca_cpo AS cpo LEFT JOIN fca_stations AS st ON cpo.cpo_id = st.cpo_id LEFT JOIN fca_charger AS cp ON cp.station_id = st.station_id where cp.network = 'SIM' GROUP BY cpo.cpo_id ORDER BY cpo.sno DESC;";

$objResult = mysqli_query($connect, $query);

// Step 2: Get station IDs grouped by CPO
$grp_query = "SELECT cpo_id, GROUP_CONCAT(station_id) AS station_ids FROM fca_stations GROUP BY cpo_id";
$grp_objResult = mysqli_query($connect, $grp_query);

$cpo_station_map = [];
while ($row = mysqli_fetch_assoc($grp_objResult)) {
    $cpo_station_map[$row['cpo_id']] = explode(',', $row['station_ids']);
} 
