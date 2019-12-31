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
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use TypeError;

class RequestHandlerRunnerTest extends TestCase
{
    public function testUsesErrorResponseGeneratorToGenerateResponseWhenRequestFactoryRaisesException()
    {
        $exception = new Exception();
        $serverRequestFactory = function () use ($exception) {
            throw $exception;
        };

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $errorResponseGenerator = function ($e) use ($exception, $response) {
            Assert::assertSame($exception, $e);
            return $response;
        };

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit($response)->shouldBeCalled();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->shouldNotBeCalled();

        $runner = new RequestHandlerRunner(
            $handler->reveal(),
            $emitter->reveal(),
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->assertNull($runner->run());
    }

    public function testRunPassesRequestGeneratedByRequestFactoryToHandleWhenNoRequestPassedToRun()
    {
        $request = $this->prophesize(ServerRequestInterface::class)->reveal();

        $serverRequestFactory = function () use ($request) {
            return $request;
        };

        $errorResponseGenerator = function ($e) {
            Assert::fail('Should never hit error response generator');
        };

        $response = $this->prophesize(ResponseInterface::class)->reveal();

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle($request)->willReturn($response);

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit($response)->shouldBeCalled();

        $runner = new RequestHandlerRunner(
            $handler->reveal(),
            $emitter->reveal(),
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->assertNull($runner->run());
    }

    public function testRaisesTypeErrorIfServerRequestFactoryDoesNotReturnARequestInstance()
    {
        $serverRequestFactory = function () {
            return null;
        };

        $response = $this->prophesize(ResponseInterface::class)->reveal();
        $errorResponseGenerator = function (Throwable $e) use ($response) {
            Assert::assertInstanceOf(TypeError::class, $e);
            return $response;
        };

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->shouldNotBeCalled();

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit($response)->shouldBeCalled();

        $runner = new RequestHandlerRunner(
            $handler->reveal(),
            $emitter->reveal(),
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->assertNull($runner->run());
    }

    public function testRaisesTypeErrorIfServerErrorResponseGeneratorFactoryDoesNotReturnAResponse()
    {
        $serverRequestFactory = function () {
            return null;
        };

        $errorResponseGenerator = function (Throwable $e) {
            Assert::assertInstanceOf(TypeError::class, $e);
            return null;
        };

        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->shouldNotBeCalled();

        $emitter = $this->prophesize(EmitterInterface::class);
        $emitter->emit(Argument::any())->shouldNotBeCalled();

        $runner = new RequestHandlerRunner(
            $handler->reveal(),
            $emitter->reveal(),
            $serverRequestFactory,
            $errorResponseGenerator
        );

        $this->expectException(TypeError::class);
        $runner->run();
    }
}
