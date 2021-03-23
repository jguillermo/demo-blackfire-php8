<?php

declare(strict_types=1);

namespace Behat\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Datetime;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;
use function array_key_exists;
use function count;
use function in_array;

final class FeatureContext implements Context
{

    private ?string $baseUrl;

    private array $headers = [];

    private array $payload = [];

    private array $files = [];

    private ?int $responseCode;

    private ?string $responseType;

    private mixed $responseBody;

    private array $responses = [];

    private array $store = [];

    private array $responseHeaders = [];

    private array $responseQueryParams = [];

    private mixed $output;

    private GuzzleHttpClient $httpClient;

    private ?string $env;

    private string $domain;

    public function __construct()
    {
        $this->domain = getenv('DOMAIN');
        $this->baseUrl = sprintf('http://%s/', $this->domain);
    }

    /**
     * @When I make a GET request to :URL
     */
    public function iMakeAGetRequest(string $URL): void
    {
        $this->makeRequest('GET', $URL);
    }

    /**
     * @Then I get a SUCCESSFUL response
     */
    public function iGetASuccessfulResponse(): void
    {
        $this->validateResponseCode(200);
    }

    /**
     * @Then I validate is HTML response
     */
    public function iValidateIsHTMLResponse(): void
    {
        if ('html' !== $this->responseType) {
            throw new RuntimeException(sprintf('Response type does not match expected, current is %s', $this->responseType));
        }
    }

    /**
     * @Then I validate the response is
     */
    public function iValidateTheResponseIs(PyStringNode $responseData): void
    {
        $invalid = false;
        $expectedResponseData = $responseData->getRaw();
        $actualResponseData = (string)$this->responseBody;
        if ($responseData->getRaw() !== (string)$this->responseBody) {
            $invalid = true;
        }
        if ($invalid) {
            throw new RuntimeException(sprintf('Response body does not match expected, current response body is %s', $this->responseBody));
        }
    }


    private function parseURL(string $url, string $baseUrl = null): string
    {
        return sprintf('%s%s', $baseUrl ?? $this->baseUrl, preg_replace('/([A-Z][A-Z|0-9|_]+\()([\w|\W]+)\)/', $this->parseValue($url), $url));
    }

    private function parseValue(mixed $value): mixed
    {
        preg_match_all('/([A-Z][A-Z|0-9|_|+]+\()([\w|\W]*)\)/', (string)$value, $matches, PREG_SET_ORDER);
        $func = null;
        if (count($matches)) {
            $func = substr($matches[0][1], 0, -1);
            $value = $matches[0][2];
            if ('' === $value) {
                $value = null;
            }
            if (null !== $value) {
                $value = $this->parseValue((string)$value);
            }
        }
        $value = match ($func) {
            'RANDOM_TEXT', 'RANDOM' => md5(mt_rand()),
            'YESTERDAY' => (new DateTime())->modify('-1 day')->format($value ?? 'Y-m-d 00:00:00'),
            'TODAY' => (new DateTime())->format($value ?? 'Y-m-d 00:00:00'),
            'TODAY+HOURS' => (new DateTime())->modify(sprintf('+%d hours', $value))->format('Y-m-d H:i:s'),
            'TOMORROW' => (new DateTime())->modify('1 day')->format($value ?? 'Y-m-d 00:00:00'),
            'TOMORROW+1' => (new DateTime())->modify('2 day')->format($value ?? 'Y-m-d 00:00:00'),
            'AS_STORED' => $this->store[$value],
            'FROM_QUERY_STRING' => $this->responseQueryParams[$value],
            'FILE_GET_CONTENTS' => base64_encode(file_get_contents($value)),
            'ENV' => getenv($value),
            default => $value,
        };

        return $value;
    }

    private function makeRequest(string $method, string $url, string $baseUrl = null, int $concurrency = null, string $saveAt = null): void
    {
        $url = $this->parseURL($url, $baseUrl);
        $request = new Request($method, $url, $this->headers);
        $options = ['verify' => false, 'on_stats' => [$this, 'guzzleRequestStats']];
        if (null !== $saveAt) {
            $options['sink'] = $saveAt;
        }
        if (BlackfireSingleton::getInstance()->getProfileConfig()) {
            $options['blackfire'] = BlackfireSingleton::getInstance()->getProfileConfig();
        }

        $options['query'] = $this->payload;

        $promise = $this->httpClient->sendAsync($request, $options)
            ->then(
                function (ResponseInterface $response): void {
                    $this->responseCode = $response->getStatusCode();
                    $this->responseType = self::parseResponseContentType($response->getHeaderLine('content-type'));
                    $this->responseBody = $response->getBody();
                    $this->responseHeaders = $response->getHeaders();
                    if (array_key_exists('X-Blackfire-Profile-Uuid', $this->responseHeaders)) {
                        BlackfireSingleton::getInstance()->setProfile($this->responseHeaders['X-Blackfire-Profile-Uuid'][0]);
                    }
                },
                function (RequestException $reason): void {
                    $response = $reason->getResponse();
                    if (null !== $response) {
                        $this->responseCode = $response->getStatusCode();
                        $this->responseType = self::parseResponseContentType($response->getHeaderLine('content-type'));
                        $this->responseBody = $response->getBody();
                        $this->responseHeaders = $response->getHeaders();
                        if (array_key_exists('X-Blackfire-Profile-Uuid', $this->responseHeaders)) {
                            BlackfireSingleton::getInstance()->setProfile($this->responseHeaders['X-Blackfire-Profile-Uuid'][0]);
                        }
                    } else {
                        throw new RuntimeException($reason->getMessage());
                    }
                }
            );
        $promise->wait();

    }

