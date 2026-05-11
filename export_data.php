<?php
// ✅ MUST BE FIRST - Prevent any HTML errors from breaking JSON
error_reporting(0);
ini_set('display_errors', 0);

// ✅ Set JSON header immediately
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// ✅ Include your DB connection
include 'db_config.php'; // adjust path if needed

// ✅ Validate inputs
$category   = isset($_GET['category'])   ? trim($_GET['category'])   : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';

if (empty($category) || empty($start_date) || empty($end_date)) {
    echo json_encode(["message" => "Missing required parameters."]);
    exit;
}

// ✅ Map category to table name (prevents SQL injection)
$allowed_tables = [
    'scholars'     => 'scholars',
    'beneficiaries' => 'beneficiaries',
    'donations'    => 'donations',
    // add more as needed
];

if (!array_key_exists($category, $allowed_tables)) {
    echo json_encode(["message" => "Invalid category: $category"]);
    exit;
}

$table = $allowed_tables[$category];

// ✅ Query with prepared statement
$sql  = "SELECT * FROM `$table` WHERE created_at BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["message" => "Query preparation failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["message" => "No records found."]);
    exit;
}

// ✅ Fetch all rows as associative array
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
exit;
?>
