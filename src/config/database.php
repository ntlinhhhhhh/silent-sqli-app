<?php
// Database configuration and connection setup

$host = 'db';
$db   = 'silent_phuzz_db';
$user = 'phuzz_user';
$pass = 'phuzz_password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset;port=3306";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
    // CRITICAL: We enable multi statements to allow multi-query execution 
    // which simulates the side-effect payloads of SilentPHUZZ (Payload_2)
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
