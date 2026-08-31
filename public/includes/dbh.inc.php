<?php
$host    = '127.0.0.1';
$db      = 'bettashop_db';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Local dev only — fine to surface the raw error here.
    // Do not do this in production (it can leak connection details).
    // this is just temporary for local development
    die('Local DB connection failed: ' . $e->getMessage() .
        '. Check that Apache/MySQL are running in XAMPP and that the ' .
        '"bettashop_db" database exists in phpMyAdmin.');
}
