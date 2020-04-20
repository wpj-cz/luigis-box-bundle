<?php

declare(strict_types=1);

namespace Answear\LuigisBoxBundle\Tests\Unit\Service;

use Answear\LuigisBoxBundle\Exception\TooManyItemsException;
use Answear\LuigisBoxBundle\Factory\ContentRemovalFactory;
use Answear\LuigisBoxBundle\Factory\ContentUpdateFactory;
use Answear\LuigisBoxBundle\Factory\PartialContentUpdateFactory;
use Answear\LuigisBoxBundle\Service\Client;
use Answear\LuigisBoxBundle\Service\Request;
use Answear\LuigisBoxBundle\ValueObject\ContentRemovalCollection;
use Answear\LuigisBoxBundle\ValueObject\ContentUpdate;
use Answear\LuigisBoxBundle\ValueObject\ContentUpdateCollection;
use Answear\LuigisBoxBundle\ValueObject\ObjectsInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\ContentUpdateDataProvider::provideSuccessContentUpdateObjects()
     */
    public function contentUpdateWithSuccess(ContentUpdateCollection $objects, array $apiResponse): void
    {
        $requestService = $this->getRequestServiceForContentUpdate($objects, $apiResponse);
        $response = $requestService->contentUpdate($objects);

        $this->assertTrue($response->isSuccess());
        $this->assertSame(\count($objects), $response->getOkCount());
        $this->assertSame(0, $response->getErrorsCount());
        $this->assertSame([], $response->getErrors());
    }

    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\ContentUpdateDataProvider::provideSuccessContentUpdateObjects()
     */
    public function partialContentUpdateWithSuccess(ContentUpdateCollection $objects, array $apiResponse): void
    {
        $requestService = $this->getRequestServiceForPartialUpdate($objects, $apiResponse);
        $response = $requestService->partialContentUpdate($objects);

        $this->assertTrue($response->isSuccess());
        $this->assertSame(\count($objects), $response->getOkCount());
        $this->assertSame(0, $response->getErrorsCount());
        $this->assertSame([], $response->getErrors());
    }

    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\ContentUpdateDataProvider::provideContentRemovalObjects()
     */
    public function contentRemovalWithSuccess(ContentRemovalCollection $objects, array $apiResponse): void
    {
        $requestService = $this->getRequestServiceForRemoval($objects, $apiResponse);
        $response = $requestService->contentRemoval($objects);

        $this->assertTrue($response->isSuccess());
        $this->assertSame(\count($objects), $response->getOkCount());
        $this->assertSame(0, $response->getErrorsCount());
        $this->assertSame([], $response->getErrors());
    }

    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\ContentUpdateDataProvider::provideAboveLimitContentUpdateObjects()
     */
    public function contentUpdateWithExceededLimit(ContentUpdateCollection $objects): void
    {
        $this->expectException(TooManyItemsException::class);
        $this->expectExceptionMessage(sprintf('Expect less than or equal %s items. Got %s.', 100, \count($objects)));

        $requestService = $this->getSimpleRequestService();
        $requestService->contentUpdate($objects);
    }

    /**
     * @test
     * @dataProvider \Answear\LuigisBoxBundle\Tests\DataProvider\ContentUpdateDataProvider::provideAboveLimitContentUpdateObjects()
     */
    public function partialContentUpdateWithExceededLimit(ContentUpdateCollection $objects): void
    {
        $this->expectException(TooManyItemsException::class);
        $this->expectExceptionMessage(sprintf('Expect less than or equal %s items. Got %s.', 50, \count($objects)));

        $requestService = $this->getSimpleRequestService();
        $requestService->partialContentUpdate($objects);
    }

    /**
     * @test
     */
    public function contentUpdateWithErrors(): void
    {
        $objects = new ContentUpdateCollection(
            [
                new ContentUpdate(
                    'test.url',
                    'products',
                    [
                        'title' => 'test url title',
                    ],
                ),
                new ContentUpdate(
                    'test.url2',
                    'categories',
                    [
                        'title' => '',
                    ]
                ),
            ]
        );

        $requestService = $this->getRequestServiceForContentUpdate(
            $objects,
            [
                'ok_count' => 1,
                'errors_count' => 1,
                'errors' => [
                    'test.url2' => [
                        'type' => 'malformed_input',
                        'reason' => 'incorrect object format',
                        'caused_by' => [
                            'title' => ['must be filled'],
                        ],
                    ],
                ],
            ]
        );
        $response = $requestService->contentUpdate($objects);

        $this->assertFalse($response->isSuccess());
        $this->assertSame(1, $response->getOkCount());
        $this->assertSame(1, $response->getErrorsCount());
        $this->assertCount(1, $response->getErrors());

        $apiResponseError = $response->getErrors()[0];
        $this->assertSame('test.url2', $apiResponseError->getUrl());
        $this->assertSame('malformed_input', $apiResponseError->getType());
        $this->assertSame('incorrect object format', $apiResponseError->getReason());
        $this->assertSame(
            [
                'title' => ['must be filled'],
            ],
            $apiResponseError->getCausedBy()
        );
    }

    private function getRequestServiceForContentUpdate(ObjectsInterface $objects, array $apiResponse): Request
    {
        $guzzleRequest = new \GuzzleHttp\Psr7\Request(
            'POST',
            new Uri('some.url'),
            [],
            'ss'
        );

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('request')
            ->with($guzzleRequest)
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode($apiResponse, JSON_THROW_ON_ERROR, 512)
                )
            );

        $contentUpdateFactory = $this->createMock(ContentUpdateFactory::class);
        $contentUpdateFactory->expects($this->once())
            ->method('prepareRequest')
            ->with($objects)->willReturn($guzzleRequest);

        $partialContentUpdateFactory = $this->createMock(PartialContentUpdateFactory::class);
        $contentRemovalUpdateFactory = $this->createMock(ContentRemovalFactory::class);

        return new Request($client, $contentUpdateFactory, $partialContentUpdateFactory, $contentRemovalUpdateFactory);
    }

    private function getRequestServiceForPartialUpdate(ObjectsInterface $objects, array $apiResponse): Request
    {
        $guzzleRequest = new \GuzzleHttp\Psr7\Request(
            'POST',
            new Uri('some.url'),
            [],
            'ss'
        );

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('request')
            ->with($guzzleRequest)
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode($apiResponse, JSON_THROW_ON_ERROR, 512)
                )
            );

        $partialContentUpdateFactory = $this->createMock(PartialContentUpdateFactory::class);
        $partialContentUpdateFactory->expects($this->once())
            ->method('prepareRequest')
            ->with($objects)->willReturn($guzzleRequest);

        $contentRemovalUpdateFactory = $this->createMock(ContentRemovalFactory::class);

        return new Request(
            $client,
            $this->createMock(ContentUpdateFactory::class),
            $partialContentUpdateFactory,
            $contentRemovalUpdateFactory
        );
    }

    private function getRequestServiceForRemoval(ObjectsInterface $objects, array $apiResponse): Request
    {
        $guzzleRequest = new \GuzzleHttp\Psr7\Request(
            'POST',
            new Uri('some.url'),
            [],
            'ss'
        );

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('request')
            ->with($guzzleRequest)
            ->willReturn(
                new Response(
                    200,
                    [],
                    json_encode($apiResponse, JSON_THROW_ON_ERROR, 512)
                )
            );

        $contentRemovalUpdateFactory = $this->createMock(ContentRemovalFactory::class);
        $contentRemovalUpdateFactory->expects($this->once())
            ->method('prepareRequest')
            ->with($objects)->willReturn($guzzleRequest);

        return new Request(
            $client,
            $this->createMock(ContentUpdateFactory::class),
            $this->createMock(PartialContentUpdateFactory::class),
            $contentRemovalUpdateFactory
        );
    }

    private function getSimpleRequestService(): Request
    {
        return new Request(
            $this->createMock(Client::class),
            $this->createMock(ContentUpdateFactory::class),
            $this->createMock(PartialContentUpdateFactory::class),
            $this->createMock(ContentRemovalFactory::class)
        );
    }
}
