<?php
	/**************************************************************************\
	* phpGroupWare - SOAP Server                                               *
	* http://www.phpgroupware.org                                              *
	* Written by Miles Lott <milosch@phpgroupware.org>                         *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

	//$login  = 'anonymous';
	//$passwd = 'anonymous1';

	$phpgw_info['flags'] = array(
		'disable_Template_class' => True,
		'currentapp' => 'login',
		'noheader'   => True
	);

	include('./header.inc.php');
	//$sessionid = $phpgw->session->create($login,$passwd);

	$server = CreateObject('phpgwapi.soap_server');
	/* _debug_array($server);exit; */

	include(PHPGW_API_INC . '/soaplib.soapinterop.php');

	$server->service($HTTP_RAW_POST_DATA);
?>
