<?php
  /**************************************************************************\
  * phpGroupWare API - Utilies loader                                        *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * This simply loads up additional utility libraries                        *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
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

  /* $Id$ */

	$d1 = strtolower(substr(PHPGW_API_INC,0,3));
	if($d1 == 'htt' || $d1 == 'ftp')
	{
		echo 'Failed attempt to break in via an old Security Hole!<br>' . "\n";
		exit;
	}
	unset($d1);

	class utilities
	{
		var $rssparser;
		var $clientsniffer;
		var $http;
		var $matrixview;
		var $menutree;
		var $sbox;

		function utilities_()
		{
			//      $GLOBALS['phpgw']->rssparser = CreateObject("phpgwapi.rssparser");
			//      $GLOBALS['phpgw']->clientsniffer = CreateObject("phpgwapi.clientsniffer");
			//      $GLOBALS['phpgw']->http = CreateObject("phpgwapi.http");
			//     $GLOBALS['phpgw']->matrixview = CreateObject("phpgwapi.matrixview");
			//     $GLOBALS['phpgw']->menutree = CreateObject("phpgwapi.menutree");
			$GLOBALS['phpgw']->sbox = CreateObject('phpgwapi.portalbox');
		}
	}
?>
