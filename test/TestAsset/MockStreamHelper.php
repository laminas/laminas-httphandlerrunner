<?php

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\TestAsset;

use function is_callable;
use function strlen;
use function substr;

use const SEEK_SET;

class MockStreamHelper
{
    /** @var string|callable(int,?int=null):string */
    private $contents;

    private int $startPosition;

    /** @var null|callable */
    private $trackPeakBufferLength;

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     * @param string|callable(int,?int=null):string $contents
     */
    public function __construct(
        $contents,
        private int $size,
        private int $position,
        ?callable $trackPeakBufferLength = null
    ) {
        $this->contents              = $contents;
        $this->startPosition         = $position;
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
