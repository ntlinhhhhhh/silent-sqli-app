-- Tạo cơ sở dữ liệu cho thực nghiệm
CREATE DATABASE IF NOT EXISTS silent_testbed;
USE silent_testbed;

-- Dọn dẹp trạng thái cũ (Drop theo thứ tự tránh lỗi khóa ngoại)
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS loyalty_points;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS phuzz_sensor;

-- =========================================================================
-- 1. BẢNG SOURCE (Nơi tiếp nhận dữ liệu đầu vào an toàn)
-- =========================================================================
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_name VARCHAR(100) NOT NULL, -- Khớp với hàm substr($name, 0, 100) ở Source
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_synced TINYINT(1) DEFAULT 0,     -- Cờ đánh dấu để luồng Batch Processing nhận diện
    INDEX idx_synced (is_synced)
);

-- =========================================================================
-- 2. CÁC BẢNG SINK (Nơi xử lý hàng loạt và bộc phát lỗ hổng)
-- =========================================================================

-- Bảng nghiệp vụ lõi (Chuẩn hóa 3NF, chỉ lưu client_id)
CREATE TABLE loyalty_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    current_tier VARCHAR(20) DEFAULT 'BRONZE',
    total_spent INT DEFAULT 0,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

-- Bảng ghi log hệ thống (Điểm yếu chí mạng do nối chuỗi thông báo)
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL, -- Nơi lưu trữ text log sinh ra từ việc nối chuỗi $cname
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fuzz_history (                                                                                                                           
        fuzz_trace_id VARCHAR(100) NOT NULL PRIMARY KEY,                                                                                                                
        url TEXT NOT NULL,                                                                                                                                              
        method VARCHAR(10) NOT NULL,                                                                                                                                    
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP                                                                                                                  
    ) ENGINE=InnoDB;

-- =========================================================================
-- 3. BẢNG HONEY-OBJECT (Dành riêng cho SilentPhuzz)
-- =========================================================================

-- Bảng bẫy để SilentPhuzz giám sát tác dụng phụ (Side-effect)
CREATE TABLE phuzz_sensor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    flag_token VARCHAR(50) DEFAULT 'clear'
);

-- Chèn sẵn một bản ghi làm "bia bắn" cho các payload Subquery của SilentPhuzz
INSERT INTO phuzz_sensor (flag_token) VALUES ('clear');

CREATE TABLE IF NOT EXISTS phuzz_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Chèn dữ liệu mồi (Dummy Data)
-- LƯU Ý: Đặt mật khẩu theo định dạng FLAG{...} để Fuzzer dễ dàng nhận diện
INSERT INTO phuzz_users (username, password) 
VALUES ('admin', 'FLAG{SQLi_Exfil_Success_99}');