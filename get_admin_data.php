<?php
header('Content-Type: application/json; charset=utf8mb4');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(); }

$host   = 'bchbyrvggka3okcjwmwv-mysql.services.clever-cloud.com';
$dbname = 'bchbyrvggka3okcjwmwv';
$dbuser = 'usdkgqrlhm5iiwtk';
$dbpass = 'dKzvf9Ns0GxUH041q5Hd';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["success" => false, "error" => "DB Connection failed"]);
    exit();
}

$type = $_GET['type'] ?? 'manage-users';
$search = trim($_GET['search'] ?? '');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = $_GET['month'] ?? 'all';

$response = ["stats" => ["total_users" => 0, "total_filed" => 0], "data" => []];

try {
    // 1. STATS (Always Fetch)
    $stmt1 = $conn->query("SELECT COUNT(*) as total FROM users");
    $response['stats']['total_users'] = (int)$stmt1->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt2 = $conn->query("SELECT ((SELECT COUNT(*) FROM leaves) + (SELECT COUNT(*) FROM overtimes)) as total");
    $response['stats']['total_filed'] = (int)$stmt2->fetch(PDO::FETCH_ASSOC)['total'];

    $searchTerm = "%$search%";

    // 2. DATA FETCHING LOGIC
    if ($type === 'manage-users') {
        $stmt = $conn->prepare("SELECT id, id_number, name, department, position, role FROM users WHERE name LIKE ? OR id_number LIKE ? ORDER BY name ASC");
        $stmt->execute([$searchTerm, $searchTerm]);
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'all-leaves') {
        $query = "SELECT l.*, u.position FROM leaves l LEFT JOIN users u ON l.employeeName = u.name WHERE (l.employeeName LIKE :s OR l.department LIKE :s)";
        $params = [':s' => $searchTerm];

        if ($year != 0) { $query .= " AND YEAR(l.start_date) = :y"; $params[':y'] = $year; }
        if ($month !== 'all') { $query .= " AND MONTH(l.start_date) = :m"; $params[':m'] = (int)$month; }
        
        $stmt = $conn->prepare($query . " ORDER BY l.id DESC");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'all-overtime') {
        $query = "SELECT o.*, u.position FROM overtimes o LEFT JOIN users u ON o.employeeName = u.name WHERE (o.employeeName LIKE :s OR o.department LIKE :s)";
        $params = [':s' => $searchTerm];

        if ($year != 0) { $query .= " AND YEAR(o.ot_date) = :y"; $params[':y'] = $year; }
        if ($month !== 'all') { $query .= " AND MONTH(o.ot_date) = :m"; $params[':m'] = (int)$month; }

        $stmt = $conn->prepare($query . " ORDER BY o.id DESC");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } elseif ($type === 'all-ob') {
        $query = "SELECT ob.*, u.name as employeeName, u.department, u.position FROM ob_logs ob LEFT JOIN users u ON u.id_number = ob.employee_id WHERE (u.name LIKE :s OR u.department LIKE :s)";
        $params = [':s' => $searchTerm];

        if ($year != 0) { $query .= " AND YEAR(ob.date) = :y"; $params[':y'] = $year; }
        if ($month !== 'all') { $query .= " AND MONTH(ob.date) = :m"; $params[':m'] = (int)$month; }

        $stmt = $conn->prepare($query . " ORDER BY ob.date DESC");
        $stmt->execute($params);
        $response['data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($response);

} catch(Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>