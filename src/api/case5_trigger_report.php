<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    // 1. Retrieve the latest registered user (which could contain our stored payload)
    $stmt = $pdo->query("SELECT id, username FROM users ORDER BY id DESC LIMIT 1");
    $latest_user = $stmt->fetch();

    if (!$latest_user) {
        echo json_encode([
            "status" => "success",
            "message" => "No users found. Please register a profile first (Endpoint A)."
        ]);
        exit;
    }

    $username_from_db = $latest_user['username'];

    // 2. Vulnerability (Endpoint B): Direct string concatenation from database value
    $sql = "SELECT id, username, role FROM users WHERE username = '" . $username_from_db . "'";
    
    // Execute query (multi statements support enabled in database.php)
    $trigger_stmt = $pdo->query($sql);
    $results = [];
    
    if ($trigger_stmt) {
        $results = $trigger_stmt->fetchAll();
        $trigger_stmt->closeCursor();
    }

    echo json_encode([
        "status" => "success",
        "message" => "Second-order report query triggered successfully.",
        "retrieved_username" => $username_from_db,
        "executed_sql" => $sql,
        "results_count" => count($results)
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage(),
        "executed_sql" => $sql ?? null
    ]);
}
