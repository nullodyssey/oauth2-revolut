# OAuth2 Revolut Provider

[![PHP Version Require](https://img.shields.io/badge/PHP-%3E%3D8.3-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/packagist/v/nullodyssey/oauth2-revolut.svg)](https://packagist.org/packages/nullodyssey/oauth2-revolut)

This package provides Revolut OAuth 2.0 support for the PHP League's [OAuth 2.0 Client](https://github.com/thephpleague/oauth2-client).

## Installation

```bash
composer require nullodyssey/oauth2-revolut
```

## Usage

### Basic Usage

```php
<?php

use NullOdyssey\OAuth2\Client\Provider\Revolut;

$provider = new Revolut([
    'clientId' => '{revolut-client-id}',
    'clientSecret' => '{revolut-client-secret}',
    'redirectUri' => 'https://example.com/callback-url',
    'privateKey' => '/path/to/private-key.pem',
    'isSandbox' => true, // Set to false for production
    'version' => 1, // API version (default: 1)
]);
```

### Handle Authorization Response

```php
<?php

// Handle the callback from Revolut
if (isset($_GET['code'])) {
    try {
        // Get an access token using the authorization code
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // Use the access token to make API calls
        $token = $accessToken->getToken();
        $expires = $accessToken->getExpires();
        $refreshToken = $accessToken->getRefreshToken();
        
        // Store tokens securely for future use
        
    } catch (Exception $e) {
        // Handle error
        echo 'Error: ' . $e->getMessage();
    }
}
```

### Configuration Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `clientId` | string | Yes | Your Revolut application's client ID |
| `clientSecret` | string | Yes | Your Revolut application's client secret |
| `redirectUri` | string | Yes | Your application's redirect URI |
| `privateKey` | string | Yes | Path to your private key file |
| `isSandbox` | bool | No | Whether to use sandbox environment (default: false) |
| `version` | int | No | API version to use (default: 1) |

## Requirements

- PHP 8.3 or higher
- League OAuth2 Client
- Lcobucci JWT

## Credits

- [vdbelt](https://github.com/vdbelt)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email community@nullodyssey.dev instead of using the issue tracker.