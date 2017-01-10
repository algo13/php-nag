<?php
namespace PhpNag;

use PhpParser\Node;

class NodeTraverser implements \PhpParser\NodeTraverserInterface
{
    public function __construct()
    {
        //$this->visitors = new \SplObjectStorage();
        $this->visitors = array();
    }
    public function addVisitor(\PhpParser\NodeVisitor $visitor)
    {
        $this->visitors[] = $visitor;
    }
    public function removeVisitor(\PhpParser\NodeVisitor $visitor)
    {
        $key = array_search($visitor, $this->visitors, true);
        if ($key !== false) {
            unset($this->visitors[$key]);
        }
    }
    public function traverse(array $nodes)
    {
        //foreach ($this->visitors as $visitor) {
        //    $visitor->beforeTraverse($nodes);
        //}
        $this->traverseArray($nodes);
        //foreach ($this->visitors as $visitor) {
        //    $visitor->afterTraverse($nodes);
        //}
        return $nodes;
    }
    protected function traverseArray(array $nodes)
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                $this->traverseArray($node);
            } elseif ($node instanceof Node) {
                $this->traverseNode($node);
            }
        }
    }
    protected function traverseNode(Node $node)
    {
        foreach ($this->visitors as $visitor) {
            $visitor->enterNode($node);
        }
        foreach ($node->getSubNodeNames() as $name) {
            $subNode =& $node->$name;
            if (is_array($subNode)) {
                $this->traverseArray($subNode);
            } elseif ($subNode instanceof Node) {
                $this->traverseNode($subNode);
            }
        }
        //foreach ($this->visitors as $visitor) {
        //    $visitor->leaveNode($node);
        //}
    }
    protected $visitors;
}
