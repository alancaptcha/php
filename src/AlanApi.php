<?php

namespace AlanCaptcha\Php;

use AlanCaptcha\Php\Http\CurlClient;
use AlanCaptcha\Php\Http\HttpClient;

/**
 * Implementation of Alan Captcha API endpoints
 */
class AlanApi
{
    /**
     * @var HttpClient Implementation to send Http requests
     */
    protected ?HttpClient $client = null;

    /**
     * @var string
     */
    protected string $baseUrl = 'https://api.alancaptcha.com';

    public function __construct(HttpClient $client=null)
    {
        if ($client === null) {
            $client = new CurlClient();

        }

        $this->setHttpClient($client);
    }

    /**
     * Set HTTP client
     *
     * @param HttpClient $client Client to use for Http post requests
     * @return $this
     */
    public function setHttpClient(HttpClient $client) : self {
        $this->client = $client;

        return $this;
    }

    /**
     * Set base url for HTTP requests
     *
     * @param string $baseUrl Base url to send requests to
     * @return $this
     */
    public function setBaseUrl (string $baseUrl) : self {
        $this->baseUrl = rtrim($this->baseUrl, '/');

        return $this;
    }

    /**
     * Retrieve one or more challenges from the Alan Captcha API
     *
     * @param string $siteKey The public siteKey for which to acquire the challenge
     * @param string $monitorTag An identifier for the specific form of the site used for statistics. Need to be preconfigured in Alan Backend. Default `general`
     * @param int $numberOfChallenges The number of challenges to retrieve. Default 1
     * @param int $numberOfPuzzles The number of puzzles within a challenge. Default depends on site key configuration.
     * @param int $difficulty Difficulty determines the complexity
     * @param int $rounds Rounds
     * @return string|array
     */
    public function challenge(
        string $siteKey,
        string $monitorTag = 'general',
        int $numberOfPuzzles = 0,
        int $difficulty = 0,
        int $rounds = 0,
        int $numberOfChallenges = 0
    ): string|array {
        $params = [
            'siteKey' => $siteKey,
            'monitorTag' => $monitorTag,
        ];
        if (0 < $numberOfChallenges) {
            $params['numberOfChallenges'] = $numberOfChallenges;
        }
        if (0 < $numberOfPuzzles) {
            $params['numberOfPuzzles'] = $numberOfPuzzles;
        }
        if (0 < $difficulty) {
            $params['difficulty'] = $difficulty;
        }
        if (0 < $rounds) {
            $params['rounds'] = $rounds;
        }

        $response = $this->client->post(
            $this->baseUrl.'/challenge',
            \json_encode($params),
            [
                'Content-Type: application/json'
            ]
        );

        if ($response['meta']['status'] == '200') {
            return $response['body']['jwt'];
        }

        throw new \RuntimeException('Failed to request challenge from API: '.$response['meta']['status'].':'.\json_encode($response['body']), 1721658747673);
    }

    /**
     * Test if a solution from the alan captcha widget is valid
     *
     * @param string $apiKey The private api key
     * @param string $alanSolutionsFormField Json encoded array containing the jwt and corresponding solutions ['jwt' => JWT, 'solutions' => [alan solutions...]]
     * @return bool
     * @throws \JsonException
     */
    public function widgetValidate(
        string $apiKey,
        string $alanSolutionsFormField
    ) : bool {
        $jwtAndSolutions = \json_decode($alanSolutionsFormField, true, 512, JSON_THROW_ON_ERROR);

        if (isset($jwtAndSolutions['jwt'], $jwtAndSolutions['solutions'])) {
            return $this->challengeValidate($apiKey, $jwtAndSolutions['jwt'], $jwtAndSolutions['solutions']);
        }

        throw new \InvalidArgumentException('Parameter alanSolutionsFormField must be json encoded with fields jwt and solutions', 1721656985308);
    }

    /**
     * Test if the computed alan solutions is valid
     *
     * @param string $apiKey The private api key
     * @param string $jwt The jwt related to the solutions
     * @param array $puzzleSolutions Solutions computed for the given JWT
     * @return bool True if valid, False if invalid, Exception on service or network disruption
     */
    public function challengeValidate(
        string $apiKey,
        string $jwt,
        array $puzzleSolutions
    ): bool {
        $response = $this->client->post(
            $this->baseUrl.'/challenge/validate',
            \json_encode([
                'key' => $apiKey,
                'jwt' =>$jwt,
                'puzzleSolutions' => $puzzleSolutions,
            ]),
            [
                'Content-Type: application/json'
            ]
        );

        if ($response['meta']['status'] == '200') {
            return $response['body']['success'];
        }

        throw new \RuntimeException('Failed to request challenge from API: '.$response['meta']['status'].':'.\json_encode($response['body']), 1721658747673);
    }

    public function health() {
        $response = $this->client->get($this->baseUrl.'/health');

        if ($response['meta']['status'] == '200') {
            return $response['body']['success'];
        }

        throw new \RuntimeException('API request failed: '.$response['meta']['status'].':'.\json_encode($response['body']), 1721813434348);
    }
}
