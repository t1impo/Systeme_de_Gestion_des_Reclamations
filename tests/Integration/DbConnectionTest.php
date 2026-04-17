<?php
use PHPUnit\Framework\TestCase;

class DbConnectionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";port=3306;dbname=" . getenv('DB_NAME') . ";charset=utf8",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    // Test 1 : connexion réussie à la vraie base
    public function testDatabaseConnection()
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
    }

    // Test 2 : la base de données cible existe
    public function testCorrectDatabaseSelected()
    {
        $stmt = $this->pdo->query("SELECT DATABASE()");
        $db   = $stmt->fetchColumn();
        $this->assertEquals(getenv('DB_NAME'), $db);
    }

    // Test 3 : les tables existent après import du schéma
    public function testTablesExistAfterImport()
    {
        $stmt   = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertNotEmpty($tables, "Aucune table trouvée dans la base");
    }

    // Test 4 : insertion d'un utilisateur en base
    public function testInsertUser()
    {
        $email = "test_" . uniqid() . "@example.com";
        $hash  = password_hash("password123", PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (:email, :password)"
        );
        $result = $stmt->execute([':email' => $email, ':password' => $hash]);
        $this->assertTrue($result);

        // Nettoyage
        $this->pdo->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$email]);
    }

    // Test 5 : lecture après insertion (round-trip)
    public function testReadAfterInsert()
    {
        $email = "read_" . uniqid() . "@example.com";
        $hash  = password_hash("pass", PASSWORD_BCRYPT);

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (?, ?)"
        )->execute([$email, $hash]);

        $stmt = $this->pdo->prepare("SELECT email FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($email, $row['email']);

        // Nettoyage
        $this->pdo->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$email]);
    }

    // Test 6 : mise à jour d'un enregistrement
    public function testUpdateUser()
    {
        $email   = "update_" . uniqid() . "@example.com";
        $newHash = password_hash("newpass", PASSWORD_BCRYPT);

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (?, ?)"
        )->execute([$email, password_hash("old", PASSWORD_BCRYPT)]);

        $this->pdo->prepare(
            "UPDATE utilisateurs SET password = ? WHERE email = ?"
        )->execute([$newHash, $email]);

        $stmt = $this->pdo->prepare("SELECT password FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $stored = $stmt->fetchColumn();

        $this->assertTrue(password_verify("newpass", $stored));

        // Nettoyage
        $this->pdo->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$email]);
    }

    // Test 7 : suppression d'un enregistrement
    public function testDeleteUser()
    {
        $email = "delete_" . uniqid() . "@example.com";

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (?, ?)"
        )->execute([$email, "hash"]);

        $this->pdo->prepare(
            "DELETE FROM utilisateurs WHERE email = ?"
        )->execute([$email]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $count = $stmt->fetchColumn();

        $this->assertEquals(0, $count);
    }

    // Test 8 : login avec credentials corrects
    public function testLoginWithValidCredentials()
    {
        $email = "login_" . uniqid() . "@example.com";
        $plain = "SecurePass!99";
        $hash  = password_hash($plain, PASSWORD_BCRYPT);

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (?, ?)"
        )->execute([$email, $hash]);

        $stmt = $this->pdo->prepare("SELECT password FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $stored = $stmt->fetchColumn();

        $this->assertTrue(password_verify($plain, $stored));

        // Nettoyage
        $this->pdo->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$email]);
    }

    // Test 9 : login avec mauvais mot de passe échoue
    public function testLoginWithWrongPasswordFails()
    {
        $email = "fail_" . uniqid() . "@example.com";
        $hash  = password_hash("correct", PASSWORD_BCRYPT);

        $this->pdo->prepare(
            "INSERT INTO utilisateurs (email, password) VALUES (?, ?)"
        )->execute([$email, $hash]);

        $stmt = $this->pdo->prepare("SELECT password FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        $stored = $stmt->fetchColumn();

        $this->assertFalse(password_verify("wrong", $stored));

        // Nettoyage
        $this->pdo->prepare("DELETE FROM utilisateurs WHERE email = ?")->execute([$email]);
    }
}