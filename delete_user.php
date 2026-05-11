<?php
// 1. Force JSON and CORS Headers
header("Content-Type: application/json; charset=utf8mb4");
header("Access-Control-Allow-Origin: *");  
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit; 
}

include 'db_config.php';

// 2. Database Credentials (Clever Cloud)
$host   = 'bchbyrvggka3okcjwmwv-mysql.services.clever-cloud.com';
$dbname = 'bchbyrvggka3okcjwmwv';
$dbuser = 'usdkgqrlhm5iiwtk';
$dbpass = 'dKzvf9Ns0GxUH041q5Hd';

// 3. FIX: Gamitin ang tamang variables ($dbuser at $dbpass)
$conn = new mysqli($host, $dbuser, $dbpass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database Connection Failed"]);
    exit;
}

// 4. Get the ID from JSON body or POST
$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? $data['id'] : (isset($_POST['id']) ? $_POST['id'] : null);

if (!$id) {
    echo json_encode(["success" => false, "error" => "Missing User ID"]);
    exit;
}

try {
    // 5. Prepare and Execute Delete
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true, "message" => "User deleted successfully"]);
        } else {
            echo json_encode(["success" => false, "error" => "User not found or already deleted"]);
        }
    } else {
        throw new Exception($stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>