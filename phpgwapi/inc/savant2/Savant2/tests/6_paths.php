<?php

/**
* 
* Tests multiple-path directory searches
*
* @version $Id$
* 
*/

function preprint($val)
{
	echo "<pre>\n";
	print_r($val);
	echo "</pre>\n";
}

error_reporting(E_ALL);

require_once 'Savant2.php';

$conf = array(
	'template_path' => 'templates',
	'resource_path' => 'resources'
);

$savant = new Savant2($conf);

echo "<h1>Paths to begin with</h1>\n";
preprint($savant->getPath('resource'));
preprint($savant->getPath('template'));

echo "<h1>Add a path</h1>\n";
$savant->addPath('resource', 'no/such/path');
preprint($savant->getPath('resource'));

echo "<h1>Find an existing resource (non-default)</h1>\n";
$file = $savant->findFile('resource', 'Savant2_Plugin_cycle.php');
preprint($file);

echo "<h1>Find an existing resource (default)</h1>\n";
$file = $savant->findFile('resource', 'Savant2_Plugin_input.php');
preprint($file);

echo "<h1>Find a non-existent template</h1>\n";
$file = $savant->findFile('template', 'no_such_template.tpl.php');
if ($file) {
	preprint($file);
} else {
	preprint("false or null");
}
?>