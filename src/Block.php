<?php

namespace HTMLBlocks;

use Carbon_Fields\Block as CFBlock;
use Carbon_Fields\Container\Block_Container;

class Block
{
    private array $blockConfig;

    private ?Block $parent;

    private Block_Container $cfBlock;

    public function __construct(array $blockConfig, Block $parent = null)
    {
        $this->blockConfig = $blockConfig;
        $this->parent = $parent;
        $this->makeBlock();
    }

    public function getCFBlock(): Block_Container
    {
        return $this->cfBlock;
    }

    public function getBlockName(): string
    {
        return str_replace(
            'carbon-fields-container-',
            'carbon-fields/',
            str_replace('_', '-', $this->cfBlock->id)
        );
    }

    private function makeBlock()
    {
        $name = $this->blockConfig['name'];
        $this->cfBlock = CFBlock::make($name);
        if ($this->parent) {
            $this->cfBlock->set_parent($this->parent->getBlockName());
        }
        $fieldConfigs = $this->blockConfig['fields'];
        $cfFields = [];
        foreach ($fieldConfigs as $fieldConfig) {
            $fieldConfig = $fieldConfig['field'];
            $field = new Field($fieldConfig);
            array_push($cfFields, $field->getCfField());
        }
        $this->cfBlock->add_fields($cfFields);

        // Set description
        $this->cfBlock->set_description($this->blockConfig['description']);

        // Set category
        $this->cfBlock->set_category(
            $this->blockConfig['category']['slug'],
            $this->blockConfig['category']['title'],
            $this->blockConfig['category']['icon']
        );

        // Set icon
        if (array_key_exists('icon', $this->blockConfig)) {
            $this->cfBlock->set_icon($this->blockConfig['icon']);
        }

        // Default render callback
        $renderCallback = function () {
            echo 'Error: Render callback not present in config. Nothing to display here.';
        };
        // Set render callback if present in config
        if (array_key_exists('render_callback', $this->blockConfig)) {
            $renderCallback = $this->blockConfig['render_callback'];
        }
        $this->cfBlock->set_render_callback($renderCallback);

        if (array_key_exists('blocks', $this->blockConfig)) {
            $this->cfBlock->set_inner_blocks(true);
            $this->cfBlock->set_inner_blocks_position('below');

            $innerBlockNames = [];
            foreach ($this->blockConfig['blocks'] as $block) {
                $child = new Block($block['block'], $this);
                array_push($innerBlockNames, $child->getBlockName());
            }
            $this->cfBlock->set_allowed_inner_blocks($innerBlockNames);
        }
    }
}
