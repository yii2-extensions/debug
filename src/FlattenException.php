<?php

declare(strict_types=1);

namespace yii\debug;

use __PHP_Incomplete_Class;
use Exception;

use function array_pop;
use function explode;
use function get_class;
use function get_object_vars;
use function get_resource_type;
use function implode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function strlen;
use function strpos;
use function substr;

/**
 * FlattenException wraps a PHP Exception to be able to serialize it.
 *
 * Implements the Throwable interface.
 *
 * Basically, this class removes all objects from the trace.
 *
 * Ported from Symfony components @link https://github.com/symfony/symfony/blob/master/src/Symfony/Component/Debug/Exception/FlattenException.php
 */
class FlattenException
{
    /**
     * Exception code; native PHP exceptions allow non-int values (DOMException), so we keep the wider type.
     */
    protected mixed $code = 0;
    /**
     * File path where the exception was created.
     */
    protected string $file = '';
    /**
     * Line number where the exception was created.
     */
    protected int $line = 0;
    /**
     * Exception message.
     */
    protected string $message = '';
    /**
     * FQCN of the original exception class.
     */
    private string $_class = '';
    /**
     * Previous flattened exception, when the original carried a `getPrevious()` chain.
     */
    private FlattenException|null $_previous = null;
    /**
     * Cached `__toString()` of the original exception (preserves the native trace formatting).
     */
    private string $_toString = '';
    /**
     * Flattened stack trace where each entry is a `{namespace, short_class, class, type, function, file, line, args}`
     * shape, with `args` recursively flattened via {@see flattenArgs()}.
     *
     * @var list<array{
     *     namespace: string,
     *     short_class: string,
     *     class: string,
     *     type: string,
     *     function: string|null,
     *     file: string|null,
     *     line: int|null,
     *     args: array<int|string, array{0: string, 1: mixed}>|array{0: string, 1: string},
     * }>
     */
    private array $_trace = [];

    public function __construct(Exception $exception)
    {
        $this->setMessage($exception->getMessage());
        $this->setCode($exception->getCode());
        $this->setFile($exception->getFile());
        $this->setLine($exception->getLine());
        $this->setTrace($exception->getTrace());
        $this->setToString($exception->__toString());
        $this->setClass(get_class($exception));

        $previous = $exception->getPrevious();

        if ($previous instanceof Exception) {
            $this->setPrevious(new self($previous));
        }
    }

    /**
     * String representation of the exception.
     */
    public function __toString(): string
    {
        return $this->_toString;
    }

    /**
     * Returns the name of the class in which the exception was created.
     */
    public function getClass(): string
    {
        return $this->_class;
    }

    /**
     * Gets the exception code. Native PHP exceptions allow non-int values (`DOMException::$code` is a string), so the
     * return type stays wide.
     */
    public function getCode(): mixed
    {
        return $this->code;
    }

    /**
     * Gets the file in which the exception occurred.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Gets the line in which the exception occurred.
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Gets the exception message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the previous flattened exception or `null` when the original had no `getPrevious()` chain.
     */
    public function getPrevious(): self|null
    {
        return $this->_previous;
    }

    /**
     * Gets the flattened stack trace.
     *
     * Each frame's `args` is either a list of `[type, payload]` tagged tuples (the regular path) or the truncation
     * sentinel `['array', '*SKIPPED over 10000 entries*']` produced once the recursive walker crosses 10 000 entries.
     *
     * @return list<array{
     *     namespace: string,
     *     short_class: string,
     *     class: string,
     *     type: string,
     *     function: string|null,
     *     file: string|null,
     *     line: int|null,
     *     args: array<int|string, array{0: string, 1: mixed}>|array{0: string, 1: string},
     * }>
     */
    public function getTrace(): array
    {
        return $this->_trace;
    }

    /**
     * Gets the stack trace as a string (extracted from the cached `__toString()` output).
     */
    public function getTraceAsString(): string
    {
        $remove = "Stack trace:\n";

        $len = strpos($this->_toString, $remove);

        if ($len === false) {
            return '';
        }

        return substr($this->_toString, $len + strlen($remove));
    }

