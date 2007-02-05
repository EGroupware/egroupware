<?php
require_once(EGW_INCLUDE_ROOT . '/phpgwapi/inc/class.dragdrop.inc.php');

if($GLOBALS['egw_info']['user']['preferences']['common']['enable_dragdrop'])
{
	$dragdrop = new dragdrop();

	$dragdrop->addCustom(
		'thesideboxcolumn',
		array('NO_DRAG'),
		false,
		false,
		false
	);

	$dragdrop->addCustom(
		'sideresize',
		array('CURSOR_W_RESIZE','MAXOFFBOTTOM+0','MAXOFFTOP+0','MAXOFFLEFT+1000','MAXOFFRIGHT+1000'),
		array('sideboxwidth'=>$this->sideboxwidth),
		'phpgwapi.dragDropFunctions.dragSidebar',
		'phpgwapi.dragDropFunctions.dropSidebar'
	);

	$dragdrop->setJSCode();
}