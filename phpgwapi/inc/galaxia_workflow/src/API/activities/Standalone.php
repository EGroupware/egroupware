<?php
include_once(GALAXIA_LIBRARY.'/src/API/BaseActivity.php');
//!! Standalone
//! Standalone class
/*!
This class handles activities of type 'standalone'
*/
class Standalone extends BaseActivity {
	function Standalone($db)
	{
	  $this->setDb($db);
	}
}
?>
