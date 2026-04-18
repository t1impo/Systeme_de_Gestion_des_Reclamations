<?php
$host     = getenv('DB_HOST') ?: '127.0.0.1';
$dbname   = getenv('DB_NAME') ?: 'bd_final';
$username = getenv('DB_USER') ?: 'appuser';
$password = getenv('DB_PASS') ?: 'apppassword';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;port=3306;charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password, $options);  // ← options passées ici aussi
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}

define('UPLOAD_DIR', '/var/www/html/uploads/reclamations');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

$ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
$ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];