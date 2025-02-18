<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\QueryReflection;

use PHPStan\Php\PhpVersion;

final class RuntimeConfiguration
{
    /**
     * methods should return `false` on error.
     */
    public const ERROR_MODE_BOOL = 'bool';
    /**
     * methods will throw exceptions on errors, therefore will never return `false`.
     */
    public const ERROR_MODE_EXCEPTION = 'exception';
    /**
     * use whatever the configured php-src version uses per default.
     *
     * @see https://phpstan.org/config-reference#phpversion
     */
    public const ERROR_MODE_DEFAULT = 'default';

    /**
     * @var self::ERROR_MODE*
     */
    private $errorMode = self::ERROR_MODE_DEFAULT;
    /**
     * @var bool
     */
    private $debugMode = false;

    public static function create(): self
    {
        return new self();
    }

    /**
     * @param self::ERROR_MODE* $mode
     */
    public function errorMode(string $mode): self
    {
        $this->errorMode = $mode;

        return $this;
    }

    public function debugMode(bool $mode): self
    {
        $this->debugMode = $mode;

        return $this;
    }

    public function isDebugEnabled(): bool
    {
        return $this->debugMode;
    }

    public function throwsPdoExceptions(PhpVersion $phpVersion): bool
    {
        if (self::ERROR_MODE_EXCEPTION === $this->errorMode) {
            return true;
        }
        if (self::ERROR_MODE_BOOL === $this->errorMode) {
            return false;
        }

        // since php8 the pdo php-src default error mode changed to exception
        return $phpVersion->getVersionId() >= 80000;
    }

    public function throwsMysqliExceptions(PhpVersion $phpVersion): bool
    {
        if (self::ERROR_MODE_EXCEPTION === $this->errorMode) {
            return true;
        }
        if (self::ERROR_MODE_BOOL === $this->errorMode) {
            return false;
        }

        // since php8.1 the mysqli php-src default error mode changed to exception
        return $phpVersion->getVersionId() >= 80100;
    }
}
