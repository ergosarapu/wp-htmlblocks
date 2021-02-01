<?php

namespace HTMLBlocks;

use Carbon_Fields\Block as CFBlock;
use Carbon_Fields\Container\Block_Container;

class Block
{
    private array $blockConfig;

    private ?Block $parent;

    private Block_Container $cfBlock;

    public function __construct(array $block_config, Block $parent = null)
    {
        $this->blockConfig = $block_config;
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
        $fields = $this->blockConfig['fields'];
        $cf_fields = [];
        foreach ($fields as $field) {
            $field = $field['field'];
            $f = new Field($field);
            array_push($cf_fields, $f->getCfField());
        }
        $this->cfBlock->add_fields($cf_fields);

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

        // Set render callback if present in config
        if (array_key_exists('render_callback', $this->blockConfig)) {
            $this->cfBlock->set_render_callback($this->blockConfig['render_callback']);
        } else {
            $this->cfBlock->set_render_callback(function () {
                echo 'Error: Render callback not present in config. Nothing to display here.';
            });
        }

        if (array_key_exists('blocks', $this->blockConfig)) {
            $this->cfBlock->set_inner_blocks(true);
            $this->cfBlock->set_inner_blocks_position('below');

            $allowed_inner_block_names = [];
            foreach ($this->blockConfig['blocks'] as $block) {
                $child = new Block($block['block'], $this);
                array_push($allowed_inner_block_names, $child->getBlockName());
            }
            $this->cfBlock->set_allowed_inner_blocks($allowed_inner_block_names);
        }
    }
}
