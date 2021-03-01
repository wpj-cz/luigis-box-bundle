<?php

declare(strict_types=1);

namespace Answear\LuigisBoxBundle\Service;

use Answear\LuigisBoxBundle\DTO\ConfigDTO;
use Answear\LuigisBoxBundle\Util\AuthenticationUtil;
use Webmozart\Assert\Assert;

class ConfigProvider
{
    public const API_VERSION = 'v1';

    /**
     * @var string
     */
    private $configName;

    /**
     * @var ConfigDTO[]
     */
    private $configs;

    public function __construct(string $defaultConfigName, array $configs)
    {
        $configsDTO = [];
        foreach ($configs as $configName => $item) {
            Assert::keyExists($item, 'host');
            Assert::keyExists($item, 'publicKey');
            Assert::keyExists($item, 'privateKey');
            Assert::keyExists($item, 'connectionTimeout');
            Assert::keyExists($item, 'requestTimeout');
            Assert::keyExists($item, 'searchTimeout');

            $configsDTO[$configName] = new ConfigDTO(
                rtrim($item['host'], '/'),
                $item['publicKey'],
                $item['privateKey'],
                $item['connectionTimeout'],
                $item['requestTimeout'],
                $item['searchTimeout']
            );
        }

        Assert::allIsInstanceOf($configsDTO, ConfigDTO::class);
        Assert::keyExists(
            $configsDTO,
            $defaultConfigName,
            sprintf(
                'No configuration with key "%s". Available configurations: %s.',
                $defaultConfigName,
                implode(', ', array_keys($configsDTO))
            )
        );

        $this->configName = $defaultConfigName;
        $this->configs = $configsDTO;
    }

    public function setConfig(string $configName): void
    {
        Assert::keyExists(
            $this->configs,
            $configName,
            sprintf(
                'No configuration with key "%s". Available configurations: %s.',
                $configName,
                implode(', ', array_keys($this->configs))
            )
        );
        $this->configName = $configName;
    }

    public function getRequestHeaders(string $httpMethod, string $endpoint, \DateTimeInterface $date): array
    {
        $configDTO = $this->getConfigDTO();

        return AuthenticationUtil::getRequestHeaders(
            $configDTO->getPublicKey(),
            $configDTO->getPrivateKey(),
            $httpMethod,
            $endpoint,
            $date
        );
    }

    public function getHost(): string
    {
        return $this->getConfigDTO()->getHost();
    }

    public function getPublicKey(): string
    {
        return $this->getConfigDTO()->getPublicKey();
    }

    public function getConnectionTimeout(): float
    {
        return $this->getConfigDTO()->getConnectionTimeout();
    }

    public function getRequestTimeout(): float
    {
        return $this->getConfigDTO()->getRequestTimeout();
    }

    public function getSearchTimeout(): float
    {
        return $this->getConfigDTO()->getSearchTimeout();
    }

    private function getConfigDTO(): ConfigDTO
    {
        return $this->configs[$this->configName];
    }
}
