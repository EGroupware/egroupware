<?php
  /**************************************************************************\
  * eGroupWare - Addressbook                                                 *
  * http://www.egroupware.org                                                *
  * Written by Joseph Engo <jengo@phpgroupware.org> and                      *
  * Miles Lott <milosch@groupwhere.org>                                      *
  * -----------------------------------------------                          *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class bofields
	{
		var $so;

		function bofields()
		{
			/* Config class here is the so object */
			$this->so = CreateObject('phpgwapi.config','addressbook');
		}

		function _read($start=0,$limit=5,$query='')
		{
			$i = 0;
			$fields = array();

			$this->so->read_repository();
			$config_name = isset($this->so->config_data['customfields']) ? 'customfields' : 'custom_fields';
			while(list($name,$descr) = @each($this->so->config_data[$config_name]))
			{
				if(is_array($descr))
				{
					$descr = $descr['label'];
				}
				/*
				if($start < $i)
				{
					continue;
				}
				*/

				$test = @strtolower($name);
				//if($query && !strstr($test,strtolower($query)))
				if($query && ($query != $test))
				{
				}
				else
				{
					$fields[$i]['name'] = $name;
					$fields[$i]['title'] = $descr;
					$fields[$i]['id'] = $i;

					/*
					if($i >= $limit)
					{
						break;
					}
					*/
					$i++;
				}
			}
			switch($sort)
			{
				case 'DESC';
					krsort($fields);
					break;
				case 'ASC':
				default:
					ksort($fields);
			}
			@reset($fields);

			return $fields;
		}

		function _save($old='',$new='')
		{
			$this->so->read_repository();

			if(!is_array($this->so->config_data['custom_fields']))
			{
				$this->so->config_data['custom_fields'] = array();
			}

			if($old)
			{
				unset($this->so->config_data['custom_fields'][$old]);
			}
			if($new)
			{
				$tmp = strtolower(str_replace(' ','_',$new));
				$this->so->config_data['custom_fields'][$tmp] = $new;
			}

			$this->so->save_repository();
		}
	}
?>
