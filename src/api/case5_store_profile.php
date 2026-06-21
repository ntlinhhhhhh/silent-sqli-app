<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$username = $_REQUEST['username'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'username' is required."]);
    exit;
}

try {
    // SECURE insertion using Prepared Statement (Endpoint A)
    // The payload is stored safely, and no SQL error is generated.
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, 'p@ssword', 'user')");
    $stmt->execute(['username' => $username]);
    
    echo json_encode([
        "status" => "success",
        "message" => "Profile created successfully. Username stored in the database.",
        "username_stored" => $username
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage()
    ]);
}
