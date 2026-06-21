<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$sort = $_REQUEST['sort'] ?? 'id';

if (empty($sort)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'sort' is required."]);
    exit;
}

// Apply Case 6 Order By filter
if (!AppFilters::isSafeOrderBy($sort)) {
    http_response_code(403);
    echo json_encode([
        "status" => "blocked",
        "message" => "Blocked by Order By Sanitizer (Blacklisted words or single quote detected)"
    ]);
    exit;
}

try {
    // Vulnerable SQL query (Direct concatenation in ORDER BY clause)
    $sql = "SELECT id, name, category_id, price, created_at FROM products ORDER BY " . $sort;
    
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
