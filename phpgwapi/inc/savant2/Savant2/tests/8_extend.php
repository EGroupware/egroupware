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

$conf = array(
	'template_path' => 'templates',
	'resource_path' => 'resources'
);

$savant = new Savant2($conf);

$savant->display('extend.tpl.php');

?>