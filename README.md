# Silent-SQLi Bypass Simulator Web-App

Dự án này là một testbed giả lập **6 kịch bản tấn công SQL Injection** được thiết kế để minh họa sự khác biệt giữa cách thức dò quét của fuzzer truyền thống (**PHUZZ gốc** - sử dụng Payload_1 dựa trên thông báo lỗi/error-based) và fuzzer cải tiến (**SilentPHUZZ** - sử dụng Payload_2 dựa trên side-effect trạng thái/state-based & log-based).

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
│   └── install_schema.sql             # Tạo bảng nghiệp vụ (users, products, orders) & bảng bẫy (sensors)
└── src/
    ├── index.php                      # Central Router tiếp nhận request + Dashboard UI tương tác đẹp mắt
    ├── config/
    │   └── database.php               # Kết nối PDO (đã kích hoạt MULTI_STATEMENTS để chạy được multi-query)
    ├── security/
    │   └── AppFilters.php             # Chứa 6 bộ lọc bảo mật mô phỏng cho các kịch bản
    └── api/                           # API chứa các điểm thực thi SQLi (Sinks) bị lỗi nối chuỗi
        ├── case1_balanced_quote.php   # Case 1: Lọc tính cân bằng dấu nháy đơn
        ├── case2_error_regex.php      # Case 2: WAF lọc regex chặn hàm ép lỗi (EXTRACTVALUE)
        ├── case3_type_casting.php     # Case 3: WAF chặn hàm ép kiểu dữ liệu nâng cao
        ├── case4_custom_sanitizer.php # Case 4: Bộ lọc hỗn hợp tầng Application (Chặn nháy lẻ & UNION, SELECT...)
        ├── case5_store_profile.php    # Case 5: Tiêm nhiễm thứ cấp - Giai đoạn 1: Lưu trữ an toàn (Endpoint A)
        ├── case5_trigger_report.php   # Case 5: Tiêm nhiễm thứ cấp - Giai đoạn 2: Kích hoạt truy vấn nối chuỗi (Endpoint B)
        └── case6_semantic_orderby.php # Case 6: Điều khiển cây biểu thức logic ORDER BY
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

## 🧪 Các Kịch Bản Thử Nghiệm

### 1. Case 1: Balanced Quote Sanitizer (Bộ lọc tính cân bằng dấu nháy)
* **Nguyên lý**: Ứng dụng kiểm tra nếu số lượng dấu nháy đơn trong input là số lẻ thì chặn đứng để tránh lỗi cú pháp SQL.
* **Payload 1 (PHUZZ gốc)**: `admin'` (1 nháy - Lẻ) $\rightarrow$ **Kết quả: Bị chặn (HTTP 403)**.
* **Payload 2 (SilentPHUZZ)**: `admin'; INSERT INTO __phuzz_probe VALUES(1); -- '` (4 nháy - Chẵn) $\rightarrow$ **Kết quả: Bypass thành công (HTTP 200)**. Kiểm tra bảng bẫy `__phuzz_probe` trên monitor sẽ thấy có bản ghi xuất hiện!

### 2. Case 2: Error-Pattern Regex Blacklist (WAF chặn từ khóa sinh lỗi)
* **Nguyên lý**: WAF quét regex để chặn các hàm gây lỗi như `EXTRACTVALUE`, `UPDATEXML` hay `UNION SELECT`.
* **Payload 1 (PHUZZ gốc)**: `1 AND EXTRACTVALUE(1, CONCAT(0x7e, version()))` $\rightarrow$ **Kết quả: Bị chặn (HTTP 403)**.
* **Payload 2 (SilentPHUZZ)**: `1; UPDATE __phuzz_update_sensor SET canary = 'hacked' WHERE id = 1; --` $\rightarrow$ **Kết quả: Bypass thành công (HTTP 200)**. Kiểm tra sensor monitor thấy cột `canary` chuyển thành `'hacked'`.

