<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info = array();
	$GLOBALS['phpgw_info']['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

	// Check header and authentication
	if (!$phpgw_setup->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);

	$download = $HTTP_POST_VARS['download'] ? $HTTP_POST_VARS['download'] : $HTTP_GET_VARS['download'];
	$submit   = $HTTP_POST_VARS['submit']   ? $HTTP_POST_VARS['submit']   : $HTTP_GET_VARS['submit'];
	$showall  = $HTTP_POST_VARS['showall']  ? $HTTP_POST_VARS['showall']  : $HTTP_GET_VARS['showall'];
	$appname  = $HTTP_POST_VARS['appname']  ? $HTTP_POST_VARS['appname']  : $HTTP_GET_VARS['appname'];
	if ($download)
	{
		$setup_tpl->set_file(array(
			'sqlarr'   => 'arraydl.tpl'
		));
		$setup_tpl->set_var('idstring',"/* \$Id" . ": tables_current.inc.php" . ",v 1.0" . " 2001/05/28 08:42:04 username " . "Exp \$ */");
		$setup_tpl->set_block('sqlarr','sqlheader','sqlheader');
		$setup_tpl->set_block('sqlarr','sqlbody','sqlbody');
		$setup_tpl->set_block('sqlarr','sqlfooter','sqlfooter');
	}
	else
	{
		$setup_tpl->set_file(array(
			'T_head' => 'head.tpl',
			'T_footer' => 'footer.tpl',
			'T_alert_msg' => 'msg_alert_msg.tpl',
			'T_login_main' => 'login_main.tpl',
			'T_login_stage_header' => 'login_stage_header.tpl',
			'T_setup_main' => 'schema.tpl',
			'applist'  => 'applist.tpl',
			'sqlarr'   => 'sqltoarray.tpl',
			'T_head'   => 'head.tpl',
			'T_footer' => 'footer.tpl'
		));
		$setup_tpl->set_block('T_login_stage_header','B_multi_domain','V_multi_domain');
		$setup_tpl->set_block('T_login_stage_header','B_single_domain','V_single_domain');
		$setup_tpl->set_block('T_setup_main','header','header');
		$setup_tpl->set_block('applist','appheader','appheader');
		$setup_tpl->set_block('applist','appitem','appitem');
		$setup_tpl->set_block('applist','appfooter','appfooter');
		$setup_tpl->set_block('sqlarr','sqlheader','sqlheader');
		$setup_tpl->set_block('sqlarr','sqlbody','sqlbody');
		$setup_tpl->set_block('sqlarr','sqlfooter','sqlfooter');
	}

	$phpgw_setup->loaddb();

	function parse_vars($table,$term)
	{
		$GLOBALS['setup_tpl']->set_var('table', $table);
		$GLOBALS['setup_tpl']->set_var('term',$term);

		list($arr,$pk,$fk,$ix,$uc) = $GLOBALS['phpgw_setup']->sql_to_array($table);
		$GLOBALS['setup_tpl']->set_var('arr',$arr);
		if (count($pk) > 1)
		{
			$GLOBALS['setup_tpl']->set_var('pks', "'".implode("','",$pk)."'");
		}
		elseif($pk && !empty($pk))
		{
			$GLOBALS['setup_tpl']->set_var('pks', "'" . $pk[0] . "'");
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('pks','');
		}

		if (count($fk) > 1)
		{
			$GLOBALS['setup_tpl']->set_var('fks', "'" . implode("','",$fk) . "'");
		}
		elseif($fk && !empty($fk))
		{
			$GLOBALS['setup_tpl']->set_var('fks', "'" . $fk[0] . "'");
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('fks','');
		}

		if (count($ix) > 1)
		{
			$GLOBALS['setup_tpl']->set_var('ixs', "'" . implode("','",$ix) . "'");
		}
		elseif($ix && !empty($ix))
		{
			$GLOBALS['setup_tpl']->set_var('ixs', "'" . $ix[0] . "'");
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('ixs','');
		}

		if (count($uc) > 1)
		{
			$GLOBALS['setup_tpl']->set_var('ucs', "'" . implode("','",$uc) . "'");
		}
		elseif($uc && !empty($uc))
		{
			$GLOBALS['setup_tpl']->set_var('ucs', "'" . $uc[0] . "'");
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('ucs','');
		}
	}

	function printout($template)
	{
		global $download,$appname,$table,$showall;

		if ($download)
		{
			$GLOBALS['setup_tpl']->set_var('appname',$appname);
			$string = $GLOBALS['setup_tpl']->parse('out',$template);
		}
		else
		{
			$GLOBALS['setup_tpl']->set_var('appname',$appname);
			$GLOBALS['setup_tpl']->set_var('table',$table);
			$GLOBALS['setup_tpl']->set_var('lang_download','Download');
			$GLOBALS['setup_tpl']->set_var('showall',$showall);
			$GLOBALS['setup_tpl']->set_var('action_url','sqltoarray.php');
			$GLOBALS['setup_tpl']->pfp('out',$template);
		}
		return $string;
	}

	function download_handler($dlstring,$fn='tables_current.inc.php')
	{
		//include( PHPGW_SERVER_ROOT . '/phpgwapi/inc/class.browser.inc.php');
		$b = CreateObject('phpgwapi.browser');
		$b->content_header($fn);
		echo $dlstring;
		exit;
	}

	if ($submit || $showall)
	{
		$dlstring = '';
		$term = '';

		if (!$download)
		{
			$phpgw_setup->show_header();
		}

		if ($showall)
		{
			$table = $appname = '';
		}

		if(!$table && !$appname)
		{
			$term = ',';
			$dlstring .= printout('sqlheader');

			$db = $phpgw_setup->db;
			$db->query('SHOW TABLES');
			while($db->next_record())
			{
				$table = $db->f(0);
				parse_vars($table,$term);
				$dlstring .= printout('sqlbody');
			}
			$dlstring .= printout('sqlfooter');

		}
		elseif($appname)
		{
			$dlstring .= printout('sqlheader');
			$term = ',';

			if(!$setup_info[$appname]['tables'])
			{
				$f = PHPGW_SERVER_ROOT . '/' . $appname . '/setup/setup.inc.php';
				if (file_exists ($f)) { include($f); }
			}

			//$tables = explode(',',$setup_info[$appname]['tables']);
			$tables = $setup_info[$appname]['tables'];
			/* $i = 1; */
			while(list($key,$table) = @each($tables))
			{
				/*
				if($i == count($tables))
				{
					$term = '';
				}
				*/
				parse_vars($table,$term);
				$dlstring .= printout('sqlbody');
				/* $i++; */
			}
			$dlstring .= printout('sqlfooter');
		}
		elseif($table)
		{
			$term = ';';
			parse_vars($table,$term);
			$dlstring .= printout('sqlheader');
			$dlstring .= printout('sqlbody');
			$dlstring .= printout('sqlfooter');
		}
		if ($download)
		{
			download_handler($dlstring);
		}
	}
	else
	{
		$phpgw_setup->show_header();

		$setup_tpl->set_var('action_url','sqltoarray.php');
		$setup_tpl->set_var('lang_submit','Show selected');
		$setup_tpl->set_var('lang_showall','Show all');
		$setup_tpl->set_var('title','SQL to schema_proc array util');
		$setup_tpl->set_var('lang_applist','Applications');
		$setup_tpl->set_var('select_to_download_file',lang('Select to download file'));
		$setup_tpl->pfp('out','appheader');

		$d = dir(PHPGW_SERVER_ROOT);
		while($entry=$d->read())
		{
			$f = PHPGW_SERVER_ROOT . '/' . $entry . '/setup/setup.inc.php';
			if (file_exists ($f)) { include($f); }
		}

		while (list($key,$data) = @each($setup_info))
		{
			if ($data['tables'] && $data['title'])
			{
				$setup_tpl->set_var('appname',$data['name']);
				$setup_tpl->set_var('apptitle',$data['title']);
				$setup_tpl->pfp('out','appitem');
			}
		}
		$setup_tpl->pfp('out','appfooter');
	}
?>
