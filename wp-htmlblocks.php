<?php

/**
 * Plugin Name:     HTMLBlocks
 * Description:     Capture sections of HTML document into Wordpress Blocks so that HTML template can be filled with Wordpress content.
 * Author:          Ergo Sarapu
 * Text Domain:     wp-htmlblocks
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package HTMLBlocks
 */

if (! defined('ABSPATH')) {
    exit;
}

use HTMLBlocks\HTMLBlocks;

add_action('plugins_loaded', function () {
    \Carbon_Fields\Carbon_Fields::boot();
    $hb = new HTMLBlocks();
    $hb->init();
});
