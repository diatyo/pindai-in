<?php

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;

final class LocalBaseURLTest extends CIUnitTestCase
{
    public function testLocalUrlDetection(): void
    {
        $server = $_SERVER;
        $environment = $_ENV;
        try {
            $_SERVER['app.baseURL'] = $_ENV['app.baseURL'] = 'http://pindai-in.test/demo/';
            $_SERVER['app.autoLocalBaseURL'] = $_ENV['app.autoLocalBaseURL'] = 'true';
            $_SERVER['app.forceGlobalSecureRequests'] = $_ENV['app.forceGlobalSecureRequests'] = 'true';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            foreach (['192.168.1.74', '192.168.43.25', '172.20.10.2'] as $ip) {
                $_SERVER['SERVER_ADDR'] = $ip;
                $_SERVER['HTTP_HOST'] = $ip;
                self::assertSame('https://' . $ip . '/demo/', (new App())->baseURL);
            }
            foreach (['evil.example', '192.168.43.99', '172.20.10.2:99999', '172.20.10.2@evil.example'] as $host) {
                $_SERVER['HTTP_HOST'] = $host;
                self::assertSame('http://pindai-in.test/demo/', (new App())->baseURL);
            }
            $_SERVER['HTTP_HOST'] = '172.20.10.2:8443';
            self::assertSame('https://172.20.10.2:8443/demo/', (new App())->baseURL);
            $_SERVER['app.forceGlobalSecureRequests'] = $_ENV['app.forceGlobalSecureRequests'] = 'false';
            $_SERVER['HTTPS'] = 'off';
            $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
            self::assertSame('http://172.20.10.2:8443/demo/', (new App())->baseURL);
            $_SERVER['HTTPS'] = 'on';
            self::assertSame('https://172.20.10.2:8443/demo/', (new App())->baseURL);
            $_SERVER['app.autoLocalBaseURL'] = $_ENV['app.autoLocalBaseURL'] = 'false';
            self::assertSame('http://pindai-in.test/demo/', (new App())->baseURL);
            $_SERVER['app.autoLocalBaseURL'] = $_ENV['app.autoLocalBaseURL'] = 'true';
            unset($_SERVER['REQUEST_METHOD']);
            self::assertSame('http://pindai-in.test/demo/', (new App())->baseURL);
        } finally {
            $_SERVER = $server;
            $_ENV = $environment;
        }
    }
}