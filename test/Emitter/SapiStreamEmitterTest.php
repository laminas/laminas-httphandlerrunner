<?php

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\Emitter;

use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\HttpHandlerRunner\Emitter\HeadersSent;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use Laminas\HttpHandlerRunner\Exception\EmitterException;
use LaminasTest\HttpHandlerRunner\TestAsset\HeaderStack;
use LaminasTest\HttpHandlerRunner\TestAsset\MockStreamHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use function gc_collect_cycles;
use function gc_disable;
use function gc_enable;
use function is_int;
use function json_encode;
use function max;
use function memory_get_usage;
use function ob_end_clean;
use function ob_end_flush;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

/**
 * @psalm-import-type ParsedRangeType from SapiStreamEmitter
 */
class SapiStreamEmitterTest extends TestCase
{
    private SapiStreamEmitter $emitter;

    protected function setUp(): void
    {
        HeaderStack::reset();
        HeadersSent::reset();
        $this->emitter = new SapiStreamEmitter();
    }

    protected function tearDown(): void
    {
        HeaderStack::reset();
        HeadersSent::reset();
    }

    public function testEmitsResponseHeaders(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();

        self::assertTrue(HeaderStack::has('HTTP/1.1 200 OK'));
        self::assertTrue(HeaderStack::has('Content-Type: text/plain'));
    }

    public function testEmitsMessageBody(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');
        $this->emitter->emit($response);
    }

    public function testMultipleSetCookieHeadersAreNotReplaced(): void
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Set-Cookie', 'foo=bar')
            ->withAddedHeader('Set-Cookie', 'bar=baz');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Set-Cookie: foo=bar', 'replace' => false, 'status_code' => 200],
            ['header' => 'Set-Cookie: bar=baz', 'replace' => false, 'status_code' => 200],
            ['header' => 'HTTP/1.1 200 OK', 'replace' => true, 'status_code' => 200],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testDoesNotLetResponseCodeBeOverriddenByPHP(): void
    {
        $response = (new Response())
            ->withStatus(202)
            ->withAddedHeader('Location', 'http://api.my-service.com/12345678')
            ->withAddedHeader('Content-Type', 'text/plain');

        $this->emitter->emit($response);

        $expectedStack = [
            ['header' => 'Location: http://api.my-service.com/12345678', 'replace' => true, 'status_code' => 202],
            ['header' => 'Content-Type: text/plain', 'replace' => true, 'status_code' => 202],
            ['header' => 'HTTP/1.1 202 Accepted', 'replace' => true, 'status_code' => 202],
        ];

        self::assertSame($expectedStack, HeaderStack::stack());
    }

