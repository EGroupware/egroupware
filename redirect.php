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

	/* Only allow redirects with a valid session */
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'noheader'   => True,
			'nonavbar'   => True,
			'currentapp' => 'home'
		)
	);
	include('./header.inc.php');


	/* Only allow redirects from inside this eGroupware installation. */
	$valid_referer = array();
	$path = preg_replace('/\/[^\/]*$/','',$_SERVER['PHP_SELF']) . '/';
	array_push($valid_referer, $path);
	array_push($valid_referer, ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['SERVER_ADDR'] . $path);
	array_push($valid_referer, ($_SERVER['HTTPS'] ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . $path);

	$referrer = trim($_SERVER['HTTP_REFERER']);
	if ((!isset($_SERVER['HTTP_REFERER'])) || (empty($referrer)))
	{
		echo "Only usable from within eGroupware.\n";
	}
	else if($_GET['go'])
	{
		$allow = false;
		foreach ($valid_referer as $urlRoot)
		{
			/* Check if the referrer begins with a valid URL. */
			if (strncmp($urlRoot, $referrer, strlen($urlRoot)) == 0)
			{
				$allow = true;
				break;
			}
		}
		if ($allow)
		{
			$url= html_entity_decode(urldecode($_GET['go']));
			unset($_GET['go']);
			/* Only add "&" if there is something to append. */
			if (!empty($_GET))
			{
				$url=$url."&".http_build_query($_GET);
			}

			Header('Location: ' . html_entity_decode(urldecode($url)));
			exit;
		}
		else
		{
			echo "Redirect not allowed for referrer '".$_SERVER['HTTP_REFERER']."'.\n";
			echo "<pre>";
			print_r($valid_referer);
			echo "<pre>\n";
		}
	}
	else
	{
		echo "Error redirecting.";
	}
?>
