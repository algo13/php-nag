<?php
namespace PhpNag;

use PhpParser\Comment;

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
            $startAttributes['startLine'] = $this->line;
            //$startAttributes['startTokenPos'] = $this->pos;
            //$startAttributes['startFilePos'] = $this->filePos;
            if (\is_string($token)) {
                $value = $token;
                if (isset($token[1])) {
                    // bug in token_get_all
                    $this->filePos += 2;
                    $id = ord('"');
                } else {
                    $this->filePos += 1;
                    $id = ord($token);
                }
            } elseif (!isset($this->dropTokens[$token[0]])) {
                $value = $token[1];
                $id = $this->tokenMap[$token[0]];

                $this->line += substr_count($value, "\n");
                $this->filePos += \strlen($value);
            } else {
                //if (isset($this->usedAttributes['comments'])) {
                if (T_COMMENT === $token[0]) {
                    $startAttributes['comments'][] = new Comment($token[1], $this->line, $this->filePos);
                } elseif (T_DOC_COMMENT === $token[0]) {
                    $startAttributes['comments'][] = new Comment\Doc($token[1], $this->line, $this->filePos);
                } else {
                    // noop
                }
                //}
                $this->line += substr_count($token[1], "\n");
                $this->filePos += \strlen($token[1]);
                continue;
            }
            //$endAttributes['endLine'] = $this->line;
            //$endAttributes['endTokenPos'] = $this->pos;
            //$endAttributes['endFilePos'] = $this->filePos - 1;
            return $id;
        }

        throw new \RuntimeException('Reached end of lexer loop');
    }
}
