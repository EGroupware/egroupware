<?php
require_once(EGW_INCLUDE_ROOT . '/phpgwapi/inc/class.dragdrop.inc.php');

if($GLOBALS['egw_info']['user']['preferences']['common']['enable_dragdrop'])
{
	$dragdrop = new dragdrop();
	$maxOffLeft = $this->sideboxwidth - 200;
	$maxOffRight = 500 - $this->sideboxwidth;
	$maxOffLeft < 0 && $maxOffLeft = 0;
	$maxOffRight < 0 && $maxOffRight = 0;

	$dragdrop->addCustom(
		'thesideboxcolumn',
		array('NO_DRAG')
	);

	$dragdrop->addCustom(
		'sideresize',
		array('CURSOR_W_RESIZE','HORIZONTAL','MAXOFFLEFT+'.$maxOffLeft,'MAXOFFRIGHT+'.$maxOffRight),
		array('sideboxwidth'=>$this->sideboxwidth),
		'phpgwapi.dragDropFunctions.dragSidebar',
		'phpgwapi.dragDropFunctions.dropSidebar'
	);

	$dragdrop->setJSCode();
}