<?php

declare(strict_types=1);

namespace Answear\LuigisBoxBundle\Tests\Acceptance\Service;

use Answear\LuigisBoxBundle\Factory\UpdateByRequestFactory;
use Answear\LuigisBoxBundle\Factory\UpdateByRequestStatusFactory;
use Answear\LuigisBoxBundle\Service\Client;
use Answear\LuigisBoxBundle\Service\LuigisBoxSerializer;
use Answear\LuigisBoxBundle\Service\UpdateByQueryRequest;
use Answear\LuigisBoxBundle\Service\UpdateByQueryRequestInterface;
use Answear\LuigisBoxBundle\Tests\DataProvider\Faker\ExampleConfiguration;
use Answear\LuigisBoxBundle\ValueObject\UpdateByQuery;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

class UpdateByQueryRequestTest extends TestCase
{
    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\UpdateByQueryRequestDataProvider::forUpdate()
     */
    public function updatePassed(
        UpdateByQuery $updateByQuery,
        string $expectedContent,
        array $apiResponse,
        int $jobId
    ): void {
        $response = $this->getService('PATCH', '/v1/update_by_query', $expectedContent, $apiResponse)->update(
            $updateByQuery
        );

        $this->assertSame($apiResponse, $response->getRawResponse());
        $this->assertSame($jobId, $response->getJobId());
    }

    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\UpdateByQueryRequestDataProvider::forUpdateStatus()
     */
    public function updateStatusPassed(
        int $jobId,
        array $apiResponse
    ): void {
        $response = $this->getService('GET', '/v1/update_by_query?job_id=' . $jobId, '', $apiResponse)
            ->getStatus($jobId);

        $this->assertSame('complete' === $apiResponse['status'], $response->isCompleted());
        $this->assertSame($apiResponse['tracker_id'], $response->getTrackerId());
        $this->assertSame($apiResponse['updates_count'] ?? null, $response->getOkCount());
        $this->assertSame($apiResponse['failures_count'] ?? null, $response->getErrorsCount());

        if (!isset($apiResponse['failures'])) {
            $this->assertNull($response->getErrors());
        } else {
            foreach ($response->getErrors() as $error) {
                $failure = $apiResponse['failures'][$error->getUrl()];

                $this->assertNotEmpty($failure);
                $this->assertSame($failure['type'], $error->getType());
                $this->assertSame($failure['reason'], $error->getReason());
                $this->assertSame($failure['caused_by'], $error->getCausedBy());
            }
        }

        $this->assertSame($apiResponse, $response->getRawResponse());
    }

    private function getService(
        string $httpMethod,
        string $endpoint,
        string $expectedContent,
        array $apiResponse
    ): UpdateByQueryRequestInterface {
        $expectedRequest = new \GuzzleHttp\Psr7\Request(
            $httpMethod,
            new Uri('host' . $endpoint),
            [
                'Content-Type' => ['application/json; charset=utf-8'],
                'date' => [''],
                'Authorization' => [''],
            ],
            $expectedContent
        );
        $serializer = new LuigisBoxSerializer();

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                $this->callback(
                    static function (\GuzzleHttp\Psr7\Request $currentRequest) use ($expectedRequest) {
                        $currentHeaders = $currentRequest->getHeaders();
                        $expectedHeaders = $expectedRequest->getHeaders();
                        $expectedHeaders['date'] = $currentHeaders['date'];
                        $expectedHeaders['Authorization'] = $currentHeaders['Authorization'];

                        $currentContent = $currentRequest->getBody()->getContents();
                        $expectedContent = $expectedRequest->getBody()->getContents();

                        switch (true) {
                            case $currentRequest->getMethod() !== $expectedRequest->getMethod():
                            case $currentRequest->getUri()->getPath() !== $expectedRequest->getUri()->getPath():
                            case $currentRequest->getUri()->getQuery() !== $expectedRequest->getUri()->getQuery():
                            case $currentHeaders !== $expectedHeaders:
                            case $currentContent !== $expectedContent:
                                //check equals for showing difference
                                self::assertEquals($expectedContent, $currentContent);
                                self::assertEquals($expectedRequest, $currentRequest);

                                return false;
                        }

                        return true;
                    }
                )
            )
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode($apiResponse, JSON_THROW_ON_ERROR, 512)
                )
            );

        $configProvider = ExampleConfiguration::provideDefaultConfig();

        return new UpdateByQueryRequest(
            $client,
            new UpdateByRequestFactory($configProvider, $serializer),
            new UpdateByRequestStatusFactory($configProvider)
        );
    }
}
