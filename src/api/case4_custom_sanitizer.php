<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../security/AppFilters.php';

$order_id = $_REQUEST['order_id'] ?? '';

if (empty($order_id)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Parameter 'order_id' is required."]);
    exit;
}

// Apply Case 4 custom application-level filter
if (!AppFilters::isSafeCustomSanitizer($order_id)) {
    http_response_code(403);
    echo json_encode([
        "status" => "blocked",
        "message" => "Blocked by Custom Sanitizer (Odd quotes or blacklisted keyword detected)"
    ]);
    exit;
}

try {
    // Vulnerable SQL query (Direct concatenation within quotes)
    $sql = "UPDATE orders SET status = 'pending' WHERE order_id = '$order_id'";
    
    // Execute query using PDO query (multi statement execution supported)
    $stmt = $pdo->query($sql);
    
    echo json_encode([
        "status" => "success",
        "executed_sql" => $sql,
        "message" => "Order updated successfully."
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "db_error",
        "message" => $e->getMessage(),
        "executed_sql" => $sql ?? null
    ]);
}
