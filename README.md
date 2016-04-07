# php-nag

Static analysis tool for PHP source code by PHP.

## Getting Started

[Download phar](https://github.com/algo13/php-nag/releases)

## Usage

~~~sh
php -d "memory_limit=512M" phpnag.phar file.php
~~~

## Summary

-   ErrorSuppress :beginner:

    ~~~php
    @file('test.txt');
    ~~~

-   AssignRef/NEW :bomb:

    ~~~php
    $instance =& new Class();
    ~~~

-   Catch/EMPTY :beginner:

    ~~~php
    try {
        func();
    } catch (Error $e) {
        // empty.
    }
    ~~~

-   Goto :beginner:

-   Variable/VARIABLE_VARIABLES :beginner:

    ~~~php
    $$value;
    ~~~

-   Variable/DEPRECATED_GLOBALS :bomb:

-   Equal/WEAK_COMP_NUM

    ~~~php
    $value = '1abc';
    if ($value == 1) {};
    ~~~

-   Equal/WEAK_COMP_FUNC

    ~~~php
    if (strpos($value, 'startswith') == 0) {};
    ~~~

-   BinaryOp/LOGICAL_OPERATOR :beginner:

    ~~~php
    if ($val1 and $val2) {};
    ~~~

-   FuncCall/NON_BEGINNER_FUNC :beginner:

-   FuncCall/DEFINE_CONST

    ~~~php
    define(STRING, 'value');
    ~~~

-   FuncCall/DEFINED_CONST

    ~~~php
    defined(STRING);
    ~~~

-   FuncCall/DEPRECATED_FUNC_PARAM\[`setlocale`/$category\] :bomb:

    ~~~php
    setlocale('STRING', 0);
    ~~~

-   FuncCall/DEPRECATED_INI_GET\[$directive\] :beginner:

-   FuncCall/DEPRECATED_INI_SET\[$directive\] :bomb:

-   FuncCall/DEPRECATED_FUNC_PARAM\[`PREG_REPLACE_EVAL`\] :bomb:

    ~~~php
    preg_replace('/pattern/e', 'value', $string);
    ~~~

-   FuncCall/DEPRECATED_FUNC_PARAM_NUM\[`array_push`\] :dash:

    ~~~php
    array_push($array, 'value');
    ~~~

-   FuncCall/WEAK_COMP_FUNC_PARAM\[`in_array`\]

    The third argument is not set.

-   FuncCall/WEAK_COMP_FUNC_PARAM\[`array_search`\]

    The third argument is not set.

-   FuncCall/DEPRECATED_FUNC_PARAM_NUM\[`htmlentities` or `htmlspecialchars`\] :smiling_imp:

    The $flags argument is not set.

-   FuncCall/DEPRECATED_FUNC_PARAM_ENC\[`htmlentities` or `htmlspecialchars`\]

    The $encoding argument is not set.

-   FuncCall/RECOMMEND_FUNC_PARAM\[`htmlentities` or `htmlspecialchars`\]

    It is recommended that you use `ENT_QUOTES` flag.

-   FuncCall/DEPRECATED_FUNC_PARAM\[`htmlentities` or `htmlspecialchars`\] :smiling_imp:

    Deprecated flag(`ENT_COMPAT`, `ENT_NOQUOTES`, `ENT_IGNORE`)

-   FuncCall/DEPRECATED_API\[$funcName\] :bomb:

-   List/ASSIGN_ORDER :bomb:

    ~~~php
    list($a[], $a[]);
    ~~~

-   List/EMPTY :bomb:

    ~~~php
    list(,);
    ~~~

-   Print/USER_INPUT\[$\_GET or $\_POST ...\] :smiling_imp:

-   Echo/USER_INPUT\[$\_GET or $\_POST ...\] :smiling_imp:

-   ClassLike/PHP4CONSTRUCT :bomb:

-   ClassLike/MIXED_ORDER\[Method/Property\]

    ~~~php
    class Example {
        public $member1;
        public function func1(){};
        public $member2;
        public function func2(){};
    }
    ~~~

-   ClassLike/VISIBILITY_MIXED_ORDER

-   ClassLike/VISIBILITY_ORDER

    `public`, `protected`, `private`

-   Unset/Superglobals :boom:

    ~~~php
    unset($_SESSION);
    ~~~

-   Switch/FALL_THROUGH

    ~~~php
    switch ($string) {
    case 'one':
        $value = 'string';
        //break; <= fall through
    case 'two':
        $value = 'string string';
        break;
    }
    ~~~

-   Switch/CONTINUE_BREAK

-   Switch/DEFAULT_NOTHING

-   Switch/DEFAULT_MULTIPLE :bomb:

-   Switch/DEFAULT_NON_TAIL :beginner:

-   FunctionLike/DUPLICATE_FUNC_PARAM :bomb:

    ~~~php
    function func($a, $a) {}
    ~~~

-   Cond/BITWISE_OPERATOR :beginner:

    ~~~php
    if ($a & $b) {}
    ~~~

-   Cond/ASSIGN_IF (for, while ...)

    ~~~php
    if ($a = func()) {}
    ~~~

-   Cond/WEAK_COMP_IF (for, while ...)
