<?php
  /**************************************************************************\
  * phpGroupWare API - Services Abstraction Class                            *
  * This file written by Miles Lott <milosch@phpgroupware.org>               *
  * Copyright (C) 2001 Miles Lott                                            *
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

	class service
	{
		var $provider = '';
		var $svc      = '';
		var $type     = '';
		var $function_map = array();

		function exec($service)
		{
			if(is_array($service))
			{
				$data     = $service[2];
				$function = $service[1];
				$temp_service = $service[0];
				settype($service,'string');
				$service = $temp_service;
				unset($temp_service);
			}
			switch ($service)
			{
				case 'schedule':
				case 'contacts':
				case 'notes':
				case 'todo':
					$this = CreateObject('phpgwapi.service_' . $service);
					break;
				case 'app_registry':
					$this = CreateObject('phpgwapi.'.$service);
					break;					
				default:
					$this = CreateObject($service);
					break;
			}
			if($function)
			{
				return $this->$function($data);
			}
		}

		function list_methods()
		{
			return $this->function_map;
		}
	}
?>
