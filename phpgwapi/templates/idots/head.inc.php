<?php
	/**************************************************************************\
	* eGroupWare                                                               *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	// get used language code
	$lang_code = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];

	$bodyheader = ' bgcolor="' . $GLOBALS['egw_info']['theme']['bg_color'] . '" alink="'
		. $GLOBALS['egw_info']['theme']['alink'] . '" link="' . $GLOBALS['egw_info']['theme']['link'] . '" vlink="'
		. $GLOBALS['egw_info']['theme']['vlink'] . '"';

	if(!$GLOBALS['egw_info']['server']['htmlcompliant'])
	{
		$bodyheader .= '';
	}

	#_debug_array($GLOBALS['egw_info']['user']['preferences']['common']);
	$theme_css = '/phpgwapi/templates/idots/css/'.$GLOBALS['egw_info']['user']['preferences']['common']['theme'].'.css';
	if(!file_exists(EGW_SERVER_ROOT.$theme_css))
	{
		$theme_css = '/phpgwapi/templates/idots/css/idots.css';
	}
	$theme_css = $GLOBALS['egw_info']['server']['webserver_url'] . $theme_css;

	//pngfix defaults to yes
	if(!$GLOBALS['egw_info']['user']['preferences']['common']['disable_pngfix'])
	{
		$pngfix_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/pngfix.js';
		$pngfix ='<!-- This solves the Internet Explorer PNG-transparency bug, but only for IE 5.5 and higher --> 
		<!--[if gte IE 5.5000]>
		<script src="'.$pngfix_src.'" type="text/javascript">
		</script>
		<![endif]-->';
	}

	if(!$GLOBALS['egw_info']['user']['preferences']['common']['disable_slider_effects'])
	{
		$slider_effects_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/slidereffects.js';
		$slider_effects = '<script src="'.$slider_effects_src.'" type="text/javascript">
		</script>';
	}
	else
	{
		$simple_show_hide_src = $GLOBALS['egw_info']['server']['webserver_url'] . '/phpgwapi/templates/idots/js/simple_show_hide.js';
		$simple_show_hide = '<script src="'.$simple_show_hide_src.'" type="text/javascript">
		</script>';
	}

	$tpl = CreateObject('phpgwapi.Template',EGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('_head' => 'head.tpl'));
	$tpl->set_block('_head','head');

	if ($GLOBALS['egw_info']['flags']['app_header'])
	{
		$app = $GLOBALS['egw_info']['flags']['app_header'];
	}
	else
	{
		$app = $GLOBALS['egw_info']['flags']['currentapp'];
		$app = isset($GLOBALS['egw_info']['apps'][$app]) ? $GLOBALS['egw_info']['apps'][$app]['title'] : lang($app);
	}

	
	if($app!='wiki') $robots ='<meta name="robots" content="none" />';
	
	$var = Array(
		'img_icon'      	=> EGW_IMAGES_DIR . '/favicon.ico',
		'img_shortcut'  	=> EGW_IMAGES_DIR . '/favicon.ico',
		'pngfix'        	=> $pngfix,
		'slider_effects'	=> $slider_effects,
		'simple_show_hide'	=> $simple_show_hide,
		'lang_code'			=> $lang_code,
		'charset'       	=> $GLOBALS['egw']->translation->charset(),
		'font_family'   	=> $GLOBALS['egw_info']['theme']['font'],
		'website_title' 	=> strip_tags($GLOBALS['egw_info']['server']['site_title']. ($app ? " [$app]" : '')),
		'body_tags'     	=> $bodyheader .' '. $GLOBALS['egw']->common->get_body_attribs(),
		'theme_css'     	=> $theme_css,
		'css'           	=> $GLOBALS['egw']->common->get_css(),
		'java_script'   	=> $GLOBALS['egw']->common->get_java_script(),
		'meta_robots'		=> $robots,
		'dir_code'			=> lang('language_direction_rtl') != 'rtl' ? '' : ' dir="rtl"',
	 );
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
