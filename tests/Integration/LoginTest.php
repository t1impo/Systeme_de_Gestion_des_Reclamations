<?php

use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase {

    private $pdo;

    // exécuté avant chaque test
    protected function setUp(): void {
        $this->pdo = new PDO(
            "mysql:host=127.0.0.1;dbname=bd_final",
            "appuser",
            "apppassword"
        );
    }

    // 🔹 Test login valide
    public function testValidLogin() {

        $username = "admin@email.com";
        $password = '$2a$12$Mmi9v/iF0Dkz9U52SQpqzu1vpPlImbT5Nu/qUvy0ICRdvOrDNkGlG';

        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE username = ? AND password = ?"
        );

        $stmt->execute([$username, $password]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($user);
        $this->assertEquals($username, $user['username']);
    }

    // 🔹 Test login invalide
    public function testInvalidLogin() {

        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE username = ? AND password = ?"
        );

        $stmt->execute(["wronguser", "wrongpass"]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertFalse($user);
    }

    // 🔹 Test sécurité injection SQL
    public function testSqlInjectionBlocked() {

        $maliciousInput = "' OR 1=1 --";

        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE username = ? AND password = ?"
        );

        $stmt->execute([$maliciousInput, $maliciousInput]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertFalse($user);
    }
}