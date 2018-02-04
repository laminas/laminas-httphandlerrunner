<?php
/**
 * @see       https://github.com/zendframework/zend-httphandlerrunner for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Zend\HttpHandlerRunner\Exception;

use InvalidArgumentException;
use Zend\HttpHandlerRunner\Emitter;

class InvalidEmitterException extends InvalidArgumentException implements ExceptionInterface
{
    /**
     * @var mixed $emitter Invalid emitter type
     */
    public static function forEmitter($emitter) : self
    {
        return new self(sprintf(
            '%s can only compose %s implementations; received %s',
            Emitter\EmitterStack::class,
            Emitter\EmitterInterface::class,
            is_object($emitter) ? get_class($emitter) : gettype($emitter)
        ));
    }
}
