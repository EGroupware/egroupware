<?php
	/**************************************************************************\
	* phpGroupWare - administration                                            *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'admin'
	);
	include('../header.inc.php');
	
	if ($GLOBALS['phpgw']->acl->check('info_access',1,'admin'))
	{
		$GLOBALS['phpgw']->redirect_link('/index.php');
	}

// Throw a little notice out if PHPaccelerator is enabled.
	if($GLOBALS['_PHPA']['ENABLED'])
	{
		echo 'PHPaccelerator enabled:</br>'."\n";
		echo 'PHPaccelerator Version: '.$GLOBALS['_PHPA']['VERSION'].'</br></p>'."\n";
	}
	
	phpinfo();

//	$phpgw->common->phpgw_footer();
?>
