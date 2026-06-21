<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$category_id = $_REQUEST['category_id'] ?? '';

if (empty($category_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'category_id' is required."]);
    exit;
}

// Apply Case 3 filter
if (AppFilters::isMatchTypeCasting($category_id)) {
    http_response_code(403);
    echo json_encode([
        "status" => "blocked",
        "message" => "Blocked by WAF: Data type manipulation or casting functions are strictly forbidden."
    ]);
    exit;
}

try {
    // Vulnerable SQL query (Direct concatenation without quotes)
    $sql = "SELECT id, name, category_id, price FROM products WHERE category_id = " . $category_id;
    
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
