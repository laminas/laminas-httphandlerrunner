<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\TestAsset;

class MockStreamHelper
{
    /** @var string|callable(int,?int=null):string */
    private $contents;

    /** @var int */
    private $position;

    /** @var int */
    private $size;

    /** @var int */
    private $startPosition;

    /** @var null|callable */
    private $trackPeakBufferLength = null;

    /** @param string|callable(int,?int=null):string $contents */
    public function __construct(
        $contents,
        int $size,
        int $startPosition,
        ?callable $trackPeakBufferLength = null
    ) {
        $this->contents              = $contents;
        $this->size                  = $size;
        $this->position              = $startPosition;
        $this->startPosition         = $startPosition;
        $this->trackPeakBufferLength = $trackPeakBufferLength;
    }

    public function handleToString(): string
    {
        $this->position = $this->size;
        return is_callable($this->contents) ? ($this->contents)(0) : $this->contents;
    }

    public function handleTell(): int
    {
        return $this->position;
    }

    public function handleEof(): bool
    {
        return $this->position >= $this->size;
    }

    public function handleSeek(int $offset, ?int $whence = SEEK_SET): bool
    {
        if ($offset >= $this->size) {
            return false;
        }

        $this->position = $offset;
        return true;
    }

    public function handleRewind(): bool
    {
        $this->position = 0;
        return true;
    }

    public function handleRead(int $length): string
    {
        if ($this->trackPeakBufferLength) {
            ($this->trackPeakBufferLength)($length);
        }

        $data = is_callable($this->contents)
            ? ($this->contents)($this->position, $length)
            : substr($this->contents, $this->position, $length);

        $this->position += strlen($data);

        return $data;
    }

    public function handleGetContents(): string
    {
        $remainingContents = is_callable($this->contents)
            ? ($this->contents)($this->position)
            : substr($this->contents, $this->position);

        $this->position += strlen($remainingContents);

        return $remainingContents;
    }
}
