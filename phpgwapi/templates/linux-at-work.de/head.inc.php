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

	$bodyheader = 'bgcolor="'.$GLOBALS['phpgw_info']['theme']['bg_color'].'" alink="'.$GLOBALS['phpgw_info']['theme']['alink'].'" link="'.$GLOBALS['phpgw_info']['theme']['link'].'" vlink="'.$GLOBALS['phpgw_info']['theme']['vlink'].'"';

	if ($fp = @fopen(PHPGW_APP_TPL."/app.css","r"))
	{
		$app_css = fread ($fp, filesize (PHPGW_APP_TPL."/app.css"));
		fclose($fp);
	}

        $p = createobject('phpgwapi.preferences');
        $preferences = $p->read_repository();
	if(isset($preferences[$GLOBALS['phpgw_info']['flags']['currentapp']]['refreshTime']))
	{ 
		$refreshTime = $preferences[$GLOBALS['phpgw_info']['flags']['currentapp']]['refreshTime']*60;
	}
	
	$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
	$app = $app ? ' ['.(isset($GLOBALS['phpgw_info']['apps'][$app]) ? $GLOBALS['phpgw_info']['apps'][$app]['title'] : lang($app)).']':'';

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));
	$var = Array (
		'img_icon'      => PHPGW_IMAGES_DIR . '/favicon.ico',
		'img_shortcut'  => PHPGW_IMAGES_DIR . '/favicon.ico',
		'charset'       => $GLOBALS['phpgw']->translation->charset(),
		'website_title'	=> $GLOBALS['phpgw_info']['server']['site_title'],
		'app_name'	=> $app,
		'body_tags'	=> $bodyheader .' '. $GLOBALS['phpgw']->common->get_body_attribs(),
		'bg_color'	=> $GLOBALS['phpgw_info']['theme']['bg_color'],
		'refreshTime'	=> $refreshTime,
		'css'		=> $GLOBALS['phpgw']->common->get_css(),
		'java_script'	=> $GLOBALS['phpgw']->common->get_java_script(),
	);
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
