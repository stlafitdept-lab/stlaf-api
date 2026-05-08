<?php
include 'cors.php';
include 'db_config.php';

$host   = 'bchbyrvggka3okcjwmwv-mysql.services.clever-cloud.com';
$dbname = 'bchbyrvggka3okcjwmwv';
$dbuser = 'usdkgqrlhm5iiwtk';
$dbpass = 'dKzvf9Ns0GxUH041q5Hd';

try {
    // FIX: Siniguro na tugma ang variable names ($dbname, $dbuser, $dbpass)
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $exception) {
    echo json_encode(["error" => "Connection error: " . $exception->getMessage()]);
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$role = isset($_GET['role']) ? strtolower($_GET['role']) : null; 
$dept = isset($_GET['department']) ? $_GET['department'] : (isset($_GET['dept']) ? $_GET['dept'] : null);
$year = isset($_GET['year']) ? $_GET['year'] : date("Y");

$response = [];

if ($role === 'approver') {
    // 1. Total Pending (Leaves + OT)
    $stmt1 = $conn->prepare("
        SELECT 
            ((SELECT COUNT(*) FROM leaves WHERE department = :dept AND status = 'Pending') +
             (SELECT COUNT(*) FROM overtimes WHERE department = :dept AND status = 'Pending')) 
        as total_pending");
    $stmt1->execute(['dept' => $dept]);
    $res1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    // 2. Total Processed
    $stmt2 = $conn->prepare("
        SELECT 
            ((SELECT COUNT(*) FROM leaves WHERE department = :dept AND status IN ('Approved', 'Rejected') AND YEAR(date_filed) = :year) +
             (SELECT COUNT(*) FROM overtimes WHERE department = :dept AND status IN ('Approved', 'Rejected') AND YEAR(ot_date) = :year)) 
        as total_processed");
    $stmt2->execute(['dept' => $dept, 'year' => $year]);
    $res2 = $stmt2->fetch(PDO::FETCH_ASSOC);

    $response = [
        "total_pending" => (int)($res1['total_pending'] ?? 0),
        "total_processed" => (int)($res2['total_processed'] ?? 0)
    ];

} else if ($role === 'employee') {
    $stmt1 = $conn->prepare("
        SELECT 
            ((SELECT COUNT(*) FROM leaves WHERE employeeId = :id AND YEAR(date_filed) = :year) +
             (SELECT COUNT(*) FROM overtimes WHERE employeeId = :id AND YEAR(ot_date) = :year)) 
        as total_requests");
    $stmt1->execute(['id' => $id, 'year' => $year]);
    $res1 = $stmt1->fetch(PDO::FETCH_ASSOC);

    $stmt2 = $conn->prepare("SELECT SUM(DATEDIFF(end_date, start_date) + 1) as used FROM leaves WHERE employeeId = :id AND status = 'Approved' AND YEAR(date_filed) = :year");
    $stmt2->execute(['id' => $id, 'year' => $year]);
    $res2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    $balance = 15 - ($res2['used'] ?? 0);

    $response = [
        "total_requests" => (int)($res1['total_requests'] ?? 0),
        "leave_credits" => (int)$balance
    ];
}

echo json_encode($response);
?>