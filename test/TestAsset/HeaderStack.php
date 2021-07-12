<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\HttpHandlerRunner\TestAsset;

/**
 * Store output artifacts
 * @psalm-type HeaderType = array{header:string,replace:bool,status_code:int|null}
 */
class HeaderStack
{
    /**
     * @var string[][]
     * @psalm-var list<HeaderType>
     */
    private static $data = [];

    /**
     * Reset state
     */
    public static function reset(): void
    {
        self::$data = [];
    }

    /**
     * Push a header on the stack
     *
     * @param string[] $header
     * @psalm-param HeaderType $header
     */
    public static function push(array $header): void
    {
        self::$data[] = $header;
    }

    /**
     * Return the current header stack
     *
     * @return string[][]
     * @psalm-return list<HeaderType>
     */
    public static function stack()
    {
        return self::$data;
    }

    /**
     * Verify if there's a header line on the stack
     *
     * @param string $header
     *
     * @return bool
     */
    public static function has(string $header): bool
    {
        foreach (self::$data as $item) {
            if ($item['header'] === $header) {
                return true;
            }
        }

        return false;
    }
}