### 3. Case 3: Type-Casting & Data Type Manipulation Filter (WAF chặn hàm ép kiểu)
* **Nguyên lý**: WAF chặn các hàm ép kiểu `CAST`, `CONVERT`, `JSON_EXTRACT` và tiền tố Hex `0x`.
* **Payload 1 (PHUZZ gốc)**: `1 AND EXTRACTVALUE(1, CONCAT(0x3a, database()))` $\rightarrow$ **Kết quả: Bị chặn (HTTP 403)**.
* **Payload 2 (SilentPHUZZ)**: `1; UPDATE __phuzz_update_sensor SET canary_value = 'triggered' WHERE id = 1; --` $\rightarrow$ **Kết quả: Bypass thành công (HTTP 200)**. Cột `canary_value` chuyển thành `'triggered'`.

### 4. Case 4: Syntax-Preserving Sanitizer ở tầng Application (Bộ lọc tự chế)
* **Nguyên lý**: Ứng dụng tự lọc ở tầng PHP, chặn dấu nháy lẻ và chặn danh sách đen từ khóa: `UNION, SELECT, EXTRACTVALUE, UPDATEXML, SLEEP`.
* **Payload 1 (PHUZZ gốc)**: `ORD-001' OR 1=1 --` $\rightarrow$ **Kết quả: Bị chặn (HTTP 403)**.
* **Payload 2 (SilentPHUZZ)**: `ORD-001'; UPDATE phuzz_sensor SET flag = 1; -- '` $\rightarrow$ **Kết quả: Bypass thành công (HTTP 200)**. Sensor flag trên monitor cập nhật thành `1`.

### 5. Case 5: Second-Order SQLi (Tiêm nhiễm thứ cấp)
* **Nguyên lý**: Đầu vào lưu ở Endpoint A an toàn qua Prepared Statement. Sau đó Endpoint B lấy ra truy vấn nối chuỗi thô.
* **Endpoint A (Store Profile)**: Nhập payload. Kể cả Payload 1 hay Payload 2 gửi lên đây đều được lưu thành công vào DB mà không sinh lỗi nào (HTTP 200). Các fuzzer thông thường sẽ kết luận "An toàn".
* **Endpoint B (Trigger Report)**: Bấm thực thi mà không truyền thêm tham số. Endpoint B sẽ đọc dữ liệu từ DB và chèn vào câu lệnh SQL. 
* **Kết quả**: Khi kích hoạt Endpoint B với **Payload 2** (`admin'; UPDATE phuzz_sensor SET flag = 55; -- '`), sensor flag sẽ nhảy lên `55`, xác nhận lỗ hổng tồn tại bất đồng bộ.

### 6. Case 6: Semantic SQL Injection: ORDER BY
* **Nguyên lý**: Lập trình viên chặn nháy đơn và các từ khóa thông dụng (`UNION, SELECT, SLEEP...`) nhưng nối trực tiếp tham số sắp xếp.
* **Payload 1 (PHUZZ gốc)**: `id' or EXTRACTVALUE(...)` $\rightarrow$ **Kết quả: Bị chặn (HTTP 403)**.
* **Payload 2 (SilentPHUZZ)**: `CASE WHEN 1=1 THEN price ELSE id END` $\rightarrow$ **Kết quả: Bypass thành công (HTTP 200)**. Thứ tự sắp xếp sản phẩm thay đổi mà không hề sinh lỗi hay sập trang.

---

## 🔍 Giám Sát Real-Time (General Query Log)
Nhờ cơ chế mount volume `./mysql-log:/var/log/mysql:ro` giữa Container MySQL và Container PHP, màn hình Dashboard hiển thị trực tiếp các truy vấn thực tế đang chạy dưới nền cơ sở dữ liệu. Bạn có thể thấy rõ câu lệnh SQL được giải mã đầy đủ khi Payload bypass bộ lọc và thực thi thành công.
