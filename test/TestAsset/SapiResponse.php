<?php

namespace Laminas\HttpHandlerRunner\Emitter;

use LaminasTest\HttpHandlerRunner\TestAsset\HeaderStack;

/**
 * Have headers been sent?
 *
 * @return false
 */
function headers_sent()
{
    return false;
}

/**
 * Emit a header, without creating actual output artifacts
 *
 * @param string   $string
 * @param bool     $replace
 * @param int|null $http_response_code
 */
function header($string, $replace = true, $http_response_code = null)
{
    HeaderStack::push(
        [
            'header'      => $string,
            'replace'     => $replace,
            'status_code' => $http_response_code,
        ]
    );
}
