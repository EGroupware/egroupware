<?php

/**
* 
* Tests assign() issues
*
* @version $Id$
* 
*/


error_reporting(E_ALL);

require_once 'Savant2.php';
$savant = new Savant2(array('template_path' => 'templates'));

echo "<h1>assign 0 (string, null)</h1>";
$val = null;
$result = $savant->assign('nullvar', $val);
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";


echo "<h1>assign 1 (string, mixed)</h1>";
$result = $savant->assign('variable', 'variable_value');
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";


echo "<h1>assign 2 (array)</h1>";
$result = $savant->assign(array('array1' => 'value1', 'array2' => 'value2'));
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";

echo "<h1>assign 3 (object)</h1>";
$object = new StdClass();
$object->obj1 = 'this';
$object->obj2 = 'that';
$object->obj3 = 'other';
$result = $savant->assign($object);
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";


echo "<h1>assignRef</h1>";
$reference = 'reference_value';
$result = $savant->assignRef('reference', $reference);
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";

/*
echo "<h1>assignObject</h1>";
$object = new stdClass();
$result = $savant->assignObject('object', $object);
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";
*/

echo "<h1>Assign variable without value</h1>";
$result = $savant->assign('variable_without_value');
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";


echo "<h1>Assign reference without value</h1>";
$result = $savant->assignRef('reference_without_value');
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";

/*
echo "<h1>Assign object when value is not object</h1>";
$reference3 = 'failed!';
$result = $savant->assignObject('object2', $reference3);
echo "result: <pre>";
print_r($result);
echo "</pre>";
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";
*/

echo "<h1>Change reference values from logic</h1>";
$reference = 'CHANGED VALUE FROM LOGIC';
echo "properties: <pre>";
print_r(get_object_vars($savant));
echo "</pre>";

echo "<h1>getVars</h1>";

echo "<p>All</p><pre>";
print_r($savant->getVars());
echo "</pre>";

echo "<p>Some</p><pre>";
print_r($savant->getVars(array('obj1', 'obj2', 'obj3')));
echo "</pre>";

echo "<p>One</p><pre>";
print_r($savant->getVars('variable'));
echo "</pre>";

echo "<p>Nonexistent</p><pre>";
var_dump($savant->getVars('nosuchvar'));
echo "</pre>";

$savant->display('assign.tpl.php');
echo "<p>After: $reference</p>";

?>