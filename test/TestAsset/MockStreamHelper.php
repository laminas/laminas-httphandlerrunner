<?php

declare(strict_types=1);

namespace LaminasTest\HttpHandlerRunner\TestAsset;

use function is_callable;
use function strlen;
use function substr;

use const SEEK_SET;

/**
 * @psalm-suppress PossiblyUnusedMethod
 * @psalm-suppress PossiblyUnusedParam
 */
class MockStreamHelper
{
    /** @var string|callable(int,?int=null):string */
    private $contents;

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
        $this->trackPeakBufferLength = $trackPeakBufferLength;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleToString(): string
    {
        $this->position = $this->size;
        return is_callable($this->contents) ? ($this->contents)(0) : $this->contents;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleTell(): int
    {
        return $this->position;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleEof(): bool
    {
        return $this->position >= $this->size;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleSeek(int $offset, ?int $whence = SEEK_SET): bool
    {
        if ($offset >= $this->size) {
            return false;
        }

        $this->position = $offset;
        return true;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleRewind(): bool
    {
        $this->position = 0;
        return true;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleRead(int $length): string
    {
        if ($this->trackPeakBufferLength !== null) {
            ($this->trackPeakBufferLength)($length);
        }

        $data = is_callable($this->contents)
            ? ($this->contents)($this->position, $length)
            : substr($this->contents, $this->position, $length);

        $this->position += strlen($data);

        return $data;
    }

    /** @psalm-suppress PossiblyUnusedReturnValue */
    public function handleGetContents(): string
    {
        $remainingContents = is_callable($this->contents)
            ? ($this->contents)($this->position)
            : substr($this->contents, $this->position);

        $this->position += strlen($remainingContents);

        return $remainingContents;
    }
}