    protected function setClass(string $class): void
    {
        $this->_class = $class;
    }

    protected function setCode(mixed $code): void
    {
        $this->code = $code;
    }

    protected function setFile(string $file): void
    {
        $this->file = $file;
    }

    protected function setLine(int $line): void
    {
        $this->line = $line;
    }

    protected function setMessage(string $message): void
    {
        $this->message = $message;
    }

    protected function setPrevious(self $previous): void
    {
        $this->_previous = $previous;
    }

    protected function setToString(string $string): void
    {
        $this->_toString = $string;
    }

    /**
     * @param array<int, mixed>|list<array<string, mixed>> $trace
     */
    protected function setTrace(array $trace): void
    {
        $this->_trace = [];

        foreach ($trace as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $rawClass = is_string($entry['class'] ?? null) ? $entry['class'] : '';
            $class = '';
            $namespace = '';

            if ($rawClass !== '') {
                $parts = explode('\\', $rawClass);
                $class = array_pop($parts);
                $namespace = implode('\\', $parts);
            }

            $this->_trace[] = [
                'namespace' => $namespace,
                'short_class' => $class,
                'class' => $rawClass,
                'type' => is_string($entry['type'] ?? null) ? $entry['type'] : '',
                'function' => is_string($entry['function'] ?? null) ? $entry['function'] : null,
                'file' => is_string($entry['file'] ?? null) ? $entry['file'] : null,
                'line' => is_int($entry['line'] ?? null) ? $entry['line'] : null,
                'args' => is_array($entry['args'] ?? null) ? $this->flattenArgs($entry['args']) : [],
            ];
        }
    }

    /**
     * Sterilizes the exception trace arguments — replaces objects with `['object', FQCN]` tuples, recurses into
     * arrays (capped at depth 10 and 10 000 entries), and stringifies scalars to keep the result serializable.
     *
     * @param array<int|string, mixed> $args
     * @param int $level Recursion level.
     * @param int $count Number of records counter.
     *
     * @return array<int|string, array{0: string, 1: mixed}>|array{0: string, 1: string} Tagged tuples keyed as in
     * `$args`, or the SKIPPED truncation sentinel when the recursive walker crosses 10 000 entries.
     */
    private function flattenArgs(array $args, int $level = 0, int &$count = 0): array
    {
        $result = [];

        foreach ($args as $key => $value) {
            if (++$count > 10000) {
                return ['array', '*SKIPPED over 10000 entries*'];
            }

            if ($value instanceof __PHP_Incomplete_Class) {
                $result[$key] = ['incomplete-object', $this->getClassNameFromIncomplete($value)];
            } elseif (is_object($value)) {
                $result[$key] = ['object', get_class($value)];
            } elseif (is_array($value)) {
                if ($level > 10) {
                    $result[$key] = ['array', '*DEEP NESTED ARRAY*'];
                } else {
                    $result[$key] = ['array', $this->flattenArgs($value, $level + 1, $count)];
                }
            } elseif ($value === null) {
                $result[$key] = ['null', null];
            } elseif (is_bool($value)) {
                $result[$key] = ['boolean', $value];
            } elseif (is_int($value)) {
                $result[$key] = ['integer', $value];
            } elseif (is_float($value)) {
                $result[$key] = ['float', $value];
            } elseif (is_resource($value)) {
                $result[$key] = ['resource', get_resource_type($value)];
            } else {
                $result[$key] = ['string', is_string($value) ? $value : ''];
            }
        }

        return $result;
    }

    /**
     * Returns the real class name of an incomplete class (the class PHP captures when unserializing an object whose
     * class definition is unavailable).
     */
    private function getClassNameFromIncomplete(__PHP_Incomplete_Class $value): string
    {
        $array = get_object_vars($value);

        $name = $array['__PHP_Incomplete_Class_Name'] ?? '';

        return is_string($name) ? $name : '';
    }
}
