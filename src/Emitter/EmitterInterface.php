<?php
/**
 * @see       https://github.com/zendframework/zend-serverhandler-runner for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-serverhandler-runner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ServerHandler\Runner\Emitter;

use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    /**
     * Emit a response.
     *
     * Emits a response, including status line, headers, and the message body,
     * according to the environment.
     *
     * Implementations of this method may be written in such a way as to have
     * side effects, such as usage of header() or pushing output to the
     * output buffer.
     *
     * Implementations MAY raise exceptions if they are unable to emit the
     * response; e.g., if headers have already been sent.
     */
    public function emit(ResponseInterface $response) : void;
}
