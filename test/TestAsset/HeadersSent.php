<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps


declare(strict_types=1);

namespace Laminas\HttpHandlerRunner\Emitter;

use LaminasTest\HttpHandlerRunner\TestAsset\HeaderStack;

final class HeadersSent
{
    private static bool $headerSent = false;
    /** @var null|string */
    public static $filename;
    /** @var null|int */
    public static $line;

    public static function reset(): void
    {
        self::$headerSent = false;
        self::$filename   = null;
        self::$line       = null;
    }

    public static function markSent(string $filename, int $line): void
    {
        self::$headerSent = true;
        self::$filename   = $filename;
        self::$line       = $line;
    }

    public static function sent(): bool
    {
        return self::$headerSent;
    }
}

function headers_sent(?string &$filename = null, ?int &$line = null): bool
{
    $filename = HeadersSent::$filename;
    $line     = HeadersSent::$line;
    return HeadersSent::sent();
}

/**
 * Emit a header, without creating actual output artifacts
 */
function header(string $headerName, bool $replace = true, ?int $httpResponseCode = null): void
{
    HeaderStack::push(
        [
            'header'      => $headerName,
            'replace'     => $replace,
            'status_code' => $httpResponseCode,
        ]
    );
}
