<?php

$filename = $_POST['filename'];
$data = $_POST['data'];

$file = realpath('..') . '\\save\\' . $filename;

$f = fopen($file, 'w');
fwrite($f, $data);
fclose($f);

?>