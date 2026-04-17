<?php
use PHPUnit\Framework\TestCase;

class DbConnectionTest extends TestCase
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

    // Test 1 : connexion réussie à la vraie base
    public function testDatabaseConnection()
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
    }

    // Test 2 : la base de données cible est bien sélectionnée
    public function testCorrectDatabaseSelected()
    {
        $stmt = $this->pdo->query("SELECT DATABASE()");
        $db   = $stmt->fetchColumn();
        $this->assertEquals(getenv('DB_NAME'), $db);
    }

    // Test 3 : les 7 tables du schéma existent
    public function testAllTablesExist()
    {
        $expected = ['users', 'categories', 'statuts', 'reclamations', 'commentaires', 'gestionnaires', 'pieces_jointes'];
        $stmt     = $this->pdo->query("SHOW TABLES");
        $actual   = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($expected as $table) {
            $this->assertContains($table, $actual, "Table manquante : $table");
        }
    }

    // Test 4 : les catégories de base existent (administrative, technique)
    public function testCategoriesSeeded()
    {
        $stmt = $this->pdo->query("SELECT categorie FROM categories ORDER BY id");
        $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('administrative', $cats);
        $this->assertContains('technique', $cats);
    }

    // Test 5 : les statuts de base existent
    public function testStatutsSeeded()
    {
        $stmt    = $this->pdo->query("SELECT statut FROM statuts");
        $statuts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('Acceptée', $statuts);
        $this->assertContains('En cours de traitement', $statuts);
    }

    // Test 6 : insertion et lecture d'un user (round-trip)
    public function testInsertAndReadUser()
    {
        $nom   = "Test User";
        $email = "test_" . uniqid() . "@example.com";
        $role  = "Réclamant";
        $hash  = password_hash("password123", PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (:nom, :email, :role, :mdp)"
        );
        $stmt->execute([':nom' => $nom, ':email' => $email, ':role' => $role, ':mdp' => $hash]);

        $stmt = $this->pdo->prepare("SELECT nom, email, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals($nom,   $row['nom']);
        $this->assertEquals($email, $row['email']);
        $this->assertEquals($role,  $row['role']);

        // Nettoyage
        $this->pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
    }

    // Test 7 : insertion d'une réclamation avec FK valides
    public function testInsertReclamation()
    {
        // Créer un user temporaire
        $email = "reclam_" . uniqid() . "@example.com";
        $this->pdo->prepare(
            "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (?, ?, ?, ?)"
        )->execute(["Temp", $email, "Réclamant", password_hash("pass", PASSWORD_BCRYPT)]);

        $userId = (int) $this->pdo->lastInsertId();

        // Récupérer des FK valides
        $catId    = (int) $this->pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn();
        $statutId = (int) $this->pdo->query("SELECT id FROM statuts LIMIT 1")->fetchColumn();

        $stmt = $this->pdo->prepare(
            "INSERT INTO reclamations (user_id, categorie_id, objet, description, date_soumission, statut_id)
             VALUES (:uid, :cid, :obj, :desc, :date, :sid)"
        );
        $result = $stmt->execute([
            ':uid'  => $userId,
            ':cid'  => $catId,
            ':obj'  => 'Test objet',
            ':desc' => 'Description de test',
            ':date' => date('Y-m-d'),
            ':sid'  => $statutId,
        ]);

        $this->assertTrue($result);

        // Nettoyage (CASCADE supprime la réclamation)
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    }

    // Test 8 : suppression user supprime ses réclamations (ON DELETE CASCADE)
    public function testCascadeDeleteReclamation()
    {
        $email = "cascade_" . uniqid() . "@example.com";
        $this->pdo->prepare(
            "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (?, ?, ?, ?)"
        )->execute(["CascTest", $email, "Réclamant", password_hash("pass", PASSWORD_BCRYPT)]);
        $userId = (int) $this->pdo->lastInsertId();

        $catId    = (int) $this->pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn();
        $statutId = (int) $this->pdo->query("SELECT id FROM statuts LIMIT 1")->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO reclamations (user_id, categorie_id, objet, description, date_soumission, statut_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $catId, 'Cascade test', 'desc', date('Y-m-d'), $statutId]);

        $reclamId = (int) $this->pdo->lastInsertId();

        // Supprimer le user → la réclamation doit disparaître aussi
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reclamations WHERE id = ?");
        $stmt->execute([$reclamId]);
        $this->assertEquals(0, (int) $stmt->fetchColumn(), "CASCADE DELETE n'a pas supprimé la réclamation");
    }

    // Test 9 : insertion d'un commentaire lié à une réclamation
    public function testInsertCommentaire()
    {
        $email = "comm_" . uniqid() . "@example.com";
        $this->pdo->prepare(
            "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (?, ?, ?, ?)"
        )->execute(["CommTest", $email, "Réclamant", password_hash("pass", PASSWORD_BCRYPT)]);
        $userId = (int) $this->pdo->lastInsertId();

        $catId    = (int) $this->pdo->query("SELECT id FROM categories LIMIT 1")->fetchColumn();
        $statutId = (int) $this->pdo->query("SELECT id FROM statuts LIMIT 1")->fetchColumn();

        $this->pdo->prepare(
            "INSERT INTO reclamations (user_id, categorie_id, objet, description, date_soumission, statut_id)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$userId, $catId, 'Objet comm', 'desc', date('Y-m-d'), $statutId]);
        $reclamId = (int) $this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO commentaires (reclamation_id, user_id, message, date_commentaire)
             VALUES (?, ?, ?, ?)"
        );
        $result = $stmt->execute([$reclamId, $userId, 'Commentaire de test', date('Y-m-d')]);
        $this->assertTrue($result);

        // Nettoyage (CASCADE)
        $this->pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
    }

    // Test 10 : login — vérification mot de passe depuis la table users
    public function testLoginWithValidCredentials()
    {
        $email = "login_" . uniqid() . "@example.com";
        $plain = "SecurePass!99";
        $hash  = password_hash($plain, PASSWORD_BCRYPT);

        $this->pdo->prepare(
            "INSERT INTO users (nom, email, role, mot_de_passe) VALUES (?, ?, ?, ?)"
        )->execute(["LoginTest", $email, "Réclamant", $hash]);

        $stmt = $this->pdo->prepare("SELECT mot_de_passe FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $stored = $stmt->fetchColumn();

        $this->assertTrue(password_verify($plain, $stored));

        // Nettoyage
        $this->pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
    }
}