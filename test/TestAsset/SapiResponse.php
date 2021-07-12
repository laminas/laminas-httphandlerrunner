<?php

/**
 * @see       https://github.com/laminas/laminas-httphandlerrunner for the canonical source repository
 * @copyright https://github.com/laminas/laminas-httphandlerrunner/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

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
function header(string $header, bool $replace = true, int $http_response_code = null): void
{
    HeaderStack::push(
        [
            'header'      => $header,
            'replace'     => $replace,
            'status_code' => $http_response_code,
        ]
    );
}
