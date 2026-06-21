<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$username = $_REQUEST['username'] ?? '';

if (empty($username)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'username' is required."]);
    exit;
}

// Apply Case 1 filter
if (!AppFilters::isBalancedQuote($username)) {
    http_response_code(403);
    echo json_encode([
        "status" => "blocked",
        "message" => "Blocked by Balanced Quote Sanitizer (Odd number of single quotes detected)"
    ]);
    exit;
}

try {
    // Vulnerable SQL query (Direct concatenation)
    $sql = "SELECT id, username, role FROM users WHERE username = '$username'";
    
    // Execute query using PDO query (multi statement execution supported)
    $stmt = $pdo->query($sql);
    $results = [];
    
    // Fetch results from the first statement
    if ($stmt) {
        $results = $stmt->fetchAll();
        $stmt->closeCursor();
    }
    
    echo json_encode([
        "status" => "success",
        "executed_sql" => $sql,
        "results_count" => count($results),
        "data" => $results
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage(),
        "executed_sql" => $sql ?? null
    ]);
}
