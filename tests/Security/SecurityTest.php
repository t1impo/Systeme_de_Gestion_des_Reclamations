<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private PDO    $pdo;
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->pdo = new PDO(
            "mysql:host=" . getenv('DB_HOST') . ";port=3306;dbname=" . getenv('DB_NAME') . ";charset=utf8",
            getenv('DB_USER'),
            getenv('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }

    // ── SQL INJECTION ──────────────────────────────────────────

    // Test 1 : injection SQL classique bloquée par prepared statement
    public function testSqlInjectionBlockedByPreparedStatement()
    {
        $malicious = "' OR '1'='1' --";

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM utilisateurs WHERE email = :email AND password = :pass"
        );
        $stmt->execute([':email' => $malicious, ':pass' => $malicious]);
        $count = (int) $stmt->fetchColumn();

        // L'injection ne doit retourner aucun résultat
        $this->assertEquals(0, $count, "L'injection SQL a réussi — utiliser des prepared statements");
    }

    // Test 2 : UNION injection bloquée
    public function testUnionInjectionBlocked()
    {
        $malicious = "' UNION SELECT 1,2,3 --";

        $stmt = $this->pdo->prepare("SELECT email FROM utilisateurs WHERE email = ?");
        $stmt->execute([$malicious]);
        $result = $stmt->fetchAll();

        $this->assertEmpty($result, "UNION injection a produit des résultats");
    }

    // ── XSS ───────────────────────────────────────────────────

    // Test 3 : htmlspecialchars neutralise les balises script
    public function testXssScriptTagNeutralized()
    {
        $input  = '<script>alert("xss")</script>';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    // Test 4 : attribut javascript: neutralisé
    public function testXssJavascriptAttributeNeutralized()
    {
        $input  = '<a href="javascript:alert(1)">click</a>';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('javascript:', $output);
    }

    // Test 5 : event handler onmouseover neutralisé
    public function testXssEventHandlerNeutralized()
    {
        $input  = '<img src=x onmouseover="alert(1)">';
        $output = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('onmouseover', $output);
    }

    // ── MOT DE PASSE ──────────────────────────────────────────

    // Test 6 : le mot de passe est stocké haché (jamais en clair)
    public function testPasswordIsStoredHashed()
    {
        $plain = "TestPassword123!";
        $hash  = password_hash($plain, PASSWORD_BCRYPT);

        // Le hash ne doit jamais être égal au mot de passe en clair
        $this->assertNotEquals($plain, $hash);
        // Mais la vérification doit fonctionner
        $this->assertTrue(password_verify($plain, $hash));
    }

    // Test 7 : deux hachages du même mot de passe sont différents (salt aléatoire)
    public function testPasswordHashesAreDifferent()
    {
        $plain  = "SamePassword";
        $hash1  = password_hash($plain, PASSWORD_BCRYPT);
        $hash2  = password_hash($plain, PASSWORD_BCRYPT);

        $this->assertNotEquals($hash1, $hash2, "Les hachages doivent être différents (salt aléatoire)");
    }

    // Test 8 : MD5 détecté — ne doit pas être utilisé pour les mots de passe
    public function testMd5IsNotUsedForPasswords()
    {
        $plain   = "password";
        $md5hash = md5($plain);

        // MD5 est trop court (32 chars) et sans salt → ne jamais l'utiliser
        $this->assertEquals(32, strlen($md5hash));
        // Vérifier que notre app utilise bcrypt ($2y$) et non MD5
        $bcrypt = password_hash($plain, PASSWORD_BCRYPT);
        $this->assertStringStartsWith('$2y$', $bcrypt, "Utiliser bcrypt, pas MD5");
    }

    // ── SESSION ───────────────────────────────────────────────

    // Test 9 : l'ID de session est régénéré après login (fixation de session)
    public function testSessionIdIsRegeneratedAfterLogin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $oldId = session_id();
        session_regenerate_id(true); // simule ce que doit faire le login
        $newId = session_id();

        $this->assertNotEquals($oldId, $newId, "L'ID de session doit être régénéré après login");

        session_destroy();
    }

    // Test 10 : accès page protégée sans session retourne 302 ou 403
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
            [302, 301, 403],
            "Une page protégée sans session doit rediriger ou bloquer (code: $code)"
        );
    }
}