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

	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

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

	// Check header and authentication
	$phpgw_info['setup']['stage']['header'] = $phpgw_setup->check_header();
	if ($phpgw_info['setup']['stage']['header'] != '10')
	{
		Header("Location: manageheader.php");
		exit;
	}
	elseif (!$phpgw_setup->auth('Config'))
	{
		$phpgw_setup->show_header(lang('Please login'),True);
		$phpgw_setup->login_form();
		$phpgw_setup->show_footer();
		exit;
	}

	$phpgw_setup->loaddb();

	function parse_vars($table,$term)
	{
		global $phpgw_setup,$setup_tpl;

		$setup_tpl->set_var('table', $table);
		$setup_tpl->set_var('term',$term);

		list($arr,$pk,$fk,$ix,$uc) = $phpgw_setup->sql_to_array($table);
		$setup_tpl->set_var('arr',$arr);
		if (count($pk) > 1)
		{
			$setup_tpl->set_var('pks', "'".implode("','",$pk)."'");
		}
		elseif($pk && !empty($pk))
		{
			$setup_tpl->set_var('pks', "'" . $pk[0] . "'");
		}
		else
		{
			$setup_tpl->set_var('pks','');
		}

		if (count($fk) > 1)
		{
			$setup_tpl->set_var('fks', "'" . implode("','",$fk) . "'");
		}
		elseif($fk && !empty($fk))
		{
			$setup_tpl->set_var('fks', "'" . $fk[0] . "'");
		}
		else
		{
			$setup_tpl->set_var('fks','');
		}

		if (count($ix) > 1)
		{
			$setup_tpl->set_var('ixs', "'" . implode("','",$ix) . "'");
		}
		elseif($ix && !empty($ix))
		{
			$setup_tpl->set_var('ixs', "'" . $ix[0] . "'");
		}
		else
		{
			$setup_tpl->set_var('ixs','');
		}

		if (count($uc) > 1)
		{
			$setup_tpl->set_var('ucs', "'" . implode("','",$uc) . "'");
		}
		elseif($uc && !empty($uc))
		{
			$setup_tpl->set_var('ucs', "'" . $uc[0] . "'");
		}
		else
		{
			$setup_tpl->set_var('ucs','');
		}
	}

	function printout($template)
	{
		global $download,$setup_tpl,$appname,$table,$showall;

		if ($download)
		{
			$setup_tpl->set_var('appname',$appname);
			$string = $setup_tpl->parse('out',$template);
		}
		else
		{
			$setup_tpl->set_var('appname',$appname);
			$setup_tpl->set_var('table',$table);
			$setup_tpl->set_var('lang_download','Download');
			$setup_tpl->set_var('showall',$showall);
			$setup_tpl->set_var('action_url','sqltoarray.php');
			$setup_tpl->pfp('out',$template);
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
