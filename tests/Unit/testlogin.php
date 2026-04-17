<?php
use PHPUnit\Framework\TestCase;

class testlogin extends TestCase
{
    private $cookieFile;

    protected function setUp(): void
    {
        // fichier pour garder la session
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cookie');
    }

    protected function tearDown(): void
    {
        unlink($this->cookieFile);
    }

    private function request($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        curl_setopt($ch, CURLOPT_URL, "http://localhost" . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        //  gestion session (très important)
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        return [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }

    // ✅ Test login SUCCESS
    public function testLoginSuccess()
    {
        $response = $this->request('/index/login_page.php', 'POST', [
            'email'    => 'reclamant@email.com',
            'password' => '$2y$10$iIcAHnS8qJxXVEXesneiUOpIVpC72NBKx7LGfb/ee355zfmCOjH1m',
        ]);

        $this->assertEquals(302, $response['code']);
        $this->assertStringContainsString('espace_reclamation.php', $response['headers']);
    }

}