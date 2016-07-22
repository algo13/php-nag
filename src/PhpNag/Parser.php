<?php
namespace PhpNag;

class Parser
{
    public function __construct()
    {
        $this->lexer = new Lexer();
        $this->parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7, $this->lexer);
        $this->visitor = new NodeVisitor();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->visitor);
    }
    public function parseFile($fileName, $contents = null)
    {
        if (func_num_args() < 2) {
            $contents = file_get_contents($fileName);
        }
        if ($contents !== false) {
            $contents = $this->parser->parse($contents);
            //$this->visitor->setTokens($this->lexer->getTokens());
            $this->visitor->setFileName($fileName);
            return $this->traverser->traverse($contents);
        }
        return false;
    }
    public function getCount()
    {
        return $this->visitor->getCount();
    }
    private $lexer;
    private $parser;
    private $visitor;
    private $traverser;
}
