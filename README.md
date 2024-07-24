# Alan Captcha API PHP library

This package allows to make use of the Alan Captcha API.  
See https://docs.alancaptcha.com for further details about the API and other options for integration like javascript widget and CMS plugins for wordpress, typo3 or NEOS.  

## Installation

Requires **PHP 8.0** or later.

**Install using [Composer](https://getcomposer.org/)**

```shell
composer require alancaptcha/php
```

## Usage

### Retrieve a challenge

A challenge is encoded in a `JWT` (`JSON Web Token`). To retrieve such token you only need to create an API instance and provide your public `siteKey`:

```php
$api = new \AlanCaptcha\Php\AlanApi();
$challengeJWT = $api->challenge('YOUR-PUBLIC-SITEKEY');
```

You can then use this `JWT` to send them to clients and require them to solve the challenge encoded in the JWT.

#### Retrieve a more difficult challenge

The siteKey encodes a default difficulty, which was specified during siteKey creation.  
But, if you detect abuse of your infrastructure, you can also increase the difficulty on your own.  

```php
$api = new \AlanCaptcha\Php\AlanApi();
$challengeJWT = $api->challenge(siteKey: 'YOUR-PUBLIC-SITEKEY', difficulty: 19);
```

You might need to test a few difficulty variants to get a better understanding of the impact.  

### Validate a solution

The solution for a challenge is an array of puzzle id's and the corresponding solution, e.g.
```json
[
  {"id":"e362a59c229946c061bf1afa3ceed7","solution":"000000000362"},
  {"id":"ee0e97c214df54928fab2935760cd9","solution":"000000001616"}
]
```

To validate the solutions for a challenge JWT you can need to provide your private apiKey, the challenge JWT and the solutions array:

```php
$api = new \AlanCaptcha\Php\AlanApi();
$isValid = $api->challengeValidate($yourPrivateApiKey, $challengeJWT, $solutions);
```

### PSR-15 middleware

This package also provides a PSR-15 middleware.  
Using this middleware you can add Alan Captcha verification for your project easily by either using the provided middleware or override the middleware and change some relevant pieces specific of your project.  

#### Features of the middleware

* It can verify challenges via HTTP request headers `X-Alan-JWT` and `X-Alan-Solution`, where `X-Alan-JWT` is the retrieved JWT used to solve the challenge and `X-Alan-Solution` is a json encoded array of solutions.  
  Using HTTP request headers, there is minimal impact on your project and infrastructure, but provides good security for your HTTP endpoints.
* Validate form POST requests created by the Alan Captcha browser widget
* Configure which of your endpoints require Alan Captcha validation and which don't

#### Example

```php
$alanMiddleware = new \AlanCaptcha\Php\Middleware\AlanCaptchaMiddleware();
$alanMiddleware->setApiKey('YOUR-PRIVATE-APIKEY')->setIncludePaths(['/\/api\/.*'/]);
```