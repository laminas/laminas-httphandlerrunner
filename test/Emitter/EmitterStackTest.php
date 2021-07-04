<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\Emitter;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use SplStack;

class EmitterStackTest extends TestCase
{
    /** @var EmitterStack */
    private $emitter;

    public function setUp(): void
    {
        $this->emitter = new EmitterStack();
    }

    public function testIsAnSplStack(): void
    {
        $this->assertInstanceOf(SplStack::class, $this->emitter);
    }

    public function testIsAnEmitterImplementation(): void
    {
        $this->assertInstanceOf(EmitterInterface::class, $this->emitter);
    }

    /** @return iterable<string, mixed[]> */
    public function nonEmitterValues(): iterable
    {
        return [
            'null'       => [null],
            'true'       => [true],
            'false'      => [false],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'string'     => ['emitter'],
            'array'      => [[$this->createMock(EmitterInterface::class)]],
            'object'     => [(object) []],
        ];
    }

    /**
     * @dataProvider nonEmitterValues
     *
     * @param mixed $value
     */
    public function testCannotPushNonEmitterToStack($value): void
    {
        $this->expectException(Exception\InvalidEmitterException::class);
        /** @psalm-suppress MixedArgument */
        $this->emitter->push($value);
    }

    /**
     * @dataProvider nonEmitterValues
     *
     * @param mixed $value
     */
    public function testCannotUnshiftNonEmitterToStack($value): void
    {
        $this->expectException(Exception\InvalidEmitterException::class);
        /** @psalm-suppress MixedArgument */
        $this->emitter->unshift($value);
    }

    /**
     * @dataProvider nonEmitterValues
     *
     * @param mixed $value
     */
    public function testCannotSetNonEmitterToSpecificIndex($value): void
    {
        $this->expectException(Exception\InvalidEmitterException::class);
        /** @psalm-suppress MixedArgument */
        $this->emitter->offsetSet(0, $value);
    }

    public function testOffsetSetReplacesExistingValue(): void
    {
        $first = $this->createMock(EmitterInterface::class);
        $replacement = $this->createMock(EmitterInterface::class);
        $this->emitter->push($first);
        $this->emitter->offsetSet(0, $replacement);
        $this->assertSame($replacement, $this->emitter->pop());
    }

    public function testUnshiftAddsNewEmitter(): void
    {
        $first = $this->createMock(EmitterInterface::class);
        $second = $this->createMock(EmitterInterface::class);
        $this->emitter->push($first);
        $this->emitter->unshift($second);
        $this->assertSame($first, $this->emitter->pop());
    }

    public function testEmitLoopsThroughEmittersUntilOneReturnsTrueValue(): void
    {
        $first = $this->createMock(EmitterInterface::class);
        $first->expects($this->never())->method('emit');

        $second = $this->createMock(EmitterInterface::class);
        $second->method('emit')->with($this->isInstanceOf(ResponseInterface::class))->willReturn(true);

        $third = $this->createMock(EmitterInterface::class);
        $third->method('emit')->with($this->isInstanceOf(ResponseInterface::class))->willReturn(false);

        $this->emitter->push($first);
        $this->emitter->push($second);
        $this->emitter->push($third);

        $response = $this->createMock(ResponseInterface::class);

        $this->assertTrue($this->emitter->emit($response));
    }

    public function testEmitReturnsFalseIfLastEmmitterReturnsFalse(): void
    {
        $first = $this->createMock(EmitterInterface::class);
        $first->method('emit')->with($this->isInstanceOf(ResponseInterface::class))->willReturn(false);

        $this->emitter->push($first);

        $response = $this->createMock(ResponseInterface::class);

        $this->assertFalse($this->emitter->emit($response));
    }

    public function testEmitReturnsFalseIfNoEmittersAreComposed(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->assertFalse($this->emitter->emit($response));
    }
}
