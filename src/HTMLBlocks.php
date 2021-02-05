<?php

namespace HTMLBlocks;

use DOMDocument;
use Symfony\Component\Yaml\Yaml;

use function Env\env;

/**
 * HTMLBlocks.
 *
 * @author Ergo Sarapu <ergosarapu@gmail.com>
 */
class HTMLBlocks
{
    /**
     * Sets up hooks
     */
    public function init(): void
    {
        add_action('carbon_fields_register_fields', [$this, 'initBlocks']);
    }

    public function initBlocks(): bool
    {
        if (!env('HTMLBLOCKS_CONFIG') || !env('HTMLBLOCKS_HTML')) {
            return false;
        }
        $config = Yaml::parseFile(env('HTMLBLOCKS_CONFIG'))['block'];

        $doc = new DOMDocument();
        $doc->loadHTMLFile(env('HTMLBLOCKS_HTML'));

        // Create template tree
        new BlockTemplate($doc, $config);

        // Create block tree
        new Block($config);

        return true;
    }
}
