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
        $configYml = <<<EOT
        value_path: text
        EOT;
        $config = Yaml::parse($configYml);

        $valueProvider = new ValueProvider(
            $config,
            [
            'text' => 'hello world'
            ]
        );
        $this->assertEquals('hello world', $valueProvider->value());
    }

    public function testFunctionValueWithValueArg()
    {
        $configYml = <<<EOT
        function:
            name: get_the_date
            args:
                - arg:
                    value: d.m.Y
        EOT;
        $config = Yaml::parse($configYml);

        $functionConfig = $config['function'];

        // Mock WP functions
        WP_Mock::userFunction('get_the_date')->andReturn(date(dot($functionConfig)->get('args.0.arg.value')));

        $valueProvider = new ValueProvider($config, []);
        $this->assertEquals(date('d.m.Y'), $valueProvider->value());
    }

    public function testFunctionValueWithValuePathArg()
    {
        $configYml = <<<EOT
        function:
            name: get_the_title
            args:
                - arg:
                    value_path: posts.0.id
        EOT;
        $config = Yaml::parse($configYml);

        // Mock WP functions
        WP_Mock::userFunction('get_the_title')->with(10)->andReturn("Hello world!");

        $valueProvider = new ValueProvider($config, [
            'posts' => [
                ['id' => 10]
            ]
        ]);
        $this->assertEquals("Hello world!", $valueProvider->value());
    }

    public function testFunctionValueWithNestedFunctionArg()
    {
        $configYml = <<<EOT
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
        $config = Yaml::parse($configYml);

        $valueProvider = new ValueProvider(
            $config,
            [
            'format' => 'Y.m.d',
            'datestring' => '2000-01-01'
            ]
        );
        $this->assertEquals("2000.01.01", $valueProvider->value());
    }
}
