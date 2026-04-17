<?php
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        // L'app tourne sur le conteneur Docker exposé au port 8080
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }

    // Helper : faire une requête HTTP avec cURL
    private function request(
        string $path,
        string $method = 'GET',
        array  $data   = [],
        array  $cookies = []
    ): array {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        if (!empty($cookies)) {
            $cookieStr = implode('; ', array_map(
                fn($k, $v) => "$k=$v",
                array_keys($cookies),
                $cookies
            ));
            curl_setopt($ch, CURLOPT_COOKIE, $cookieStr);
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'code'    => $httpCode,
            'headers' => substr($raw, 0, $hdrSize),
            'body'    => substr($raw, $hdrSize),
        ];
    }

    // Test 1 : page login retourne HTTP 200
    public function testLoginPageReturns200()
    {
        $res = $this->request('/index/login_page.php');
        $this->assertEquals(200, $res['code']);
    }

    // Test 2 : page login contient un formulaire HTML
    public function testLoginPageContainsForm()
    {
        $res = $this->request('/index/login_page.php');
        $this->assertStringContainsString('<form', $res['body']);
    }

    // Test 3 : Bootstrap est chargé dans la page
    public function testBootstrapIsLoaded()
    {
        $res = $this->request('/conf/head.php');
        $this->assertStringContainsStringIgnoringCase('bootstrap', $res['body']);
    }

    // Test 4 : page inexistante retourne 404
    public function testNonExistentPageReturns404()
    {
        $res = $this->request('/index/cette_page_nexiste_pas.php');
        $this->assertEquals(404, $res['code']);
    }

    // Test 5 : login avec credentials invalides ne connecte pas
    public function testLoginWithInvalidCredentialsFails()
    {
        $res = $this->request('/index/login_page.php', 'POST', [
            'email'    => 'nobody@nowhere.com',
            'password' => 'wrongpass',
        ]);
        // Doit rester sur la page login (200) ou erreur, jamais rediriger vers dashboard
        $this->assertNotEquals(302, $res['code'], "Ne doit pas rediriger vers dashboard avec de mauvais credentials");
        $this->assertStringNotContainsStringIgnoringCase('dashboard', $res['headers']);
    }

    // Test 6 : accès à une page protégée sans session redirige vers login
    public function testProtectedPageRedirectsWithoutSession()
    {
        $res = $this->request('/espace_reclament/espace_reclamation.php');
        // Doit rediriger (302) vers la page de login
        $this->assertContains($res['code'], [301, 302, 403]);
    }

    // Test 7 : Content-Type est text/html
    public function testContentTypeIsHtml()
    {
        $res = $this->request('/index/login_page.php');
        $this->assertStringContainsStringIgnoringCase('text/html', $res['headers']);
    }

    // Test 8 : la page d'accueil répond
    public function testHomepageResponds()
    {
        $res = $this->request('/');
        $this->assertContains($res['code'], [200, 301, 302]);
    }
}