<?php

namespace JoshThackeray\GetAddress;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use JoshThackeray\GetAddress\Contracts\ClientInterface;
use JoshThackeray\GetAddress\Exceptions\AccessForbiddenException;
use JoshThackeray\GetAddress\Exceptions\GetAddressException;
use JoshThackeray\GetAddress\Exceptions\InvalidApiKeyException;
use JoshThackeray\GetAddress\Exceptions\InvalidPostcodeException;
use JoshThackeray\GetAddress\Exceptions\PostcodeNotFoundException;
use JoshThackeray\GetAddress\Exceptions\TooManyRequestsException;
use JoshThackeray\GetAddress\Responses\Contracts\FindResponseInterface;
use JoshThackeray\GetAddress\Responses\FindResponse;
use JsonException;

class Client implements ClientInterface
{
    protected string $apiKey;

    protected string $endpoint = 'https://api.getAddress.io';

    private GuzzleClient $client;

    /**
     * Creates a new Client instance for making requests.
     *
     * @param string $apiKey
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new GuzzleClient([
            'base_uri' => $this->endpoint,
            'timeout'  => 15,
        ]);
    }

    /**
     * Makes a GET request returning a (JSON) array response.
     *
     * @param string $uri
     * @param array $options
     * @return array
     * @throws GetAddressException
     * @throws JsonException
     */
    private function getJson(string $uri, array $options = []) : array
    {
        try {
            $response = $this->client->get($uri, $options);
        } catch (GuzzleException $e) {

            switch ($e->getCode()) {
                case 404:
                    throw new PostcodeNotFoundException($e->getResponse(), $e);
                case 400:
                    throw new InvalidPostcodeException($e->getResponse(), $e);
                case 401:
                    throw new InvalidApiKeyException($e->getResponse(), $e);
                case 403:
                    throw new AccessForbiddenException($e->getResponse(), $e);
                case 429:
                    throw new TooManyRequestsException($e->getResponse(), $e);
            }

        }

        $body = $response->getBody();

        return json_decode($body->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Search Postal addresses by a single UK postcode and house number (if specified).
     *
     * @param string $postcode
     * @param string $house
     * @param $expand
     * @return FindResponseInterface
     * @throws GetAddressException
     * @throws JsonException
     */
    public function find(string $postcode, string $house = '', $expand = false): FindResponseInterface
    {
        $query = http_build_query([
            'expand' => $expand ? "true" : "false",
            'api-key' => $this->apiKey
        ]);

        if(!isset($house) || (isset($house) && empty($house))) {
            $response = $this->getJson('/find/' . $postcode . '?' . $query);
        } else {
            $response = $this->getJson('/find/' . $postcode . '/' . $house . '?' . $query);
        }

        return new FindResponse($response);
    }
}