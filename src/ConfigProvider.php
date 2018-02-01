<?php
/**
 * @see       https://github.com/zendframework/zend-httphandlerrunner for the canonical source repository
 * @copyright Copyright (c) 2018 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-httphandlerrunner/blob/master/LICENSE.md New BSD License
 */

namespace Zend\HttpHandlerRunner;

class ConfigProvider
{
    public function __invoke() : array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies() : array
    {
        return [
        ];
    }
}
