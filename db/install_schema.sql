CREATE DATABASE IF NOT EXISTS silent_phuzz_db;
USE silent_phuzz_db;

-- 1. Normal Business Tables
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user'
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category_id INT NOT NULL,
    price INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending'
);

-- 2. Sensor Tables for SilentPHUZZ Side-Effect Detection
-- Used for Case 1 (Balanced Quote)
CREATE TABLE IF NOT EXISTS __phuzz_probe (
    id INT PRIMARY KEY
);

-- Used for Case 2 & Case 3 (Error-Regex & Type-Casting Blacklists)
CREATE TABLE IF NOT EXISTS __phuzz_update_sensor (
    id INT PRIMARY KEY,
    canary VARCHAR(100) DEFAULT 'safe',
    canary_value VARCHAR(100) DEFAULT 'safe'
);

-- Used for Case 4 & Case 5 (Custom Sanitizer & Second-Order SQLi)
CREATE TABLE IF NOT EXISTS phuzz_sensor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flag INT DEFAULT 0,
    marker VARCHAR(100) NULL
);

-- 3. Insert Seed Data
INSERT INTO __phuzz_update_sensor (id, canary, canary_value) VALUES (1, 'safe', 'safe')
ON DUPLICATE KEY UPDATE canary = 'safe', canary_value = 'safe';

INSERT INTO phuzz_sensor (id, flag, marker) VALUES (1, 0, NULL)
ON DUPLICATE KEY UPDATE flag = 0, marker = NULL;

INSERT INTO users (username, password, role) VALUES 
('admin', 'p@ssword', 'admin'), 
('guest', '12345', 'user')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO products (name, category_id, price) VALUES 
('iPhone 15', 1, 1000), 
('ThinkPad X1', 1, 1500), 
('AirPods', 2, 200)
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO orders (order_id, status) VALUES 
('ORD-001', 'pending')
ON DUPLICATE KEY UPDATE order_id = order_id;
