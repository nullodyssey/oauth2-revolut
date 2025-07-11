<?php

declare(strict_types=1);

namespace NullOdyssey\OAuth2\Revolut\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use NullOdyssey\OAuth2\Client\Provider\Revolut;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class RevolutTestCase extends TestCase
{
    private const array CONFIG = [
        'clientId' => 'test_client_id',
        'redirectUri' => 'https://example.com/callback',
    ];

    public function testMissingPrivateKeyThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The "privateKey" option is required.');

        new Revolut(self::CONFIG);
    }

    public function testEmptyPrivateKeyThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The "privateKey" option cannot be empty.');

        new Revolut([...self::CONFIG, 'privateKey' => '']);
    }

    public function testNullPrivateKeyThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The "privateKey" option cannot be empty.');

        new Revolut([...self::CONFIG, 'privateKey' => null]);
    }

    public function testInvalidVersionThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The "version" option must be a numeric value.');

        new Revolut([...self::CONFIG, 'privateKey' => 'valid_key', 'version' => 'invalid_version']);
    }

    public function testInvalidIsSandboxThrowsException(): void
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The "isSandbox" option must be a boolean value.');

        new Revolut([...self::CONFIG, 'privateKey' => 'valid_key', 'isSandbox' => 'not_a_boolean']);
    }

    public function testReturnsSandboxBaseAuthorizationUrl(): void
    {
        $revolut = new Revolut([...self::CONFIG, 'privateKey' => 'valid_key', 'isSandbox' => true]);

        self::assertStringContainsString(
            'https://sandbox-business.revolut.com',
            $revolut->getBaseAuthorizationUrl()
        );

        self::assertStringContainsString(
            'https://sandbox-b2b.revolut.com',
            $revolut->getBaseAccessTokenUrl([])
        );
    }

    public function testReturnVersionedBaseAccessTokenUrl(): void
    {
        $revolut = new Revolut([...self::CONFIG, 'privateKey' => 'valid_key', 'version' => 2]);

        self::assertStringContainsString(
            'https://b2b.revolut.com/api/2.0',
            $revolut->getBaseAccessTokenUrl([])
        );
    }

    public function testGetAccessTokenSuccess(): void
    {
        $revolut = new Revolut([
            ...self::CONFIG,
            'privateKey' => \sprintf('file://%s/test_key.pem', __DIR__),
            'version' => 1,
            'isSandbox' => true,
        ]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('send')
            ->willReturn(new Response(200, [], (string) json_encode([
                'access_token' => 'test_access_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => 'test_refresh_token',
            ])));

        $revolut->setHttpClient($client);

        $token = $revolut->getAccessToken('authorization_code', [
            'code' => 'the_code',
        ]);

        self::assertSame('test_access_token', $token->getToken());
        self::assertSame('test_refresh_token', $token->getRefreshToken());
    }

    public function testShouldThrowExceptionOnReceiveErrorObject(): void
    {
        $revolut = new Revolut([
            ...self::CONFIG,
            'privateKey' => \sprintf('file://%s/test_key.pem', __DIR__),
            'version' => 1,
            'isSandbox' => true,
        ]);

        $message = 'The request is invalid.';
        $status = rand(400, 600);

        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('{"error_description": "'.$message.'","code": '.$status.'}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('getHeader')->willReturn(['content-type' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('send')
            ->willReturn($response);

        $revolut->setHttpClient($client);

        self::expectException(IdentityProviderException::class);
        self::expectExceptionMessage($message);

        $revolut->getAccessToken('authorization_code', [
            'code' => 'the_code',
        ]);
    }

    public function testShouldThrowExceptionWithFallbackOnMessage(): void
    {
        $revolut = new Revolut([
            ...self::CONFIG,
            'privateKey' => \sprintf('file://%s/test_key.pem', __DIR__),
            'version' => 1,
            'isSandbox' => true,
        ]);

        $message = 'The request is invalid.';
        $status = rand(400, 600);

        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('{"message": "'.$message.'","code": '.$status.'}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('getHeader')->willReturn(['content-type' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('send')
            ->willReturn($response);

        $revolut->setHttpClient($client);

        self::expectException(IdentityProviderException::class);
        self::expectExceptionMessage($message);

        $revolut->getAccessToken('authorization_code', [
            'code' => 'the_code',
        ]);
    }

    public function testShouldThrowExceptionWithFallbackOnReason(): void
    {
        $revolut = new Revolut([
            ...self::CONFIG,
            'privateKey' => \sprintf('file://%s/test_key.pem', __DIR__),
            'version' => 1,
            'isSandbox' => true,
        ]);

        $message = 'Unknown Reason';
        $status = rand(400, 600);

        $body = $this->createMock(StreamInterface::class);
        $body->method('__toString')->willReturn('{"unknown_property": "'.$message.'","code": '.$status.'}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $response->method('getBody')->willReturn($body);
        $response->method('getReasonPhrase')->willReturn($message);
        $response->method('getHeader')->willReturn(['content-type' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())
            ->method('send')
            ->willReturn($response);

        $revolut->setHttpClient($client);

        self::expectException(IdentityProviderException::class);
        self::expectExceptionMessage($message);

        $revolut->getAccessToken('authorization_code', [
            'code' => 'the_code',
        ]);
    }
}
