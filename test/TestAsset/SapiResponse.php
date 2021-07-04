<?php // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps


declare(strict_types=1);

namespace Laminas\HttpHandlerRunner\Emitter;

use LaminasTest\HttpHandlerRunner\TestAsset\HeaderStack;

/**
 * Have headers been sent?
 *
 * @return false
 */
function headers_sent(): bool
{
    return false;
}

/**
 * Emit a header, without creating actual output artifacts
 */
function header(string $header, bool $replace = true, ?int $httpResponseCode = null): void
{
    HeaderStack::push(
        [
            'header'      => $header,
            'replace'     => $replace,
            'status_code' => $httpResponseCode,
        ]
    );
}
