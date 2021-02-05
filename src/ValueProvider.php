<?php

namespace HTMLBlocks;

use InvalidArgumentException;

final class ValueProvider
{
    private array $valueConfig;

    private array $cfValues;

    public function __construct(array $valueConfig, array $cfValues)
    {
        $this->valueConfig = $valueConfig;
        $this->cfValues = $cfValues;
    }

    public function value(): string
    {
        if (array_key_exists('value_path', $this->valueConfig)) {
            return self::getValuePathValue($this->valueConfig['value_path'], $this->cfValues);
        } elseif (array_key_exists('function', $this->valueConfig)) {
            return self::getFunctionValue($this->valueConfig['function'], $this->cfValues);
        }
        throw new InvalidArgumentException("Missing 'value_path' or 'function' attribute.");
    }

    private static function getFunctionValue(
        array $functionConfig,
        array $cfValues
    ): string {
        $name = $functionConfig['name'];

        // Build function arguments
        $arguments = [];
        foreach ($functionConfig['args'] as $arg) {
            $arg = $arg['arg'];

            // Static value
            if (array_key_exists('value', $arg)) {
                array_push($arguments, $arg['value']);
                continue;
            }

            // Value from value path
            if (array_key_exists('value_path', $arg)) {
                array_push($arguments, self::getValuePathValue($arg['value_path'], $cfValues));
                continue;
            }

            // Value from function
            if (array_key_exists('function', $arg)) {
                array_push($arguments, self::getFunctionValue($arg['function'], $cfValues));
                continue;
            }

            throw new InvalidArgumentException("Missing 'value', 'value_path' or 'function' attribute.");
        }
        // Call function
        $result = call_user_func_array($name, $arguments);
        return $result;
    }

    private static function getValuePathValue(string $valuePath, array $cfValues)
    {
        $dotCfValues = dot($cfValues);
        return $dotCfValues->get($valuePath);
    }
}
