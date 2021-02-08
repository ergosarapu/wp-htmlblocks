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

    private ?DOMNode $nextSiblingNode;

    private DOMNodeList $nodes;

    public function __construct(
        DOMDocument $doc,
        array &$blockConfig,
        BlockTemplate $parent = null,
        BlockTemplate $prevSibling = null
    ) {
        $this->doc = $doc;
        $this->domXPath = new DOMXPath($doc);
        $this->blockConfig =& $blockConfig;
        $this->parent = $parent;
        $this->prevSibling = $prevSibling;
        $this->children = [];
        $this->load();
    }

    public function getChildrenMarker(): ?DOMComment
    {
        return $this->childrenMarker;
    }

    public function setChildrenMarker(DOMNode $beforeNode): void
    {
        $marker = $this->doc->createComment($this->blockConfig['name'] . ' children marker');
        $beforeNode->parentNode->insertBefore($marker, $beforeNode);
        $this->childrenMarker = $marker;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getChildrenMarkerName(): ?string
    {
        $marker = $this->getChildrenMarker();
        if ($this->getChildrenMarker()) {
            return $marker->nodeValue;
        }
        return null;
    }

    private function nodeOrNextSiblingEffective(?DOMNode $node): ?DOMNode
    {
        while (($node instanceof DOMText && trim($node->wholeText) == '') || $node instanceof DOMComment) {
            $node = $node->nextSibling;
        }
        return $node;
    }

    protected function getLastNodeNextSiblingEffective(): DOMNode
    {
        return $this->nextSiblingNode;
    }

    private function loadNodes()
    {
        // XPath defining the block, allowed to match one or more closest sibling nodes
        $xpath = $this->blockConfig['xpath'];

        // Query nodes using XPath
        $this->nodes = $this->getNodesByXPath($xpath, $this->domXPath);
    }

    private function removeNodes()
    {
        // Remove matched nodes from DOM
        foreach ($this->nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    private function validateSiblingNodes(int $index): DOMNode
    {
        $node = $this->nodes->item($index);

        // Check the node is sibling with previous node
        if ($index < $this->nodes->length - 1) {
            $expectedNode = $this->nodeOrNextSiblingEffective($node->nextSibling);
            $actual = $this->nodeOrNextSiblingEffective($this->nodes->item($index + 1));
            if (!$actual->isSameNode($expectedNode)) {
                throw new InvalidArgumentException(
                    "Block nodes must be closest siblings. XPath: '" . $this->blockConfig['xpath'] . "'"
                );
            }
        }

        // Check the node is also closest sibling with the last template last node
        if ($this->prevSibling && $index == 0) {
            $expectedNode = $this->prevSibling->getLastNodeNextSiblingEffective();
            $actual = $this->nodeOrNextSiblingEffective($node);

            if (!$actual->isSameNode($expectedNode)) {
                throw new InvalidArgumentException(
                    "Closest block's nodes must be closest siblings. XPath '"
                    . $this->blockConfig['xpath'] . "' "
                    . "and XPath '" . $this->prevSibling->blockConfig['xpath']
                    . "' are not matching closest siblings"
                );
            }
        }
        return $node;
    }

    private function createChildTemplates()
    {
        if (array_key_exists('blocks', $this->blockConfig)) {
            $blocks =& $this->blockConfig['blocks'];
            $lastTemplate = null;
            foreach ($blocks as &$block) {
                $block =& $block['block'];

                $template = new BlockTemplate($this->doc, $block, $this, $lastTemplate);
                array_push($this->children, $template);
                $lastTemplate = $template;
            }
        }
    }

    public function load()
    {
        // Load nodes from parent document
        $this->loadNodes();

        // Create new document and replace existing
        $this->doc = new DOMDocument();
        $this->domXPath = new DOMXPath($this->doc);

        for ($i = 0; $i < $this->nodes->length; $i++) {
            // Validate node
            $node = $this->validateSiblingNodes($i);

            // Remember last node's next effective sibling (required when loading next sibling template, if applicable)
            if ($i == $this->nodes->length - 1) {
                $this->nextSiblingNode = $this->nodeOrNextSiblingEffective($node->nextSibling);
            }

            // Set marker
            if (!$this->prevSibling && $i == 0 && $this->parent) {
                $this->parent->setChildrenMarker($node);
            }

            // Copy node to new document
            $node = $this->doc->importNode($node, true);
            $node = $this->doc->appendChild($node);
        }

        // Create child templates
        $this->createChildTemplates();

        // Set render callback, note the __invoke magic method!
        $this->blockConfig['render_callback'] = $this;

        // Remove matched nodes from parent document
        $this->removeNodes();
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function __invoke($cfValues, $attributes, $innerBlocks)
    {
        echo $this->render($cfValues, $innerBlocks);
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

    public function render(array $cfValues, string $cfInnerBlocksHtml): string
    {
        // Do not modify the template doc directly, create copy first
        $copy = $this->doc->cloneNode(true);

        // Render fields
        $this->renderFields($cfValues, $copy);

        // Save HTML
        $html = $this->saveHTML($copy);

        // Replace child marker with inner HTML
        if ($this->getChildrenMarkerName()) {
            $html = preg_replace('/<!--' . $this->getChildrenMarkerName() . '-->/', $cfInnerBlocksHtml, $html, 1);
        }

        return $html;
    }

    private function renderFields(array $cfValues, DOMDocument $doc): void
    {
        if (!array_key_exists('fields', $this->blockConfig)) {
            return;
        }
        $domXpath = new DOMXPath($doc);
        $fieldsConfig = $this->blockConfig['fields'];
        foreach ($fieldsConfig as $fieldConfig) {
            $field = $fieldConfig['field'];

            $replaces = $field['replaces'];
            foreach ($replaces as $replace) {
                $replace = $replace['replace'];
                $xpath = $replace['xpath'];
                $valueProvider = new ValueProvider($replace, $cfValues);
                $value = $valueProvider->value();

                $nodes = $this->getNodesByXPath($xpath, $domXpath);
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
}
