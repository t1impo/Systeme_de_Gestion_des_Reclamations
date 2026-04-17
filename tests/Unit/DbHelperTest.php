<?php
use PHPUnit\Framework\TestCase;

class DbHelperTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";port=3306;dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    // Test 1 : connexion PDO retourne bien un objet PDO
    public function testPdoInstanceIsCorrectType()
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
    }

    // Test 2 : mauvais credentials → exception PDOException
    public function testBadCredentialsThrowsException()
    {
        $this->expectException(PDOException::class);
        new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME'),
            "wrong_user",
            "wrong_pass",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    // Test 3 : hachage bcrypt valide (format utilisé dans users.mot_de_passe)
    public function testPasswordHashIsValid()
    {
        $plain  = "MonMotDePasse123";
        $hashed = password_hash($plain, PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($plain, $hashed));
    }

    // Test 4 : mauvais mot de passe ne passe pas la vérification
    public function testWrongPasswordFails()
    {
        $hashed = password_hash("correct", PASSWORD_BCRYPT);
        $this->assertFalse(password_verify("wrong", $hashed));
    }

    // Test 5 : validation email (colonne users.email)
    public function testValidEmailFormat()
    {
        $this->assertNotFalse(filter_var("user@example.com", FILTER_VALIDATE_EMAIL));
    }

    // Test 6 : email invalide détecté
    public function testInvalidEmailFormat()
    {
        $this->assertFalse(filter_var("not-an-email", FILTER_VALIDATE_EMAIL));
    }

    // Test 7 : champ nom vide détecté (users.nom NOT NULL)
    public function testEmptyNomDetected()
    {
        $input = "   ";
        $this->assertEmpty(trim($input));
    }

    // Test 8 : SELECT simple retourne un tableau
    public function testSelectQueryReturnsArray()
    {
        $stmt = $this->pdo->query("SELECT 1 AS val");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['val']);
    }

    // Test 9 : injection neutralisée par prepared statement sur users
    public function testPreparedStatementBlocksInjection()
    {
        $input = "' OR '1'='1";
        $stmt  = $this->pdo->prepare("SELECT :input AS safe");
        $stmt->execute([':input' => $input]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals($input, $row['safe']);
    }

    // Test 10 : htmlspecialchars neutralise le XSS (pour objet/description reclamations)
    public function testHtmlspecialcharsEscapesXss()
    {
        $dangerous = '<script>alert("xss")</script>';
        $safe      = htmlspecialchars($dangerous, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }
}