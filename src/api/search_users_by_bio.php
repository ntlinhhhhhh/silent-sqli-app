<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    // 1. Retrieve the latest registered user (which could contain our stored payload)
    $stmt = $pdo->query("SELECT id, username, bio FROM users ORDER BY id DESC LIMIT 1");
    $latest_user = $stmt->fetch();

    if (!$latest_user) {
        echo json_encode([
            "status" => "success",
            "message" => "No users found. Please register a profile first (Endpoint A)."
        ]);
        exit;
    }

    $bio_from_db = $latest_user['bio'] ?? '';

    // 2. VULNERABLE (Endpoint B): SQL Query executed using raw string concatenation via LIKE
    $sql = "SELECT id, username, role, bio FROM users WHERE bio LIKE '%$bio_from_db%'";
    
    // Execute query insecurely
    $trigger_stmt = $pdo->query($sql);
    $results = [];
    
    if ($trigger_stmt) {
        $results = $trigger_stmt->fetchAll();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Second-order search query triggered using retrieved bio.",
        "retrieved_bio" => $bio_from_db,
        "executed_sql" => $sql,
        "results_count" => count($results)
    ]);
} catch (\PDOException $e) {
    // Suppress SQL errors to hide them from Error-based fuzzers (Fuzzer 1)
    // We return HTTP 200 OK and a generic success response instead of HTTP 500
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Second-order search query processed.",
        "results_count" => 0
    ]);
}
