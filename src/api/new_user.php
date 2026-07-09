<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$username = $_REQUEST['username'] ?? '';
$bio = $_REQUEST['bio'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'username' is required."]);
    exit;
}

// Validate username regex (alphanumeric and underscore, 3 to 100 characters)
if (!preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Username must be alphanumeric and between 3-100 characters."]);
    exit;
}

if (empty($bio)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'bio' is required."]);
    exit;
}

$sanitized_bio = AppFilters::sanitizeBio($bio);

if (empty($sanitized_bio)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Bio is invalid or empty after sanitization."]);
    exit;
}

try {
    // SECURE insertion using Prepared Statement (Endpoint A)
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, bio) VALUES (:username, 'p@ssword', 'user', :bio)");
    $stmt->execute(['username' => $username, 'bio' => $sanitized_bio]);
    
    echo json_encode([
        "status" => "success",
        "message" => "Profile created successfully. Username and Bio stored securely.",
        "username_stored" => $username,
        "bio_stored" => $sanitized_bio
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage()
    ]);
}
