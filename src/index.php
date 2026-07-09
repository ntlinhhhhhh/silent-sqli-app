<?php
// Central Router, Helper APIs, and Interactive Dashboard UI

// 1. Dynamic Routing to Vulnerable APIs
if (isset($_GET['case'])) {
    $case = $_GET['case'];
    switch ($case) {
        case '1a':
            require_once __DIR__ . '/api/new_user.php';
            break;
        case '1b':
            require_once __DIR__ . '/api/search_users_by_bio.php';
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
            $insert_sensor = $pdo->query("SELECT * FROM __phuzz_sensor_insert")->fetchAll();
            $update_sensor = $pdo->query("SELECT * FROM __phuzz_sensor_update")->fetchAll();
            $delete_sensor = $pdo->query("SELECT * FROM __phuzz_sensor_delete")->fetchAll();
            
            // Fetch Normal Tables State for context
            $users = $pdo->query("SELECT id, username, role FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
            
            // Read MySQL General Query Log
            $log_lines = [];
            $log_path = '/var/log/mysql/general.log';
            if (file_exists($log_path) && is_readable($log_path)) {
                $lines = file($log_path);
                if ($lines) {
                    $filtered_lines = [];
                    foreach ($lines as $line) {
                        $lower_line = strtolower($line);
                        if (strpos($lower_line, '__phuzz_sensor_insert') !== false ||
                            strpos($lower_line, '__phuzz_sensor_update') !== false ||
                            strpos($lower_line, '__phuzz_sensor_delete') !== false ||
                            strpos($lower_line, 'users order by id desc limit 5') !== false ||
                            strpos($lower_line, 'api=sensor_status') !== false) {
                            continue;
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
                    "users" => $users
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
            $pdo->exec("TRUNCATE TABLE __phuzz_sensor_insert;");
            $pdo->exec("TRUNCATE TABLE __phuzz_sensor_update;");
            $pdo->exec("INSERT INTO __phuzz_sensor_update (id, canary) VALUES (1, 'safe');");
            $pdo->exec("TRUNCATE TABLE __phuzz_sensor_delete;");
            $pdo->exec("INSERT INTO __phuzz_sensor_delete (id, marker) VALUES (1, 'active');");
            $pdo->exec("DROP TABLE IF EXISTS users;");
            $pdo->exec("CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(20) DEFAULT 'user',
                bio TEXT NULL,
                FULLTEXT(bio)
            ) ENGINE=InnoDB;");
            $pdo->exec("INSERT INTO users (username, password, role, bio) VALUES 
                ('admin', 'p@ssword', 'admin', 'System Administrator'), 
                ('guest', '12345', 'user', 'Regular Guest User');");
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
        /* Modern Glassmorphic Sleek Dark Style */
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
            overflow: hidden;
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
            align-items: center;
            gap: 1rem;
        }

        .auto-refresh-container {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            cursor: pointer;
            user-select: none;
        }

        .auto-refresh-container input {
            cursor: pointer;
            accent-color: var(--accent-primary);
        }

        button.btn-refresh {
            background: rgba(59, 130, 246, 0.1);
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
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

        button.btn-refresh:hover {
            background: rgba(59, 130, 246, 0.25);
            color: #fff;
            border-color: var(--accent-primary);
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.4);
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
            grid-template-columns: 240px 1fr 340px;
            gap: 1rem;
            padding: 1rem;
            height: calc(100vh - 60px);
            overflow: hidden;
        }

        /* Sidebar */
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
            padding: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(16, 185, 129, 0.04));
            border-color: var(--accent-primary);
            box-shadow: 0 0 12px var(--card-glow);
        }

        .nav-item-num {
            font-size: 0.65rem;
            font-weight: 600;
            color: #fff;
            background: var(--accent-primary);
            padding: 0.05rem 0.3rem;
            border-radius: 3px;
            width: fit-content;
            margin-bottom: 0.3rem;
        }

        .nav-item-title {
            font-weight: 700;
            font-size: 0.9rem;
            color: #fff;
        }

        .nav-item-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        /* Main Content */
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
            padding: 1.25rem;
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

        /* Tabs */
        .case-tabs {
            display: flex;
            gap: 0.4rem;
            margin-bottom: 0.8rem;
            border-bottom: 1px solid var(--card-border);
            padding-bottom: 0.4rem;
        }

        .sub-tab {
            padding: 0.4rem 0.8rem;
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

        /* Code Block Container */
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
            max-height: 140px;
            overflow-y: auto;
        }

        .code-container code {
            font-family: 'Fira Code', monospace;
            font-size: 0.75rem !important;
        }

        /* Sandbox Box */
        .sandbox-box {
            background: rgba(15, 23, 42, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 8px;
            padding: 0.85rem;
        }

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

        /* HTTP Response Output Panel */
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
            max-height: 120px;
            overflow-y: auto;
        }

        /* Monitor Sidebar (Right) */
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

        /* Terminal Logs */
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
            max-height: 250px;
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

        /* Scrollbars */
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
            <label class="auto-refresh-container" title="Check to poll database sensors and query logs dynamically every 3 seconds">
                <input type="checkbox" id="chk-auto-refresh">
                Auto Refresh (3s)
            </label>
            <button class="btn-refresh" onclick="fetchSensorStatus()" title="Refresh Sensors and Logs Now">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
                Refresh
            </button>
            <button class="btn-reset" onclick="resetDatabase()" title="Restore DB schemas and seeds">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                Reset Database
            </button>
        </div>
    </header>

    <div class="app-container">
        
        <!-- SIDEBAR: CASES -->
        <div class="sidebar">
            <div class="section-title">Vulnerability Cases</div>
            
            <div class="nav-item active" id="nav-case-1">
                <div class="nav-item-num">Case 1</div>
                <div class="nav-item-title">Second-Order Secure</div>
                <div class="nav-item-desc">Defended using Case 1 sanitizer filter (Endpoint A) and Prepared Statements (Endpoint B).</div>
            </div>
        </div>

        <!-- MAIN INTERACTIVE WORKSPACE -->
        <div class="main-content">
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title" id="display-title">Case 1: Second-Order Secure Prepared Statements</div>
                    <span class="panel-tag" id="display-method">GET / POST</span>
                </div>

                <div class="case-description" id="display-desc">
                    Explanation will load here...
                </div>

                <!-- Endpoint switcher -->
                <div class="case-tabs" id="case-1-tabs">
                    <div class="sub-tab active" onclick="switchEndpoint('1a')" id="sub-tab-1a">Endpoint A: Save Profile</div>
                    <div class="sub-tab" onclick="switchEndpoint('1b')" id="sub-tab-1b">Endpoint B: Trigger Query</div>
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
                        <button class="preset-btn preset-p1" onclick="loadPreset(1)">
                            Odd Quote (Blocked)
                        </button>
                        <button class="preset-btn preset-p2" onclick="loadPreset(2)">
                            INSERT Mutation
                        </button>
                        <button class="preset-btn preset-p2" onclick="loadPreset(3)">
                            UPDATE Mutation
                        </button>
                        <button class="preset-btn preset-p2" onclick="loadPreset(4)">
                            DELETE Mutation
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

        <!-- RIGHT SIDEBAR: REAL-TIME DB SENSORS -->
        <div class="monitor-sidebar">
            <div class="section-title">Database Sensor Monitor</div>
            
            <!-- Sensor 1: Insert Sensor -->
            <div class="monitor-card" id="sensor-card-insert">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-insert"></span>
                        Insert Sensor
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Table: `__phuzz_sensor_insert`</span>
                    <span class="sensor-val" id="val-insert">0 rows</span>
                </div>
            </div>

            <!-- Sensor 2: Update Sensor -->
            <div class="monitor-card" id="sensor-card-update">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-update"></span>
                        Update Sensor
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Table: `__phuzz_sensor_update`</span>
                    <span class="sensor-val" id="val-update">safe</span>
                </div>
            </div>

            <!-- Sensor 3: Delete Sensor -->
            <div class="monitor-card" id="sensor-card-delete">
                <div class="monitor-card-header">
                    <div class="monitor-card-title">
                        <span class="sensor-indicator" id="indicator-delete"></span>
                        Delete Sensor
                    </div>
                </div>
                <div class="sensor-value-box">
                    <span class="sensor-label">Table: `__phuzz_sensor_delete`</span>
                    <span class="sensor-val" id="val-delete">active</span>
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
        const caseDetails = {
            '1': {
                title: 'Case 1: Second-Order Search User by Bio',
                method: 'GET / POST (Param: bio)',
                desc: 'Demonstrates a secure Second-Order implementation. Endpoint A (new_user.php) sanitizes the bio using AppFilters::sanitizeBio (preserving semicolons and parentheses) and stores it using Prepared Statements. Endpoint B (search_users_by_bio.php) retrieves the bio and queries it using raw string concatenation with LIKE (insecure sink). This setup is secured against SQLi by htmlspecialchars encoding quotes!',
                param: 'bio',
                filepath: 'src/api/new_user.php (Endpoint A)',
                p1: "admin'",
                p2: "admin'; INSERT INTO __phuzz_sensor_insert (marker) VALUES ('hacked'); -- ",
                p3: "admin'; UPDATE __phuzz_sensor_update SET canary = 'hacked' WHERE id = 1; -- ",
                p4: "admin'; DELETE FROM __phuzz_sensor_delete WHERE id = 1; -- ",
                code: `// Endpoint A: new_user.php (Sanitized + Prepared Statement)
$sanitized_bio = AppFilters::sanitizeBio($bio);
if (empty($sanitized_bio)) {
    http_response_code(400);
    exit;
}
$stmt = $pdo->prepare("INSERT INTO users (username, password, role, bio) VALUES (:username, 'p@ssword', 'user', :bio)");
$stmt->execute(['username' => $username, 'bio' => $sanitized_bio]);

// AppFilters::sanitizeBio blacklist logic:
$blacklist = '/(UNION\\s+SELECT|EXTRACTVALUE|UPDATEXML|JSON_EXTRACT|CONVERT|BENCHMARK|SLEEP)/i';
if (preg_match($blacklist, $bio)) { return ''; }

// Endpoint B: search_users_by_bio.php (Vulnerable LIKE Concatenation)
$latest = $pdo->query("SELECT bio FROM users ORDER BY id DESC LIMIT 1")->fetch();
$sql = "SELECT id, username, role, bio FROM users WHERE bio LIKE '%" . $latest['bio'] . "%'";
$results = $pdo->query($sql)->fetchAll();`
            }
        };

        let currentCase = '1';
        let currentSubCase = '1a';

        function switchEndpoint(endpoint) {
            currentSubCase = endpoint;
            document.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
            document.getElementById(`sub-tab-${endpoint}`).classList.add('active');
            
            if (endpoint === '1a') {
                document.getElementById('display-filepath').innerText = 'src/api/new_user.php (Endpoint A)';
                document.getElementById('input-param-desc').innerText = 'Parameter: bio (Stores input with sanitizeBio filter)';
                document.getElementById('payload-input').placeholder = "Enter bio profile to store...";
            } else {
                document.getElementById('display-filepath').innerText = 'src/api/search_users_by_bio.php (Endpoint B)';
                document.getElementById('input-param-desc').innerText = 'Trigger API (No inputs needed, uses LIKE concatenation)';
                document.getElementById('payload-input').placeholder = "No query parameter required. Will trigger based on last DB row.";
            }
        }

        function loadPreset(payloadNum) {
            const info = caseDetails[currentCase];
            if (payloadNum === 1) {
                document.getElementById('payload-input').value = info.p1;
            } else if (payloadNum === 2) {
                document.getElementById('payload-input').value = info.p2;
            } else if (payloadNum === 3) {
                document.getElementById('payload-input').value = info.p3;
            } else if (payloadNum === 4) {
                document.getElementById('payload-input').value = info.p4;
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
            // Insert Table (__phuzz_sensor_insert)
            const insertRows = sensors.insert.length;
            const valInsert = document.getElementById('val-insert');
            const indInsert = document.getElementById('indicator-insert');
            valInsert.innerText = `${insertRows} rows`;
            if (insertRows > 0) {
                valInsert.classList.add('hacked');
                indInsert.classList.add('triggered');
            } else {
                valInsert.classList.remove('hacked');
                indInsert.classList.remove('triggered');
            }

            // Update Table (__phuzz_sensor_update)
            const canary = sensors.update[0]?.canary || 'safe';
            const valUpdate = document.getElementById('val-update');
            const indUpdate = document.getElementById('indicator-update');
            valUpdate.innerText = canary;
            if (canary !== 'safe') {
                valUpdate.classList.add('hacked');
                indUpdate.classList.add('triggered');
            } else {
                valUpdate.classList.remove('hacked');
                indUpdate.classList.remove('triggered');
            }

            // Delete Table (__phuzz_sensor_delete)
            const deleteRows = sensors.delete.length;
            const valDelete = document.getElementById('val-delete');
            const indDelete = document.getElementById('indicator-delete');
            if (deleteRows === 0) {
                valDelete.innerText = 'deleted';
                valDelete.classList.add('hacked');
                indDelete.classList.add('triggered');
            } else {
                valDelete.innerText = 'active';
                valDelete.classList.remove('hacked');
                indDelete.classList.remove('triggered');
            }
        }

        function updateLogsUI(logs) {
            const container = document.getElementById('mysql-log-stream');
            container.innerHTML = '';
            
            logs.forEach(line => {
                const lineDiv = document.createElement('div');
                lineDiv.className = 'terminal-line';
                
                if (line.includes('Query') || line.includes('Prepare') || line.includes('Execute')) {
                    lineDiv.classList.add('sql-query');
                    if (line.includes('__phuzz_sensor') || line.includes('UPDATE') || line.includes('INSERT') || line.includes('DELETE')) {
                        lineDiv.classList.add('sql-trigger');
                    }
                } else {
                    lineDiv.classList.add('system');
                }
                
                lineDiv.innerText = line;
                container.appendChild(lineDiv);
            });

            container.scrollTop = container.scrollHeight;
        }

        async function sendPayload() {
            const inputVal = document.getElementById('payload-input').value;
            const targetCase = currentSubCase;
            
            const info = caseDetails[currentCase];
            let url = `index.php?case=${targetCase}`;
            
            if (targetCase === '1a') {
                const paramName = info.param;
                url += `&${paramName}=${encodeURIComponent(inputVal)}`;
                // Add a random clean username to satisfy new_user.php requirements
                const randUser = 'user_' + Math.floor(Math.random() * 9000 + 1000);
                url += `&username=${randUser}`;
            }

            try {
                const res = await fetch(url);
                const status = res.status;
                const statusText = res.statusText;
                const bodyJson = await res.json();
                
                const respBlock = document.getElementById('response-block');
                const respStatus = document.getElementById('response-status');
                const respJson = document.getElementById('response-json');
                
                respBlock.style.display = 'block';
                respStatus.innerText = `${status} ${statusText}`;
                
                respStatus.className = 'status-badge';
                if (status === 200) respStatus.classList.add('status-200');
                else if (status === 403) respStatus.classList.add('status-403');
                else if (status === 500) respStatus.classList.add('status-500');
                else respStatus.classList.add('status-other');

                let displayData = bodyJson;
                if (targetCase === '1a' && status === 200) {
                    // Automatically trigger Endpoint B to observe second-order query
                    try {
                        const triggerRes = await fetch('index.php?case=1b');
                        const triggerJson = await triggerRes.json();
                        displayData = {
                            "store_endpoint_a": bodyJson,
                            "trigger_endpoint_b": triggerJson
                        };
                    } catch (triggerErr) {
                        displayData = {
                            "store_endpoint_a": bodyJson,
                            "trigger_endpoint_b_error": triggerErr.message
                        };
                    }
                }

                respJson.textContent = JSON.stringify(displayData, null, 4);
                Prism.highlightElement(respJson);
                
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
                document.getElementById('response-block').style.display = 'none';
            } catch (err) {
                console.error("Reset failed", err);
            }
        }

        // Initialize
        window.addEventListener('load', () => {
            const info = caseDetails[currentCase];
            document.getElementById('display-title').innerText = info.title;
            document.getElementById('display-method').innerText = info.method;
            document.getElementById('display-desc').innerHTML = info.desc;
            document.getElementById('display-filepath').innerText = info.filepath;
            document.getElementById('display-code').textContent = info.code;
            document.getElementById('input-param-desc').innerText = `Parameter: ${info.param}`;
            document.getElementById('payload-input').value = '';

            Prism.highlightAll();
            fetchSensorStatus();
            
            setInterval(() => {
                const chk = document.getElementById('chk-auto-refresh');
                if (chk && chk.checked) {
                    fetchSensorStatus();
                }
            }, 3000);
        });
    </script>
</body>
</html>
