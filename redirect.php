<?php
	/**************************************************************************\
	* eGroupWare - save redirect script                                        *
	* idea by: Jason Wies <jason@xc.net>                                       *
	* doing and adding to cvs: Lars Kneschke <lkneschke@linux-at-work.de>      *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	
	/* $Id$ */

	/*
	  Use this script when you want to link to a external url.
	  This way you don't send something like sessionid as referer
	  
	  Use this in your app:
	  
	  "<a href=\"$webserverURL/redirect.php?go=".htmlentities(urlencode('http://www.egroupware.org')).'">'
	*/

	if(!function_exists('html_entity_decode'))
	{
		function html_entity_decode($given_html, $quote_style = ENT_QUOTES)
		{
			$trans_table = array_flip(get_html_translation_table( HTML_SPECIALCHARS, $quote_style));
			$trans_table['&#39;'] = "'";
			return(strtr($given_html, $trans_table));
		}
	}

	if($_GET['go'])
	{
		Header('Location: ' . html_entity_decode(urldecode($_GET['go'])));
		exit;
	}
	else
	{
		echo "this won't work!!";
	}
?>
