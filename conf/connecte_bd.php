<?php
$host     = '127.0.0.1';                
$dbname   = 'bd_final'; 
$username = 'appuser';                     
$password = 'apppassword';                         

try {
    $dsn = "mysql:host=$host;dbname=$dbname;port=3306";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $username, $password);
} catch (PDOException $e) {
    
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}
define('UPLOAD_DIR','/var/www/html/uploads/reclamations');

// Taille max par fichier (ici 5 Mo) – utilisée seulement pour le contrôle, pas stockée en BD
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Types MIME autorisés
$ALLOWED_MIME_TYPES = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

// Extensions autorisées
$ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
