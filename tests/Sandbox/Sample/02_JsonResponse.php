<?php

declare(strict_types=1);

/**
 * Slide 2. HTTP API response body: JSON path chains vs manual `json_decode()` and array digging.
 */

namespace Sample\PhpUnit {

    use App\Api\Client;
    use PHPUnit\Framework\TestCase;

    final class UserEndpointTest extends TestCase
    {
        public function testReturnsUserProfile(): void
        {
            $body = (new Client())->get('/api/users/42')->getBody()->getContents();

            $this->assertJson($body);
            $json = \json_decode($body, true, flags: \JSON_THROW_ON_ERROR);

            $this->assertIsArray($json);
            $this->assertArrayHasKey('data', $json);
            $this->assertArrayHasKey('meta', $json);

            $this->assertIsArray($json['data']);
            $this->assertArrayHasKey('id', $json['data']);
            $this->assertSame(42, $json['data']['id']);
            $this->assertArrayHasKey('email', $json['data']);
            $this->assertStringContainsString('@', $json['data']['email']);

            $this->assertArrayHasKey('roles', $json['data']);
            $this->assertIsArray($json['data']['roles']);
            $this->assertCount(2, $json['data']['roles']);
            $this->assertContains('admin', $json['data']['roles']);
        }
    }
}

namespace Sample\Testo {

    use App\Api\Client;
    use Testo\Assert;
    use Testo\Test;

    #[Test]
    final class UserEndpointTest
    {
        public function returnsUserProfile(): void
        {
            $body = (new Client())->get('/api/users/42')->getBody()->getContents();

            Assert::json($body)
                ->isObject()
                ->hasKeys(['data', 'meta'])
                ->assertPath('$.data.id', fn($id) => Assert::same($id->decode(), 42))
                ->assertPath('$.data.email', fn($email) => Assert::string($email->decode())
                    ->contains('@'))
                ->assertPath('$.data.roles', fn($roles) => Assert::array($roles->decode())
                    ->hasCount(2)
                    ->contains('admin'));
        }
    }
}
