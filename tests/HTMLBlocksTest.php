<?php

namespace HTMLBlocks\Tests;

use HTMLBlocks\HTMLBlocks;

final class HTMLBlocksTest extends CFTest
{
    public function setUp(): void
    {
        parent::setUp();
        putenv('HTMLBLOCKS_CONFIG=./tests/config/testLoadConfig.yml');
    }

    public function testInitBlocks()
    {
        $success = $this->runInitBlocks();
        $this->assertTrue($success);
    }

    public function testInitBlocksNoBlocksConfigInEnv()
    {
        putenv('HTMLBLOCKS_CONFIG');
        $success = $this->runInitBlocks();
        $this->assertFalse($success);
    }

    private function runInitBlocks(): bool
    {
        $htmlBlocks = new HTMLBlocks();
        return $htmlBlocks->initBlocks();
    }
}
