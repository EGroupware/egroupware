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
		var $found_hooks = Array();
		function hooks()
		{
			//$GLOBALS['phpgw']->db->query("SELECT hook_appname, hook_location, hook_filename FROM phpgw_hooks WHERE hook_location='".$location."'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->query("SELECT hook_appname, hook_location, hook_filename FROM phpgw_hooks",__LINE__,__FILE__);
			while( $GLOBALS['phpgw']->db->next_record() )
			{
				$this->found_hooks[$GLOBALS['phpgw']->db->f('hook_appname')][$GLOBALS['phpgw']->db->f('hook_location')] = $GLOBALS['phpgw']->db->f('hook_filename');
			}
			//echo '<pre>';
			//print_r($this->found_hooks);
			//echo '</pre>';
		}

		/*!
		@function process
		@abstract loads up all the hooks the user has rights to
		@discussion Someone flesh this out please
		*/
		// Note: $no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		function process($location, $order = '', $no_permission_check = False)
		{
			$SEP = filesystem_separator();
			if ($order == '')
			{
				settype($order,'array');
				$order[] = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			/* First include the ordered apps hook file */
			reset ($order);
			while (list(,$appname) = each($order))
			{
				if (isset($this->found_hooks[$appname][$location]))
				{
					$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $this->found_hooks[$appname][$location];
					if (file_exists($f) &&
						( $GLOBALS['phpgw_info']['user']['apps'][$appname] || (($no_permission_check || $appname == 'preferences') && $appname)) )
					{
						include($f);
					}
				}
				$completed_hooks[$appname] = True;
			}

			/* Then add the rest */

			if ($no_permission_check)
			{
				reset($GLOBALS['phpgw_info']['apps']);
				while (list(,$p) = each($GLOBALS['phpgw_info']['apps']))
				{
					$appname = $p['name'];
					if (! isset($completed_hooks[$appname]) || $completed_hooks[$appname] != True)
					{
						if (isset($this->found_hooks[$appname][$location]))
						{
							$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $this->found_hooks[$appname][$location];
							if (file_exists($f))
							{
								include($f);
							}
						}
					}
				}
			}
			else
			{
				reset ($GLOBALS['phpgw_info']['user']['apps']);
				while (list(,$p) = each($GLOBALS['phpgw_info']['user']['apps']))
				{
					$appname = $p['name'];
					if (! isset($completed_hooks[$appname]) || $completed_hooks[$appname] != True)
					{
						if (isset($this->found_hooks[$appname][$location]))
						{
							$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $this->found_hooks[$appname][$location];
							if (file_exists($f))
							{
								include($f);
							}
						}
					}
				}
			}
		}

		/*!
		@function single
		@abstract call the hooks for a single application
		@param $location hook location - required
		@param $appname application name - optional
		*/
		// Note: $no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		function single($location, $appname = '', $no_permission_check = False)
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			$SEP = filesystem_separator();

			/* First include the ordered apps hook file */
			if (isset($this->found_hooks[$appname][$location]))
			{
				$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $this->found_hooks[$appname][$location];
				if (file_exists($f) &&
					( $GLOBALS['phpgw_info']['user']['apps'][$appname] || (($no_permission_check || $location == 'config' || $appname == 'phpgwapi') && $appname)) )
				{
					include($f);
					return True;
				}
				else
				{
					return False;
				}
			}
			else
			{
				return False;
			}
		}

		/*!
		@function single_tpl
		@abstract call the hooks for a single application, return output from the hook
		@discussion This is a BROKEN function on php3... wcm is not using it anymore
		@param $location hook location - required
		@param $appname application name - optional
		*/
		function single_tpl($location, $appname='', $no_permission_check=False)
		{
			if(!$appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			$SEP = filesystem_separator();

			if(@isset($this->found_hooks[$appname][$location]))
			{
				$f = PHPGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $this->found_hooks[$appname][$location];
				if(@file_exists($f) &&
					( $GLOBALS['phpgw_info']['user']['apps'][$appname] || (($no_permission_check || $location == 'config' || $appname == 'phpgwapi') && $appname)) )
				{
					eval('$retval = include(\$f);');
					return $retval;
				}
				else
				{
					return '';
				}
			}
			else
			{
				return '';
			}
		}

		/*!
		@function count
		@abstract loop through the applications and count the hooks
		*/
		function count($location)
		{
			$count = 0;
			reset($GLOBALS['phpgw_info']['user']['apps']);
			$SEP = filesystem_separator();
			while ($permission = each($GLOBALS['phpgw_info']['user']['apps']))
			{
				if (isset($this->found_hooks[$permission[0]][$location]))
				{
					++$count;
				}
			}
			return $count;
		}

		/*! 
		@function read()
		@abstract currently not being used
		*/
		function read()
		{
			//if (!is_array($this->found_hooks))
			//{
				$this->hooks();
			//}
			return $this->found_hooks;
		}
	}
?>
