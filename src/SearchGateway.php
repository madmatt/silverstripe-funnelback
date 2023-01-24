<?php

namespace Madmatt\Funnelback;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class SearchGateway
{
    use Configurable;
    use Injectable;

    private static $dependencies = [
        'logger' => '%$' . LoggerInterface::class,
    ];

    private Client $client;

    protected LoggerInterface $logger;

    private string $api_url;

    private string $api_username;

    private string $api_password;

    private string $api_collection;

    public function __construct()
    {
        $this->api_url = Environment::getEnv('SS_FUNNELBACK_URL');
        $this->api_username = Environment::getEnv('SS_FUNNELBACK_USERNAME');
        $this->api_password = Environment::getEnv('SS_FUNNELBACK_PASSWORD');
        $this->api_collection = Environment::getEnv('SS_FUNNELBACK_COLLECTION');

        if ($this->verifyEnvironmentVariables()) {
            $this->client = new Client([
                'base_uri' => $this->api_url,
            ]);
        }
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Should output an array as $decoded['response']['resultPacket']
     * if api request worked, otherwise @throws Exception
     */
    public function getResults(string $query, int $start, int $limit, string $sort): ?array
    {
       
        if (!$this->client) {
            $message = SearchGateway::class. '::$client is not initialized, likely env vars are not configured
            correctly.';

            $this->logger->notice($message);
            throw new Exception($message);
        }

        try {
            $requestQuery = [
                'collection' => $this->api_collection,
                'query' => $query,
                'start_rank' => $start,
                'num_ranks' => $limit,
                'sort' => $sort
            ];
            
            $response = $this->client->request('GET', '/s/search.json', [
                'auth' => [$this->api_username, $this->api_password],
                'query' => $requestQuery,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            if ($response->getStatusCode() != 200) {
                $message = sprintf(
                    "Invalid Funnelback response. Code: %d, Response body: %s, Request body: %s",
                    $response->getStatusCode(),
                    $response->getBody(),
                    var_export($requestQuery, true)
                );

                $this->logger->notice($message);
                throw new Exception($message);
            }

           
            $body = $response->getBody();
            $decoded = json_decode($body, true);

            if ($decoded == null) {
                $this->logger->notice($message = "Invalid JSON response: ". $body);
                throw new Exception($message);
            }

            return $decoded['response']['resultPacket'] ?? [];
        } catch (Exception $e) {
            var_dump($e->getMessage());

            $this->logger->notice($message = "Exception: " . $e->getMessage());
            throw new Exception($message);
        }
    }

    protected function verifyEnvironmentVariables()
    {
        if (!$this->api_url) {
            user_error('Environment variable SS_FUNNELBACK_URL is not supplied', E_USER_ERROR);
            return false;
        }

        if (!$this->api_username) {
            user_error('Environment variable SS_FUNNELBACK_USERNAME is not supplied', E_USER_ERROR);
            return false;
        }

        if (!$this->api_password) {
            user_error('Environment variable SS_FUNNELBACK_PASSWORD is not supplied', E_USER_ERROR);
            return false;
        }

        if (!$this->api_collection) {
            user_error('Environment variable SS_FUNNELBACK_COLLECTION is not supplied', E_USER_ERROR);
            return false;
        }

        return true;
    }
}
