<?php
  /**************************************************************************\
  * phpGroupWare - Admin config                                              *
  * Written by Miles Lott <milosch@phpgroupware.org>                         *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => "admin",
	);
	include('../header.inc.php');

	$t = CreateObject('phpgwapi.Template',PHPGW_APP_TPL);
	$t->set_file(array(
		'header' => 'config_head.tpl',
		'footer' => 'config_footer.tpl'
	));

	$c = CreateObject('phpgwapi.config',$appname);
	$c->read_repository();

	if ($c->config_data)
	{
		$current_config = $c->config_data;
	}
	else
	{
		$c->appname = 'phpgwapi';
		$c->read_repository();
		$current_config = $c->config_data;
	}
	//echo print_r($current_config); exit;

	if ($cancel)
	{
		Header('Location: '.$phpgw->link('/admin/index.php'));
	}

	if ($submit)
	{
		while (list($key,$config) = each($newsettings))
		{
			//echo '<br>' . $key . ' = "' . $config . '"';
			$c->config_data[$key] = $config;
		}
		$c->save_repository(True);

		Header('Location: '.$phpgw->link('/admin/index.php'));
		$phpgw->common->phpgw_exit();
	}

	$phpgw->common->phpgw_header();
	echo parse_navbar();

	$t->set_var('title',lang('Site Configuration'));
	$t->set_var('action_url',$phpgw->link('/admin/config.php'));
	$t->pparse('out','header');

	include(PHPGW_SERVER_ROOT . SEP . $appname . SEP . 'setup' . SEP . 'config.inc.php');
	if ($appname == 'admin')
	{
		include(PHPGW_SERVER_ROOT . SEP . 'preferences' . SEP . 'setup' . SEP . 'config.inc.php');
	}

	$t->pparse('out','footer');
	$phpgw->common->phpgw_footer();
?>
