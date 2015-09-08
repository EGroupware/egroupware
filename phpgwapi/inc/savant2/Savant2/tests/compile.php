<?php

/**
* 
* Tests the basic compiler
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

// instantiate Savant
require_once 'Savant2.php';

$conf = array(
	'template_path' => 'templates',
	'resource_path' => 'resources',
	'restrict' => true // adding path restrictions!
);

$savant = new Savant2($conf);

// instantiate a compiler...
require_once 'Savant2/Savant2_Compiler_basic.php';
$compiler = new Savant2_Compiler_basic();
$compiler->compileDir = '/tmp/';
$compiler->forceCompile = true;

// and tell Savant to use it.
$savant->setCompiler($compiler);

// set up vars
$array = array(
	'key0' => 'val0',
	'key1' => 'val1',
	'key2' => 'val2',
);

$var1 = 'variable1';
$var2 = 'variable2';
$var3 = 'variable3';

$ref1 = 'reference1';
$ref2 = 'reference2';
$ref3 = 'reference3';

// assign vars
$savant->assign($var1, $var1);
$savant->assign($var2, $var2);
$savant->assign($var3, $var3);

// assigns $array to a variable $set
$savant->assign('set', $array);

// assigns the keys and values of array
$savant->assign($array);

// assign references
$savant->assignRef($ref1, $ref1);
$savant->assignRef($ref2, $ref2);
$savant->assignRef($ref3, $ref3);

echo "<h1>The 'good' template</h1>";
$compiler->strict = false;
$result = $savant->display('compile.tpl.php');
preprint($result);

echo "<h1>The 'bad' template</h1>";
$compiler->strict = true;
$result = $savant->display('compile_bad.tpl.php');
preprint($result);
?>