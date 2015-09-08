<?php

require_once 'Savant2/Filter.php';

class Savant2_Filter_fester extends Savant2_Filter {
	
	var $count = 0;
	
	function filter(&$text)
	{
		$text .= "<br />Fester has a light bulb in his mouth (" .
			$this->count ++ . ")\n";
			
		$text .= "<br />Fester has a light bulb in his mouth again (" .
			$this->count ++ . ")\n";
	}
}
?>