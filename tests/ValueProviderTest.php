<?php

namespace HTMLBlocks\Tests;

use HTMLBlocks\ValueProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use WP_Mock;

final class ValueProviderTest extends TestCase
{
    public function testValuePathValue()
    {
        $config_yml = <<<EOT
        value_path: text
        EOT;
        $config = Yaml::parse($config_yml);

        $value_path = $config['value_path'];
        $actual = ValueProvider::getValuePathValue(
            $value_path,
            [
                'text' => 'hello world'
            ]
        );
        $this->assertEquals('hello world', $actual);
    }

    public function testFunctionValueWithValueArg()
    {
        $config_yml = <<<EOT
        function:
            name: get_the_date
            args:
                - arg:
                    value: d.m.Y
        EOT;
        $config = Yaml::parse($config_yml);

        $function_config = $config['function'];

        // Mock WP functions
        WP_Mock::userFunction('get_the_date')->andReturn(date(dot($function_config)->get('args.0.arg.value')));

        $actual = ValueProvider::getFunctionValue($function_config, []);
        $this->assertEquals(date('d.m.Y'), $actual);
    }

    public function testFunctionValueWithValuePathArg()
    {
        $config_yml = <<<EOT
        function:
            name: get_the_title
            args:
                - arg:
                    value_path: posts.0.id
        EOT;
        $config = Yaml::parse($config_yml);

        $function_config = $config['function'];

        // Mock WP functions
        WP_Mock::userFunction('get_the_title')->with(10)->andReturn("Hello world!");

        $actual = ValueProvider::getFunctionValue(
            $function_config,
            [
                'posts' => [
                    ['id' => 10]
                ]
            ]
        );
        $this->assertEquals("Hello world!", $actual);
    }

    public function testFunctionValueWithNestedFunctionArg()
    {
        $config_yml = <<<EOT
        function:
            name: date
            args:
                - arg:
                    value_path: format
                - arg:
                    function:
                        name: strtotime
                        args:
                            - arg:
                                value_path: datestring
        EOT;
        $config = Yaml::parse($config_yml);

        $function_config = $config['function'];

        $actual = ValueProvider::getFunctionValue(
            $function_config,
            [
                'format' => 'Y.m.d',
                'datestring' => '2000-01-01'
            ]
        );
        $this->assertEquals("2000.01.01", $actual);
    }
}
