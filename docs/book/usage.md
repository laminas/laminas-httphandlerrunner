# Installation and Usage

To install this package, run the following [composer](https://getcomposer.org)
command:

```bash
$ composer require laminas/laminas-httphandlerrunner
```

The package provides both [emitters](emitters.md) and the [request handler
runner](runner.md), and these are generally used within the bootstrap of your
application.

We recommend using a dependency injection container to define your various
instances, including the [PSR-15](https://www.php-fig.org/psr/psr-15) request
handler representing your application, the response emitter, the server request
factory, the server request error response generator, potentially, the runner
itself.

The example below instantiates the runner manually by pulling its dependencies
from a configured [PSR-11](https://www.php-fig.org/psr/psr-11) container.

```php
use Laminas\HttpHandlerRunner\Emitter\EmitterStack;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;

$container = require 'config/container.php';

$runner = new RequestHandlerRunner(
    $container->get(ApplicationRequestHandler::class),
    $container->get(EmitterStack::class),
    $container->get('ServerRequestFactory'),
    $container->get('ServerRequestErrorResponseGenerator')
);
$runner->run();
```
