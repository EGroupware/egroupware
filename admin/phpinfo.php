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

	$GLOBALS['phpgw_info']['flags'] = array
	(
		'noframework'	=> True,
		'currentapp'	=> 'admin'
	);
	include('../header.inc.php');

// Throw a little notice out if PHPaccelerator is enabled.
	if($GLOBALS['_PHPA']['ENABLED'])
	{
		echo 'PHPaccelerator enabled:</br>'."\n";
		echo 'PHPaccelerator Version: '.$GLOBALS['_PHPA']['VERSION'].'</br></p>'."\n";
	}

	phpinfo();
?>
