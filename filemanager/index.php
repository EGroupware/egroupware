<?php

###
# DEV NOTE:
#
# index.php is depreciated by the inc/class.xxfilemanager.inc.php files.
# index.php is still used in the 0.9.14 release, but all future changes should be
# made to the inc/class.xxfilemanager.inc.php files (3-tiered).  This includes using templates.
###

###
# Enable this to display some debugging info
###

	$phpgw_flags = Array(
		'currentapp'	=>	'filemanager',
		'noheader'	=>	True,
		'nonavbar'	=>	True,
		'noappheader'	=>	True,
		'noappfooter'	=>	True,
		'nofooter'	=>	True
	);
	$GLOBALS['phpgw_info']['flags'] = $phpgw_flags;
	
	include('../header.inc.php');

	$parms = Array(
		'menuaction'=> 'filemanager.uifilemanager.index'
	);

	$GLOBALS['phpgw']->redirect($GLOBALS['phpgw']->link('/index.php',$parms));
	$GLOBALS['phpgw_info']['flags']['nodisplay'] = True;
	exit;
?>
