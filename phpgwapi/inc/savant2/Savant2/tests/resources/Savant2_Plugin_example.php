<?php

/**
* 
* Example plugin for unit testing.
*
* @version $Id$
*
*/

require_once 'Savant2/Plugin.php';

class Savant2_Plugin_example extends Savant2_Plugin {
	
	var $msg = "Example: ";
	
	function plugin()
	{
		echo $this->msg . "this is an example!";
	}
}
?>