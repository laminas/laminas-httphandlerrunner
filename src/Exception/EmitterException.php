<?php
/**
 * @see       https://github.com/zendframework/zend-serverhandler-runner for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-serverhandler-runner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\ServerHandler\Runner\Exception;

use RuntimeException;

class EmitterException extends RuntimeException implements ExceptionInterface
{
    public static function forHeadersSent() : self
    {
        return new self('Unable to emit response; headers already sent');
    }

    public static function forOutputSent() : self
    {
        return new self('Output has been emitted previously; cannot emit response');
    }
}
