# Changelog

All notable changes to this project will be documented in this file, in reverse chronological order by release.

## 1.1.0 - TBD

### Added

- Nothing.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.2 - 2019-02-19

### Added

- [#9](https://github.com/zendframework/zend-httphandlerrunner/pull/9) adds support for PHP 7.3.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.1 - 2018-02-21

### Added

- Nothing.

### Changed

- [#2](https://github.com/zendframework/zend-httphandlerrunner/pull/2) modifies
  how the request and error response factories are composed with the
  `RequestHandlerRunner` class. In both cases, they are now encapsulated in a
  closure which also defines a return type hint, ensuring that if the factories
  produce an invalid return type, a PHP `TypeError` will be raised.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.

## 1.0.0 - 2018-02-05

Initial stable release.

The `Zend\HttpRequestHandler\Emitter` subcomponent was originally released as
part of two packages:

- `EmitterInterface` and the two SAPI emitter implementations were released
  previously as part of the [zend-diactoros](https://docs.zendframework.com/zend-daictoros)
  package.

- `EmitterStack` was previously released as part of the
  [zend-expressive](https://docs.zendframework.com/zend-expressive/) package.

These features are mostly verbatim from that package, with minor API changes.

The `RequestHandlerRunner` was originally developed as part of version 3
development of zend-expressive, but extracted here for general use with
[PSR-15](https://www.php-fig.org/psr/psr-15) applications.

### Added

- Everything.

### Changed

- Nothing.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Nothing.
