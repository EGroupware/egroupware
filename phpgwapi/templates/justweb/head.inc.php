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

	$bodyheader = 'BGCOLOR="'.$GLOBALS['phpgw_info']['theme']['bg_color'].'"';

	$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
	$app = $app ? ' ['.(isset($GLOBALS['phpgw_info']['apps'][$app]) ? $GLOBALS['phpgw_info']['apps'][$app]['title'] : lang($app)).']':'';

	$tpl = CreateObject('phpgwapi.Template',PHPGW_TEMPLATE_DIR);
	$tpl->set_unknowns('remove');
	$tpl->set_file(array('head' => 'head.tpl'));

	$var = Array (
		'img_icon'      => PHPGW_IMAGES_DIR . '/favicon.ico',
		'img_shortcut'  => PHPGW_IMAGES_DIR . '/favicon.ico',
		'webserver_url'	=> $GLOBALS['phpgw_info']['server']['webserver_url'],
		'home'		=> $GLOBALS['phpgw']->link('/index.php'),
		'appt'		=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.uicalendar.day')),
		'todo'		=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'todo.uitodo.add')),
		'prefs'		=> $GLOBALS['phpgw']->link('/preferences/index.php'),
		'email'		=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'email.uipreferences.preferences')),
		'calendar'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'calendar.uipreferences.preferences')),
		'addressbook'	=> $GLOBALS['phpgw']->link('/index.php',Array('menuaction'=>'addressbook.uiaddressbook.preferences')),
		'charset'	=> lang('charset'),
		'website_title'	=> $GLOBALS['phpgw_info']['server']['site_title'] . $app,
		'body_tags'	=> $bodyheader . $GLOBALS['phpgw']->common->get_body_attribs(),
		'css'		=> $GLOBALS['phpgw']->common->get_css(),
		'java_script'	=> $GLOBALS['phpgw']->common->get_java_script(),
	);
	$tpl->set_var($var);
	$tpl->pfp('out','head');
	unset($tpl);
?>
