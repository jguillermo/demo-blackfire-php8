<?php

declare(strict_types=1);

namespace Behat\Behat;

use Blackfire\Bridge\Guzzle\Middleware;
use Blackfire\Build\Build;
use Blackfire\Build\Scenario;
use Blackfire\Client as BlackfireClient;
use Blackfire\ClientConfiguration;
use Blackfire\Profile;
use Blackfire\Profile\Configuration;
use Blackfire\Report;

final class BlackfireSingleton
{
    private bool $isProfiling = false;

    private ?BlackfireClient $blackfire;

    private ?Configuration $profileConfig;

    private ?Build $build = null;

    private ?Scenario $scenario = null;

    private ?Report $scenarioReport = null;

    private ?Report $buildReport = null;

    private Profile|null $profile = null;

    private array $gitInfo = [];

    private static ?BlackfireSingleton $instance = null;

    private function __construct(private string $blackfireClientId, private string $blackfireClientToken, private string $blackfireClientEnvironment)
    {
        $this->blackfire = new BlackfireClient(new ClientConfiguration($this->blackfireClientId, $this->blackfireClientToken, $this->blackfireClientEnvironment));
        $this->profileConfig = new Configuration();
        $this->getGitInfo();
    }

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self(
                getenv('BLACKFIRE_CLIENT_ID'),
                getenv('BLACKFIRE_CLIENT_TOKEN'),
                getenv('BLACKFIRE_ENVIRONMENT')
            );
        }

        return self::$instance;
    }

    public function doProfile(bool $profile = false): void
    {
        $this->isProfiling = $profile;
    }

    public function startBuild(): void
    {
        if ($this->isProfiling) {
            $this->profileConfig->assert('main.wall_time < 100ms', 'Basic wall time assertion');
            $this->profileConfig->assert('percent(main.wall_time) < 10%', 'Its not slowest');
            $this->profileConfig->assert('percent(main.peak_memory) < 10%', 'Does not consume more memory');
            $title = sprintf(
                '%s - %s - # %s',
                getenv('DOMAIN'),
                getenv('MICROSERVICE'),
                false !== getenv('GITHUB_RUN_NUMBER') ? getenv('GITHUB_RUN_NUMBER') : '(local env)'
            );
            $options = [
                'title' => $title,
                'trigger_name' => 'BEHAT',
                'external_id' => $this->gitInfo['currentCommit'],
                'external_parent_id' => $this->gitInfo['masterCommit'],
            ];
            $this->build = $this->blackfire->startBuild($this->blackfireClientEnvironment, $options);
        }
    }

    public function startScenario(mixed $handler, string $feature, string $scenario): void
    {
        if ($this->isProfiling) {
            $handler->push(Middleware::create($this->blackfire), 'blackfire');
            $parsedFeatureName = str_replace(' ', '-', strtolower($scenario));
            $options = [
                'title' => $feature,
                'external_id' => sprintf('%s-%s', $this->gitInfo['currentCommit'], $parsedFeatureName),
                'external_parent_id' => sprintf('%s-%s', $this->gitInfo['masterCommit'], $parsedFeatureName),
            ];
            $this->scenario = $this->blackfire->startScenario($this->build, $options);
            $this->profileConfig->setScenario($this->scenario);
            $this->profileConfig->setTitle($scenario);
        }
    }

    public function closeScenario(): void
    {
        if ($this->isProfiling) {
            $this->scenarioReport = $this->blackfire->closeScenario($this->scenario);
        }
    }

    public function closeBuild(): void
    {
        if ($this->isProfiling) {
            $this->buildReport = $this->blackfire->closeBuild($this->build);
        }
    }

    public function getScenarioReport(): ?Report
    {
        return $this->scenarioReport;
    }

    public function getBuildReport(): ?Report
    {
        return $this->buildReport;
    }

    public function getProfileConfig(): ?Configuration
    {
        return $this->profileConfig;
    }

    public function setProfile(string $uuid): void
    {
        if ($this->isProfiling) {
            $this->profile = $this->blackfire->getProfile($uuid);
        }
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    private function getGitInfo(): void
    {
        $this->gitInfo = [];
        $this->gitInfo['currentCommit'] = trim(shell_exec('git rev-parse --verify HEAD'));
        $this->gitInfo['masterCommit'] = trim(shell_exec('git rev-parse --verify origin/master'));
    }
}
