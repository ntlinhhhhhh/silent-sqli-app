<?php
// Central Router, Helper APIs, and Interactive Dashboard UI

// 1. Dynamic Routing to Vulnerable APIs
if (isset($_GET['case'])) {
    $case = $_GET['case'];
    switch ($case) {
        case '1':
            require_once __DIR__ . '/api/case1_balanced_quote.php';
            break;
        case '2':
            require_once __DIR__ . '/api/case2_error_regex.php';
            break;
        case '3':
            require_once __DIR__ . '/api/case3_type_casting.php';
            break;
        case '4':
            require_once __DIR__ . '/api/case4_custom_sanitizer.php';
            break;
        case '5a':
            require_once __DIR__ . '/api/case5_store_profile.php';
            break;
        case '5b':
            require_once __DIR__ . '/api/case5_trigger_report.php';
            break;
        case '6':
            require_once __DIR__ . '/api/case6_semantic_orderby.php';
            break;
        default:
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Case not found."]);
    }
    exit;
}

// 2. Helper APIs for the Dashboard UI
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    // Status Monitor API
    if ($_GET['api'] === 'sensor_status') {
        try {
            require_once __DIR__ . '/config/database.php';
            
            // Fetch Sensor Tables State
            $probe = $pdo->query("SELECT * FROM __phuzz_probe")->fetchAll();
            $update_sensor = $pdo->query("SELECT * FROM __phuzz_update_sensor")->fetchAll();
            $phuzz_sensor = $pdo->query("SELECT * FROM phuzz_sensor")->fetchAll();
            
            // Fetch Normal Tables State for context
            $users = $pdo->query("SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
            $orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5")->fetchAll();
            
            // Read MySQL General Query Log
            $log_lines = [];
            $log_path = '/var/log/mysql/general.log';
            if (file_exists($log_path) && is_readable($log_path)) {
                $lines = file($log_path);
                if ($lines) {
                    // Extract last 25 lines
                    $log_lines = array_slice($lines, -25);
                } else {
                    $log_lines = ["[Empty Log] Database is running but no queries executed yet."];
                }
            } else {
                $log_lines = ["MySQL general log is not readable or not generated yet. (Make sure db service is healthy and log volume is mounted properly)"];
            }
            
            echo json_encode([
                "status" => "success",
                "sensors" => [
                    "probe" => $probe,
                    "update_sensor" => $update_sensor,
                    "phuzz_sensor" => $phuzz_sensor
                ],
                "business_data" => [
                    "users" => $users,
                    "orders" => $orders
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
            $pdo->exec("TRUNCATE TABLE __phuzz_probe;");
            $pdo->exec("TRUNCATE TABLE __phuzz_update_sensor;");
            $pdo->exec("INSERT INTO __phuzz_update_sensor (id, canary, canary_value) VALUES (1, 'safe', 'safe');");
            $pdo->exec("TRUNCATE TABLE phuzz_sensor;");
            $pdo->exec("INSERT INTO phuzz_sensor (id, flag, marker) VALUES (1, 0, NULL);");
            $pdo->exec("TRUNCATE TABLE users;");
            $pdo->exec("INSERT INTO users (username, password, role) VALUES 
                ('admin', 'p@ssword', 'admin'), 
                ('guest', '12345', 'user');");
            $pdo->exec("TRUNCATE TABLE orders;");
            $pdo->exec("INSERT INTO orders (order_id, status) VALUES ('ORD-001', 'pending');");
            $pdo->exec("TRUNCATE TABLE products;");
            $pdo->exec("INSERT INTO products (name, category_id, price) VALUES 
                ('iPhone 15', 1, 1000), 
                ('ThinkPad X1', 1, 1500), 
                ('AirPods', 2, 200);");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            
            echo json_encode(["status" => "success", "message" => "Database successfully reset to defaults!"]);
        } catch (\Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Silent-SQLi Bypass Simulator</title>
    
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Code Highlight CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
    
    <style>
        /* Modern Glassmorphic Sleek Dark Style - Fixed Height Layout */
        :root {
            --bg-color: #0b0f19;
            --accent-primary: #3b82f6; /* Modern Blue */
            --accent-success: #10b981; /* Emerald Green */
            --accent-warning: #f59e0b; /* Amber */
            --accent-danger: #ef4444; /* Rose Red */
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --card-bg: rgba(30, 41, 59, 0.45);
            --card-border: rgba(255, 255, 255, 0.08);
            --card-glow: rgba(59, 130, 246, 0.06);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(59, 130, 246, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.08) 0%, transparent 45%);
            background-attachment: fixed;
            color: var(--text-main);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden; /* Prevent overall page scrolling */
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 2rem;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--card-border);
            height: 60px;
            z-index: 100;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .logo-icon {
            width: 1.8rem;
            height: 1.8rem;
            background: linear-gradient(135deg, var(--accent-primary), var(--accent-success));
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: #fff;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }

        .logo-title {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #fff, var(--text-muted));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-badge {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent-primary);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        button.btn-reset {
            background: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 500;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        button.btn-reset:hover {
            background: var(--accent-danger);
            color: #fff;
            border-color: var(--accent-danger);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
        }

        .app-container {
            flex: 1;
            display: grid;
            grid-template-columns: 260px 1fr 340px;
            gap: 1rem;
            padding: 1rem;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Sidebar - Navigation Cases */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            height: 100%;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin: 0.25rem 0.25rem;
        }

        .nav-item {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .nav-item:hover {
            background: rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.25);
            transform: translateX(2px);
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(16, 185, 129, 0.04));
            border-color: var(--accent-primary);
            box-shadow: 0 0 12px var(--card-glow);
        }

        .nav-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-item-num {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--accent-primary);
            background: rgba(59, 130, 246, 0.12);
            padding: 0.05rem 0.3rem;
            border-radius: 3px;
        }

        .nav-item.active .nav-item-num {
            background: var(--accent-primary);
            color: #fff;
        }

        .nav-item-title {
            font-weight: 600;
            font-size: 0.85rem;
            color: #fff;
        }

        .nav-item-desc {
            font-size: 0.7rem;
            color: var(--text-muted);
            line-height: 1.2;
        }

        /* Center Content - Detailed Test Case & Sandbox */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 100%;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .panel {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            backdrop-filter: blur(8px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 0.6rem;
            margin-bottom: 0.6rem;
        }

        .panel-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .panel-tag {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.15);
            color: var(--accent-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .case-description {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 0.8rem;
        }

        /* Tabs inside panel */
        .case-5-tabs {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.8rem;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 0.4rem;
        }

        .sub-tab {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid transparent;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .sub-tab:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .sub-tab.active {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: var(--accent-primary);
        }

        /* Code Block Container (Compact) */
        .code-container {
            margin-bottom: 0.8rem;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .code-header {
            background: rgba(15, 23, 42, 0.8);
            padding: 0.3rem 0.8rem;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .code-container pre {
            margin: 0 !important;
            border-radius: 0 !important;
            background: rgba(15, 23, 42, 0.4) !important;
            max-height: 120px; /* Reduced from 250px to fit monitor */
            overflow-y: auto;
        }

        .code-container code {
            font-family: 'Fira Code', monospace;
            font-size: 0.75rem !important;
        }

        /* Form Controls */
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            margin-bottom: 0.8rem;
        }

        .input-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        .input-field {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 6px;
            padding: 0.5rem 0.8rem;
            color: #fff;
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem;
            width: 100%;
            transition: all 0.2s;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.2);
            background: rgba(15, 23, 42, 0.9);
        }

        /* Preset Buttons */
        .preset-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }

        .preset-btn {
            font-family: inherit;
            font-size: 0.7rem;
            font-weight: 500;
            padding: 0.3rem 0.6rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .preset-p1 {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
        }

        .preset-p1:hover {
            background: rgba(239, 68, 68, 0.18);
            border-color: rgba(239, 68, 68, 0.4);
        }

        .preset-p2 {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #a7f3d0;
        }

        .preset-p2:hover {
            background: rgba(16, 185, 129, 0.18);
            border-color: rgba(16, 185, 129, 0.4);
        }

        .action-row {
            display: flex;
            justify-content: flex-end;
        }

        .btn-submit {
            background: var(--accent-primary);
            color: #fff;
            border: none;
            padding: 0.5rem 1.2rem;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 3px 8px rgba(59, 130, 246, 0.25);
        }

        .btn-submit:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
        }

        /* HTTP Response Panel (Compact & Independent Scroll) */
        .response-panel {
            margin-top: 0.5rem;
            display: none;
        }

        .response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }

        .status-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
        }

        .status-200 { background: rgba(16, 185, 129, 0.15); color: var(--accent-success); border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-403 { background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-500 { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-other { background: rgba(148, 163, 184, 0.15); color: var(--text-muted); border: 1px solid rgba(148, 163, 184, 0.3); }

        .response-body pre {
            background: rgba(15, 23, 42, 0.75) !important;
            border: 1px solid var(--card-border);
            border-radius: 6px;
            max-height: 130px; /* Reduced from 250px to make page compact */
            overflow-y: auto;
        }

        /* Sidebar Right - Real-Time Monitor (Fixed Position column) */
        .monitor-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 100%;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .monitor-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 0.8rem;
        }

        .monitor-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.3rem;
        }

        .monitor-card-title {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .sensor-indicator {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--text-muted);
            position: relative;
        }

        .sensor-indicator.triggered {
            background: var(--accent-success);
            box-shadow: 0 0 6px var(--accent-success);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.6; }
            100% { transform: scale(1); opacity: 1; }
        }

        .sensor-value-box {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 5px;
            padding: 0.45rem 0.65rem;
            font-family: 'Fira Code', monospace;
            font-size: 0.7rem;
            margin-bottom: 0.35rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sensor-label {
            color: var(--text-muted);
        }

        .sensor-val {
            color: #38bdf8;
            font-weight: 600;
        }

        .sensor-val.hacked {
            color: var(--accent-success);
            font-weight: bold;
        }

        /* Terminal Query Log Area (Grown to fit remaining height) */
        .terminal-log {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #05070f;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            padding: 0.7rem;
            min-height: 180px;
        }

        .terminal-header {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 0.3rem;
        }

        .terminal-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            display: inline-block;
        }
        .td-red { background: var(--accent-danger); }
        .td-yellow { background: var(--accent-warning); }
        .td-green { background: var(--accent-success); }

        .terminal-body {
            flex: 1;
            font-family: 'Fira Code', monospace;
            font-size: 0.68rem;
            color: #a7f3d0;
            overflow-y: auto;
            line-height: 1.35;
            max-height: 280px;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .terminal-line {
            margin-bottom: 0.3rem;
            border-left: 2px solid transparent;
            padding-left: 0.3rem;
        }

        .terminal-line.sql-query {
            border-color: var(--accent-primary);
            color: #e2e8f0;
        }
        
        .terminal-line.sql-trigger {
            border-color: var(--accent-success);
            color: #34d399;
            background: rgba(16, 185, 129, 0.04);
        }

        .terminal-line.system {
            color: var(--text-muted);
        }

        footer {
            text-align: center;
            padding: 0.5rem;
            font-size: 0.75rem;
            color: var(--text-muted);
            border-top: 1px solid var(--card-border);
            background: rgba(15, 23, 42, 0.4);
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .desc-highlight {
            background: rgba(59, 130, 246, 0.12);
            color: #93c5fd;
            padding: 0.05rem 0.25rem;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.85em;
        }

        /* Adjust scrollbar styles for modern look */
        ::-webkit-scrollbar {
            width: 4px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>

    <header>
        <div class="logo-area">
            <div class="logo-icon">S</div>
            <div class="logo-title">Silent-SQLi Bypass Simulator</div>
            <div class="logo-badge">WAF vs Fuzzer</div>
        </div>
        <div class="header-actions">
            <button class="btn-reset" onclick="resetDatabase()" title="Restore DB schemas and seeds">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                Reset Database
            </button>
        </div>
    </header>

    <div class="app-container">
        
        <!-- SIDEBAR: 6 CASES -->
        <div class="sidebar">
            <div class="section-title">Vulnerability Cases</div>
            
            <div class="nav-item active" onclick="selectCase('1')" id="nav-case-1">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 1</span>
                </div>
                <div class="nav-item-title">Balanced Quotes</div>
                <div class="nav-item-desc">Blocks odd single quotes, bypasses even structured quotes.</div>
            </div>

            <div class="nav-item" onclick="selectCase('2')" id="nav-case-2">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 2</span>
                </div>
                <div class="nav-item-title">Error-Pattern WAF</div>
                <div class="nav-item-desc">Blocks error-based SQL (EXTRACTVALUE), bypasses silent DML.</div>
            </div>

            <div class="nav-item" onclick="selectCase('3')" id="nav-case-3">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 3</span>
                </div>
                <div class="nav-item-title">Type Casting / XML</div>
                <div class="nav-item-desc">WAF blocks CAST/CONVERT/Hex, bypasses primitive operations.</div>
            </div>

            <div class="nav-item" onclick="selectCase('4')" id="nav-case-4">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 4</span>
                </div>
                <div class="nav-item-title">Syntax Sanitizer</div>
                <div class="nav-item-desc">Custom filter blocks odd quotes & words (UNION, SELECT, SLEEP).</div>
            </div>

            <div class="nav-item" onclick="selectCase('5')" id="nav-case-5">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 5</span>
                </div>
                <div class="nav-item-title">Second-Order SQLi</div>
                <div class="nav-item-desc">Stored safely (Endpoint A), executed dynamic-concat later (Endpoint B).</div>
            </div>

            <div class="nav-item" onclick="selectCase('6')" id="nav-case-6">
                <div class="nav-item-header">
                    <span class="nav-item-num">Case 6</span>
                </div>
                <div class="nav-item-title">Semantic ORDER BY</div>
                <div class="nav-item-desc">Blocks error/union/sleep, bypasses CASE WHEN sorting tree.</div>
            </div>
        </div>

        <!-- MAIN INTERACTIVE WORKSPACE -->
        <div class="main-content">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title" id="display-title">Case 1: Balanced Quote Sanitizer</div>
                    <span class="panel-tag" id="display-method">GET / POST</span>
                </div>

                <div class="case-description" id="display-desc">
                    Explanation will load here...
                </div>

                <!-- Endpoint switcher for Case 5 -->
                <div class="case-sub-tabs" id="case-5-tabs" style="display: none;">
                    <div class="sub-tab active" onclick="switchCase5Endpoint('5a')" id="sub-tab-5a">Endpoint A: Save Profile</div>
                    <div class="sub-tab" onclick="switchCase5Endpoint('5b')" id="sub-tab-5b">Endpoint B: Trigger Report Query</div>
                </div>

                <!-- Code View -->
                <div class="code-container">
                    <div class="code-header">
                        <span>Sanitizer & Query Code</span>
                        <span id="display-filepath">security/AppFilters.php</span>
                    </div>
                    <pre><code class="language-php" id="display-code">
// Loading code...
                    </code></pre>
                </div>

                <!-- Interactive Test Box -->
                <div class="sandbox-box">
                    <div class="input-group">
                        <label class="input-label" for="payload-input">
                            <span>SQL Injection Sandbox Input</span>
                            <span id="input-param-desc">Parameter: username</span>
                        </label>
                        <input type="text" id="payload-input" class="input-field" placeholder="Enter input payload...">
                    </div>

                    <div class="preset-row">
                        <span style="font-size:0.75rem; color:var(--text-muted); align-self:center; margin-right:0.25rem;">Preset Payloads:</span>
                        <button class="preset-btn preset-p1" id="btn-preset-1" onclick="loadPreset(1)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            Payload 1: PHUZZ gốc (Blocked)
                        </button>
                        <button class="preset-btn preset-p2" id="btn-preset-2" onclick="loadPreset(2)">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            Payload 2: SilentPHUZZ (Bypass)
                        </button>
                    </div>

                    <div class="action-row">
                        <button class="btn-submit" onclick="sendPayload()">Execute Attack</button>
                    </div>
                </div>
            </div>

            <!-- HTTP Response Output Panel -->
            <div class="panel response-panel" id="response-block">
                <div class="response-header">
                    <div style="font-weight:700; font-size:0.9rem;">HTTP Response Output</div>
                    <span id="response-status" class="status-badge status-200">200 OK</span>
                </div>
                <div class="response-body">
                    <pre><code class="language-json" id="response-json">{}</code></pre>
                </div>
            </div>
        </div>

        <!-- RIGHT SIDEBAR: REAL-TIME DB & GENERAL LOG SENSORS -->
        <div class="monitor-sidebar">
            <div class="section-title">Database Sensor Monitor</div>
            
            <!-- Sensor 1: Case 1 Probe Table -->
            <div class="monitor-card" id="sensor-card-case1">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-probe"></span>
                        Case 1: Trap Table
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Table: `__phuzz_probe`</span>
                    <span class="sensor-val" id="val-probe">0 rows</span>
                </div>
            </div>

            <!-- Sensor 2: Case 2 & 3 Update Sensor -->
            <div class="monitor-card" id="sensor-card-case23">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-updates"></span>
                        Case 2 & 3: Canary States
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Canary (Case 2):</span>
                    <span class="sensor-val" id="val-canary">safe</span>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Canary Value (Case 3):</span>
                    <span class="sensor-val" id="val-canary-value">safe</span>
                </div>
            </div>

            <!-- Sensor 3: Case 4 & 5 Flag Sensor -->
            <div class="monitor-card" id="sensor-card-case45">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-sensor-flag"></span>
                        Case 4 & 5: Custom Sensors
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Sensor Flag:</span>
                    <span class="sensor-val" id="val-sensor-flag">0</span>
                </div>
            </div>

            <!-- Terminal General Query Logs -->
            <div class="terminal-log">
                <div class="terminal-header">
                    <span class="terminal-dot td-red"></span>
                    <span class="terminal-dot td-yellow"></span>
                    <span class="terminal-dot td-green"></span>
                    <span style="margin-left: 0.5rem">MySQL General Query Log Stream</span>
                </div>
                <div class="terminal-body" id="mysql-log-stream">
                    <!-- Logs stream here -->
                    Loading MySQL Query Logs...
                </div>
            </div>
        </div>
    </div>

    <footer>
        Silent-SQLi Detection Testbed &bull; Built for Advanced Security Fuzzing Simulations &copy; 2026
    </footer>

    <!-- Highlight JS libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup-templating.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>

    <script>
        // Data containing details for each Case
        const caseDetails = {
            '1': {
                title: 'Case 1: Balanced Quote Sanitizer',
                method: 'GET / POST (Param: username)',
                desc: 'Developers write filters based on the amateur concept that SQLi always breaks syntax with an odd number of single quotes (\'). They block strings with odd counts of \'. Fuzzer 1 (PHUZZ gốc) fails because it sends odd quotes. Fuzzer 2 (SilentPHUZZ) bypasses by packing an even quote sequence with side-effects like INSERT INTO a trap table.',
                param: 'username',
                filepath: 'src/api/case1_balanced_quote.php',
                p1: "admin'",
                p2: "admin'; INSERT INTO __phuzz_probe VALUES(1); -- '",
                code: `// AppFilters::isBalancedQuote()
function isBalancedQuote($input) {
    if (substr_count($input, "'") % 2 !== 0) {
        return false; // Block odd quotes!
    }
    return true; // Pass even quotes
}

// Vulnerable Endpoint
$sql = "SELECT id, username, role FROM users WHERE username = '$username'";
$pdo->query($sql);`
            },
            '2': {
                title: 'Case 2: Error-Pattern Regex Blacklist WAF',
                method: 'GET / POST (Param: id)',
                desc: 'ModSecurity-style basic WAF uses Regex signatures to detect error-based SQLi (such as EXTRACTVALUE, UPDATEXML, UNION SELECT). Payload_1 tries to trigger errors to extract database version, getting blocked by the WAF. Payload_2 executes a silent DML UPDATE update statement inside DBMS with no errors (state-based blind), easily bypassing the WAF regex.',
                param: 'id',
                filepath: 'src/api/case2_error_regex.php',
                p1: "1 AND EXTRACTVALUE(1, CONCAT(0x7e, version()))",
                p2: "1; UPDATE __phuzz_update_sensor SET canary = 'hacked' WHERE id = 1; --",
                code: `// AppFilters::isMatchErrorPattern()
function isMatchErrorPattern($input) {
    $blacklist_regex = '/(CONVERT_TZ|ST_LatFromGeoHash|EXTRACTVALUE|UPDATEXML|UNION\\s+SELECT.*)/i';
    return (bool)preg_match($blacklist_regex, $input);
}

// Vulnerable Endpoint (Numeric Injection)
$sql = "SELECT id, name, category_id, price FROM products WHERE id = " . $id;
$pdo->query($sql);`
            },
            '3': {
                title: 'Case 3: Type Casting & Data Manipulation Blacklist',
                method: 'GET / POST (Param: category_id)',
                desc: 'To force SQL errors, attackers cast types (e.g. converting String to Int or XML/JSON manipulations). WAFs block CAST, CONVERT, JSON_EXTRACT, Hex literals (0x). Fuzzer 1 (Error-based) gets blocked for using EXTRACTVALUE & Hex formatting. Fuzzer 2 (SilentPHUZZ) bypasses by modifying tables using basic, primitive DML operations (DML update with basic strings) without any casting signatures.',
                param: 'category_id',
                filepath: 'src/api/case3_type_casting.php',
                p1: "1 AND EXTRACTVALUE(1, CONCAT(0x3a, database()))",
                p2: "1; UPDATE __phuzz_update_sensor SET canary_value = 'triggered' WHERE id = 1; --",
                code: `// AppFilters::isMatchTypeCasting()
function isMatchTypeCasting($input) {
    $type_casting_blacklist = '/(CAST\\s*\\(|CONVERT\\s*\\(|EXTRACTVALUE|UPDATEXML|JSON_EXTRACT|0x[0-9a-fA-F]+)/i';
    return (bool)preg_match($type_casting_blacklist, $input);
}

// Vulnerable Endpoint (Numeric Injection)
$sql = "SELECT id, name, category_id, price FROM products WHERE category_id = " . $category_id;
$pdo->query($sql);`
            },
            '4': {
                title: 'Case 4: Syntax-Preserving App-level Sanitizer',
                method: 'POST / GET (Param: order_id)',
                desc: 'A mixed sanitizer implemented in application layer: it checks for balanced quote count AND blacklists common keywords (UNION, SELECT, EXTRACTVALUE, UPDATEXML, SLEEP). Payload_1 is blocked immediately due to odd quotes or blacklisted words. Payload_2 uses even quotes and an UPDATE statement (which is not blacklisted) to alter the sensor flag, bypassing the validator entirely.',
                param: 'order_id',
                filepath: 'src/api/case4_custom_sanitizer.php',
                p1: "ORD-001' OR 1=1 --",
                p2: "ORD-001'; UPDATE phuzz_sensor SET flag = 1; -- '",
                code: `// AppFilters::isSafeCustomSanitizer()
function isSafeCustomSanitizer($input) {
    if (substr_count($input, "'") % 2 !== 0) return false;
    $blacklist = ['UNION', 'SELECT', 'EXTRACTVALUE', 'UPDATEXML', 'SLEEP'];
    foreach ($blacklist as $word) {
        if (stripos($input, $word) !== false) return false;
    }
    return true;
}

// Vulnerable Endpoint (Updating Orders Table)
$sql = "UPDATE orders SET status = 'pending' WHERE order_id = '$order_id'";
$pdo->query($sql);`
            },
            '5': {
                title: 'Case 5: Second-Order SQL Injection',
                method: 'GET / POST (Param: username)',
                desc: 'The vulnerability triggers at a different endpoint from where input is captured. Endpoint A securely saves the input using Prepared Statements (HTTP 200, no errors, Payload_1 seems safe). Endpoint B retrieves it from the DB and concatenates it into a new dynamic query. When Endpoint B executes, Payload_2 runs, triggering a state update in the background.',
                param: 'username',
                filepath: 'src/api/case5_store_profile.php (Endpoint A)',
                p1: "admin'",
                p2: "admin'; UPDATE phuzz_sensor SET flag = 55; -- '",
                code: `// Endpoint A: case5_store_profile.php (Prepared statement - 100% Safe at save)
$stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, 'p@ssword', 'user')");
$stmt->execute(['username' => $username]);

// Endpoint B: case5_trigger_report.php (Vulnerable concatenation from stored DB values)
$user = $pdo->query("SELECT username FROM users ORDER BY id DESC LIMIT 1")->fetch();
$sql = "SELECT id, username, role FROM users WHERE username = '" . $user['username'] . "'";
$pdo->query($sql);`
            },
            '6': {
                title: 'Case 6: Semantic ORDER BY Injection',
                method: 'GET / POST (Param: sort)',
                desc: 'Instead of causing an error, semantic SQLi alters the output logic (e.g. changing the sorting behavior). The developer blocks quotes, UNION, SELECT, SLEEP, EXTRACTVALUE, but leaves the ORDER BY parameter naked. Payload_1 gets blocked. Payload_2 uses a CASE-WHEN condition tree to control sorting (e.g. sorting by created_at vs id based on a truth value), bypassing filters completely.',
                param: 'sort',
                filepath: 'src/api/case6_semantic_orderby.php',
                p1: "id' or EXTRACTVALUE(1, version())",
                p2: "CASE WHEN 1=1 THEN price ELSE id END",
                code: `// AppFilters::isSafeOrderBy()
function isSafeOrderBy($input) {
    $blacklist = ["'", "UNION", "SELECT", "SLEEP", "EXTRACTVALUE"];
    foreach ($blacklist as $word) {
        if (stripos($input, $word) !== false) return false;
    }
    return true;
}

// Vulnerable Endpoint (Sorting products)
$sql = "SELECT id, name, category_id, price FROM products ORDER BY " . $sort;
$pdo->query($sql);`
            }
        };

        let currentCase = '1';
        let currentSubCase5 = '5a';

        function selectCase(caseNum) {
            currentCase = caseNum;
            
            // Highlight nav item
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            document.getElementById(`nav-case-${caseNum}`).classList.add('active');

            const info = caseDetails[caseNum];
            document.getElementById('display-title').innerText = info.title;
            document.getElementById('display-method').innerText = info.method;
            document.getElementById('display-desc').innerHTML = info.desc;
            document.getElementById('display-filepath').innerText = info.filepath;
            document.getElementById('display-code').textContent = info.code;
            document.getElementById('input-param-desc').innerText = `Parameter: ${info.param}`;
            document.getElementById('payload-input').value = '';

            // Prism highlight reload
            Prism.highlightAll();

            // Clear previous response
            document.getElementById('response-block').style.display = 'none';

            // Special toggle for Case 5
            if (caseNum === '5') {
                document.getElementById('case-5-tabs').style.display = 'flex';
                switchCase5Endpoint(currentSubCase5);
            } else {
                document.getElementById('case-5-tabs').style.display = 'none';
            }
        }

        function switchCase5Endpoint(endpoint) {
            currentSubCase5 = endpoint;
            document.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(`sub-tab-${endpoint}`).classList.add('active');
            
            const info = caseDetails['5'];
            if (endpoint === '5a') {
                document.getElementById('display-filepath').innerText = 'src/api/case5_store_profile.php (Endpoint A)';
                document.getElementById('input-param-desc').innerText = 'Parameter: username (Stores input securely)';
                document.getElementById('payload-input').placeholder = "Enter username profile to store...";
            } else {
                document.getElementById('display-filepath').innerText = 'src/api/case5_trigger_report.php (Endpoint B)';
                document.getElementById('input-param-desc').innerText = 'Trigger API (No inputs needed)';
                document.getElementById('payload-input').placeholder = "No query parameter required. Will trigger based on last DB row.";
            }
        }

        function loadPreset(payloadNum) {
            const info = caseDetails[currentCase];
            if (payloadNum === 1) {
                document.getElementById('payload-input').value = info.p1;
            } else {
                document.getElementById('payload-input').value = info.p2;
            }
        }

        // Fetch sensor state and logs
        async function fetchSensorStatus() {
            try {
                const res = await fetch('index.php?api=sensor_status');
                const data = await res.json();
                
                if (data.status === 'success') {
                    updateSensorsUI(data.sensors);
                    updateLogsUI(data.mysql_log);
                }
            } catch (err) {
                console.error("Failed to fetch sensor status", err);
            }
        }

        function updateSensorsUI(sensors) {
            // Probe Table traps (Case 1)
            const probeRows = sensors.probe.length;
            const valProbe = document.getElementById('val-probe');
            const indProbe = document.getElementById('indicator-probe');
            valProbe.innerText = `${probeRows} rows`;
            if (probeRows > 0) {
                valProbe.classList.add('hacked');
                indProbe.classList.add('triggered');
            } else {
                valProbe.classList.remove('hacked');
                indProbe.classList.remove('triggered');
            }

            // Canary tables (Case 2, 3)
            const canary = sensors.update_sensor[0]?.canary || 'safe';
            const canaryVal = sensors.update_sensor[0]?.canary_value || 'safe';
            
            const valCanary = document.getElementById('val-canary');
            valCanary.innerText = canary;
            if (canary !== 'safe') {
                valCanary.classList.add('hacked');
                document.getElementById('indicator-updates').classList.add('triggered');
            } else {
                valCanary.classList.remove('hacked');
            }

            const valCanaryVal = document.getElementById('val-canary-value');
            valCanaryVal.innerText = canaryVal;
            if (canaryVal !== 'safe') {
                valCanaryVal.classList.add('hacked');
                document.getElementById('indicator-updates').classList.add('triggered');
            } else {
                valCanaryVal.classList.remove('hacked');
            }

            if (canary === 'safe' && canaryVal === 'safe') {
                document.getElementById('indicator-updates').classList.remove('triggered');
            }

            // Custom Sensor tables (Case 4, 5)
            const flag = sensors.phuzz_sensor[0]?.flag ?? 0;
            const valFlag = document.getElementById('val-sensor-flag');
            const indFlag = document.getElementById('indicator-sensor-flag');
            valFlag.innerText = flag;
            if (flag > 0) {
                valFlag.classList.add('hacked');
                indFlag.classList.add('triggered');
            } else {
                valFlag.classList.remove('hacked');
                indFlag.classList.remove('triggered');
            }
        }

        function updateLogsUI(logs) {
            const container = document.getElementById('mysql-log-stream');
            container.innerHTML = '';
            
            logs.forEach(line => {
                const lineDiv = document.createElement('div');
                lineDiv.className = 'terminal-line';
                
                // Colorize logs slightly
                if (line.includes('Query') || line.includes('Prepare') || line.includes('Execute')) {
                    lineDiv.classList.add('sql-query');
                    if (line.includes('__phuzz_') || line.includes('phuzz_sensor') || line.includes('UPDATE') || line.includes('INSERT')) {
                        lineDiv.classList.add('sql-trigger');
                    }
                } else {
                    lineDiv.classList.add('system');
                }
                
                lineDiv.innerText = line;
                container.appendChild(lineDiv);
            });

            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        }

        // Send HTTP Attack Request to PHP Endpoint
        async function sendPayload() {
            const inputVal = document.getElementById('payload-input').value;
            let targetCase = currentCase;
            
            if (currentCase === '5') {
                targetCase = currentSubCase5;
            }
            
            const info = caseDetails[currentCase];
            let url = `index.php?case=${targetCase}`;
            
            // Build body or query params based on case setup
            let options = {};
            if (targetCase !== '5b') {
                const paramName = info.param;
                url += `&${paramName}=${encodeURIComponent(inputVal)}`;
            }

            try {
                const res = await fetch(url);
                const status = res.status;
                const statusText = res.statusText;
                const bodyJson = await res.json();
                
                // Update UI Response Block
                const respBlock = document.getElementById('response-block');
                const respStatus = document.getElementById('response-status');
                const respJson = document.getElementById('response-json');
                
                respBlock.style.display = 'block';
                respStatus.innerText = `${status} ${statusText}`;
                
                // Remove existing status classes and add correct one
                respStatus.className = 'status-badge';
                if (status === 200) respStatus.classList.add('status-200');
                else if (status === 403) respStatus.classList.add('status-403');
                else if (status === 500) respStatus.classList.add('status-500');
                else respStatus.classList.add('status-other');

                respJson.textContent = JSON.stringify(bodyJson, null, 4);
                Prism.highlightElement(respJson);
                
                // Fetch the database and log status immediately
                setTimeout(fetchSensorStatus, 600);
            } catch (err) {
                console.error("API request failed", err);
            }
        }

        // Reset Database API call
        async function resetDatabase() {
            if (!confirm("Are you sure you want to reset the database? This truncates sensors and resets seeding data.")) {
                return;
            }
            try {
                const res = await fetch('index.php?api=reset_db');
                const data = await res.json();
                alert(data.message);
                fetchSensorStatus();
                // Clear response block
                document.getElementById('response-block').style.display = 'none';
            } catch (err) {
                console.error("Reset failed", err);
            }
        }

        // Start polling sensor status
        window.addEventListener('load', () => {
            selectCase('1');
            fetchSensorStatus();
            // Poll every 3 seconds to keep DB sensors and Logs updated
            setInterval(fetchSensorStatus, 3000);
        });
    </script>
</body>
</html>
