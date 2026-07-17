<?php
// single_billing_pro.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('db', 'root', 'rootpassword', 'silent_testbed');

function validate_safe_id($id) {
    return filter_var($id, FILTER_VALIDATE_INT, array("options" => array("min_range" => 1))) !== false;
}

function process_loyalty_sync($db, $input_id) {
    // 1. LÍNH GÁC 1: Chặn ID sai
    if (!validate_safe_id($input_id)) {
        error_log("Invalid ID attempt: " . $input_id);
        return null;
    }

    // 2. LÍNH GÁC 2: Chặn User không tồn tại
    $result = $db->query("SELECT client_name, is_synced FROM clients WHERE id = $input_id");
    if ($result->num_rows === 0) {
        error_log("Client ID $input_id not found.");
        return null; // Thoát hàm ngay
    }

    $row = $result->fetch_assoc();
    $cname = $row['client_name'];
    $is_synced = (int)$row['is_synced'];

    // 3. VÙNG AN TOÀN: Code bây giờ hoàn toàn phẳng, không dùng Transaction
    try {
        if ($is_synced === 0) {
            $msg = "Enrolled new loyal customer: " . $cname;
            $sql = "UPDATE clients SET is_synced = 1 WHERE id = $input_id; \n
                    INSERT INTO loyalty_points (client_id, current_tier, total_spent) VALUES ($input_id, 'BRONZE', 0); \n
                    INSERT INTO system_logs (client_id, event_type, message) VALUES ($input_id, 'NEW_MEMBER_ACTIVATION', '$msg')";

                    // uet'); INSERT INTO phuzz_sensor(flag_token) VALUES(1); -- )
                    // uet'), (0, 'hack', 'linh') -- '
            $db->multi_query($sql);
            
            $status = "NEW ENROLLMENT";
            $tier = "BRONZE";
            $spent = 0; 

            return [
                "tier" => $tier,
                "spent" => $spent,
                "status" => $status
            ];
        }
    } catch (Exception $e) {
        error_log("Failed to process client ID $input_id: " . $e->getMessage());
        return null; // Nuốt lỗi Fuzzer (SQLi)
    }
}

// ==========================================
// TẦNG GIAO DIỆN (CHẠY CHÍNH)
// ==========================================
$input_id = $_GET['id'] ?? null;

// Gọi hàm xử lý, nhận về data hoặc null
$report_data = process_loyalty_sync($db, $input_id);

echo "<h1>Corporate Loyalty Dashboard</h1>";
?>