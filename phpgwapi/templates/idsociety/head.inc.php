<?php
  /**************************************************************************\
  * phpGroupWare                                                             *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	// needed until hovlink is specified in all theme files
	if (isset($GLOBALS['phpgw_info']['theme']['hovlink'])
	 && ($GLOBALS['phpgw_info']['theme']['hovlink'] != ''))
	{
		$csshover = 'A:hover{ text-decoration:none; color: ' .$GLOBALS['phpgw_info']['theme']['hovlink'] .'; }';
	}
	else
	{
		$csshover = '';
	}

	$app_css = '';
	if(@isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
	{
		list($app,$class,$method) = explode('.',$GLOBALS['HTTP_GET_VARS']['menuaction']);
		if(is_array($GLOBALS[$class]->public_functions) && $GLOBALS[$class]->public_functions['css'])
		{
			$app_css = $GLOBALS[$class]->css();
		}
	}

	$bodyheader = 'bgcolor="'.$GLOBALS['phpgw_info']['theme']['bg_color'].'" alink="'.$GLOBALS['phpgw_info']['theme']['alink'].'" link="'.$GLOBALS['phpgw_info']['theme']['link'].'" vlink="'.$GLOBALS['phpgw_info']['theme']['vlink'].'"';
	if (!$GLOBALS['phpgw_info']['server']['htmlcompliant'])
	{
		$bodyheader .= ' topmargin="0" marginheight="0" marginwidth="0" leftmargin="0"';
	}
	
	/*
	@capability:  page autorefresh
	@discussion: I know of 3 ways to get a page to reload, 2 of those ways are pretty much the same
	(1) the http header 
		Refresh: 5;
	(2) the META http-equiv 
		<META HTTP-EQUIV="Refresh" CONTENT="5">
	both 1 and 2 have the same effect as hitting the "reload" button, which in *many* browsers will
	force a re-download of all the images on the page, i.e. the browser will NOT use the cached images
	(3) java script combo of "window.setTimeout" with "window.location"
		window.setTimeout('window.location="http://example.com/phpgw/email/index.php"; ',1800000);
	method 3 is the only one I know of that will use the images from the cache.
	also, 3 takes a reload value in miliseconds, so a value of 180000 is really 3 minutes
	ALSO, use if..then code to only auto-refresh certain pages, such as email/index.php
	@author Angles Nov 28, 2001
	*/
	$auto_refresh_enabled = True;
	//$auto_refresh_enabled = False;
	// initialize reload location to empty string
	$reload_me = '';
	if ($auto_refresh_enabled)
	{
		if ((stristr($GLOBALS['PHP_SELF'], '/email/index.php'))
		||  (	((isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
			&& (stristr($GLOBALS['HTTP_GET_VARS']['menuaction'], 'email.uiindex.index')))
		    )
		)
		{
			if ((isset($GLOBALS['phpgw_info']['flags']['email_refresh_uri']))
			&& ($GLOBALS['phpgw_info']['flags']['email_refresh_uri'] != ''))
			{
				$reload_me = $GLOBALS['phpgw']->link('/index.php',$GLOBALS['phpgw_info']['flags']['email_refresh_uri']);
			}
			else
			{
				$reload_me = $GLOBALS['phpgw']->link('/email/index.php');
			}
		}
		elseif (eregi("^.*\/home\.php.*$",$GLOBALS['PHP_SELF']))
		{
			$reload_me = $GLOBALS['phpgw']->link('/home.php');			
		}
	}
	// make the JS command string if necessary
	if (($auto_refresh_enabled)
	&& ($reload_me != ''))
	{
		// set refresh time in miliseconds  (1000 = 1 sec)  (180000 = 180 sec = 3 minutes)
		//  ( 240000 = 240 sec = 4 min)   (300000 = 5 min)   (600000 = 10 min)
		$refresh_ms = '240000';
		$email_reload_js = 
			'window.setTimeout('."'".'window.location="'
			.$reload_me.'"; '."'".','.$refresh_ms.');';
	}
	else
	{
		$email_reload_js = '';
	}

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));
	$var = Array (
		'img_icon'      => PHPGW_IMAGES . '/favicon.ico',
		'img_shortcut'  => PHPGW_IMAGES . '/favicon.ico',
		'charset'		=> lang('charset'),
		'font_family'	=> $GLOBALS['phpgw_info']['theme']['font'],
		'website_title'	=> $GLOBALS['phpgw_info']['server']['site_title'],
		'body_tags'		=> $bodyheader,
		'css_link'		=> $GLOBALS['phpgw_info']['theme']['link'],
		'css_alink'		=> $GLOBALS['phpgw_info']['theme']['alink'],
		'css_vlink'		=> $GLOBALS['phpgw_info']['theme']['vlink'],
		'css_hovlink'	=> $csshover,
		'email_reload_js'	=> $email_reload_js,
		'app_css'		=> $app_css
	);
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
