<?php

namespace HTMLBlocks\Tests;

use DOMDocument;
use HTMLBlocks\BlockTemplate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use WP_Mock;

final class BlockTemplateRenderTest extends TestCase
{
    protected function setUp(): void
    {
    }

    public function testRenderNestedBlocksStructure()
    {
        // Init Block config
        $configYml = <<<EOT
        name: 0-0
        xpath: //div[@id="0-0"]
        blocks:
            - block:
                name: 1-0
                xpath: //div[@id="1-0"]
                blocks:
                    - block:
                        name: 2-0
                        xpath: //div[@id="2-0"]
        EOT;
        $blockConfig = Yaml::parse($configYml);

        // Init HTML
        $blockHtml = <<<EOT
        <div id="0-0">0-0
            <div id="1-0">1-0
                <div id="2-0">2-0</div>
            </div>
        </div>
        EOT;
        $doc = new DOMDocument();
        $doc->loadHTML($blockHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl00 = new BlockTemplate($doc, $blockConfig);
        $tmpl10 = $tmpl00->getChildren()[0];
        $tmpl20 = $tmpl10->getChildren()[0];

        // Render templates
        $str20 = $tmpl20->render([], '');
        $str20 .= $tmpl20->render([], ''); // Render inner template second time
        $str10 = $tmpl10->render([], $str20);
        $str10 .= $tmpl10->render([], $str20); // Render inner template second time
        $str00 = $tmpl00->render([], $str10);
        $actual = preg_replace("/\s+/", "", $str00);

        $expected = <<<EOT
        <div id="0-0">0-0
            <div id="1-0">1-0
                <div id="2-0">2-0</div>
                <div id="2-0">2-0</div>
            </div>
            <div id="1-0">1-0
                <div id="2-0">2-0</div>
                <div id="2-0">2-0</div>
            </div>
        </div>
        EOT;
        $expected = preg_replace("/\s+/", "", $expected);
        $this->assertEquals($expected, $actual);
    }

    public function testRenderMissingReplaceValuePathOrFunction()
    {
        // Set up fields configuration
        $configYml = <<<EOT
        name: Block 0-0
        xpath: //div[@id="0-0"]
        fields:
            - field:
                type: textarea
                name: text_field
                replaces:
                    - replace:
                        xpath: //div/text()
        EOT;
        $blockConfig = Yaml::parse($configYml);

        // Init HTML
        $blockHtml = '<div id="0-0">old text</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($blockHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $blockConfig);

        // Set up Carbon Fields values
        $cfValues = ['text_field' => 'new text'];

        $this->expectException("InvalidArgumentException");
        $this->expectExceptionMessage("Missing 'value_path' or 'function' attribute.");

        // Run test
        $tmpl->render($cfValues, '');
    }

    public function testRenderTextNode()
    {
        // Set up fields configuration
        $configYml = <<<EOT
        name: Block 0-0
        xpath: //div[@id="0-0"]
        fields:
            - field:
                type: textarea
                name: text_field
                replaces:
                    - replace:
                        xpath: //div/text()
                        value_path: text_field
        EOT;
        $blockConfig = Yaml::parse($configYml);

        // Init HTML
        $blockHtml = '<div id="0-0">old text</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($blockHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $blockConfig);

        // Set up Carbon Fields values
        $cfValues = ['text_field' => 'new text'];

        // Run test
        $actual = $tmpl->render($cfValues, '');

        $expected = \str_replace('old', 'new', $blockHtml);
        $this->assertEquals($expected, trim($actual));
    }

    public function testRenderAttrNode()
    {
        // Set up fields configuration
        $configYml = <<<EOT
        name: Block 0-0
        xpath: //img
        fields:
            - field:
                type: image
                name: img_field
                replaces:
                    - replace:
                        xpath: //img/@src
                        function:
                            name: wp_get_attachment_image_url
                            args:
                                - arg:
                                    value_path: img_field
        EOT;
        $blockConfig = Yaml::parse($configYml);

        // Set up HTML
        $html = '<img src="http://example.com/old.jpg">';
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $blockConfig);

        // Set up Carbon Fields values
        $cfValues = ['img_field' => 10];

        // Mock WP functions
        WP_Mock::userFunction('wp_get_attachment_image_url')->with(10)->andReturn('http://example.com/new.jpg');

        // Run test
        $actual = $tmpl->render($cfValues, '');
        $expected = \str_replace('old', 'new', $html);
        $this->assertEquals($expected, trim($actual));
    }

    public function testRenderFieldTypeAssociation()
    {
        // Set up fields configuration
        $configYml = <<<EOT
        name: Block 0-0
        xpath: //div[@id="0-0"]
        fields:
            - field:
                type: association
                name: posts
                label: Block Posts
                replaces:
                    - replace:
                        xpath: //*[@id="title"]/text()
                        function:
                            name: get_the_title
                            args:
                                - arg:
                                    value_path: posts.1.id
                    - replace:
                        xpath: //*[@id="excerpt"]/text()
                        function:
                            name: get_the_excerpt
                            args:
                                - arg:
                                    value_path: posts.1.id
                    - replace:
                        xpath: //*[@id="post_thumbnail_url"]/@src
                        function:
                            name: get_the_post_thumbnail_url
                            args:
                                - arg:
                                    value_path: posts.1.id
                    - replace:
                        xpath: //*[@id="post_permalink"]/@href
                        function:
                            name: get_permalink
                            args:
                                - arg:
                                    value_path: posts.1.id
        EOT;
        $blockConfig = Yaml::parse($configYml);

        // Set up HTML
        $html = <<<EOT
        <div id="0-0">
        <div id="title">old title</div>
        <div id="excerpt">old excerpt</div>
        <a id="post_permalink" href="http://example.com/old">
            <img id="post_thumbnail_url" src="http://example.com/old.jpg">
        </a>
        </div>
        EOT;
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $blockConfig);

        // Set up Carbon Fields values
        $cfValues = [
            'posts' => [
                [
                    'id' => 11
                ],
                [
                    'id' => 10
                ]]];

        // Mock WP functions
        WP_Mock::userFunction('get_the_title')->with(10)->andReturn('new title');
        WP_Mock::userFunction('get_the_excerpt')->with(10)->andReturn('new excerpt');
        WP_Mock::userFunction('get_the_post_thumbnail_url')->with(10)->andReturn('http://example.com/new.jpg');
        WP_Mock::userFunction('get_permalink')->with(10)->andReturn('http://example.com/new');

        // Run test
        $actual = $tmpl->render($cfValues, '');
        $expected = \str_replace('old', 'new', $html);
        $this->assertEquals($expected, trim($actual));
    }
}
