<?php
include_once(GALAXIA_LIBRARY.'/src/API/BaseActivity.php');
//!! SwitchActivity
//! SwitchActivity class
/*!
This class handles activities of type 'switch'
*/
class SwitchActivity extends BaseActivity {
	function SwitchActivity($db)
	{
	  $this->setDb($db);
	}
}
?>
