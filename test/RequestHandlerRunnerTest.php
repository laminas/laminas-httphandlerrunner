<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

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
use TypeError;

class RequestHandlerRunnerTest extends TestCase
{
    public function testUsesErrorResponseGeneratorToGenerateResponseWhenRequestFactoryRaisesException(): void
    {
        $exception = new Exception();
        $serverRequestFactory = function () use ($exception) {
            throw $exception;
        };

        $response = $this->createMock(ResponseInterface::class);

        $errorResponseGenerator = function ($e) use ($exception, $response) {
            Assert::assertSame($exception, $e);
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

        $this->assertNull($runner->run());
    }

    public function testRunPassesRequestGeneratedByRequestFactoryToHandleWhenNoRequestPassedToRun(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $serverRequestFactory = function () use ($request): ServerRequestInterface {
            return $request;
        };

        $errorResponseGenerator = function () {
            Assert::fail('Should never hit error response generator');
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

        $this->assertNull($runner->run());
    }

    public function testRaisesTypeErrorIfServerRequestFactoryDoesNotReturnARequestInstance(): void
    {
        $serverRequestFactory = function () {
            return null;
        };

        $response = $this->createMock(ResponseInterface::class);
        $errorResponseGenerator = function (Throwable $e) use ($response) {
            Assert::assertInstanceOf(TypeError::class, $e);
            return $response;
        };

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->once())->method('emit')->with($response);

        $runner = new RequestHandlerRunner(
            $handler,
            $emitter,
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->assertNull($runner->run());
    }

    public function testRaisesTypeErrorIfServerErrorResponseGeneratorFactoryDoesNotReturnAResponse(): void
    {
        $serverRequestFactory = function (): ?ServerRequestInterface {
            return null;
        };

        $errorResponseGenerator = function (Throwable $e) {
            Assert::assertInstanceOf(TypeError::class, $e);
            return null;
        };

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects($this->never())->method('emit');

        $runner = new RequestHandlerRunner(
            $handler,
            $emitter,
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->expectException(TypeError::class);
        $runner->run();
    }
}
