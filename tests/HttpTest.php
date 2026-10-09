<?php

use PHPUnit\Framework\TestCase;

/** pb_http(): what plugins use to talk to other services. */
final class HttpTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private int $port = 0;

    protected function setUp(): void
    {
        $GLOBALS['pb_config'] = pb_test_config();
    }

    protected function tearDown(): void
    {
        if ($this->server) {
            proc_terminate($this->server);
            proc_close($this->server);
        }
    }

    private function startServer(): void
    {
        $this->port = random_int(20000, 40000);
        $this->server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$this->port", __DIR__ . '/fixtures/http/server.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $this->port)) {
                $GLOBALS['pb_config']['allow_insecure_urls'] = true; // the test server speaks plain http
                return;
            }
            usleep(100000);
        }
        $this->markTestSkipped('could not start the local test server');
    }

    public function test_only_https_and_clean_headers_are_accepted(): void
    {
        foreach ([fn() => pb_http('http://example.com/'), fn() => pb_http('ftp://example.com/'), fn() => pb_http('//example.com/')] as $call) {
            try {
                $call();
                $this->fail('a non-https address went through');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('https', $e->getMessage());
            }
        }
        foreach ([['X-Key' => "a\r\nBcc: x@example.com"], ["X-Key\r\nX-Evil" => 'a'], ['X Key' => 'a']] as $headers) {
            try {
                pb_http('https://example.com/', $headers);
                $this->fail('a header with a line break went through');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Cabeçalho', $e->getMessage());
            }
        }
    }

    public function test_tests_can_answer_instead_of_the_network(): void
    {
        $GLOBALS['pb_config']['http'] = fn($url, $headers, $timeout, $body) => ['status' => 200, 'body' => json_encode(compact('url', 'headers', 'timeout', 'body'))];
        $answer = pb_http('https://api.example.com/x', ['X-Api-Key' => 'k'], 5, 'corpo');
        $this->assertSame(200, $answer['status']);
        $this->assertSame(['url' => 'https://api.example.com/x', 'headers' => ['X-Api-Key' => 'k'], 'timeout' => 5, 'body' => 'corpo'], json_decode($answer['body'], true));
    }

    public function test_the_real_request_sends_headers_and_body_and_reports_the_status(): void
    {
        $this->startServer();
        $get = pb_http("http://127.0.0.1:$this->port/ok", ['X-Api-Key' => 'segredo']);
        $this->assertSame(200, $get['status']);
        $this->assertSame(['method' => 'GET', 'key' => 'segredo', 'body' => ''], json_decode($get['body'], true));

        $post = json_decode(pb_http("http://127.0.0.1:$this->port/ok", [], 8, '{"a":1}')['body'], true);
        $this->assertSame(['POST', '{"a":1}'], [$post['method'], $post['body']]);

        $this->assertSame(200, pb_http("http://127.0.0.1:$this->port/redirect")['status'], 'follows a redirect and reports the final status');
        $denied = pb_http("http://127.0.0.1:$this->port/denied");
        $this->assertSame([403, '{"error":"no"}'], [$denied['status'], $denied['body']], 'an error status comes back for the plugin to judge');
        $this->assertSame(404, pb_http("http://127.0.0.1:$this->port/missing")['status']);
    }

    public function test_a_huge_answer_and_a_dead_address_are_refused(): void
    {
        $this->startServer();
        try {
            pb_http("http://127.0.0.1:$this->port/big");
            $this->fail('3 MB went through');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('grande demais', $e->getMessage());
        }
        $this->expectException(RuntimeException::class);
        pb_http('http://127.0.0.1:1/none', [], 2);
    }
}
