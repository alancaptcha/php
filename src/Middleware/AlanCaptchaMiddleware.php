<?php

declare(strict_types=1);

namespace AlanCaptcha\Php\Middleware;

use AlanCaptcha\Php\AlanApi;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * PSR Middleware implementation example to handle Alan Captcha Validation automatically via HTTP request headers or during form data post
 */
class AlanCaptchaMiddleware implements Middleware
{
    /**
     * @var AlanApi|null
     */
    protected ?AlanApi $api = null;
    /**
     * @var string The private alan captcha api key
     */
    protected string $apiKey = '';

    /**
     * @var array A list of uri paths to exclude from captcha validation. Can be concrete paths, glob wildcards or regex
     */
    protected $excludePaths = [];

    /**
     * @var array A list of uri paths to include to captcha validation. Can be concrete paths, glob wildcards or regex
     */
    protected $includePaths = [];

    /**
     * @var bool Determines if the middleware was active during the current request
     */
    protected $validationProcessed = false;

    /**
     * Returns a newly created response in case the captcha validation failed
     * NOTE: You should override this method and adapt it to your used PHP framework's response
     *
     * @return ResponseInterface
     */
    protected function onCaptchaValidationFailed() : ResponseInterface {
        return new Response(403);
    }

    /**
     * Alan Captcha Middleware checks if a http request header `X-Alan-JWT` and `X-Alan-Solutions`
     *
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): ResponseInterface
    {
        if (!$this->shallVerifyCaptcha($request)) {
            return $handler->handle($request);
        }

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('AlanCaptchaMiddleware has no private api key set', 1721818106628);
        }

        if ($this->api === null) {
            $this->api = new AlanApi();
        }

        $this->validationProcessed = true;

        $isValid = false;
        if ($request->hasHeader('X-Alan-JWT') && $request->hasHeader('X-Alan-Solution')) {

            $jwt = $request->getHeader('X-Alan-JWT')[0];
            $encodedSolutions = $request->getHeader('X-Alan-Solution')[0];
            $alanSolutions = ['jwt' => $jwt, 'solutions' => json_decode($encodedSolutions)];

            try {
                $isValid = $this->api->challengeValidate($this->apiKey, $alanSolutions['jwt'], $alanSolutions['solutions']);
            } catch (\RuntimeException $e){
                // in case of Alan Captcha service disruption like network errors we assume a valid captcha to avoid bubbling up such errors to other services
                $isValid = true;
            }
        } else if (\strtoupper($request->getMethod()) == 'POST') {
            $alanSolutions = $this->getAlanSolutionFromRequestBody($request);

            if ($alanSolutions !== null) {
                try{
                    $isValid = $this->api->widgetValidate($this->apiKey, $alanSolutions);
                }
                catch (\RuntimeException $e){
                    // in case of Alan Captcha service disruption like network errors we assume a valid captcha to avoid bubbling up such errors to other services
                    $isValid = true;
                }
                catch (\InvalidArgumentException $e){}
            }
        }

        if ($isValid) {
            return $handler->handle($request);
        }

        return $this->onCaptchaValidationFailed();
    }

    /**
     * @param AlanApi $api
     * @return $this
     */
    public function setAlanApi(AlanApi $api): self {
        $this->api = $api;
        return $this;
    }

    /**
     * Set the private api key for captcha validation
     *
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey(string $apiKey): self {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Set list of paths or patterns to exclude from Alan Captcha validation
     *
     * @param array $excludePaths Array of paths to exclude Alan Captcha validation
     * @return $this
     */
    public function setExcludePaths(array $excludePaths): self {
        $this->excludePaths = $excludePaths;
        return $this;
    }

    /**
     * Set list of paths or patterns to surely include for Alan Captcha validation
     * @param array $excludePaths List of paths or patterns to include for Alan Captcha validation
     * @return $this
     */
    public function setIncludePaths(array $includePaths): self {
        $this->includePaths = $includePaths;
        return $this;
    }

    /**
     * @return bool True, if the middleware did process an alan captcha check, False otherwise - independent of the success
     */
    public function validationHadBeenProcessed(): bool {
        return $this->validationProcessed;
    }

    protected function getAlanSolutionFromRequestBody (Request $request) : ?string {
        $requestBody = $request->getParsedBody();
        if (is_array($requestBody) && \array_key_exists('alan-solution', $requestBody)) {
            return $requestBody['alan-solution'];
        }

        return null;
    }

    protected function shallVerifyCaptcha (Request $request) {
        if (empty($this->excludePaths) && empty($this->includePaths)) {
            return false;
        }

        $path = $request->getUri()->getPath();

        $doesMatch = function ($path, $pathsToMatchAgainst) {
            if (!empty($pathsToMatchAgainst)) {
                if (\in_array($path, $pathsToMatchAgainst)) {
                    return true;
                }
                else {
                    foreach ($pathsToMatchAgainst as $pathToMatchAgainst) {
                        if (\fnmatch($pathToMatchAgainst, $path)) {
                            return true;
                        }

                        if (!\preg_match('/^[\/@#](.+)[\/@#imsxADSUXJun]+$/', $pathToMatchAgainst) ){
                            $pathToMatchAgainst = \preg_quote($pathToMatchAgainst);
                            $pathToMatchAgainst = '/' . $pathToMatchAgainst . '/';
                        }

                        if (\preg_match($pathToMatchAgainst, $path)) {
                            return true;
                        }
                    }
                }
            }

            return false;
        };

        if ($doesMatch($path, $this->excludePaths)) {
            return false;
        }
        if ($doesMatch($path, $this->includePaths)) {
            return true;
        }

        return false;
    }
}
