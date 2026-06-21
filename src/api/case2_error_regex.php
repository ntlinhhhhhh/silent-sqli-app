<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$id = $_REQUEST['id'] ?? '';

if (empty($id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'id' is required."]);
    exit;
}

// Apply Case 2 filter
if (AppFilters::isMatchErrorPattern($id)) {
    http_response_code(403);
    echo json_encode([
        "status" => "blocked",
        "message" => "Blocked by WAF: Malicious error-pattern (regex) detected!"
    ]);
    exit;
}

try {
    // Vulnerable SQL query (Direct concatenation without quotes)
    $sql = "SELECT id, name, category_id, price FROM products WHERE id = " . $id;
    
    // Execute query using PDO query (multi statement execution supported)
    $stmt = $pdo->query($sql);
    $results = [];
    
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
