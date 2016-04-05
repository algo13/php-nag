<?php
namespace PhpNag;

use \PhpParser\Node;
use \PhpNag\Utils\BuiltInUtils;

class NodeVisitor extends \PhpParser\NodeVisitorAbstract
{
    public function __construct()
    {
        $this->prettyPrinter = new \PhpParser\PrettyPrinter\Standard;
        $this->fileName = '';
        $this->count = 0;
    }
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
        $this->count = 0;
    }
    public function getCount()
    {
        return $this->count;
    }
    public function report(Node $node, $type)
    {
        // TODO:
        /*
        foreach ([
            //'If/INCOMPLETE_ELSEIF',
            'FuncCall/WEAK_COMP_FUNC_PARAM',
            'ClassLike/VISIBILITY_MIXED_ORDER',
            'ClassLike/MIXED_ORDER[Method/Property]',
            'Equal/WEAK_COMP_NUM',
            'Equal/WEAK_COMP_FUNC[substr]',
            'Cond/WEAK_COMP[preg_match',
            'FuncCall/DEPRECATED_INI_GET[',
            'Cond/ASSIGN_',
            'BinaryOp/LOGICAL_OPERATOR',
            'Switch/DEFAULT_NOTHING',
            'Switch/DEFAULT_NON_TAIL',
        ] as $value) {
            if (strpos($type, $value) === 0) {
                return;
            }
        }
        //*/
        echo $type, ' in ', $this->fileName, '(', $node->getAttribute('startLine'), ')', \PHP_EOL;
        ++$this->count;
    }
    public function enterNode(Node $node)
    {
        // TODO:
        /*
        $method = 'enter'.str_replace(['Expr_', 'Stmt_'], '', $node->getType());
        if (method_exists($this, $method)) {
            $this->$method($node);
        }
        return;
        */
        if ($node instanceof Node\Expr) {
            if ($node instanceof Node\Expr\Variable) {
                $this->enterVariable($node);
            } elseif ($node instanceof Node\Expr\BinaryOp) {
                $this->enterBinaryOp($node);
            } elseif ($node instanceof Node\Expr\FuncCall) {
                $this->enterFuncCall($node);
            } elseif ($node instanceof Node\Expr\List_) {
                $this->enterList($node);
            } elseif ($node instanceof Node\Expr\AssignRef) {
                if ($node->expr instanceof Node\Expr\New_) {
                    $this->report($node, 'AssignRef/NEW');
                }
            } else {
                // TODO:
            }
        //} elseif ($node instanceof Node\Scalar) {
        //} elseif ($node instanceof Node\Arg) {
        //} elseif ($node instanceof Node\Name) {
        } elseif ($node instanceof Node\Stmt) {
            if ($node instanceof Node\Stmt\Echo_) {
                $this->enterEcho($node);
            } elseif ($node instanceof Node\Stmt\ClassLike) {
                $this->enterClassLike($node);
            } elseif ($node instanceof Node\Stmt\If_) {
                $this->enterCond($node->cond, 'IF');
                //if (!empty($node->elseifs) && ($node->else === null)) {
                //    $this->report($node, 'If/INCOMPLETE_ELSEIF');
                //}
            } elseif ($node instanceof Node\Stmt\ElseIf_) {
                $this->enterCond($node->cond, 'ELSEIF');
            } elseif ($node instanceof Node\Stmt\For_) {
                $this->enterLoop($node, 'FOR');
            } elseif ($node instanceof Node\Stmt\While_) {
                $this->enterLoop($node, 'WHILE');
            } elseif ($node instanceof Node\Stmt\Do_) {
                $this->enterLoop($node, 'DO');
            } elseif ($node instanceof Node\Stmt\Unset_) {
                $this->enterUnset($node);
            } elseif ($node instanceof Node\Stmt\Switch_) {
                $this->enterSwitch($node);
            } elseif ($node instanceof Node\Stmt\Catch_) {
                if (empty($node->stmts)) {
                    $this->report($node, 'Catch/EMPTY');
                }
            } elseif ($node instanceof Node\Stmt\Goto_) {
                $this->report($node, 'Goto');
            } else {
                // TODO:
            }
        } elseif ($node instanceof Node\FunctionLike) {
            $this->enterFunctionLike($node);
        //} elseif ($node instanceof Node\Param) {
        //} elseif ($node instanceof Node\Const_) {
        } else {
            // TODO:
        }
    }
    private function enterVariable(Node\Expr\Variable $node)
    {
        if ($node->name instanceof Node\Expr\Variable) {
            $this->report($node, 'Variable/VARIABLE_VARIABLES');
        } elseif (is_string($node->name)) {
            $name = strtoupper($node->name);
            $info = BuiltInUtils::getDeprecatedGlobals($name);
            if ($info !== false) {
                $this->report($node, "Variable/DEPRECATED_GLOBALS[$name]");
            }
        }
    }
    private function enterBinaryOp(Node\Expr\BinaryOp $node)
    {
        if ($node instanceof Node\Expr\BinaryOp\Equal || $node instanceof Node\Expr\BinaryOp\NotEqual) {
            if (($node->left instanceof Node\Scalar\LNumber) || ($node->right instanceof Node\Scalar\LNumber)
             || ($node->left instanceof Node\Scalar\DNumber) || ($node->right instanceof Node\Scalar\DNumber)
            ) {
                $this->report($node, 'Equal/WEAK_COMP_NUM');
            }
            $funcName = $this->getMixReturnFuncCall($node->left);
            if ($funcName !== false) {
                $this->report($node->left, 'Equal/WEAK_COMP_FUNC['.$funcName.']');
            }
            $funcName = $this->getMixReturnFuncCall($node->right);
            if ($funcName !== false) {
                $this->report($node->right, 'Equal/WEAK_COMP_FUNC['.$funcName.']');
            }
        } elseif ($node instanceof Node\Expr\BinaryOp\LogicalAnd
         || $node instanceof Node\Expr\BinaryOp\LogicalOr
         || $node instanceof Node\Expr\BinaryOp\LogicalXor
        ) {
            $this->report($node, 'BinaryOp/LOGICAL_OPERATOR');
        }
    }
    private function enterFuncCall(Node\Expr\FuncCall $node)
    {
        if (is_string($node->name) || method_exists($node->name, '__toString')) {
            $funcName = (string)$node->name;
        } else {
            return;
        }
        $funcName = strtolower($funcName);
        switch ($funcName) {
            case 'fopen':
            case 'srand':
            case 'mt_srand':
                $this->report($node, "FuncCall/NON_BEGINNER_FUNC[$funcName]");
                break;
            case 'define':
            case 'defined':
                $name = $node->args[0]->value;
                //if (!($name instanceof Node\Scalar\String_)) {
                if ($name instanceof Node\Expr\ConstFetch) {
                    $this->report($node, "FuncCall/DEFINE_NAME");
                }
                break;
            case 'setlocale':
                $category = $node->args[0]->value;
                if ($category instanceof Node\Scalar\String_) {
                    $this->report($node, 'FuncCall/DEPRECATED_FUNC_PARAM[setlocale/$category]');
                }
                break;
            case 'ini_get':
            case 'ini_set':
                $directive = $node->args[0]->value;
                if ($directive instanceof Node\Scalar\String_) {
                    $info = BuiltInUtils::getDeprecatedIni(strtolower($directive->value));
                    if ($info !== false) {
                        $name = strtoupper($funcName);
                        $this->report($node, "FuncCall/DEPRECATED_{$name}[$directive->value]");
                    }
                }
                break;
            case 'preg_replace':
                static $pairs = array(
                    '(' => ')',
                    '[' => ']',
                    '{' => '}',
                    '<' => '>',
                );
                $pattern = $node->args[0]->value;
                if (!($pattern instanceof Node\Scalar\String_)) {
                    break;
                }
                $pattern = $pattern->value;
                $length = strlen($pattern);
                if ($length < 4) {
                    break;
                }
                $lhs = $pattern[0];
                $rhs = isset($pairs[$lhs]) ? $pairs[$lhs] : $lhs;
                $offset = strrpos($pattern, $rhs);
                if (($offset === false) || ($offset <= 1) || ($length <= ($offset + 1))) {
                    break;
                }
                if (strpos($pattern, 'e', $offset + 1) !== false) {
                    $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM[PREG_REPLACE_EVAL]");
                }
                break;
            case 'array_push':
                if (count($node->args) < 2) {
                    $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM_NUM[$funcName]");
                }
                break;
            case 'in_array':
            case 'array_search':
                if ((count($node->args) < 3)
                 || !($node->args[2]->value instanceof Node\Expr\ConstFetch)
                 || (strtolower($node->args[2]->value->name->toString()) !== 'true')
                ) {
                    $this->report($node, "FuncCall/WEAK_COMP_FUNC_PARAM[$funcName]");
                }
                break;
            case 'htmlentities':
            case 'htmlspecialchars':
                $count = count($node->args);
                if ($count < 3) {
                    if ($count < 2) {
                        $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM_NUM[$funcName]");
                        break;
                    }
                    $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM_ENC[$funcName]");
                }
                $nodeFlags = $node->args[1]->value;
                $flags = [];
                $hasOther = false;
                while (($nodeFlags instanceof Node\Expr\BinaryOp\BitwiseOr)) {
                    if ($nodeFlags->right instanceof Node\Expr\ConstFetch) {
                        $flags[] = $nodeFlags->right->name->toString();
                    } else {
                        $hasOther = true;
                    }
                    $nodeFlags = $nodeFlags->left;
                }
                if ($nodeFlags instanceof Node\Expr\ConstFetch) {
                    $flags[] = $nodeFlags->name->toString();
                } else {
                    $hasOther = true;
                }
                if (!$hasOther && !in_array('ENT_QUOTES', $flags, true)) {
                    $this->report($node, "FuncCall/RECOMMEND_FUNC_PARAM[$funcName/ENT_QUOTES]");
                }
                foreach (['ENT_COMPAT','ENT_NOQUOTES','ENT_IGNORE'] as $value) {
                    if (in_array($value, $flags, true)) {
                        $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM[$funcName/$value]");
                        break;
                    }
                }
                break;
            default:
                $info = BuiltInUtils::getDeprecatedFunc($funcName);
                if ($info !== false) {
                    $this->report($node, "FuncCall/DEPRECATED_API[$funcName]");
                }
                break;
        }
    }
    private function enterList(Node\Expr\List_ $node)
    {
        $store = [];
        foreach ($node->vars as $var) {
            if ($var !== null) {
                $pretty = $this->prettyPrinter->prettyPrint(array($var));
                if (isset($store[$pretty])) {
                    $this->report($node, 'List/ASSIGN_ORDER');
                } else {
                    $store[$pretty] = true;
                }
            }
        }
        if (empty($store)) {
            $this->report($node, 'List/EMPTY');
        }
    }
    private function enterEcho(Node\Stmt\Echo_ $node)
    {
        static $superglobals = null;
        if ($superglobals === null) {
            $superglobals = array_flip([
                '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_REQUEST',
            ]);
        }
        foreach ($node->exprs as $expr) {
            if ($expr instanceof Node\Expr\ArrayDimFetch) {
                while ($expr->var instanceof Node\Expr\ArrayDimFetch) {
                    $expr = $expr->var;
                }
                if (($expr->var instanceof Node\Expr\Variable)
                 && is_string($expr->var->name)
                 && array_key_exists(strtoupper($expr->var->name), $superglobals)
                ) {
                    $this->report($node, 'Echo/USER_INPUT[$'.$expr->var->name.']');
                }
            }
        }
    }
    // PSR-2: (abstract|final)?\s+(public|protected|private)\s+(static)?\s+function\s+(?<funcName>.*)\((?<param>.*)\) {}
    private function enterClassLike(Node\Stmt\ClassLike $node)
    {
        $visibilities = ['Method' => array(), 'Property' => array()];
        $previous = ['Method' => '', 'Property' => '', 't' => '' ];
        foreach ($node->stmts as $stmt) {
            $target = '';
            if ($stmt instanceof Node\Stmt\ClassMethod) {
                if ($stmt->name === $node->name) {
                    $this->report($stmt, 'ClassLike/PHP4CONSTRUCT');
                }
                $target = 'Method';
            } elseif ($stmt instanceof Node\Stmt\Property) {
                $target = 'Property';
            //} elseif ($stmt instanceof Node\Stmt\ClassConst) {
            //    $target = 'Const';
            }
            if (!empty($target)) {
                if (($target !== $previous['t']) && ($previous['t'] !== '') && !empty($visibilities[$target])) {
                    $this->report($stmt, 'ClassLike/MIXED_ORDER[Method/Property]');
                }
                // public=0, protected=1, private=2
                $visibility = (($stmt->type & Node\Stmt\Class_::VISIBILITY_MODIFER_MASK) >> 1);
                if ($visibility !== $previous[$target]) {
                    $previous[$target] = $visibility;
                    $visibilities[$target][] = $visibility;
                }
                $previous['t'] = $target;
            }
        }
        foreach ($visibilities as $key => $value) {
            if (3 < count($value)) {
                $this->report($node, "ClassLike/VISIBILITY_MIXED_ORDER[$key]");
            } else {
                if (!self::isSorted($value)) {
                    $this->report($node, "ClassLike/VISIBILITY_ORDER[$key]");
                }
            }
        }
    }
    private function enterLoop(Node\Stmt $node, $type)
    {
        $this->enterCond($node->cond, $type);
    }
    private function enterUnset(Node\Stmt\Unset_ $node)
    {
        static $superglobals = null;
        if ($superglobals === null) {
            $superglobals = array_flip([
                'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES',
                '_COOKIE', '_SESSION', '_REQUEST', '_ENV', 'argc', 'argv'
            ]);
        }
        foreach ($node->vars as $var) {
            if ($var instanceof Node\Expr\Variable
             && is_string($var->name)
             && array_key_exists($var->name, $superglobals)
            ) {
                $this->report($node, 'Unset/Superglobals[$'.$var->name.']');
            }
        }
    }
    private function enterSwitch(Node\Stmt\Switch_ $node)
    {
        $this->enterCond($node->cond, 'SWITCH');
        $defaultCaseCount = 0;
        $fallThrough = null;
        foreach ($node->cases as $case) {
            if ($case->cond === null) {
                ++$defaultCaseCount;
            } else {
                $this->enterCond($case->cond, 'CASE');
            }
            if ($fallThrough !== null) {
                $isIntention = false;
                foreach ((array)$case->getAttribute('comments') as $value) {
                    if (preg_match('/FALL[ -]?THROUGH|No break/i', $value->getText()) === 1) {
                        $isIntention = true;
                        break;
                    }
                }
                if ($isIntention === false) {
                    $this->report($fallThrough, 'Switch/FALL_THROUGH');
                }
                $fallThrough = null;
            }
            $stmtsCount = count($case->stmts);
            if (0 < $stmtsCount) {
                $last = $case->stmts[$stmtsCount - 1];
                if ($last instanceof Node\Stmt\Continue_) {
                    if ($last->num === null || $last->num->value === 1) {
                        $this->report($case, 'Switch/CONTINUE_BREAK');
                    }
                } elseif (!(($last instanceof Node\Stmt\Break_)
                 || ($last instanceof Node\Stmt\Return_)
                 || ($last instanceof Node\Stmt\Throw_)
                )) {
                    $fallThrough = $case;
                }
            }
        }
        if ($defaultCaseCount === 0) {
            $this->report($node, 'Switch/DEFAULT_NOTHING');
        } elseif (1 < $defaultCaseCount) {
            $this->report($node, 'Switch/DEFAULT_MULTIPLE');
        } else {
            $caseCount = count($node->cases);
            if (0 < $caseCount) {
                if ($node->cases[$caseCount - 1]->cond !== null) {
                    $this->report($node, 'Switch/DEFAULT_NON_TAIL');
                }
            }
        }
    }
    private function enterFunctionLike(Node\FunctionLike $node)
    {
        $store = [];
        foreach ($node->getParams() as $param) {
            $name = $param->name;
            if (isset($store[$name])) {
                $this->report($node, "FunctionLike/DUPLICATE_FUNC_PARAM[$name]");
            } else {
                $store[$name] = true;
            }
        }
    }
    private function enterCond($cond, $type)
    {
        if (is_array($cond)) {
            foreach ($cond as $conditions) {
                $this->enterCond($conditions, $type);
            }
        } elseif ($cond instanceof Node\Expr\BooleanNot) {
            $this->enterCond($cond->expr, $type);
        } elseif (($cond instanceof Expr\inaryOp\BooleanOr)
         || ($cond instanceof Expr\inaryOp\BooleanAnd)
        ) {
            $this->enterCond($cond->left, $type);
            $this->enterCond($cond->right, $type);
        } elseif ($node instanceof Node\Expr\BinaryOp\BitwiseOr
         || $node instanceof Node\Expr\BinaryOp\BitwiseAnd
        ) {
            $this->report($node, 'Cond/BITWISE_OPERATOR');
        } elseif ($cond instanceof Node\Expr\Assign) {
            $this->report($cond, 'Cond/ASSIGN_'.$type);
        } else {
            $funcName = $this->getMixReturnFuncCall($cond);
            if ($funcName !== false) {
                $this->report($cond, "Cond/WEAK_COMP_{$type}[$funcName]");
            }
        }
    }
    private function getMixReturnFuncCall($node)
    {
        if ($node instanceof Node\Expr\FuncCall) {
            if (is_string($node->name) || method_exists($node->name, '__toString')) {
                $funcName = (string)$node->name;
                if (BuiltInUtils::isReturnMixed(strtolower($funcName))) {
                    return $funcName;
                }
            }
        }
        return false;
    }
    private static function isSorted(array $ary)
    {
        $last = count($ary);
        if (0 !== $last) {
            $first = 0;
            $next = $first;
            while (++$next != $last) {
                if ($ary[$next] < $ary[$first]) {
                    return false;
                }
                ++$first;
            }
        }
        return true;
    }
    private $fileName;
    private $prettyPrinter;
    private $count;
}
