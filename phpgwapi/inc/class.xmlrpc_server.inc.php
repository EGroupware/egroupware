<?php
  /**************************************************************************\
  * eGroupWare API - XML-RPC Server using builtin php functions              *
  * This file written by Miles Lott <milos@groupwhere.org>                   *
  * Copyright (C) 2003 Miles Lott                                            *
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

  /* $Id$ */

	if(empty($GLOBALS['phpgw_info']['server']['xmlrpc_type']))
	{
		$GLOBALS['phpgw_info']['server']['xmlrpc_type'] = 'php';
	}
	include_once(PHPGW_API_INC . SEP . 'class.xmlrpc_server_' . $GLOBALS['phpgw_info']['server']['xmlrpc_type'] . '.inc.php');
