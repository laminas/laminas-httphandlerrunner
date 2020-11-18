<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\Emitter;

use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter;
use LaminasTest\HttpHandlerRunner\TestAsset\HeaderStack;
use Prophecy\Argument;
use Psr\Http\Message\StreamInterface;

use function gc_collect_cycles;
use function gc_disable;
use function gc_enable;
use function is_callable;
use function json_encode;
use function max;
use function memory_get_usage;
use function ob_end_clean;
use function ob_end_flush;
use function ob_get_clean;
use function ob_start;
use function str_repeat;
use function strlen;
use function substr;

use const SEEK_SET;

class SapiStreamEmitterTest extends AbstractEmitterTest
{
    public function setUp(): void
    {
        HeaderStack::reset();
        $this->emitter = new SapiStreamEmitter();
    }

    public function testEmitCallbackStreamResponse()
    {
        $stream = new CallbackStream(function () {
            return 'it works';
        });
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream);
        ob_start();
        $this->emitter->emit($response);
        $this->assertSame('it works', ob_get_clean());
    }

    public function testDoesNotInjectContentLengthHeaderIfStreamSizeIsUnknown()
    {
        $stream = $this->prophesize(StreamInterface::class);
        $stream->__toString()->willReturn('Content!');
        $stream->isSeekable()->willReturn(false);
        $stream->isReadable()->willReturn(false);
        $stream->eof()->willReturn(true);
        $stream->rewind()->willReturn(true);
        $stream->getSize()->willReturn(null);
        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();
        foreach (HeaderStack::stack() as $header) {
            $this->assertStringNotContainsString('Content-Length:', $header['header']);
        }
    }

    /**
    * Create a new stream prophecy and setup common promises
    *
    * @param string|callable $contents              Stream contents.
    * @param integer         $size                  Size of stream contents.
    * @param integer         $startPosition         Start position of internal stream data pointer.
    * @param callable|null   $trackPeakBufferLength Called on "read" calls.
    *                                               Receives data length (i.e. data length <= buffer length).
    * @return ObjectProphecy                        Returns new stream prophecy.
    */
    private function setUpStreamProphecy($contents, $size, $startPosition, callable $trackPeakBufferLength = null)
    {
        $position = $startPosition;

        $stream = $this->prophesize(StreamInterface::class);

        $stream
            ->__toString()
            ->will(function () use ($contents, $size, & $position) {
                $position = $size;
                return is_callable($contents) ? $contents(0) : $contents;
            });

        $stream->getSize()->willReturn($size);

        $stream->tell()->will(function () use (& $position) {
            return $position;
        });

        $stream->eof()->will(function () use ($size, & $position) {
            return ($position >= $size);
        });

        $stream
            ->seek(Argument::type('integer'), Argument::any())
            ->will(function ($args) use ($size, & $position) {
                if ($args[0] >= $size) {
                    return false;
                }

                $position = $args[0];
                return true;
            });

        $stream->rewind()->will(function () use (& $position) {
            $position = 0;
            return true;
        });

        $stream
            ->read(Argument::type('integer'))
            ->will(function ($args) use ($contents, & $position, & $trackPeakBufferLength) {
                if ($trackPeakBufferLength) {
                    $trackPeakBufferLength($args[0]);
                }

                $data = is_callable($contents)
                    ? $contents($position, $args[0])
                    : substr($contents, $position, $args[0]);

                $position += strlen($data);

                return $data;
            });

        $stream->getContents()->will(function () use ($contents, & $position) {
            $remainingContents = is_callable($contents)
                ? $contents($position)
                : substr($contents, $position);

            $position += strlen($remainingContents);

            return $remainingContents;
        });

        return $stream;
    }

    public function emitStreamResponseProvider()
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
     * @dataProvider emitStreamResponseProvider
     * @param bool    $seekable        Indicates if stream is seekable
     * @param bool    $readable        Indicates if stream is readable
     * @param string  $contents        Contents stored in stream
     * @param int     $maxBufferLength Maximum buffer length used in the emitter call.
     */
    public function testEmitStreamResponse(bool $seekable, bool $readable, string $contents, int $maxBufferLength)
    {
        $size = strlen($contents);
        $startPosition = 0;
        $peakBufferLength = 0;
        $rewindCalled = false;
        $fullContentsCalled = false;

        $stream = $this->setUpStreamProphecy(
            $contents,
            $size,
            $startPosition,
            function ($bufferLength) use (& $peakBufferLength) {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );

        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());

        ob_start();
        $emitter = new SapiStreamEmitter($maxBufferLength);
        $emitter->emit($response);
        $emittedContents = ob_get_clean();

        if ($seekable) {
            $rewindPredictionClosure = function () use (& $rewindCalled) {
                $rewindCalled = true;
            };

            $stream->rewind()->should($rewindPredictionClosure);
            $stream->seek(0)->should($rewindPredictionClosure);
            $stream->seek(0, SEEK_SET)->should($rewindPredictionClosure);
        } else {
            $stream->rewind()->shouldNotBeCalled();
            $stream->seek(Argument::type('integer'), Argument::any())->shouldNotBeCalled();
        }

        if ($readable) {
            $stream->__toString()->shouldNotBeCalled();
            $stream->read(Argument::type('integer'))->shouldBeCalled();
            $stream->eof()->shouldBeCalled();
            $stream->getContents()->shouldNotBeCalled();
        } else {
            $fullContentsPredictionClosure = function () use (& $fullContentsCalled) {
                $fullContentsCalled = true;
            };

            $stream->__toString()->should($fullContentsPredictionClosure);
            $stream->read(Argument::type('integer'))->shouldNotBeCalled();
            $stream->eof()->shouldNotBeCalled();

            if ($seekable) {
                $stream->getContents()->should($fullContentsPredictionClosure);
            } else {
                $stream->getContents()->shouldNotBeCalled();
            }
        }

        $stream->checkProphecyMethodsPredictions();

        $this->assertSame($seekable, $rewindCalled);
        $this->assertSame(! $readable, $fullContentsCalled);
        $this->assertSame($contents, $emittedContents);
        $this->assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    public function emitRangeStreamResponseProvider()
    {
        return [
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [true,   true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [true,   true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [true,   true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [true,   true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [true,  false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [true,  false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [true,  false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [true,  false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [false,  true, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [false,  true, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [false,  true, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [false,  true, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',   5],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321',  10],
            [false, false, ['bytes', 10,  20, '*'],    '01234567890987654321', 100],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',   5],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012',  10],
            [false, false, ['bytes', 10,  20, '*'], '01234567890987654321012', 100],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',   5],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321',  10],
            [false, false, ['bytes', 10, 100, '*'],    '01234567890987654321', 100],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',   5],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012',  10],
            [false, false, ['bytes', 10, 100, '*'], '01234567890987654321012', 100],
        ];
    }


    /**
     * @dataProvider emitRangeStreamResponseProvider
     * @param bool   $seekable        Indicates if stream is seekable
     * @param bool   $readable        Indicates if stream is readable
     * @param array  $range           Emitted range of data [$unit, $first, $last, $length]
     * @param string $contents        Contents stored in stream
     * @param int    $maxBufferLength Maximum buffer length used in the emitter call.
     */
    public function testEmitRangeStreamResponse(
        bool $seekable,
        bool $readable,
        array $range,
        string $contents,
        int $maxBufferLength
    ) {
        list($unit, $first, $last, $length) = $range;
        $size = strlen($contents);

        if ($readable && ! $seekable) {
            $startPosition = $first;
        } else {
            $startPosition = 0;
        }

        $peakBufferLength = 0;
        $seekCalled = false;

        $stream = $this->setUpStreamProphecy(
            $contents,
            $size,
            $startPosition,
            function ($bufferLength) use (& $peakBufferLength) {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );
        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*')
            ->withBody($stream->reveal());

        ob_start();
        $emitter = new SapiStreamEmitter($maxBufferLength);
        $emitter->emit($response);
        $emittedContents = ob_get_clean();

        $stream->rewind()->shouldNotBeCalled();

        if ($seekable) {
            $seekPredictionClosure = function () use (& $seekCalled) {
                $seekCalled = true;
            };

            $stream->seek($first)->should($seekPredictionClosure);
            $stream->seek($first, SEEK_SET)->should($seekPredictionClosure);
        } else {
            $stream->seek(Argument::type('integer'), Argument::any())->shouldNotBeCalled();
        }

        $stream->__toString()->shouldNotBeCalled();

        if ($readable) {
            $stream->read(Argument::type('integer'))->shouldBeCalled();
            $stream->eof()->shouldBeCalled();
            $stream->getContents()->shouldNotBeCalled();
        } else {
            $stream->read(Argument::type('integer'))->shouldNotBeCalled();
            $stream->eof()->shouldNotBeCalled();
            $stream->getContents()->shouldBeCalled();
        }

        $stream->checkProphecyMethodsPredictions();

        $this->assertSame($seekable, $seekCalled);
        $this->assertSame(substr($contents, $first, $last - $first + 1), $emittedContents);
        $this->assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
    }

    public function emitMemoryUsageProvider()
    {
        return [
            [true,   true,  1000,   20,       null,  512],
            [true,   true,  1000,   20,       null, 4096],
            [true,   true,  1000,   20,       null, 8192],
            [true,  false,   100,  320,       null,  512],
            [true,  false,   100,  320,       null, 4096],
            [true,  false,   100,  320,       null, 8192],
            [false,  true,  1000,   20,       null,  512],
            [false,  true,  1000,   20,       null, 4096],
            [false,  true,  1000,   20,       null, 8192],
            [false, false,   100,  320,       null,  512],
            [false, false,   100,  320,       null, 4096],
            [false, false,   100,  320,       null, 8192],
            [true,   true,  1000,   20,   [25, 75],  512],
            [true,   true,  1000,   20,   [25, 75], 4096],
            [true,   true,  1000,   20,   [25, 75], 8192],
            [false,  true,  1000,   20,   [25, 75],  512],
            [false,  true,  1000,   20,   [25, 75], 4096],
            [false,  true,  1000,   20,   [25, 75], 8192],
            [true,   true,  1000,   20, [250, 750],  512],
            [true,   true,  1000,   20, [250, 750], 4096],
            [true,   true,  1000,   20, [250, 750], 8192],
            [false,  true,  1000,   20, [250, 750],  512],
            [false,  true,  1000,   20, [250, 750], 4096],
            [false,  true,  1000,   20, [250, 750], 8192],
        ];
    }

    /**
     * @dataProvider emitMemoryUsageProvider
     * @param bool       $seekable         Indicates if stream is seekable
     * @param bool       $readable         Indicates if stream is readable
     * @param int        $sizeBlocks       Number the blocks of stream data.
     *     Block size is equal to $maxBufferLength.
     * @param int        $maxAllowedBlocks Maximum allowed memory usage in block units.
     * @param null|array $rangeBlocks      Emitted range of data in block units [$firstBlock, $lastBlock].
     * @param int        $maxBufferLength  Maximum buffer length used in the emitter call.
     */
    public function testEmitMemoryUsage(
        bool $seekable,
        bool $readable,
        int $sizeBlocks,
        int $maxAllowedBlocks,
        ?array $rangeBlocks,
        int $maxBufferLength
    ) {
        $sizeBytes = $maxBufferLength * $sizeBlocks;
        $maxAllowedMemoryUsage = $maxBufferLength * $maxAllowedBlocks;
        $peakBufferLength = 0;
        $peakMemoryUsage = 0;

        $position = 0;

        if ($rangeBlocks) {
            $first    = $maxBufferLength * $rangeBlocks[0];
            $last     = ($maxBufferLength * $rangeBlocks[1]) + $maxBufferLength - 1;

            if ($readable && ! $seekable) {
                $position = $first;
            }
        }

        $closureTrackMemoryUsage = function () use (& $peakMemoryUsage) {
            $peakMemoryUsage = max($peakMemoryUsage, memory_get_usage());
        };

        $stream = $this->setUpStreamProphecy(
            function ($position, $length = null) use (& $sizeBytes) {
                if (! $length) {
                    $length = $sizeBytes - $position;
                }

                return str_repeat('0', $length);
            },
            $sizeBytes,
            $position,
            function ($bufferLength) use (& $peakBufferLength) {
                if ($bufferLength > $peakBufferLength) {
                    $peakBufferLength = $bufferLength;
                }
            }
        );
        $stream->isSeekable()->willReturn($seekable);
        $stream->isReadable()->willReturn($readable);

        $response = (new Response())
            ->withStatus(200)
            ->withBody($stream->reveal());


        if ($rangeBlocks) {
            $response = $response->withHeader('Content-Range', 'bytes ' . $first . '-' . $last . '/*');
        }

        ob_start(
            function () use (& $closureTrackMemoryUsage) {
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

        $this->assertLessThanOrEqual($maxBufferLength, $peakBufferLength);
        $this->assertLessThanOrEqual($maxAllowedMemoryUsage, $peakMemoryUsage - $localMemoryUsage);
    }

    public function testEmitEmptyResponse()
    {
        $response = (new EmptyResponse())
            ->withStatus(204);

        ob_start();
        $this->emitter->emit($response);
        $this->assertEmpty($response->getHeaderLine('content-type'));
        $this->assertEmpty(ob_get_clean());
    }

    public function testEmitHtmlResponse()
    {
        $contents = '<!DOCTYPE html>'
            . '<html>'
            . '    <body>'
            . '        <h1>Hello world</h1>'
            . '    </body>'
            . '</html>';

        $response = (new HtmlResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('content-type'));
        $this->assertSame($contents, ob_get_clean());
    }

    public function emitJsonResponseProvider()
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
     * @dataProvider emitJsonResponseProvider
     * @param mixed $contents Contents stored in stream
     */
    public function testEmitJsonResponse($contents)
    {
        $response = (new JsonResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        $this->assertSame('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame(json_encode($contents), ob_get_clean());
    }

    public function testEmitTextResponse()
    {
        $contents = 'Hello world';

        $response = (new TextResponse($contents))
            ->withStatus(200);

        ob_start();
        $this->emitter->emit($response);
        $this->assertSame('text/plain; charset=utf-8', $response->getHeaderLine('content-type'));
        $this->assertSame($contents, ob_get_clean());
    }

    public function contentRangeProvider()
    {
        return [
            ['bytes 0-2/*', 'Hello world', 'Hel'],
            ['bytes 3-6/*', 'Hello world', 'lo w'],
            ['items 0-0/1', 'Hello world', 'Hello world'],
        ];
    }

    /**
     * @dataProvider contentRangeProvider
     */
    public function testContentRange(string $header, string $body, string $expected)
    {
        $response = (new Response())
            ->withHeader('Content-Range', $header);

        $response->getBody()->write($body);

        ob_start();
        $this->emitter->emit($response);
        $this->assertSame($expected, ob_get_clean());
    }

    public function testContentRangeUnseekableBody()
    {
        $body = new CallbackStream(function () {
            return 'Hello world';
        });
        $response = (new Response())
            ->withBody($body)
            ->withHeader('Content-Range', 'bytes 3-6/*');

        ob_start();
        $this->emitter->emit($response);
        $this->assertSame('lo w', ob_get_clean());
    }
}
