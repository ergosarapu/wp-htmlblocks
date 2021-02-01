<?php

namespace HTMLBlocks\Tests;

use HTMLBlocks\Block;
use Symfony\Component\Yaml\Yaml;
use WP_Mock;

final class BlockTest extends CFTest
{
    private array $config;

    public function setUp(): void
    {
        parent::setUp();
        $this->config = Yaml::parseFile('./tests/config/testLoadConfig.yml')['block'];
    }

    public function testMakeBlock()
    {
        WP_Mock::passthruFunction('remove_accents');

        $block = new Block($this->config);
        $id = $block->getCFBlock()->get_id();

        // This line is from CF Block_Container implementation
        $block_type_name = str_replace('carbon-fields-container-', 'carbon-fields/', str_replace('_', '-', $id));

        $this->assertEquals($block_type_name, $block->getBlockName());
    }
}
