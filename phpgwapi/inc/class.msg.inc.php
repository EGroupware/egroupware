<?php
	/**************************************************************************\
	* phpGroupWare API - php IMAP SO access object constructor                 *
	* ------------------------------------------------------------------------ *
	* This file written by Mark Peters <skeeter@phpgroupware.org>              *
	* and Angelo Tony Puglisi (Angles) <angles@phpgroupware.org>               *
	* Handles initializing the appropriate class dcom object                   *
	* Copyright (C) 2001 Mark Peters                                           *
	* ------------------------------------------------------------------------ *
	* This library is part of the phpGroupWare API                             *
	* http://www.phpgroupware.org/api                                          * 
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; either version 2.1 of the License,         *
	* or any later version.                                                    *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/

	if(isset($p1) && ($p1) && ((stristr($p1, 'imap') || stristr($p1, 'pop') || stristr($p1, 'nntp'))))
	{
		$msg_server_type = $p1;
	}
	else
	{
		$msg_server_type = $GLOBALS['phpgw_info']['user']['preferences']['email']['msg_server_type'];
	}
	print_debug('msg class constructor, param $p1', $p1);
	print_debug('msg class constructor, $msg_server_type', $msg_server_type);

	if (extension_loaded('imap') || function_exists('imap_open'))
	{
		$imap_builtin = True;
		$sock_fname = '';
		print_debug('imap builtin extension is available');
	}
	else
	{
		$imap_builtin = False;
		$sock_fname = '_sock';
		print_debug('imap builtin extension NOT available, using socket class');
	}

	// -----  include SOCKET or PHP-BUILTIN classes as necessary
	if ($imap_builtin == False)
	{
		CreateObject('phpgwapi.network');
		print_debug('created phpgwapi network class used with sockets');
	}

	//CreateObject('phpgwapi.msg_base'.$sock_fname);
	include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_base'.$sock_fname.'.inc.php');

	if (($msg_server_type == 'imap') || ($msg_server_type == 'imaps'))
	{
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_imap'.$sock_fname.'.inc.php');
	}
	elseif (($msg_server_type == 'pop3') || ($msg_server_type == 'pop3s'))
	{
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_pop3'.$sock_fname.'.inc.php');
	}
	elseif ($msg_server_type == 'nntp')
	{
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_nntp'.$sock_fname.'.inc.php');
	}
	elseif ((isset($msg_server_type)) && ($msg_server_type != ''))
	{
		// educated guess based on info being available:
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_'.$GLOBALS['phpgw_info']['user']['preferences']['email']['msg_server_type'].$sock_fname.'.inc.php');
	}
	else
	{
		// DEFAULT FALL BACK:
		include(PHPGW_INCLUDE_ROOT.'/phpgwapi/inc/class.msg_imap.inc.php');
	}
?>
