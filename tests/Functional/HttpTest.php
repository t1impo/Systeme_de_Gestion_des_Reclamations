<?php
use PHPUnit\Framework\TestCase;

class HttpTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
    }

    private function request(
        string $path,
        string $method  = 'GET',
        array  $data    = [],
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

    // Test 3 : Bootstrap est chargé — via login_page.php qui inclut head.php
    public function testBootstrapIsLoaded()
    {
        //  On passe par login_page.php qui fait include('../conf/head.php')
        $res = $this->request('/index/login_page.php');
        $this->assertStringContainsStringIgnoringCase('bootstrap', $res['body']);
    }

    // Test 4 : page inexistante retourne 404
    public function testNonExistentPageReturns404()
    {
        $res = $this->request('/index/cette_page_nexiste_pas.php');
        //  Apache peut retourner 404 pour un fichier .php inexistant
        // (différent d'un dossier sans index qui retourne 403)
        $this->assertEquals(404, $res['code']);
    }

    // Test 5 : login avec credentials invalides ne connecte pas
    public function testLoginWithInvalidCredentialsFails()
    {
        $res = $this->request('/index/login_page.php', 'POST', [
            'email'        => 'nobody@nowhere.com',
            'mot_de_passe' => 'wrongpass',
        ]);
        $this->assertNotEquals(302, $res['code']);
        $this->assertStringNotContainsStringIgnoringCase('dashboard', $res['headers']);
    }

    // Test 6 : accès à une page protégée sans session redirige vers login
    public function testProtectedPageRedirectsWithoutSession()
    {
        $res = $this->request('/espace_reclament/espace_reclamation.php');
        //  assertContainsEquals remplace assertContains pour PHPUnit 9+
        $this->assertContainsEquals(
            $res['code'],
            [301, 302, 403],
            "La page protégée doit rediriger ou interdire l'accès sans session"
        );
    }

    // Test 7 : Content-Type est text/html
    public function testContentTypeIsHtml()
    {
        $res = $this->request('/index/login_page.php');
        $this->assertStringContainsStringIgnoringCase('text/html', $res['headers']);
    }
}