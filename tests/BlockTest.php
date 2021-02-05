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
        $blockId = $block->getCFBlock()->get_id();

        // This line is from CF Block_Container implementation
        $blockTypeName = str_replace('carbon-fields-container-', 'carbon-fields/', str_replace('_', '-', $blockId));

        $this->assertEquals($blockTypeName, $block->getBlockName());
    }
}
