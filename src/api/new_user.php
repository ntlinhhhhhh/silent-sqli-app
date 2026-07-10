<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../security/AppFilters.php';

    $username = $_REQUEST['username'] ?? '';
    $bio = $_REQUEST['bio'] ?? '';

    if (empty($username)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
        exit;
    }

    // Validate username regex (alphanumeric and underscore, 3 to 100 characters)
    if (!preg_match('/^[a-zA-Z0-9_]{3,100}$/', $username)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
        exit;
    }

    if (empty($bio)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
        exit;
    }

    $sanitized_bio = AppFilters::sanitizeBio($bio);

    if (empty($sanitized_bio)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid request parameters."]);
        exit;
    }

    // SECURE insertion using Prepared Statement (Endpoint A)
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, bio) VALUES (:username, 'p@ssword', 'user', :bio)");
    $stmt->execute(['username' => $username, 'bio' => $sanitized_bio]);
    
    echo json_encode([
        "status" => "success",
        "message" => "Request processed successfully.",
        "username_stored" => $username,
        "bio_stored" => $sanitized_bio
    ]);
} catch (\Throwable $e) {
    error_log("Error in new_user.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Request failed to process."
    ]);
}