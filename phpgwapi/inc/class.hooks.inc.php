<?php
  /**************************************************************************\
  * phpGroupWare API - Hooks                                                 *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * Allows applications to "hook" into each other                            *
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

	/*!
	@class hooks
	@abstract  class which gives ability for applications to set and use hooks to communicate with each other
	@author    Dan Kuykendall
	@copyright LGPL
	@package   phpgwapi
	@access    public
	*/
	class hooks
	{
		/*! 
		@function read()
		@abstract currently not being used
		*/
		function read()
		{
			$db = $GLOBALS['phpgw']->db;

			$db->query('select * from phpgw_hooks');
			while ($db->next_record())
			{
				$return_array[$db->f('hook_id')]['app']      = $db->f('hook_appname');
				$return_array[$db->f('hook_id')]['location'] = $db->f('hook_location');
				$return_array[$db->f('hook_id')]['filename'] = $db->f('hook_filename');
			}
			if(isset($return_array))
			{
				return $return_array;
			}
			else
			{
				return False;
			}
		}

		/*!
		@function process
		@abstract process the hooks
		@discussion not currently being used
		@param \$type 
		@param \$where
		*/
		function process($type,$where='')
		{
			$currentapp = $GLOBALS['phpgw_info']['flags']['currentapp'];
			$type = strtolower($type);

			if ($type != 'location' && $type != 'app')
			{
				return False;
			}

			// Add a check to see if that location/app has a hook
			// This way it doesn't have to loop everytime

			while ($hook = each($GLOBALS['phpgw_info']['hooks']))
			{
				if ($type == 'app')
				{
					if ($hook[1]['app'] == $currentapp)
					{
						$include_file = $GLOBALS['phpgw_info']['server']['server_root'] . '/'
							. $currentapp . '/hooks/'
							. $hook[1]['app'] . $hook[1]['filename'];
						include($include_file);
					}
				}
				elseif ($type == "location")
				{
					if ($hook[1]["location"] == $where)
					{
						$include_file = $GLOBALS['phpgw_info']['server']['server_root'] . '/'
							. $hook[1]['app'] . '/hooks/'
							. $hook[1]['filename'];
						if (! is_file($include_file))
						{
							$GLOBALS['phpgw']->common->phpgw_error('Failed to include hook: ' . $include_file);
						}
						else
						{
							include($include_file);
						}
					}
				}
			}
		}
	}
?>
