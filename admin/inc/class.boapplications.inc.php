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
	}
