<?php

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner;

use Exception;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class RequestHandlerRunnerTest extends TestCase
{
    public function testUsesErrorResponseGeneratorToGenerateResponseWhenRequestFactoryRaisesException(): void
    {
        $exception            = new Exception();
        $serverRequestFactory = function () use ($exception): ServerRequestInterface {
            throw $exception;
        };

        $response = $this->createMock(ResponseInterface::class);

        $errorResponseGenerator = function (Throwable $passedThrowable) use ($exception, $response): ResponseInterface {
            Assert::assertSame($exception, $passedThrowable);
            return $response;
        };

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($response);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $runner = new RequestHandlerRunner(
            $handler,
            $emitter,
            $serverRequestFactory,
            $errorResponseGenerator
        );

        self::assertNull($runner->run());
    }

    public function testRunPassesRequestGeneratedByRequestFactoryToHandleWhenNoRequestPassedToRun(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $serverRequestFactory = function () use ($request): ServerRequestInterface {
            return $request;
        };

        $errorResponseGenerator = function (): ResponseInterface {
            self::fail('Should never hit error response generator');
        };

        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->with($request)->willReturn($response);

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($response);

        $runner = new RequestHandlerRunner(
            $handler,
            $emitter,
            $serverRequestFactory,
            $errorResponseGenerator
        );

        self::assertNull($runner->run());
    }
}
