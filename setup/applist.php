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

	$phpgw_info['flags'] = array(
		'noheader' => True,
		'nonavbar' => True,
		'currentapp' => 'home',
		'noapi' => True
	);
	include ('./inc/functions.inc.php');

	$tpl_root = $phpgw_setup->setup_tpl_dir('setup');
	$setup_tpl = CreateObject('phpgwapi.Template',$tpl_root);
	$setup_tpl->set_file(array('T_footer' => 'footer.tpl'));
	$setup_tpl->set_block('T_footer','footer','footer');

	$f = CreateObject('phpgwapi.xmlrpcmsg','system.listApps',array(CreateObject('phpgwapi.xmlrpcval',0, "int")));
	print "<pre>" . htmlentities($f->serialize()) . "</pre>\n";
	$c = CreateObject('phpgwapi.xmlrpc_client',"/phpgroupware/xmlrpc.php", $HTTP_HOST, 80);
	$c->setDebug(1);
	$r = $c->send($f);
	if (!$r)
	{
		die('send failed');
	}
	$v = $r->value();
	if (!$r->faultCode())
	{
	//	print "<HR>I got this value back<BR><PRE>" .
	//	htmlentities($r->serialize()). "</PRE><HR>\n";
	}
	else
	{
		print 'Fault: ';
		print 'Code: ' . $r->faultCode() . " Reason '" .$r->faultString()."'<br>";
	}

	$phpgw_setup->show_footer();
?>
