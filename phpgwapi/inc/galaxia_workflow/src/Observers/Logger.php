<?php
include_once(GALAXIA_LIBRARY.'/src/common/Observer.php');
//!! Logger
//! Log
/*!

  blah....
*/
class Logger extends Observer {
	var $_filename;
	
	function Logger($filename) {
		$this->_filename = $filename;
		$fp = fopen($this->_filename,"a");
		if(!$fp) {
		  trigger_error("Logger cannot append to log file: ".$this->filename,E_USER_WARNING);
		}
		if($fp) {
		fclose($fp);
		}

	}
	
	function notify($event,$msg) {
		// Add a line to the log file.
		$fp = fopen($this->_filename,"a");
		$date = date("[d/m/Y h:i:s]");
		$msg=trim($msg);
		fputs($fp,$date." ".$msg."\n");
		fclose($fp);
	}
}
?>
