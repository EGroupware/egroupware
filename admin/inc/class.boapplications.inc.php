<?php
  /**************************************************************************\
  * phpGroupWare - administration                                            *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class boapplications
	{
		var $public_functions = array(
			'register_all_hooks' => True
		);

		var $so;

		function boapplications()
		{
			$this->so = CreateObject('admin.soapplications');
		}

		function get_list()
		{
			return $this->so->get_list();
		}

		function read($app_name)
		{
			return $this->so->read($app_name);
		}

		function add($data)
		{
			return $this->so->add($data);
		}

		function save($data)
		{
			return $this->so->save($data);
		}

		function exists($app_name)
		{
			return $this->so->exists($app_name);
		}

		function app_order()
		{
			return $this->so->app_order();
		}

		function delete($app_name)
		{
			return $this->so->delete($app_name);
		}

		function register_hook($hook_app)
		{
			return $this->so->register_hook($hook_app);
		}

		function register_all_hooks()
		{
			$SEP = filesystem_separator();
			$app_list = $this->get_list();
			$hooks = CreateObject('phpgwapi.hooks');
			while(list($app_name,$app) = each($app_list))
			{			
				$f = PHPGW_SERVER_ROOT . $SEP . $app_name . $SEP . 'setup' . $SEP . 'setup.inc.php';
				if(@file_exists($f))
				{
					include($f);
					while(is_array($setup_info[$app_name]['hooks']) && list(,$hook) = @each($setup_info[$app_name]['hooks']))
					{
						if(!@$hooks->found_hooks[$app_name][$hook])
						{
							$this->register_hook(
								Array(
									'app_name'	=> $app_name,
									'hook'	=> $hook
								)
							);
						}
					}
				}
			}
			Header('Location: '.$GLOBALS['phpgw']->link('/admin/index.php'));
			$GLOBALS['phpgw']->common->phpgw_exit();

		}
	}
