<?php
define('PHPNAG_VERSIOIN', '0.0.1-bata2');
if (file_exists(__DIR__.'../vendor/autoload.php')) {
    require __DIR__.'../vendor/autoload.php';
} else {
    require __DIR__.'../../vendor/autoload.php';
}
// ----------------------------------------------------------------------------
if ($argc <= 1) {
    exit(usage());
}
$files = [];
for ($i = 1; $i < $argc; ++$i) {
    if ($argv[$i][0] === '-') {
        exit(usage());
    }
    if (is_dir($argv[$i])) {
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $argv[$i],
                \FilesystemIterator::SKIP_DOTS
            )
        ) as $fileInfo) {
            if (in_array(
                $fileInfo->getExtension(),
                ['php', 'phps', 'php5', 'php7', 'html', 'htm', 'phtml', 'tpl', 'cgi', 'inc'],
                true
            )) {
                $files[] = $fileInfo->getPathname();
            }
        }
    } else {
        $files[] = $argv[$i];
    }
}
$timeStart = microtime(true);
$total = 0;
$parser = new \PhpNag\Parser();
foreach ($files as $fileName) {
    if (is_readable($fileName)) {
        try {
            //echo 'File: '.$fileName.\PHP_EOL;
            $parser->parseFile($fileName);
            $total += $parser->getCount();
        } catch (\PhpParser\Error $e) {
            //echo 'Parse Error: ', $e->getMessage(), \PHP_EOL;
            echo 'Parse Error: ', $fileName, \PHP_EOL;
        }
    } else {
        echo 'Could not open input file: ', $fileName, \PHP_EOL;
    }
}
echo 'Total: ', $total, \PHP_EOL;
echo 'Finished in ', microtime(true) - $timeStart, ' seconds', \PHP_EOL;
// ----------------------------------------------------------------------------
function usage()
{
    return 'Version: ' . PHPNAG_VERSIOIN . \PHP_EOL . <<<OUTPUT
Usage: phpnag file1.php [file2.php ...]
Analyzes PHP source code.

Example:
    phpnag file.php

OUTPUT;
}
