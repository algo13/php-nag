<?php
namespace PhpNag;

use PhpParser\Node;
use PhpNag\Utils\BuiltInUtils;

class NodeVisitor extends \PhpParser\NodeVisitorAbstract
{
    public function __construct()
    {
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
        static $cache = [];
        $name = get_class($node);
        if (!isset($cache[$name])) {
            $cache[$name] = [];
            //$method = 'enter'.str_replace(['Expr_', 'Stmt_', '_'], '', $node->getType());
            $method = 'enter'.str_replace(['Expr\\', 'Stmt\\', '\\'], '', substr(rtrim($name, '_'), 15));
            //if (method_exists($this, $method)) {
            if (is_callable([$this, $method])) {
                $cache[$name][] = $method;
            }
            if ($node instanceof Node\Expr\BinaryOp) {
                $cache[$name][] = 'enterBinaryOp';
            } elseif ($node instanceof Node\FunctionLike) {
                $cache[$name][] = 'enterFunctionLike';
            } elseif ($node instanceof Node\Stmt\ClassLike) {
                $cache[$name][] = 'enterClassLike';
            }
        }
        foreach ($cache[$name] as $method) {
            $this->$method($node);
        }
    }
    private function enterErrorSuppress(Node\Expr\ErrorSuppress $node)
    {
        $this->report($node, 'ErrorSuppress');
    }
    private function enterAssignRef(Node\Expr\AssignRef $node)
    {
        if ($node->expr instanceof Node\Expr\New_) {
            $this->report($node, 'AssignRef/NEW');
        }
    }
    private function enterCastUnset(Node\Expr\Cast\Unset_ $node)
    {
        $this->report($node, 'Cast/UNSET');
    }
    private function enterIf(Node\Stmt\If_ $node)
    {
        $this->enterCond($node->cond, 'IF');
        //if (!empty($node->elseifs) && ($node->else === null)) {
        //    $this->report($node, 'If/INCOMPLETE_ELSEIF');
        //}
    }
    private function enterElseIf(Node\Stmt\ElseIf_ $node)
    {
        $this->enterCond($node->cond, 'ELSEIF');
    }
    private function enterFor(Node\Stmt\For_ $node)
    {
        $this->enterLoop($node, 'FOR');
        if (1 < count($node->cond)) {
            $this->report($node, 'For/COND_MULTIPLE');
        }
    }
    private function enterWhile(Node\Stmt\While_ $node)
    {
        $this->enterLoop($node, 'WHILE');
    }
    private function enterDo(Node\Stmt\Do_ $node)
    {
        $this->enterLoop($node, 'DO');
    }
    private function enterCatch(Node\Stmt\Catch_ $node)
    {
        if (empty($node->stmts)) {
            $this->report($node, 'Catch/EMPTY');
        }
    }
    private function enterGoto(Node\Stmt\Goto_ $node)
    {
        $this->report($node, 'Goto');
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
    private function enterArrayDimFetch(Node\Expr\ArrayDimFetch $node)
    {
        if ($node->dim instanceof Node\Expr\ConstFetch) {
            $name = $node->dim->name->toString();
            if ($name !== strtoupper($name)) {
                $this->report($node, 'ArrayDimFetch/Const');
            }
        }
    }
    private function enterBinaryOp(Node\Expr\BinaryOp $node)
    {
        if ($node instanceof Node\Expr\BinaryOp\Equal || $node instanceof Node\Expr\BinaryOp\NotEqual) {
            foreach ([$node->left, $node->right] as $side) {
                if (($side instanceof Node\Scalar\LNumber) || ($side instanceof Node\Scalar\DNumber)) {
                    $this->report($node, 'Equal/WEAK_COMP_NUM');
                    break;
                } elseif ($side instanceof Node\Expr\ConstFetch) {
                    $name = strtolower($side->name->toString());
                    if ($name === 'null') {
                        $this->report($node, 'Equal/WEAK_COMP_NULL');
                        break;
                    } elseif (($name === 'true') || ($name === 'false')) {
                        $this->report($node, 'Equal/WEAK_COMP_BOOL');
                        break;
                    }
                } else {
                    $funcName = self::getMixReturnFuncCall($side);
                    if ($funcName !== false) {
                        $this->report($node, 'Equal/WEAK_COMP_FUNC['.$funcName.']');
                        break;
                    }
                }
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
            case 'mt_srand':
            case 'strtr':
                $this->report($node, "FuncCall/NON_BEGINNER_FUNC[$funcName]");
                break;
            case 'compact':
                $name = false;
                foreach ($node->args as $arg) {
                    $name = self::getUserInput($arg->value);
                    if ($name !== false) {
                        $this->report($node, 'FuncCall/USER_INPUT[$'.$name.']');
                        break;
                    }
                }
                if ($name === false) {
                    $this->report($node, "FuncCall/NON_BEGINNER_FUNC[$funcName]");
                }
                break;
            case 'extract':
                $name = self::getUserInput($node->args[0]->value);
                if ($name !== false) {
                    $this->report($node, 'FuncCall/USER_INPUT[$'.$name.']');
                } else {
                    $this->report($node, "FuncCall/NON_BEGINNER_FUNC[$funcName]");
                }
                break;
            case 'wddx_deserialize':
                $name = self::getUserInput($node->args[0]->value);
                if ($name !== false) {
                    $this->report($node, 'FuncCall/USER_INPUT[$'.$name.']');
                }
                break;
            case 'chmod':
                $mode = $node->args[1]->value;
                if (!($mode instanceof Node\Scalar\LNumber)
                 || ($mode->getAttribute('kind', Node\Scalar\LNumber::KIND_DEC) !== Node\Scalar\LNumber::KIND_OCT)
                ) {
                    $this->report($mode, "FuncCall/NON_OCT[$funcName]");
                }
                break;
            case 'define':
            case 'defined':
                $name = $node->args[0]->value;
                //if (!($name instanceof Node\Scalar\String_)) {
                if ($name instanceof Node\Expr\ConstFetch) {
                    $this->report($node, 'FuncCall/'.strtoupper($funcName).'_CONST');
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
            case 'mb_ereg_replace':
            case 'mb_eregi_replace':
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
            case 'session_regenerate_id':
                if ((count($node->args) < 1)
                 || !($node->args[0]->value instanceof Node\Expr\ConstFetch)
                 || (strtolower($node->args[0]->value->name->toString()) !== 'true')
                ) {
                    $this->report($node, "FuncCall/DEPRECATED_FUNC_PARAM[$funcName]");
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
        foreach ($node->items as $item) {
            if ($item !== null) {
                if (in_array($item, $store, false)) {
                    $this->report($node, 'List/ASSIGN_ORDER');
                } else {
                    $store[] = $item;
                }
            }
        }
        if (empty($store)) {
            $this->report($node, 'List/EMPTY');
        }
    }
    private function enterPrint(Node\Expr\Print_ $node)
    {
        $name = self::getUserInput($node->expr);
        if ($name !== false) {
            $this->report($node, 'Print/USER_INPUT[$'.$name.']');
        }
    }
    private function enterEcho(Node\Stmt\Echo_ $node)
    {
        foreach ($node->exprs as $expr) {
            $name = self::getUserInput($expr);
            if ($name !== false) {
                $this->report($node, 'Echo/USER_INPUT[$'.$name.']');
            }
        }
    }
    private function enterProperty(Node\Stmt\Property $node)
    {
        if (1 < count($node->props)) {
            $this->report($node, 'Property/MULTIPLE');
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
                $visibility = (($stmt->flags & Node\Stmt\Class_::VISIBILITY_MODIFER_MASK) >> 1);
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
            $stmtsCount = count($case->stmts);
            if (0 < $stmtsCount) {
                do {
                    $stmt = $case->stmts[--$stmtsCount];
                    if ($stmt instanceof Node\Stmt\Break_ || $stmt instanceof Node\Stmt\Return_
                     || $stmt instanceof Node\Stmt\Throw_ || $stmt instanceof Node\Stmt\Continue_
                    ) {
                        if ($stmt instanceof Node\Stmt\Continue_) {
                            if ($stmt->num === null || $stmt->num->value === 1) {
                                $this->report($case, 'Switch/CONTINUE_BREAK');
                            }
                        }
                        break;
                    } elseif ($stmt instanceof Node\Stmt\Nop) {
                        foreach ((array)$stmt->getAttribute('comments') as $value) {
                            // Intention comment
                            if (preg_match('/FALL[ -]?THROUGH|No break/i', $value->getText()) === 1) {
                                break 2;
                            }
                        }
                    } else {
                        $this->report($stmt, 'Switch/FALL_THROUGH');
                    }
                } while ($stmtsCount !== 0);
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
        } elseif (($cond instanceof Node\Expr\BinaryOp\BooleanOr)
         || ($cond instanceof Node\Expr\BinaryOp\BooleanAnd)
        ) {
            $this->enterCond($cond->left, $type);
            $this->enterCond($cond->right, $type);
        } elseif ($cond instanceof Node\Expr\BinaryOp\BitwiseOr
         || $cond instanceof Node\Expr\BinaryOp\BitwiseAnd
        ) {
            $this->report($cond, 'Cond/BITWISE_OPERATOR');
        } elseif ($cond instanceof Node\Expr\Assign) {
            $this->report($cond, 'Cond/ASSIGN_'.$type);
        } else {
            $funcName = self::getMixReturnFuncCall($cond);
            if ($funcName !== false) {
                $this->report($cond, "Cond/WEAK_COMP_{$type}[$funcName]");
            }
        }
    }
    private static function getUserInput($node)
    {
        static $userinput = null;
        if ($userinput === null) {
            $userinput = array_flip([
                '_GET', '_POST', '_COOKIE', '_REQUEST', '_FILES', '_SERVER',
                'HTTP_GET_VARS', 'HTTP_POST_VARS', 'HTTP_COOKIE_VARS', 'HTTP_REQUEST_VARS',
                'HTTP_POST_FILES', 'HTTP_SERVER_VARS', 'HTTP_RAW_POST_DATA',
                'argc', 'argv',
            ]);
        }
        if ($node instanceof Node\Expr\ArrayDimFetch) {
            while ($node->var instanceof Node\Expr\ArrayDimFetch) {
                $node = $node->var;
            }
            $node = $node->var;
        }
        if (($node instanceof Node\Expr\Variable)
         && is_string($node->name)
         && array_key_exists($node->name, $userinput)
        ) {
            return $node->name;
        }
        return false;
    }
    private static function getMixReturnFuncCall($node)
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
    private $count;
}
