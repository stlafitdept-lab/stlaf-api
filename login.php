<?php
/**
 * file: login.php
 * author: Iya
 * date: June 25, 2026
 * purpose: Validates employee credentials and issues sessions/access context configurations based on organizational roles.
 */

// 1. Force explicit JSON headers instantly
header("Content-Type: application/json; charset=UTF-8");

// 2. Safely capture the incoming Origin domain
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$cleanOrigin = rtrim($origin, '/');

$allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://192.168.100.38:5173',
    'http://192.168.137.1:5173',
];

// 3. CRITICAL CORS FIX: Never fallback to '*' when credentials are required.
// Always mirror the valid origin back, or echo your primary local origin as a safe fallback.
if (in_array($cleanOrigin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: " . $origin);
} else {
    header("Access-Control-Allow-Origin: http://localhost:5173"); 
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle CORS Preflight checks immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

/**
 * Password checker
 * Supports hashed password and plain text password
 */
function verifyUserPassword($inputPassword, $storedPassword)
{
    if ($storedPassword === null || $storedPassword === '') {
        return false;
    }

    $passwordInfo = password_get_info($storedPassword);

    // Hashed password
    if (!empty($passwordInfo['algo'])) {
        return password_verify($inputPassword, $storedPassword);
    }

    // Plain text password
    return hash_equals((string)$storedPassword, (string)$inputPassword);
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request body'
    ]);
    exit();
}

// Inputs
$username   = trim($data['username'] ?? '');
$department = trim($data['department'] ?? '');
$password   = (string)($data['password'] ?? '');
$role       = strtolower(trim($data['role'] ?? ''));

// Basic validation
if ($role === '' || $password === '') {
    echo json_encode([
        'success' => false,
        'message' => 'All fields are required.'
    ]);
    exit();
}

if ($role === 'approver') {
    if ($department === '' && $username === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Department is required.'
        ]);
        exit();
    }
} elseif (in_array($role, ['employee', 'superadmin'], true)) {
    if ($username === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Username is required.'
        ]);
        exit();
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role selected.'
    ]);
    exit();
}

// DB Connection
$host   = 'bchbyrvggka3okcjwmwv-mysql.services.clever-cloud.com';
$dbname = 'bchbyrvggka3okcjwmwv';
$dbuser = 'usdkgqrlhm5iiwtk';
$dbpass = 'dKzvf9Ns0GxUH041q5Hd';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $dbuser,
        $dbpass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);
    exit();
}

$user = null;

// ==========================================
// EMPLOYEE LOGIN
// ==========================================
if ($role === 'employee') {
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE TRIM(id_number) = TRIM(?)
        AND LOWER(TRIM(role)) IN ('employee', 'requestor/employee', 'requestor')
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
        exit();
    }

    if (!verifyUserPassword($password, $user['password'] ?? '')) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid password.'
        ]);
        exit();
    }
}

// ==========================================
// APPROVER LOGIN
// ==========================================
elseif ($role === 'approver') {
    $loginDepartment = trim($department !== '' ? $department : $username);

    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE LOWER(TRIM(department)) = LOWER(TRIM(?))
        AND (
            LOWER(TRIM(role)) = 'approver'
            OR LOWER(TRIM(role)) LIKE '%approver%'
        )
    ");
    $stmt->execute([$loginDepartment]);
    $approvers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$approvers || count($approvers) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No approver found for department: ' . $loginDepartment
        ]);
        exit();
    }

    foreach ($approvers as $approver) {
        if (verifyUserPassword($password, $approver['password'] ?? '')) {
            $user = $approver;
            break;
        }
    }

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid password.'
        ]);
        exit();
    }
}

// ==========================================
// SUPERADMIN LOGIN
// ==========================================
elseif ($role === 'superadmin') {
    $stmt = $pdo->prepare("
        SELECT * FROM users
        WHERE TRIM(username) = TRIM(?)
        AND LOWER(TRIM(role)) = 'superadmin'
        LIMIT 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found.'
        ]);
        exit();
    }

    if (!verifyUserPassword($password, $user['password'] ?? '')) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid password.'
        ]);
        exit();
    }
}

// ==========================================
// SUCCESS RESPONSE
// ==========================================
if ($user) {
    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id_number'  => $user['id_number'] ?? '',
            'username'   => $user['username'] ?? '',
            'name'       => $user['name'] ?? '',
            'role'       => $user['role'] ?? '',
            'department' => $user['department'] ?? '',
            'position'   => $user['position'] ?? ''
        ]
    ]);
    exit();
}
