<?php
include_once(GALAXIA_LIBRARY.'/src/API/BaseActivity.php');
//!! Start
//! Start class
/*!
This class handles activities of type 'start'
*/
class Start extends BaseActivity {
	function Start($db)
	{
	  $this->setDb($db);
	}
}
?>
