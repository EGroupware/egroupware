<?php
include_once(GALAXIA_LIBRARY.'/src/API/BaseActivity.php');
//!! End
//! End class
/*!
This class handles activities of type 'end'
*/
class End extends BaseActivity {
	function End($db)
	{
	  $this->setDb($db);
	}
}
?>
