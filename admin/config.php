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

	$GLOBALS['phpgw_info'] = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'admin',
		'enable_nextmatchs_class' => True
	);
	include('../header.inc.php');

	switch($GLOBALS['HTTP_GET_VARS']['appname'])
	{
		case 'admin':
		case 'preferences':
			$appname = 'preferences';
			$config_appname = 'phpgwapi';
			break;
		case 'addressbook':
		case 'calendar':
		case 'email':
		case 'nntp':
			/*
			  Other special apps can go here for now, e.g.:
			  case 'bogusappname':
			*/
			$appname = $GLOBALS['HTTP_GET_VARS']['appname'];
			$config_appname = 'phpgwapi';
			break;
		default:
			$appname = $GLOBALS['HTTP_GET_VARS']['appname'];
			$config_appname = $appname;
			break;
	}

	$t = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir($appname));
	$t->set_unknowns('keep');
	$t->set_file(array('config' => 'config.tpl'));
	$t->set_block('config','header','header');
	$t->set_block('config','body','body');
	$t->set_block('config','footer','footer');

	$c = CreateObject('phpgwapi.config',$config_appname);
	$c->read_repository();

	if ($c->config_data)
	{
		$current_config = $c->config_data;
	}

	if ($GLOBALS['HTTP_POST_VARS']['cancel'])
	{
		Header('Location: '.$GLOBALS['phpgw']->link('/admin/index.php'));
	}

	if ($GLOBALS['HTTP_POST_VARS']['submit'])
	{
		while (list($key,$config) = each($GLOBALS['HTTP_POST_VARS']['newsettings']))
		{
			if ($config)
			{
				$c->config_data[$key] = $config;
			}
			else
			{
				unset($c->config_data[$key]);
			}
		}
		$c->save_repository(True);

		Header('Location: '.$GLOBALS['phpgw']->link('/admin/index.php'));
		$GLOBALS['phpgw']->common->phpgw_exit();
	}

	$GLOBALS['phpgw']->common->phpgw_header();
	echo parse_navbar();

	$t->set_var('title',lang('Site Configuration'));
	$t->set_var('action_url',$GLOBALS['phpgw']->link('/admin/config.php','appname=' . $appname));
	$t->set_var('th_bg',     $GLOBALS['phpgw_info']['theme']['th_bg']);
	$t->set_var('th_text',   $GLOBALS['phpgw_info']['theme']['th_text']);
	$t->set_var('row_on',    $GLOBALS['phpgw_info']['theme']['row_on']);
	$t->set_var('row_off',   $GLOBALS['phpgw_info']['theme']['row_off']);
	$t->pparse('out','header');

	$vars = $t->get_undefined('body');

	$GLOBALS['phpgw']->common->hook_single('config',$appname);

	while (list($null,$value) = each($vars))
	{
		$valarray = explode('_',$value);
		$type = $valarray[0];
		$new = $newval = '';

		while($chunk = next($valarray))
		{
			$new[] = $chunk;
		}
		$newval = implode(' ',$new);

		switch ($type)
		{
			case 'lang':
				$t->set_var($value,lang($newval));
				break;
			case 'value':
				$newval = ereg_replace(' ','_',$newval);
				$t->set_var($value,$current_config[$newval]);
				break;
/*			case 'checked':
				$newval = ereg_replace(' ','_',$newval);
				if ($current_config[$newval])
				{
					$t->set_var($value,' checked');
				}
				else
				{
					$t->set_var($value,'');
				}
				break;*/
			case 'selected':
				$configs = array();
				$config  = '';
				$newvals = explode(' ',$newval);
				$setting = end($newvals);
				for ($i=0;$i<(count($newvals) - 1); $i++)
				{
					$configs[] = $newvals[$i];
				}
				$config = implode('_',$configs);
				/* echo $config . '=' . $current_config[$config]; */
				if ($current_config[$config] == $setting)
				{
					$t->set_var($value,' selected');
				}
				else
				{
					$t->set_var($value,'');
				}
				break;
			case 'hook':
				$newval = ereg_replace(' ','_',$newval);
				$t->set_var($value,$newval($current_config));
				break;
			default:
				$t->set_var($value,'');
				break;
		}
	}

	$t->pfp('out','body');

	$t->set_var('lang_submit', lang('submit'));
	$t->set_var('lang_cancel', lang('cancel'));
	$t->pfp('out','footer');
	$GLOBALS['phpgw']->common->phpgw_footer();
?>
