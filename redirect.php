<?php
	/**************************************************************************\
	* phpGroupWare - save redirect script                                      *
	* idea by: Jason Wies <jason@xc.net>                                       *
	* doing and adding to cvs: Lars Kneschke <lkneschke@linux-at-work.de>      *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	if ($GLOBALS['go'])
	{
	        Header('Location: ' . $GLOBALS['go']);
	        exit;
	}
	else
	{
		print "this want work!!";
	}
?>