    private function validateResponseCode(int $code): void
    {
        if ($this->responseCode !== $code) {
            throw new RuntimeException(sprintf('Response status code does not match expected, current is %s', $this->responseCode));
        }
    }


    private static function parseResponseContentType(string $contentType): ?string
    {
        if (in_array($contentType, ['application/json', 'application/json; charset=UTF-8'], true)) {
            $contentType = 'json';
        } elseif (in_array($contentType, ['text/html', 'text/html; charset=UTF-8'], true)) {
            $contentType = 'html';
        } elseif (in_array($contentType, ['text/html', 'application/x-www-form-urlencoded'], true)) {
            $contentType = 'form';
        } elseif ('application/pdf' === $contentType) {
            $contentType = 'pdf';
        }

        return $contentType;
    }


    /**
     * @BeforeSuite
     */
    public static function setupSuite(BeforeSuiteScope $scope): void
    {
        (new Dotenv(true))->loadEnv(__DIR__ . '/../../.env');
        $blackfire = BlackfireSingleton::getInstance();
        $blackfire->doProfile('@blackfire' === $scope->getSuite()->getSettings()['filters']['tags']);
        $blackfire->startBuild();
    }

    /**
     * @BeforeScenario
     */
    public function setupScenario(BeforeScenarioScope $scope): void
    {
        $this->cleanup();
        $this->env = $scope->getScenario()->hasTag('live') ? 'LIVE' : 'TEST';
        $this->httpClient = new GuzzleHttpClient(['cookies' => true]);
        BlackfireSingleton::getInstance()->startScenario($this->httpClient->getConfig('handler'), $scope->getFeature()->getTitle(), $scope->getScenario()->getTitle());

    }

    /**
     * @AfterScenario
     */
    public function teardownScenario(): void
    {
        BlackfireSingleton::getInstance()->closeScenario();
        $this->cleanup();
    }

    /**
     * @AfterSuite
     */
    public static function teardownSuite(): void
    {
        BlackfireSingleton::getInstance()->closeBuild();
    }

    public function cleanup(): void
    {
        $this->headers = [];
        $this->payload = [];
        $this->files = [];
        $this->responseCode = null;
        $this->responseType = null;
        $this->responseBody = null;
        $this->store = [];
        $this->env = null;
    }

    public function guzzleRequestStats(TransferStats $stats): void
    {
        parse_str(parse_url((string)$stats->getEffectiveUri(), PHP_URL_QUERY) ?? '', $this->responseQueryParams);
    }

    private function validateTime(int $expected, int $actual): void
    {
        $actual = (int)($actual / 1000);
        if ($expected < $actual) {
            throw new RuntimeException(sprintf('Response time its above %d ms, actual value is %d ms', $expected, $actual));
        }
    }

    private function validateSize(int $expected, int $actual): void
    {
        $actual = (int)($actual / 1024000);
        if ($expected < $actual) {
            throw new RuntimeException(sprintf('Size its above %d MBs, actual value is %d MBs', $expected, $actual));
        }
    }


    /**
     * @Then I validate response wall time was below :time ms
     */
    public function iValidateResponseWallTimeWasBelow(int $time): void
    {
        $profile = BlackfireSingleton::getInstance()->getProfile();
        if ($profile) {
            $this->validateTime($time, $profile->getMainCost()->getWallTime());
        }
    }

    /**
     * @Then I validate response cpu time was below :time ms
     */
    public function iValidateResponseCPUTimeWasBelow(int $time): void
    {
        $profile = BlackfireSingleton::getInstance()->getProfile();
        if ($profile) {
            $this->validateTime($time, $profile->getMainCost()->getCpu());
        }
    }

    /**
     * @Then I validate response io time was below :time ms
     */
    public function iValidateResponseIOTimeWasBelow(int $time): void
    {
        $profile = BlackfireSingleton::getInstance()->getProfile();
        if ($profile) {
            $this->validateTime($time, $profile->getMainCost()->getIo());
        }
    }

    /**
     * @Then I validate response memory was below :size MBs
     */
    public function iValidateResponseMemoryWasBelow(int $size): void
    {
        $profile = BlackfireSingleton::getInstance()->getProfile();
        if ($profile) {
            $this->validateSize($size, $profile->getMainCost()->getPeakMemoryUsage());
        }
    }

    /**
     * @Then I validate response network traffic size was below :size MBs
     */
    public function iValidateResponseNetworkTrafficSizeWasBelow(int $size): void
    {
        $profile = BlackfireSingleton::getInstance()->getProfile();
        if ($profile) {
            $this->validateSize($size, $profile->getMainCost()->getNetwork());
        }
    }

}
