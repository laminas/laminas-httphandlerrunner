# Emitters

Emitters are used to _emit_ a [PSR-7](https://www.php-fig.org/psr/psr-7)
response. This should generally happen when running under a traditional PHP
server API that uses output buffers, such as Apache or php-fpm.

Emitters are described by `Laminas\HttpHandlerRunner\Emitter\EmitterInterface`:

```php
use Psr\Http\Message\ResponseInterface;

interface EmitterInterface
{
    public function emit(ResponseInterface $response) : bool;
}
```

Typically, such emitters will perform the following:

- Emit a response status line.
- Emit all response headers.
- Emit the response body.

(The first two items may be swapped in order; many SAPIs allow emitting multiple
status lines, and will use the last one present. As such, most implementations
will emit the status line after the headers to ensure the correct one is emitted
by the SAPI.)

The `emit()` method allows returning a boolean. This value can be checked to
determine if the emitter was able to emit the response. This capability is used
by the provided `EmitterStack` to allow composing multiple emitters that can
introspect the response to determine whether or not they are capable of emitting
it.

## SapiEmitter

`Laminas\HttpHandlerRunner\Emitter\SapiEmitter` accepts the response instance, and
uses the built-in PHP function `header()` to emit both the headers as well as
the status line. It then uses `echo` to emit the response body.

Internally, it also does a number of verifications:

- If headers have been previously sent, it will raise an exception.
- If output has been previously sent, it will raise an exception.

These are performed in order to ensure the integrity of the response emitted.

It also filters header names to normalize them; this is done in part to ensure
that if multiple headers of the same name are emitted, the SAPI will report them
correctly.

This emitter can _always_ handle a response, and thus _always_ returns true.

## SapiStreamEmitter

`Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter` behaves similarly to the
`SapiEmitter`, with two key differences:

- It allows emitting a a _content range_, if a `Content-Range` header is
  specified in the response.
- It will iteratively emit a range of bytes from the response, based on the
  buffer length provided to the emitter during construction. This is
  particularly useful when returning _files_ or _downloads_.

The emitter accepts an integer argument to the constructor, indicating the
maximum buffer length; by default, this is 8192 bytes.

This emitter can _always_ handle a response, and thus _always_ returns true.

## EmitterStack

`Laminas\HttpHandlerRunner\Emitter\EmitterStack` allows providing a
last-in-first-out (LIFO) stack of emitters instead of a single emitter. If an
emitter is incapable of handling the response and returns `false`, the stack
will move to the next emitter. If an emitter returns `true`, the stack
short-circuits and immediately returns.

The `EmitterStack` extends `SplStack`, and thus allows you to add emitters using
any of the methods that class defines; we recommend adding them in LIFO order
using `push()`:

```php
$stack->push($last);
$stack->push($second);
$stack->push($first);
```

### Conditionally using the SapiStreamEmitter

The `SapiStreamEmitter` is capable of emitting any response. However, for
in-memory responses, you may want to use the more efficient `SapiEmitter`. How
can you do this?

One way is to check for response artifacts that indicate a file download, such
as the `Content-Disposition` or `Content-Range` headers; if those headers are
not present, you could return `false` from the emitter, and otherwise continue.
You could achieve this by decorating the `SapiStreamEmitter`:

```php
$sapiStreamEmitter = new SapiStreamEmitter($maxBufferLength);
$conditionalEmitter = new class ($sapiStreamEmitter) implements EmitterInterface {
    private $emitter;

    public function __construct(EmitterInterface $emitter)
    {
        $this->emitter = $emitter;
    }

    public function emit(ResponseInterface) : bool
    {
        if (! $response->hasHeader('Content-Disposition')
            && ! $response->hasHeader('Content-Range')
        ) {
            return false;
        }
        return $this->emitter->emit($response);
    }
};

$stack = new EmitterStack();
$stack->push(new SapiEmitter());
$stack->push($conditionalEmitter);
```

In this way, you can have the best of both worlds, using the memory-efficient
`SapiStreamEmitter` for large file downloads or streaming buffers, and the
general-purpose `SapiEmitter` for everything else.
