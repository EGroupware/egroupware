<?php
	
	// FIXME add copyright header
	/*
	eGroupWare - http://www.egroupware.org
	written by Pim Snel <pim@lingewoud.nl>
	*/


	$phpgw_flags = Array(
		'currentapp'    =>      'filemanager',
		'noheader'      =>      True,
		'nonavbar'      =>      True,
		'noappheader'   =>      True,
		'noappfooter'   =>      True,
		'nofooter'      =>      True
	);

	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;

	include('../header.inc.php');

	Header('Location: '.$GLOBALS['phpgw']->link('/index.php','menuaction=filemanager.uifilemanager.index'));
	$GLOBALS['phpgw']->common->phpgw_exit();
?>
