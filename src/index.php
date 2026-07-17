<?php
// Central Router, Helper APIs, and Interactive Dashboard UI

// Redirect PHP error logs to a local file in the workspace directory (src/php_errors.log)
ini_set('error_log', '/var/www/html/php_errors.log');

// Disable buggy PDO query hooks from uopz fuzzer instrumentation to prevent local dashboard crashes
if (function_exists('uopz_unset_return')) {
    try {
        uopz_unset_return('PDO', 'query');
    } catch (\Throwable $t) {}
}

// Define a fallback mock for the fuzzer's auto-prepended instrumentation to prevent crashes
if (!function_exists('__fuzzer_rewrite_select_query')) {
    function __fuzzer_rewrite_select_query($query) {
        return $query;
    }
}

// 1. Dynamic Routing to Vulnerable APIs
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($request_path === '/register') {
    require_once __DIR__ . '/api/register.php';
    exit;
}

if ($request_path === '/billing') {
    require_once __DIR__ . '/api/billing.php';
    exit;
}

// 2. Helper APIs for the Dashboard UI
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // Status Monitor API
    if ($_GET['api'] === 'sensor_status') {
        try {
            require_once __DIR__ . '/config/database.php';
            
            // Enable events_statements_history_long consumer dynamically
            try {
                $pdo->exec("UPDATE performance_schema.setup_consumers SET ENABLED = 'YES' WHERE NAME = 'events_statements_history_long';");
            } catch (\Exception $pe) {}
            
            // Fetch Sensor Tables State
            $phuzz_sensor = $pdo->query("SELECT * FROM phuzz_sensor LIMIT 1")->fetch();
            $is_hacked = ($phuzz_sensor && $phuzz_sensor['flag_token'] !== 'clear');
            
            $insert_sensor = $is_hacked ? [['id' => 1]] : [];
            $update_sensor = $is_hacked ? [['canary' => 'hacked']] : [['canary' => 'safe']];
            $delete_sensor = $is_hacked ? [] : [['id' => 1]];
            
            // Read MySQL General Query Log
            $log_lines = [];
            $log_path = '/var/log/mysql/general.log';
            if (file_exists($log_path) && is_readable($log_path)) {
                $lines = file($log_path);
                if ($lines) {
                    // Query performance_schema to extract recently executed queries and their results
                    $status_map = [];
                    try {
                        $perf_stmt = $pdo->query("SELECT SQL_TEXT, MYSQL_ERRNO, MESSAGE_TEXT, ROWS_AFFECTED, ERRORS 
                                                  FROM performance_schema.events_statements_history_long 
                                                  WHERE SQL_TEXT IS NOT NULL 
                                                  ORDER BY TIMER_START DESC 
                                                  LIMIT 200");
                        if ($perf_stmt) {
                            $perf_rows = $perf_stmt->fetchAll();
                            foreach ($perf_rows as $row) {
                                $norm_sql = trim(strtolower($row['SQL_TEXT']));
                                if (!isset($status_map[$norm_sql])) {
                                    $status_map[$norm_sql] = $row;
                                }
                            }
                        }
                    } catch (\Exception $pe) {
                        // Suppress if Performance Schema is not fully populated yet
                    }

                    $filtered_lines = [];
                    foreach ($lines as $line) {
                        $lower_line = strtolower($line);
                        
                        // Strict Whitelist: only log queries on tables used by endpoints
                        $is_endpoint_query = (
                            strpos($lower_line, 'clients') !== false ||
                            strpos($lower_line, 'loyalty_points') !== false ||
                            strpos($lower_line, 'system_logs') !== false
                        );
                        
                        // Exclude healthcheck SELECT 1 or internal fuzzer queries on performance_schema
                        if (strpos($lower_line, 'select 1') !== false || 
                            strpos($lower_line, 'version_comment') !== false || 
                            strpos($lower_line, 'performance_schema') !== false) {
                            $is_endpoint_query = false;
                        }
                        
                        if (!$is_endpoint_query) {
                            continue;
                        }
                        
                        // Try to correlate if this line is a Query
                        $query_part = '';
                        if (preg_match('/Query\s+(.+)$/i', $line, $matches)) {
                            $query_part = $matches[1];
                        }
                        
                        if ($query_part !== '') {
                            $clean_query = trim(strtolower($query_part));
                            $status_suffix = "";
                            
                            foreach ($status_map as $err_sql => $row) {
                                if (strpos($clean_query, $err_sql) !== false || strpos($err_sql, $clean_query) !== false) {
                                    if ($row['MYSQL_ERRNO'] > 0) {
                                        $status_suffix = " | [STATUS: FAILED - " . $row['MESSAGE_TEXT'] . "]";
                                    } else {
                                        $status_suffix = " | [STATUS: SUCCESS - " . $row['ROWS_AFFECTED'] . " rows affected]";
                                    }
                                    break;
                                }
                            }
                            if ($status_suffix !== "") {
                                $line = trim($line) . $status_suffix;
                            }
                        }
                        
                        $filtered_lines[] = $line;
                    }
                    // Extract last 25 lines of clean logs
                    $log_lines = array_slice($filtered_lines, -25);
                } else {
                    $log_lines = ["[Empty Log] Database is running but no queries executed yet."];
                }
            } else {
                $log_lines = ["MySQL general log is not readable or not generated yet."];
            }
            
            echo json_encode([
                "status" => "success",
                "sensors" => [
                    "insert" => $insert_sensor,
                    "update" => $update_sensor,
                    "delete" => $delete_sensor
                ],
                "business_data" => [
                    "users" => []
                ],
                "mysql_log" => array_map('trim', $log_lines)
            ]);
        } catch (\Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
    
    // Reset Database state API
    if ($_GET['api'] === 'reset_db') {
        try {
            require_once __DIR__ . '/config/database.php';
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $pdo->exec("DROP TABLE IF EXISTS loyalty_points;");
            $pdo->exec("DROP TABLE IF EXISTS system_logs;");
            $pdo->exec("DROP TABLE IF EXISTS phuzz_sensor;");
            $pdo->exec("DROP TABLE IF EXISTS clients;");
            $pdo->exec("DROP TABLE IF EXISTS users;");
            
            $pdo->exec("CREATE TABLE clients (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_name VARCHAR(100) NOT NULL,
                registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_synced TINYINT(1) DEFAULT 0
            ) ENGINE=InnoDB;");
 
            $pdo->exec("CREATE TABLE loyalty_points (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                current_tier VARCHAR(20) DEFAULT 'BRONZE',
                total_spent INT DEFAULT 0
            ) ENGINE=InnoDB;");
 
            $pdo->exec("CREATE TABLE system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;");
 
            $pdo->exec("CREATE TABLE phuzz_sensor (
                id INT AUTO_INCREMENT PRIMARY KEY,
                flag_token VARCHAR(50) DEFAULT 'clear'
            ) ENGINE=InnoDB;");
 
            $pdo->exec("INSERT INTO phuzz_sensor (flag_token) VALUES ('clear');");
            
            $pdo->exec("INSERT INTO clients (client_name) VALUES ('admin'), ('guest');");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            
            echo json_encode(["status" => "success", "message" => "Database successfully reset to defaults!"]);
        } catch (\Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
}
?>
