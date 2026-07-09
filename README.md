# Silent-SQLi Bypass Simulator Web-App

Dự án này là một testbed giả lập **Case 1: Second-Order Secure Prepared Statements (formerly Case 7)** được thiết kế để minh họa việc thử nghiệm bypass bộ lọc (WAF/Sanitizer) đối với các fuzzer.

Web-app được thiết kế với giao diện Dashboard đẹp mắt, trực quan giúp kiểm thử và giám sát thời gian thực (Real-time Sensor Monitoring & MySQL General Query Log Stream).

---

## 🛠️ Kiến Trúc Hệ Thống & Các File

```text
silent-sqli-app/
├── Dockerfile                         # Khởi tạo image PHP-FPM 8.1 kèm PDO MySQL extension
├── docker-compose.yml                 # Cấu hình 3 dịch vụ: Nginx (cổng 8888), PHP (cổng 9000), MySQL (cổng 3306)
├── nginx.conf                         # Cấu hình Nginx reverse proxy sang PHP-FPM
├── README.md                          # Tài liệu hướng dẫn sử dụng
├── db/
│   └── install_schema.sql             # Tạo bảng nghiệp vụ (users) & bảng bẫy (sensors: phuzz_sensor_insert, phuzz_sensor_update, phuzz_sensor_delete)
└── src/
    ├── index.php                      # Central Router tiếp nhận request + Dashboard UI tương tác đẹp mắt
    ├── config/
    │   └── database.php               # Kết nối PDO (đã kích hoạt MULTI_STATEMENTS để chạy được multi-query)
    ├── security/
    │   └── AppFilters.php             # Chứa bộ lọc bảo mật mô phỏng cho Case 1 (isSafeCase1)
    └── api/                           # API chứa các điểm thực thi SQLi (Sinks)
        ├── case1_store.php            # Case 1: Tiêm nhiễm thứ cấp - Giai đoạn 1: Lưu trữ (Endpoint A)
        └── case1_trigger.php          # Case 1: Tiêm nhiễm thứ cấp - Giai đoạn 2: Kích hoạt truy vấn (Endpoint B)
```

---

## 🚀 Hướng Dẫn Khởi Chạy

Để khởi chạy toàn bộ môi trường độc lập, bạn cần có Docker và Docker Compose cài đặt trên máy.

1. **Khởi chạy container**:
   Mở terminal tại thư mục gốc của dự án (`D:\silent-sqli-app`) và chạy lệnh sau:
   ```bash
   docker-compose up -d --build
   ```

2. **Truy cập Giao diện Dashboard**:
   Mở trình duyệt web và truy cập địa chỉ:
   ```text
   http://localhost:8888
   ```

3. **Reset Database**:
   Trên giao diện web, ở góc trên cùng bên phải có nút **"Reset Database"** giúp bạn dễ dàng khôi phục trạng thái mặc định của cơ sở dữ liệu và xóa trắng các sensor sau mỗi lần chạy thử nghiệm.

---

## 🧪 Kịch Bản Thử Nghiệm

### Case 1: Second-Order Secure Prepared Statements (formerly Case 7)
* **Nguyên lý**: Ứng dụng kiểm tra đầu vào nghiêm ngặt bằng bộ lọc kết hợp `AppFilters::isSafeCase1()` ở Endpoint A (Lưu trữ) trước khi lưu vào DB. Tiếp theo, ở Endpoint B (Kích hoạt), dữ liệu được lấy ra và truy vấn an toàn bằng Prepared Statements.
* **Payload 1 (WAF Block)**: `admin'` (Bị chặn 403 Forbidden).
* **Payload 2 (Bypass Attempt - INSERT)**: `admin'; INSERT INTO phuzz_sensor_insert (marker) VALUES ('hacked'); -- '`
* **Payload 3 (Bypass Attempt - UPDATE)**: `admin'; UPDATE phuzz_sensor_update SET canary = 'hacked' WHERE id = 1; -- '`
* **Payload 4 (Bypass Attempt - DELETE)**: `admin'; DELETE FROM phuzz_sensor_delete WHERE id = 1; -- '`

---

## 🔍 Giám Sát Real-Time (General Query Log)
Nhờ cơ chế mount volume `./mysql-log:/var/log/mysql:ro` giữa Container MySQL và Container PHP, màn hình Dashboard hiển thị trực tiếp các truy vấn thực tế đang chạy dưới nền cơ sở dữ liệu. Bạn có thể thấy rõ câu lệnh SQL được giải mã đầy đủ khi Payload bypass bộ lọc và thực thi thành công.
