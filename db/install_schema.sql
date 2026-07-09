CREATE DATABASE IF NOT EXISTS silent_phuzz_db;
USE silent_phuzz_db;

-- 1. Normal Business Tables
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    bio TEXT NULL,
    FULLTEXT(bio)
) ENGINE=InnoDB;

-- 2. Sensor Tables for SilentPHUZZ Side-Effect Detection
CREATE TABLE IF NOT EXISTS __phuzz_sensor_insert (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marker VARCHAR(100) NULL
);

CREATE TABLE IF NOT EXISTS __phuzz_sensor_update (
    id INT PRIMARY KEY,
    canary VARCHAR(100) DEFAULT 'safe'
);

CREATE TABLE IF NOT EXISTS __phuzz_sensor_delete (
    id INT PRIMARY KEY,
    marker VARCHAR(100) DEFAULT 'active'
);

-- 3. Insert Seed Data
INSERT INTO __phuzz_sensor_update (id, canary) VALUES (1, 'safe')
ON DUPLICATE KEY UPDATE canary = 'safe';

INSERT INTO __phuzz_sensor_delete (id, marker) VALUES (1, 'active')
ON DUPLICATE KEY UPDATE marker = 'active';


INSERT INTO users (username, password, role) VALUES 
('admin', 'p@ssword', 'admin'), 
('guest', '12345', 'user')
ON DUPLICATE KEY UPDATE username = username;
