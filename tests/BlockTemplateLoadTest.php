<?php

namespace HTMLBlocks\Tests;

use DOMDocument;
use HTMLBlocks\BlockTemplate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class BlockTemplateLoadTest extends TestCase
{
    private array $config;

    private DOMDocument $doc;

    protected function setUp(): void
    {
        $this->config = Yaml::parseFile('./tests/config/' . $this->getName() . '.yml')['block'];
        $this->doc = new DOMDocument();
        $this->doc->loadHTMLFile('./tests/config/test.html');
    }

    public function testLoadConfig()
    {
        new BlockTemplate($this->doc, $this->config);

        // Test that render callback exists for every block
        $this->verifyRenderCallbackExist($this->config);
        $this->assertTrue(true);
    }

    public function testLoadInvalidConfigBlockNodesNotClosestSiblings()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessageMatches('/Block nodes must be closest siblings/');

        new BlockTemplate($this->doc, $this->config);
    }

    public function testLoadInvalidConfigClosestBlockNodesNotClosestSiblings()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessageMatches('/Closest block\'s nodes must be closest siblings/');

        new BlockTemplate($this->doc, $this->config);
    }

    private function verifyRenderCallbackExist($block)
    {
        $this->assertArrayHasKey('render_callback', $block);
        if (!array_key_exists('blocks', $block)) {
            return;
        }
        $blocks = $block['blocks'];
        foreach ($blocks as $block) {
            $block = $block['block'];
            $this->assertArrayHasKey('render_callback', $block);
        }
    }
}
