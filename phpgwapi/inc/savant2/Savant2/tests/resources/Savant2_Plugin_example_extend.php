<?php

/**
* 
* Example plugin for unit testing.
*
* @version $Id$
*
*/

$this->loadPlugin('example');

class Savant2_Plugin_example_extend extends Savant2_Plugin_example {
	
	var $msg = "Extended Example! ";
	
}
?>