<?php
use PHPUnit\Framework\TestCase;

class DbHelperTest extends TestCase
{
    // Test 1 : connexion PDO retourne bien un objet PDO
    public function testPdoInstanceIsCorrectType()
    {
        $pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->assertInstanceOf(PDO::class, $pdo);
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

    // Test 3 : hachage mot de passe avec password_hash
    public function testPasswordHashIsValid()
    {
        $plain    = "MonMotDePasse123";
        $hashed   = password_hash($plain, PASSWORD_BCRYPT);
        $this->assertTrue(password_verify($plain, $hashed));
    }

    // Test 4 : un mauvais mot de passe ne passe pas la vérification
    public function testWrongPasswordFails()
    {
        $hashed = password_hash("correct", PASSWORD_BCRYPT);
        $this->assertFalse(password_verify("wrong", $hashed));
    }

    // Test 5 : validation email basique
    public function testValidEmailFormat()
    {
        $this->assertNotFalse(filter_var("user@example.com", FILTER_VALIDATE_EMAIL));
    }

    // Test 6 : email invalide détecté
    public function testInvalidEmailFormat()
    {
        $this->assertFalse(filter_var("not-an-email", FILTER_VALIDATE_EMAIL));
    }

    // Test 7 : champ vide détecté
    public function testEmptyFieldDetected()
    {
        $input = "   ";
        $this->assertEmpty(trim($input));
    }

    // Test 8 : requête SELECT retourne un tableau
    public function testSelectQueryReturnsArray()
    {
        $pdo  = $this->getPdo();
        $stmt = $pdo->query("SELECT 1 AS val");
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['val']);
    }

    // Test 9 : injection basique neutralisée par les prepared statements
    public function testPreparedStatementBlocksInjection()
    {
        $pdo   = $this->getPdo();
        $input = "' OR '1'='1";
        $stmt  = $pdo->prepare("SELECT :input AS safe");
        $stmt->execute([':input' => $input]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // La valeur est traitée comme une chaîne, pas comme du SQL
        $this->assertEquals($input, $row['safe']);
    }

    // Test 10 : htmlspecialchars neutralise le XSS
    public function testHtmlspecialcharsEscapesXss()
    {
        $dangerous = '<script>alert("xss")</script>';
        $safe      = htmlspecialchars($dangerous, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }

    // ---- helper privé ----
    private function getPdo(): PDO
    {
        return new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}