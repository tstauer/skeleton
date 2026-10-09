<?php

// router for "php -S": serve existing files from public/, everything else through Symfony
$path = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
if (\is_string($path) && '/' !== $path && \is_file(__DIR__ . '/../../public' . $path)) {
    return false;
}

$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../../public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/../../public/index.php';
