<?php
class AppFilters {
    // Case 1 & Case 4: Count the balancing of single quotes
    public static function isBalancedQuote($input) {
        if (substr_count($input, "'") % 2 !== 0) {
            return false;
        }
        return true;
    }

    // Case 2: Scan for regex patterns of functions used to force DBMS display errors
    public static function isMatchErrorPattern($input) {
        $blacklist_regex = '/(CONVERT_TZ|ST_LatFromGeoHash|EXTRACTVALUE|UPDATEXML|UNION\s+SELECT.*)/i';
        if (preg_match($blacklist_regex, $input)) {
            return true;
        }
        return false;
    }

    // Case 3: Block advanced data structure operations and Hex formatting (0x)
    public static function isMatchTypeCasting($input) {
        $type_casting_blacklist = '/(CAST\s*\(|CONVERT\s*\(|EXTRACTVALUE|UPDATEXML|JSON_EXTRACT|0x[0-9a-fA-F]+)/i';
        if (preg_match($type_casting_blacklist, $input)) {
            return true;
        }
        return false;
    }

    // Case 4: Application-level Custom Sanitizer (combination of odd-quote check & keyword blacklist)
    public static function isSafeCustomSanitizer($input) {
        if (!self::isBalancedQuote($input)) {
            return false;
        }
        
        $blacklist = ['UNION', 'SELECT', 'EXTRACTVALUE', 'UPDATEXML', 'SLEEP'];
        foreach ($blacklist as $word) {
            if (stripos($input, $word) !== false) {
                return false;
            }
        }
        return true;
    }

    // Case 6: Sanitizer dedicated to data sorting parameters (ORDER BY)
    public static function isSafeOrderBy($input) {
        $blacklist = ["'", "UNION", "SELECT", "SLEEP", "EXTRACTVALUE"];
        foreach ($blacklist as $word) {
            if (stripos($input, $word) !== false) {
                return false;
            }
        }
        return true;
    }
}
