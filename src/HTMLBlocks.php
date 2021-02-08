<?php

namespace HTMLBlocks;

use DOMDocument;
use ValueError;

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
        try {
            $configLoader = new ConfigLoader();
            $configList = $configLoader->listConfigs();
        } catch (ValueError $err) {
            return false;
        }

        /** @var Config $conf */
        foreach ($configList as $conf) {
            // Create DOM for HTML
            $doc = new DOMDocument();
            $doc->loadHTMLFile($conf->getHtmlPath());

            // Create template tree
            $confArr = $conf->getConfig();
            new BlockTemplate($doc, $confArr);

            // Create block tree
            new Block($confArr);
        }
        return true;
    }
}
