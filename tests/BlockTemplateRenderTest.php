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
        $config_yml = <<<EOT
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
        $block_config = Yaml::parse($config_yml);

        // Init HTML
        $block_html = <<<EOT
        <div id="0-0">0-0
            <div id="1-0">1-0
                <div id="2-0">2-0</div>
            </div>
        </div>
        EOT;
        $doc = new DOMDocument();
        $doc->loadHTML($block_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl_00 = new BlockTemplate($doc, $block_config);
        $tmpl_10 = $tmpl_00->getChildren()[0];
        $tmpl_20 = $tmpl_10->getChildren()[0];

        // Render templates
        $str_20 = $tmpl_20->render([], '');
        $str_20 .= $tmpl_20->render([], ''); // Render inner template second time
        $str_10 = $tmpl_10->render([], $str_20);
        $str_10 .= $tmpl_10->render([], $str_20); // Render inner template second time
        $str_00 = $tmpl_00->render([], $str_10);
        $actual = preg_replace("/\s+/", "", $str_00);

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
        $config_yml = <<<EOT
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
        $block_config = Yaml::parse($config_yml);

        // Init HTML
        $block_html = '<div id="0-0">old text</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($block_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $block_config);

        // Set up Carbon Fields values
        $cf_values = ['text_field' => 'new text'];

        $this->expectException("InvalidArgumentException");
        $this->expectExceptionMessage("Missing 'value_path' or 'function' attribute.");

        // Run test
        $actual = $tmpl->render($cf_values, '');
    }

    public function testRenderTextNode()
    {
        // Set up fields configuration
        $config_yml = <<<EOT
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
        $block_config = Yaml::parse($config_yml);

        // Init HTML
        $block_html = '<div id="0-0">old text</div>';
        $doc = new DOMDocument();
        $doc->loadHTML($block_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $block_config);

        // Set up Carbon Fields values
        $cf_values = ['text_field' => 'new text'];

        // Run test
        $actual = $tmpl->render($cf_values, '');

        $expected = \str_replace('old', 'new', $block_html);
        $this->assertEquals($expected, trim($actual));
    }

    public function testRenderAttrNode()
    {
        // Set up fields configuration
        $config_yml = <<<EOT
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
        $block_config = Yaml::parse($config_yml);

        // Set up HTML
        $html = '<img src="http://example.com/old.jpg">';
        $doc = new DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Init template tree
        $tmpl = new BlockTemplate($doc, $block_config);

        // Set up Carbon Fields values
        $cf_values = ['img_field' => 10];

        // Mock WP functions
        WP_Mock::userFunction('wp_get_attachment_image_url')->with(10)->andReturn('http://example.com/new.jpg');

        // Run test
        $actual = $tmpl->render($cf_values, '');
        $expected = \str_replace('old', 'new', $html);
        $this->assertEquals($expected, trim($actual));
    }

    public function testRenderFieldTypeAssociation()
    {
        // Set up fields configuration
        $config_yml = <<<EOT
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
        $block_config = Yaml::parse($config_yml);

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
        $tmpl = new BlockTemplate($doc, $block_config);

        // Set up Carbon Fields values
        $cf_values = [
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
        $actual = $tmpl->render($cf_values, '');
        $expected = \str_replace('old', 'new', $html);
        $this->assertEquals($expected, trim($actual));
    }
}
