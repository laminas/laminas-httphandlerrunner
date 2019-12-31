# laminas-httphandlerrunner

This component provides  utilities for:

- Emitting [PSR-7](https://www.php-fig.org/psr/psr-7) responses.
- Running [PSR-15](https://www.php-fig.org/psr/psr-15) server request handlers,
  which involves marshaling a PSR-7 `ServerRequestInterface`, handling
  exceptions due to request creation, and emitting the response returned by the
  composed request handler.

The `RequestHandlerRunner` will be used in the bootstrap of your application to
fire off the `RequestHandlerInterface` representing your application.
