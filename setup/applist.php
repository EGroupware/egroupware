<?php
/**************************************************************************\
* phpGroupWare - XML-RPC Test App                                          *
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
	include('./inc/functions.inc.php');
	include('./inc/xml_functions.inc.php');

	// Check header and authentication
	if (!$phpgw_setup->auth('Config'))
	{
		Header('Location: index.php');
		exit;
	}
	// Does not return unless user is authorized

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array(
		'T_head'   => 'head.tpl',
		'T_footer' => 'footer.tpl'
	));
	$setup_tpl->set_block('T_footer','footer','footer');

	$host = 'us3.phpgroupware.org';
	$path = '/cvsdemo/xmlrpc.php';

	$phpgw_setup->show_header(lang('Application List'),True);

	/* Login as demo */
	$login = CreateObject(
		'phpgwapi.xmlrpcmsg',
		'system.login',
		array(
			CreateObject(
				'phpgwapi.xmlrpcval',
				array(
					'domain'   => CreateObject('phpgwapi.xmlrpcval','default','string'),
					'username' => CreateObject('phpgwapi.xmlrpcval','demo','string'),
					'password' => CreateObject('phpgwapi.xmlrpcval','guest','string')
				),
				'struct'
			)
		)
	);
	echo '<pre>' . htmlentities($login->serialize()) . "</pre>\n";

	$c = CreateObject('phpgwapi.xmlrpc_client',$path,$host,80);
	$c->setDebug(1);
	$r = $c->send($login);
	$v = $r->value();
	$result = xmlrpc_decode($v);

	/* Get applist */
	$f = CreateObject('phpgwapi.xmlrpcmsg','system.listApps','');
	echo '<pre>' . htmlentities($f->serialize()) . "</pre>\n";

	$c = CreateObject('phpgwapi.xmlrpc_client',$path,$host,80);
	$c->setDebug(1);
	$c->username = $result['sessionid'];
	$c->password = $result['kp3'];
	$r = $c->send($f);

	/* Logout */
	$logout = CreateObject(
		'phpgwapi.xmlrpcmsg',
		'system.logout',
		array(
			CreateObject(
				'phpgwapi.xmlrpcval',
				array(
					'sessionid' => CreateObject('phpgwapi.xmlrpcval',$result['sessionid'],'string'),
					'kp3'       => CreateObject('phpgwapi.xmlrpcval',$result['kp3'],'string')
				),
				'struct'
			)
		)
	);
	echo '<pre>' . htmlentities($logout->serialize()) . "</pre>\n";

	$c = CreateObject('phpgwapi.xmlrpc_client',$path,$host,80);
	$c->setDebug(1);
	$r = $c->send($logout);
	$v = $r->value();

	$phpgw_setup->show_footer();
?>
