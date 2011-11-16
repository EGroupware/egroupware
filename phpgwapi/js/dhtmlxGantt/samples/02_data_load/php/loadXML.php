<?php

$file = realpath('..') . '\\' . $_GET['path'];

if (file_exists($file)) {
    header('Content-type: text/xml');
    readfile($file);
}

?>