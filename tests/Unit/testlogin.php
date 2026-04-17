<?php
use PHPUnit\Framework\TestCase;

class TestLogin extends TestCase
{
    private $baseUrl = "http://localhost/index/login_page.php";

    private function request($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        // 🔥 URL FIXE avec index.php comme point d'entrée
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);

        return [
            'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'body' => $response,
        ];
    }

    public function testLoginSuccess()
    {
        $response = $this->request('/index/login_page.php', 'POST', [
            'email'    => 'reclamant@email.com',
            'password' => 'reclamant',
        ]);

        $this->assertEquals(302, $response['code']);
        $this->assertStringContainsString('espace_reclamation.php', $response['body']);
    }
}