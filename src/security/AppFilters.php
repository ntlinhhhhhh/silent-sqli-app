<?php
class AppFilters {
    public static function sanitizeBio($input) {
        if (!is_string($input)) {
            return '';
        }

        // Apply blacklist filter
        $blacklist = '/(UNION\s+SELECT|EXTRACTVALUE|UPDATEXML|JSON_EXTRACT|CONVERT|BENCHMARK|SLEEP)/i';
        if (preg_match($blacklist, $input)) {
            return ''; // Return empty string to trigger 400 Bad Request
        }

        $bio = trim($input);
        $bio = strip_tags($bio);
        // Whitelist regex: Chỉ giữ lại các ký tự được phép để chèn sqli, bao gồm cả dấu ngoặc (), dấu chấm phẩy ; và gạch dưới _
        $bio = preg_replace('/[^\p{L}\p{N}\s\.,!?\'"\(\);_-]/u', '', $bio);
        
        if (mb_strlen($bio, 'UTF-8') > 200) {
            $bio = mb_substr($bio, 0, 200, 'UTF-8'); // cắt chuỗi tối đa bio 200 ký tự
        }
        // Giữ nguyên dấu nháy đơn và nháy kép bằng cách dùng ENT_NOQUOTES để hỗ trợ kiểm thử SQLi
        $bio = htmlspecialchars($bio, ENT_NOQUOTES, 'UTF-8');
        return $bio;
    }
}
