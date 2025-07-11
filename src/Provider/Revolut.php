<?php

declare(strict_types=1);

namespace NullOdyssey\OAuth2\Client\Provider;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Revolut extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * @var string[]
     */
    protected array $defaultScopes = ['READ'];

    protected string $privateKey = '';
    protected int $version = 1;
    protected bool $isSandbox = false;

    /**
     * @param array<string, mixed>  $options
     * @param array<string, object> $collaborators
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->validateOptions($options);

        parent::__construct($options, $collaborators);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \InvalidArgumentException
     */
    private function validateOptions(array $options = []): void
    {
        if (false === \array_key_exists('privateKey', $options)) {
            throw new \InvalidArgumentException('The "privateKey" option is required.');
        }

        if (null === $options['privateKey'] || '' === $options['privateKey']) {
            throw new \InvalidArgumentException('The "privateKey" option cannot be empty.');
        }

        if (true === \array_key_exists('version', $options) && false === is_numeric($options['version'])) {
            throw new \InvalidArgumentException('The "version" option must be a numeric value.');
        }

        if (true === \array_key_exists('isSandbox', $options) && !\is_bool($options['isSandbox'])) {
            throw new \InvalidArgumentException('The "isSandbox" option must be a boolean value.');
        }
    }

    public function getBaseAuthorizationUrl(): string
    {
        return true === $this->isSandbox
            ? 'https://sandbox-business.revolut.com/app-confirm'
            : 'https://business.revolut.com/app-confirm';
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        $version = number_format($this->version, 1, '.', '');

        return true === $this->isSandbox
            ? \sprintf('https://sandbox-b2b.revolut.com/api/%s/auth/token', $version)
            : \sprintf('https://b2b.revolut.com/api/%s/auth/token', $version);
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        throw new \Exception('Resource owner details not available for Revolut.');
    }

    /**
     * @return string[]
     */
    protected function getDefaultScopes(): array
    {
        return $this->defaultScopes;
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        if (400 <= $response->getStatusCode()) {
            $message = true === \array_key_exists('error_description', $data) ? $data['error_description'] : null;

            // fallback to 'message' if 'error_description' is not set
            if (null === $message) {
                $message = true === \array_key_exists('message', $data) ? $data['message'] : null;
            }

            throw new IdentityProviderException($message ?? $response->getReasonPhrase(), true === \array_key_exists('code', $data) ? $data['code'] : $response->getStatusCode(), $response);
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        throw new \Exception('Resource owner details not available for Revolut.');
    }

    public function getAccessToken($grant, array $options = []): AccessToken|AccessTokenInterface
    {
        $time = new \DateTimeImmutable();
        $config = Configuration::forSymmetricSigner(new Sha256(), InMemory::file($this->privateKey));

        $token = $config->builder()
            ->issuedBy(parse_url($this->redirectUri, \PHP_URL_HOST))
            ->permittedFor('https://revolut.com')
            ->issuedAt($time)
            ->expiresAt($time->modify('+1 hour'))
            ->withHeader('alg', 'RS256')
            ->relatedTo($this->clientId)
            ->getToken($config->signer(), $config->signingKey());

        $options += [
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion' => $token->toString(),
        ];

        return parent::getAccessToken($grant, $options);
    }
}
