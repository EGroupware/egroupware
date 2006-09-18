<?php
	/**************************************************************************\
	* eGroupWare API - Timed Asynchron Services for eGroupWare                 *
	* Written by Lars Kneschke <l.kneschke@metaways.de>                        *
	* This script executes the asyncservice for all domains                    *
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/                                               *
	* ------------------------------------------------------------------------ *
	* This library is free software; you can redistribute it and/or modify it  *
	* under the terms of the GNU Lesser General Public License as published by *
	* the Free Software Foundation; version 2.1 of the License.                *
	* This library is distributed in the hope that it will be useful, but      *
	* WITHOUT ANY WARRANTY; without even the implied warranty of               *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
	* See the GNU Lesser General Public License for more details.              *
	* You should have received a copy of the GNU Lesser General Public License *
	* along with this library; if not, write to the Free Software Foundation,  *
	* Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
	\**************************************************************************/

	/* $Id$ */

	$path_to_egroupware = realpath(dirname(__FILE__).'/../..');	//  need to be adapted if this script is moved somewhere else
	
	$headerIncPHP = file($path_to_egroupware. '/header.inc.php');
	
	foreach($headerIncPHP as $line) {
		if(preg_match("/GLOBALS\['egw_domain']\['(.*)']/", $line, $matches)) {
			system($_ENV["_"]. ' -q -d memory_limit=16M '.$path_to_egroupware.'/phpgwapi/cron/asyncservices.php '. $matches[1]);
		}
	}
?>