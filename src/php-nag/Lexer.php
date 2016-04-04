<?php
namespace PhpNag;

use \PhpParser\Comment;

class Lexer extends \PhpParser\Lexer
{
    public function getNextToken(&$value = null, &$startAttributes = null, &$endAttributes = null)
    {
        $startAttributes = array();
        $endAttributes   = array();
        while (1) {
            if (isset($this->tokens[++$this->pos])) {
                $token = $this->tokens[$this->pos];
            } else {
                // EOF token with ID 0
                $token = "\0";
            }
            if (is_string($token)) {
                // bug in token_get_all
                if ('b"' === $token) {
                    $value = 'b"';
                    $this->filePos += 2;
                    $id = ord('"');
                } else {
                    $value = $token;
                    $this->filePos += 1;
                    $id = ord($token);
                }
                $startAttributes['startLine'] = $this->line;
                //$endAttributes['endLine'] = $this->line;
                //$endAttributes['endTokenPos'] = $this->pos;
                //$endAttributes['endFilePos'] = $this->filePos - 1;
                return $id;
            } else {
                $this->line += substr_count($token[1], "\n");
                $this->filePos += strlen($token[1]);
                if (T_COMMENT === $token[0]) {
                    $startAttributes['comments'][] = new Comment($token[1], $token[2]);
                } elseif (T_DOC_COMMENT === $token[0]) {
                    $startAttributes['comments'][] = new Comment\Doc($token[1], $token[2]);
                } elseif (!isset($this->dropTokens[$token[0]])) {
                    $value = $token[1];
                    $startAttributes['startLine'] = $token[2];
                    //$endAttributes['endLine'] = $this->line;
                    //$endAttributes['endTokenPos'] = $this->pos;
                    //$endAttributes['endFilePos'] = $this->filePos - 1;
                    return $this->tokenMap[$token[0]];
                }
            }
        }
        throw new \RuntimeException('Reached end of lexer loop');
    }
}
