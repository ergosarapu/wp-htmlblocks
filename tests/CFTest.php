<?php

namespace HTMLBlocks\Tests;

use WP_Mock;

abstract class CFTest extends WP_Mock\Tools\TestCase
{
    public function setUp(): void
    {
        WP_Mock::setUp();

        // Boot Carbon Fields
        WP_Mock::userFunction('did_action')->with('init')->andReturn(0);
        WP_Mock::userFunction('trailingslashit')->andReturnUsing(function ($arg1) {
            return $arg1;
        });
        if (!defined('WP_PLUGIN_DIR')) {
            define('WP_PLUGIN_DIR', '');
        }
        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', 'true');
        }
        WP_Mock::userFunction('plugins_url')->andReturn('');
        WP_Mock::userFunction('content_url')->andReturn('');
        WP_Mock::userFunction('site_url')->andReturn('');
        \Carbon_Fields\Carbon_Fields::boot();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
    }
}
