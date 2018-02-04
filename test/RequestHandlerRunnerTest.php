<?php
/**
 * @see       https://github.com/zendframework/zend-httphandlerrunner for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\HttpHandlerRunner;

use Exception;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;

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
}
