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

	if($GLOBALS['phpgw_info']['user']['preferences']['common']['show_generation_time'])
	{
		$mtime = microtime(); 
		$mtime = explode(' ',$mtime); 
		$mtime = $mtime[1] + $mtime[0]; 
		$GLOBALS['page_start_time'] = $mtime; 
	}

	// get used language code
	$lang_code = 'en';

	$bodyheader = ' bgcolor="' . $GLOBALS['phpgw_info']['theme']['bg_color'] . '" alink="'
		. $GLOBALS['phpgw_info']['theme']['alink'] . '" link="' . $GLOBALS['phpgw_info']['theme']['link'] . '" vlink="'
		. $GLOBALS['phpgw_info']['theme']['vlink'] . '"';

	if(!$GLOBALS['phpgw_info']['server']['htmlcompliant'])
	{
		$bodyheader .= '';
	}

	#_debug_array($GLOBALS['phpgw_info']['user']['preferences']['common']);
	$theme_css = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/css/'.$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'].'.css';
	if(!file_exists($theme_css))
	{
		$theme_css = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/css/'.$GLOBALS['phpgw_info']['user']['preferences']['common']['theme'].'.css';
	}

	//pngfix defaults to yes
	if(!$GLOBALS['phpgw_info']['user']['preferences']['common']['disable_pngfix'])
	{
		$pngfix_src = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/js/pngfix.js';
		$pngfix ='<!-- This solves the Internet Explorer PNG-transparency bug, but only for IE 5.5 and higher --> 
		<!--[if gte IE 5.5000]>
		<script src="'.$pngfix_src.'" type="text/javascript">
		</script>
		<![endif]-->';
	}

	if(!$GLOBALS['phpgw_info']['user']['preferences']['common']['disable_slider_effects'])
	{
		$slider_effects_src = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/js/slidereffects.js';
		$slider_effects = '<script src="'.$slider_effects_src.'" type="text/javascript">
		</script>';
	}
	else
	{
		$simple_show_hide_src = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/templates/jerryr/js/simple_show_hide.js';
		$simple_show_hide = '<script src="'.$simple_show_hide_src.'" type="text/javascript">
		</script>';
	}

// 030204 ndee for calling foldertree

	$foldertree_src = $GLOBALS['phpgw_info']['server']['webserver_url'] . '/phpgwapi/js/foldertree/foldertree.js';
	$js_foldertree = '<script src="'.$foldertree_src.'" type="text/javascript"></script>';

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('_head' => 'head.tpl'));
	$tpl->set_block('_head','head');

	if ($GLOBALS['phpgw_info']['flags']['app_header'])
	{
		$app = $GLOBALS['phpgw_info']['flags']['app_header'];
	}
	else
	{
		$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
		$app = isset($GLOBALS['phpgw_info']['apps'][$app]) ? $GLOBALS['phpgw_info']['apps'][$app]['title'] : lang($app);
	}
	$var = Array(
		'img_icon'      => PHPGW_IMAGES_DIR . '/favicon.ico',
		'img_shortcut'  => PHPGW_IMAGES_DIR . '/favicon.ico',
		'pngfix'        => $pngfix,
		'slider_effects'=> $slider_effects,
		'simple_show_hide'=> $simple_show_hide,
		'lang_code'=> $lang_code,
		'charset'       => $GLOBALS['phpgw']->translation->charset(),
		'font_family'   => $GLOBALS['phpgw_info']['theme']['font'],
		'website_title' => $GLOBALS['phpgw_info']['server']['site_title'] . ($app ? " [$app]" : ''),
		'body_tags'     => $bodyheader .' '. $GLOBALS['phpgw']->common->get_body_attribs(),
		'theme_css'     => $theme_css,
		'css'           => $GLOBALS['phpgw']->common->get_css(),
		'java_script'   => $GLOBALS['phpgw']->common->get_java_script(),
		'js_foldertree'	=> $js_foldertree,
	);
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