    public function testWillThrowEmitterExceptionWhenHeadersAreAlreadySent(): void
    {
        $sentInLine = __LINE__;
        HeadersSent::markSent(__FILE__, $sentInLine);

        $this->expectException(EmitterException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Unable to emit response; headers already sent in %s:%d',
                __FILE__,
                $sentInLine
            )
        );
        $this->emitter->emit($this->createMock(ResponseInterface::class));
    }

    public function testEmitCallbackStreamResponse(): void
    {
        $emitter  = new SapiStreamEmitter();
        $stream   = new CallbackStream(static fn(): string => 'it works');
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);
        ob_start();
        $result = $emitter->emit($response);
        self::assertTrue($result);
        self::assertSame('it works', ob_get_clean());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('Content!');
        $stream->method('isSeekable')->willReturn(false);
        $stream->method('isReadable')->willReturn(false);
        $stream->method('eof')->willReturn(true);
        $stream->method('getSize')->willReturn(null);
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();
        foreach (HeaderStack::stack() as $header) {
            self::assertStringNotContainsString('Content-Length:', $header['header']);
        }
    }

    /**
     * @psalm-return array<array-key, array{0: bool, 1: bool, 2: string, 3: int}>
     */
    public static function emitStreamResponseProvider(): array
    {
        return [
            [true,   true,    '01234567890987654321',   10],
            [true,   true,    '01234567890987654321',   20],
            [true,   true,    '01234567890987654321',  100],
            [true,   true, '01234567890987654321012',   10],
            [true,   true, '01234567890987654321012',   20],
            [true,   true, '01234567890987654321012',  100],
            [true,  false,    '01234567890987654321',   10],
            [true,  false,    '01234567890987654321',   20],
            [true,  false,    '01234567890987654321',  100],
            [true,  false, '01234567890987654321012',   10],
            [true,  false, '01234567890987654321012',   20],
            [true,  false, '01234567890987654321012',  100],
            [false,  true,    '01234567890987654321',   10],
            [false,  true,    '01234567890987654321',   20],
            [false,  true,    '01234567890987654321',  100],
            [false,  true, '01234567890987654321012',   10],
            [false,  true, '01234567890987654321012',   20],
            [false,  true, '01234567890987654321012',  100],
            [false, false,    '01234567890987654321',   10],
            [false, false,    '01234567890987654321',   20],
            [false, false,    '01234567890987654321',  100],
            [false, false, '01234567890987654321012',   10],
            [false, false, '01234567890987654321012',   20],
            [false, false, '01234567890987654321012',  100],
        ];
    }

    /**
     * @param bool    $seekable        Indicates if stream is seekable
     * @param bool    $readable        Indicates if stream is readable
     * @param string  $contents        Contents stored in stream
     * @param int     $maxBufferLength Maximum buffer length used in the emitter call.
     */
    #[DataProvider('emitStreamResponseProvider')]
    public function testEmitStreamResponse(bool $seekable, bool $readable, string $contents, int $maxBufferLength): void
    {
        $startPosition    = 0;
        $peakBufferLength = 0;

        $streamHelper = new MockStreamHelper(
            $contents,
            strlen($contents),
            $startPosition,
            static function (int $bufferLength) use (&$peakBufferLength): void {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('isReadable')->willReturn($readable);

        if ($seekable) {
            $stream
                ->expects($this->atLeastOnce())
                ->method('rewind')
                ->willReturnCallback([$streamHelper, 'handleRewind']);
            $stream->method('seek')->willReturnCallback([$streamHelper, 'handleSeek']);
        }

        if (! $seekable) {
            $stream->expects($this->never())->method('rewind');
            $stream->expects($this->never())->method('seek');
        }

        if ($readable) {
            $stream->expects($this->never())->method('__toString');
            $stream->method('eof')->willReturnCallback([$streamHelper, 'handleEof']);
            $stream->method('read')->willReturnCallback([$streamHelper, 'handleRead']);
        }

        if (! $readable) {
            $stream->expects($this->never())->method('read');
            $stream->expects($this->never())->method('eof');

            $seekable
                ? $stream
                    ->method('getContents')
                    ->willReturnCallback([$streamHelper, 'handleGetContents'])
                : $stream->expects($this->never())->method('getContents');

            $stream->method('__toString')->willReturnCallback([$streamHelper, 'handleToString']);
        }

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        ob_start();
        $emitter = new SapiStreamEmitter($maxBufferLength);
        $emitter->emit($response);
        $emittedContents = ob_get_clean();

        self::assertSame($contents, $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    /**
     * @psalm-return array<array-key, array{
     *     0: bool,
     *     1: bool,
     *     2: ParsedRangeType,
     *     3: string,
     *     4: int
     * }>
     */
    public static function emitRangeStreamResponseProvider(): array
    {
        return [
            // seekable, readable,                   range,                 contents, max buffer length
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321', 5],
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321', 10],
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321', 100],
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 5],
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 10],
            [true, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 100],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321', 5],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321', 10],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321', 100],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 5],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 10],
            [true, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321', 5],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321', 10],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321', 100],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 5],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 10],
            [true, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 100],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321', 5],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321', 10],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321', 100],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 5],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 10],
            [true, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321', 5],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321', 10],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321', 100],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 5],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 10],
            [false, true, ['bytes', 10, 20, '*'], '01234567890987654321012', 100],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321', 5],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321', 10],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321', 100],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 5],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 10],
            [false, true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321', 5],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321', 10],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321', 100],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 5],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 10],
            [false, false, ['bytes', 10, 20, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321', 5],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321', 10],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321', 100],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 5],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 10],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
        ];
    }

    /**
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param array  $range           Emitted range of data [$unit, $first, $last, $length]
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength Maximum buffer length used in the emitter call.
     * @psalm-param ParsedRangeType $range
     */
    #[DataProvider('emitRangeStreamResponseProvider')]
    public function testEmitRangeStreamResponse(
        bool $seekable,
        bool $readable,
        array $range,
        string $contents,
        int $maxBufferLength
    ): void {
        [, $first, $last] = $range;
        $size             = strlen($contents);

        $startPosition = $readable && ! $seekable
            ? $first
            : 0;

        $peakBufferLength = 0;

        $trackPeakBufferLength = static function (int $bufferLength) use (&$peakBufferLength): void {
            if ($bufferLength > $peakBufferLength) {
                $peakBufferLength = $bufferLength;
            }
        };

        $streamHelper = new MockStreamHelper(
            $contents,
            $size,
            $startPosition,
            $trackPeakBufferLength
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('isReadable')->willReturn($readable);
        $stream->method('getSize')->willReturn($size);
        $stream->method('tell')->willReturnCallback([$streamHelper, 'handleTell']);

        $stream->expects($this->never())->method('rewind');

        if ($seekable) {
            $stream
                ->expects($this->atLeastOnce())
                ->method('seek')
                ->willReturnCallback([$streamHelper, 'handleSeek']);
        } else {
            $stream->expects($this->never())->method('seek');
        }

        $stream->expects($this->never())->method('__toString');

        if ($readable) {
            $stream
                ->expects($this->atLeastOnce())
                ->method('read')
                ->with($this->isType('int'))
                ->willReturnCallback([$streamHelper, 'handleRead']);
            $stream
                ->expects($this->atLeastOnce())
                ->method('eof')
                ->willReturnCallback([$streamHelper, 'handleEof']);
            $stream->expects($this->never())->method('getContents');
        } else {
            $stream->expects($this->never())->method('read');
            $stream->expects($this->never())->method('eof');
            $stream
                ->expects($this->atLeastOnce())
                ->method('getContents')
                ->willReturnCallback([$streamHelper, 'handleGetContents']);
        }

        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*')
            ->withBody($stream);

        ob_start();
        $emitter = new SapiStreamEmitter($maxBufferLength);
        $emitter->emit($response);
        $emittedContents = ob_get_clean();

        self::assertSame(substr($contents, $first, $last - $first + 1), $emittedContents);
        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    /**
     * @psalm-return array<array-key, array{
     *     0: bool,
     *     1: bool,
     *     2: int,
     *     3: int,
     *     4: null|array{0: int, 1: int},
     *     5: int
     * }>
     */
    public static function emitMemoryUsageProvider(): array
    {
        return [
            // seekable, readable,  size,  max,      range, max-length
            [true,           true,  1000,   20,       null,  512],
            [true,           true,  1000,   20,       null, 4096],
            [true,           true,  1000,   20,       null, 8192],
            [true,          false,   100,  320,       null,  512],
            [true,          false,   100,  320,       null, 4096],
            [true,          false,   100,  320,       null, 8192],
            [false,          true,  1000,   20,       null,  512],
            [false,          true,  1000,   20,       null, 4096],
            [false,          true,  1000,   20,       null, 8192],
            [false,         false,   100,  320,       null,  512],
            [false,         false,   100,  320,       null, 4096],
            [false,         false,   100,  320,       null, 8192],
            [true, true, 1000, 20, [25, 75], 512],
            [true, true, 1000, 20, [25, 75], 4096],
            [true, true, 1000, 20, [25, 75], 8192],
            [false, true, 1000, 20, [25, 75], 512],
            [false, true, 1000, 20, [25, 75], 4096],
            [false, true, 1000, 20, [25, 75], 8192],
            [true, true, 1000, 20, [250, 750], 512],
            [true, true, 1000, 20, [250, 750], 4096],
            [true, true, 1000, 20, [250, 750], 8192],
            [false, true, 1000, 20, [250, 750], 512],
            [false, true, 1000, 20, [250, 750], 4096],
            [false, true, 1000, 20, [250, 750], 8192],
        ];
    }

    /**
     * @param bool       $seekable         Indicates if stream is seekable
     * @param bool       $readable         Indicates if stream is readable
     * @param int        $sizeBlocks       Number the blocks of stream data.
     *     Block size is equal to $maxBufferLength.
     * @param int        $maxAllowedBlocks Maximum allowed memory usage in block units.
     * @param null|array $rangeBlocks      Emitted range of data in block units [$firstBlock, $lastBlock].
     * @param int        $maxBufferLength  Maximum buffer length used in the emitter call.
     * @psalm-param array{0:int,1:int}|null $rangeBlocks
     */
    #[DataProvider('emitMemoryUsageProvider')]
    public function testEmitMemoryUsage(
        bool $seekable,
        bool $readable,
        int $sizeBlocks,
        int $maxAllowedBlocks,
        ?array $rangeBlocks,
        int $maxBufferLength
    ): void {
        $sizeBytes             = $maxBufferLength * $sizeBlocks;
        $maxAllowedMemoryUsage = $maxBufferLength * $maxAllowedBlocks;
        $peakBufferLength      = 0;
        $peakMemoryUsage       = 0;

        $position = 0;

        $first = null;
        $last  = null;

        if ($rangeBlocks !== null) {
            $first = $maxBufferLength * $rangeBlocks[0];
            $last  = ($maxBufferLength * $rangeBlocks[1]) + $maxBufferLength - 1;

            if ($readable && ! $seekable) {
                $position = $first;
            }
        }

        $closureTrackMemoryUsage = static function () use (&$peakMemoryUsage): void {
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_usage());
        };

        $contentsCallback = static function (int $position, ?int $length = null) use (&$sizeBytes): string {
            self::assertIsInt($sizeBytes);
            if ($length === null) {
                $length = $sizeBytes - $position;
            }

            return str_repeat('0', $length);
        };

        $trackPeakBufferLength = static function (int $bufferLength) use (&$peakBufferLength): void {
            if ($bufferLength > $peakBufferLength) {
                $peakBufferLength = $bufferLength;
            }
        };

        self::assertIsInt($sizeBytes);
        self::assertIsInt($position);
        $streamHelper = new MockStreamHelper(
            $contentsCallback,
            $sizeBytes,
            $position,
            $trackPeakBufferLength
        );

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('isSeekable')->willReturn($seekable);
        $stream->method('isReadable')->willReturn($readable);
        $stream->method('eof')->willReturnCallback([$streamHelper, 'handleEof']);

        if ($seekable) {
            $stream
                ->method('seek')
                ->willReturnCallback([$streamHelper, 'handleSeek']);
        }

        if ($readable) {
            $stream
                ->method('read')
                ->willReturnCallback([$streamHelper, 'handleRead']);
        }

        if (! $readable) {
            $stream
                ->method('getContents')
                ->willReturnCallback([$streamHelper, 'handleGetContents']);
        }

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);

        if (is_int($first) && is_int($last)) {
            $response = $response->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*');
        }

        ob_start(
            static function () use (&$closureTrackMemoryUsage): string {
                $closureTrackMemoryUsage();
                return '';
            },
            $maxBufferLength
        );

        gc_collect_cycles();

        gc_disable();

        $emitter = new SapiStreamEmitter($maxBufferLength);
        $emitter->emit($response);

        ob_end_flush();

        gc_enable();

        gc_collect_cycles();

        $localMemoryUsage = memory_get_usage();

        self::assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
        self::assertLessThanOrEqual($maxAllowedMemoryUsage, $peakMemoryUsage - $localMemoryUsage);
    }

    public function testEmitEmptyResponse(): void
    {
        $response = (new EmptyResponse())
            ->withStatus(204);

        ob_start();
        $this->emitter->emit($response);
        self::assertEmpty($response->getHeaderLine('content-type'));
        self::assertEmpty(ob_get_clean());
    }

    public function testEmitHtmlResponse(): void
    {
        $contents = <<<'HTML'
            <!DOCTYPE html>'
            <html>
                <body>
                    <h1>Hello world</h1>
                </body>
            </html>
            HTML;

        $response = (new HtmlResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertSame($contents, ob_get_clean());
    }

    /**
     * @psalm-return array<array-key, array>
     */
    public static function emitJsonResponseProvider(): array
    {
        // @codingStandardsIgnoreStart
        return [
            [0.1                                                                         ],
            ['test'                                                                      ],
            [true                                                                        ],
            [1                                                                           ],
            [['key1' => 'value1']                                                        ],
            [null                                                                        ],
            [[[0.1, 0.2], ['test', 'test2'], [true, false], ['key1' => 'value1'], [null]]],
        ];
        // @codingStandardsIgnoreEnd
    }

    /**
     * @param mixed $contents Contents stored in stream
     */
    #[DataProvider('emitJsonResponseProvider')]
    public function testEmitJsonResponse(mixed $contents): void
    {
        $response = (new JsonResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertSame(json_encode($contents), ob_get_clean());
    }

    public function testEmitTextResponse(): void
    {
        $contents = 'Hello world';

        $response = (new TextResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        self::assertSame('text/plain; charset=utf-8', $response->getHeaderLine('content-type'));
        self::assertSame($contents, ob_get_clean());
    }

    /**
     * @psalm-return array<array-key, array{0: string, 1: string, 2: string}>
     */
    public static function contentRangeProvider(): array
    {
        return [
            ['bytes 0-2/*', 'Hello world', 'Hel'],
            ['bytes 3-6/*', 'Hello world', 'lo w'],
            ['items 0-0/1', 'Hello world', 'Hello world'],
        ];
    }

    #[DataProvider('contentRangeProvider')]
    public function testContentRange(string $header, string $body, string $expected): void
    {
        $response = (new Response())
            ->withHeader('Content-Range', $header);

        $response->getBody()->write($body);

        ob_start();
        $this->emitter->emit($response);
        self::assertSame($expected, ob_get_clean());
    }

    public function testContentRangeUnseekableBody(): void
    {
        $body     = new CallbackStream(static fn(): string => 'Hello world');
        $response = (new Response())
            ->withBody($body)
            ->withHeader('Content-Range', 'bytes 3-6/*');

        ob_start();
        $this->emitter->emit($response);
        self::assertSame('lo w', ob_get_clean());
    }
}
