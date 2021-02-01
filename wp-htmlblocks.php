<?php

/**
 * Plugin Name:     HTMLBlocks
 * Description:     Enrich random HTML template with Wordpress content using Wordpress Blocks experience.
 * Author:          Ergo Sarapu
 * Text Domain:     wp-htmlblocks
 * Domain Path:     /languages
 * Version:         0.1.0
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
