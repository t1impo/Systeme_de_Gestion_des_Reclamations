<?php
use PHPUnit\Framework\TestCase;

class DbConnectionTest extends TestCase
{
    public function testDatabaseConnection()
    {
        $host     = getenv('DB_HOST')     ?: '127.0.0.1';
        $dbname   = getenv('DB_NAME')     ?: 'bd_final';
        $user     = getenv('DB_USER')     ?: 'appuser';
        $password = getenv('DB_PASS')     ?: 'apppassword';

        try {
            $pdo = new PDO(
                "mysql:host={$host};port=3306;dbname={$dbname};charset=utf8",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->assertInstanceOf(PDO::class, $pdo);
        } catch (PDOException $e) {
            $this->fail("Database connection failed: " . $e->getMessage());
        }
    }
}