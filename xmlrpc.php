<?php
  /**************************************************************************\
  * phpGroupWare xmlrpc server                                               *
  * http://www.phpgroupware.org                                              *
  * Written by Dan Kuykendall <dan@kuykendall.org>                           *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	$phpgw_info['flags'] = array(
		'currentapp' => 'login',
		'noheader'   => True
	);

	include('./header.inc.php');

	$server = CreateObject('phpgwapi.xmlrpc_server');
	/* _debug_array($server);exit; */

	include(PHPGW_API_INC . '/xmlrpc.interop.php');

	$server->service($HTTP_RAW_POST_DATA);
?>
