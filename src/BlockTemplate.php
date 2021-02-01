<?php

namespace HTMLBlocks;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMException;
use DOMNode;
use DOMNodeList;
use DOMText;
use DOMXpath;
use InvalidArgumentException;

/**
 * HTMLBlocks.
 *
 * @author Ergo Sarapu <ergosarapu@gmail.com>
 */
class BlockTemplate
{
    private DOMDocument $doc;

    private DOMXPath $domXPath;

    private ?DOMComment $childrenMarker = null;

    private ?BlockTemplate $parent;

    private ?BlockTemplate $prevSibling;

    private array $children;

    private array $blockConfig;

    private ?DOMNode $lastNodeNextSiblingEffective;

    public function __construct(
        DOMDocument $doc,
        array &$block_config,
        BlockTemplate $parent = null,
        BlockTemplate $prev_sibling = null
    ) {
        $this->doc = $doc;
        $this->domXPath = new DOMXPath($doc);
        $this->blockConfig =& $block_config;
        $this->parent = $parent;
        $this->prevSibling = $prev_sibling;
        $this->children = [];
        $this->load();
    }

    public function getChildrenMarker(): ?DOMComment
    {
        return $this->childrenMarker;
    }

    public function setChildrenMarker(DOMNode $before_node): void
    {
        $marker = $this->doc->createComment($this->blockConfig['name'] . ' children marker');
        $before_node->parentNode->insertBefore($marker, $before_node);
        $this->childrenMarker = $marker;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getLastNodeEffective(): DOMNode
    {
        return $this->nodeOrPreviousSiblingEffective($this->getLastNode());
    }

    public function getChildrenMarkerName(): ?string
    {
        $marker = $this->getChildrenMarker();
        if ($this->getChildrenMarker()) {
            return $marker->nodeValue;
        }
        return null;
    }

    private function nodeOrPreviousSiblingEffective(DOMNode $node): DOMNode
    {
        // Ignore previous empty text node and comment siblings
        while (
            ($node instanceof DOMText && trim($node->wholeText) == '') ||
            $node instanceof DOMComment
        ) {
            $node = $node->previousSibling;
        }
        return $node;
    }

    private function nodeOrNextSiblingEffective(?DOMNode $node): ?DOMNode
    {
        while (($node instanceof DOMText && trim($node->wholeText) == '') || $node instanceof DOMComment) {
            $node = $node->nextSibling;
        }
        return $node;
    }

    private function setPrevSibling(BlockTemplate $prev_sibling)
    {
        $this->prevSibling = $prev_sibling;
    }

    private function getLastNodeNextSiblingEffective(): DOMNode
    {
        return $this->lastNodeNextSiblingEffective;
    }

    public function load()
    {

        // XPath defining the block, allowed to match one or more closest sibling nodes
        $xpath = $this->blockConfig['xpath'];

        // Query nodes using XPath
        $nodes = $this->getNodesByXPath($xpath, $this->domXPath);

        $this->doc = new DOMDocument();
        $this->domXPath = new DOMXPath($this->doc);

        // Remember first node from old document
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            // Check the node is sibling with previous node
            if ($i < $nodes->length - 1) {
                $expected_node = $this->nodeOrNextSiblingEffective($node->nextSibling);
                $actual = $this->nodeOrNextSiblingEffective($nodes->item($i + 1));
                if (!$actual->isSameNode($expected_node)) {
                    throw new InvalidArgumentException("Block nodes must be closest siblings. XPath: '$xpath'");
                }
            }

            // Check the node is also closest sibling with the last template last node
            if ($this->prevSibling && $i == 0) {
                $expected_node = $this->prevSibling->getLastNodeNextSiblingEffective();
                $actual = $this->nodeOrNextSiblingEffective($node);

                if (!$actual->isSameNode($expected_node)) {
                    throw new InvalidArgumentException(
                        "Closest block's nodes must be closest siblings. XPath '$xpath' "
                        . "and XPath '" . $this->prevSibling->blockConfig['xpath']
                        . "' are not matching closest siblings"
                    );
                }
            }

            // Remember last node's next effective sibling (required when loading next sibling template, if applicable)
            if ($i == $nodes->length - 1) {
                $this->lastNodeNextSiblingEffective = $this->nodeOrNextSiblingEffective($node->nextSibling);
            }

            if (!$this->prevSibling && $i == 0 && $this->parent) {
                $this->parent->setChildrenMarker($node);
            }

            // Copy node to new document
            $node = $this->doc->importNode($node, true);
            $node = $this->doc->appendChild($node);
        }
        $html = $this->doc->saveHTML();

        // Create child templates
        if (array_key_exists('blocks', $this->blockConfig)) {
            $blocks =& $this->blockConfig['blocks'];
            $last_template = null;
            for ($b = 0; $b < count($blocks); $b++) {
                $block =& $blocks[$b]['block'];

                $template = new BlockTemplate($this->doc, $block, $this, $last_template);
                array_push($this->children, $template);
                $last_template = $template;
            }
        }

        // Set render callback
        $this->blockConfig['render_callback'] = function ($cf_values, $attributes, $inner_blocks) {
            echo $this->render($cf_values, $inner_blocks);
        };

        // Remove matched nodes from DOM
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Returns nodes by XPath query string
     * @param  DOMDocument $doc   The document to query from
     * @param  string      $xpath XPath query string
     * @return DOMNodeList List of nodes
     */
    private function getNodesByXPath(string $xpath, DOMXPath $domXPath, ?DOMNode $contextnode = null): DOMNodeList
    {
        $nodes = $domXPath->query($xpath, $contextnode);
        if (!$nodes || $nodes->length == 0) {
            throw new DOMException('Could not get results for XPath query: "' . $xpath . '\"');
        }
        return $nodes;
    }

    public function render(array $cf_values, string $cf_inner_blocks_html): string
    {
        // Do not modify the template doc directly, create copy first
        $copy = $this->doc->cloneNode(true);

        // Render fields
        $this->renderFields($cf_values, $copy);

        // Save HTML
        $html = $this->saveHTML($copy);

        // Replace child marker with inner HTML
        if ($this->getChildrenMarkerName()) {
            $html = preg_replace('/<!--' . $this->getChildrenMarkerName() . '-->/', $cf_inner_blocks_html, $html, 1);
        }

        return $html;
    }

    private function renderFields(array $cf_values, DOMDocument $doc): void
    {
        if (!array_key_exists('fields', $this->blockConfig)) {
            return;
        }
        $dom_xpath = new DOMXPath($doc);
        $fields_config = $this->blockConfig['fields'];
        foreach ($fields_config as $field_config) {
            $field = $field_config['field'];

            $replaces = $field['replaces'];
            foreach ($replaces as $replace) {
                $replace = $replace['replace'];
                $xpath = $replace['xpath'];

                $value = null;
                if (array_key_exists('value_path', $replace)) {
                    $value = ValueProvider::getValuePathValue($replace['value_path'], $cf_values);
                } elseif (array_key_exists('function', $replace)) {
                    $value = ValueProvider::getFunctionValue($replace['function'], $cf_values);
                } else {
                    throw new InvalidArgumentException("Missing 'value_path' or 'function' attribute.");
                }

                $nodes = $this->getNodesByXPath($xpath, $dom_xpath);
                foreach ($nodes as $node) {
                    if ($node instanceof DOMText) {
                        $fragment = $doc->createDocumentFragment();
                        set_error_handler(function () use ($node, $value) {
                            // Not a valid XML
                            $node->data = htmlentities($value);
                        });
                        $success = $fragment->appendXML($value);
                        restore_error_handler();
                        if ($success) {
                            $node->parentNode->replaceChild($fragment, $node);
                        }
                    } elseif ($node instanceof DOMAttr) {
                        $node->value = $value;
                    }
                }
            }
        }
    }

    /**
     * TODO: Smaily specific, refactor
     * Returns the HTML of document by keeping markers special characters { }
     * @param  DOMDocument $doc  Document to HTML
     * @param  [DOMNode]   $node Node to HTML
     * @return string            Generated HTML
     */
    private function saveHTML(DOMDocument $doc, DOMNode $node = null): string
    {
        $html = $doc->saveHTML($node);
        $html = str_replace('%7B', '{', $html);
        $html = str_replace('%7D', '}', $html);
        return $html;
    }

    private function saveNodeListHTML(DOMNodeList $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->saveHTML($this->doc, $node);
        }
        return $html;
    }
}
