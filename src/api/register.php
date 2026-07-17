<?php
// register.php - The Source
// Kích hoạt chế độ ném ngoại lệ nghiêm ngặt của MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Kết nối đến cơ sở dữ liệu thực nghiệm
$db = new  mysqli('db', 'root', 'rootpassword', 'silent_testbed');

// Nhận dữ liệu đầu vào (Payload sẽ được fuzzer tiêm vào đây)
$raw_client_name = $_POST['client_name'] ?? '';

// Defense Layer 1: Application-level Data Truncation
// Cắt chuỗi để khớp với giới hạn VARCHAR(100) của schema, tránh lỗi "Data too long"
if (strlen($raw_client_name) > 100) {
    $raw_client_name = substr($raw_client_name, 0, 100);
}

// Defense Layer 2: Parameterized Prepared Statement
// Sử dụng Prepare Statement để ngăn chặn SQLi truyền thống ngay tại Source
$stmt = $db->prepare("INSERT INTO clients (client_name, registered_at) VALUES (?, NOW())");
$stmt->bind_param("s", $raw_client_name);

// them system log prepared statement
$log_stmt = $db->prepare("INSERT INTO system_logs (client_id, event_type, message) VALUES (LAST_INSERT_ID(), 'NEW_CLIENT_REGISTERED', ?)");
$log_message = "New client registered: " . $raw_client_name;
$log_stmt->bind_param("s", $log_message);

try {
    $stmt->execute();
    $log_stmt->execute();
    // Luôn trả về HTTP 200 OK nếu lưu thành công chuỗi payload
    echo "Client registered successfully! (HTTP 200 OK)";
} catch (Exception $e) {
    // Nuốt lỗi và chỉ ghi log cục bộ (Exception-Free đối với Fuzzer)
    error_log("System error: " . $e->getMessage());
}
?>