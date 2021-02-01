<?php

namespace HTMLBlocks;

use InvalidArgumentException;

final class ValueProvider
{
    public static function getFunctionValue(
        array $function_config,
        array $cf_values
    ): string {
        $name = $function_config['name'];

        // Build function arguments
        $arguments = [];
        foreach ($function_config['args'] as $arg) {
            $arg = $arg['arg'];

            // Static value
            if (array_key_exists('value', $arg)) {
                array_push($arguments, $arg['value']);
                continue;
            }

            // Value from value path
            if (array_key_exists('value_path', $arg)) {
                array_push($arguments, self::getValuePathValue($arg['value_path'], $cf_values));
                continue;
            }

            // Value from function
            if (array_key_exists('function', $arg)) {
                array_push($arguments, self::getFunctionValue($arg['function'], $cf_values));
                continue;
            }

            throw new InvalidArgumentException("Missing 'value', 'value_path' or 'function' attribute.");
        }
        // Call function
        $result = call_user_func_array($name, $arguments);
        return $result;
    }

    public static function getValuePathValue(string $value_path, array $cf_values)
    {
        $dot_cf_values = dot($cf_values);
        return $dot_cf_values->get($value_path);
    }
}
