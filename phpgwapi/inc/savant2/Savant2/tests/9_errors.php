<?php

/**
* 
* Tests default plugins
*
* @version $Id$
* 
*/

error_reporting(E_ALL);

require_once 'Savant2.php';

$savant = new Savant2();

require_once 'PEAR.php';
PEAR::setErrorHandling(PEAR_ERROR_PRINT);

echo "<h1>PEAR_Error</h1>\n";
$savant->setError('pear');
$result = $savant->loadPlugin('nosuchthing');
echo "<pre>\n";
print_r($result);
echo "</pre>\n\n";

echo "<h1>PEAR_ErrorStack</h1>\n";
$savant->setError('stack');
$result = $savant->loadPlugin('nosuchthing');
echo "<pre>\n";
print_r($result);
echo "</pre>\n\n";

echo "<pre>\n";
print_r(print_r($GLOBALS['_PEAR_ERRORSTACK_SINGLETON']));
echo "</pre>\n\n";

echo "<h1>Exception</h1>\n";
$savant->setError('exception');
$result = $savant->loadPlugin('nosuchthing');
echo "<pre>\n";
print_r($result);
echo "</pre>\n\n";


?>