<?php
	/**************************************************************************\
	* eGroupWare - save redirect script                                      *
	* idea by: Jason Wies <jason@xc.net>                                       *
	* doing and adding to cvs: Lars Kneschke <lkneschke@linux-at-work.de>      *
	* http://www.egroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	/*
	  Use this script when you want to link to a external url.
	  This way you don't send sonething like sessionid's as referer
	  
	  Use this in your app:
	  
	  "<a href=\"$webserverURL/redirect.php?go=".htmlentities(urlencode('http://www.egroupware.org')).'">'
	*/

	if ($_GET['go'])
	{
	        Header('Location: ' . html_entity_decode(urldecode($_GET['go'])));
	        exit;
	}
	else
	{
		print "this want work!!";
	}
?>