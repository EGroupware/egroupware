<?php
	/**************************************************************************\
	* eGroupWare - Admin - Global categories                                   *
	* http://www.egroupware.org                                                *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */
	/* $Source$ */

	class bocategories
	{
		var $cats;

		var $start;
		var $query;
		var $sort;
		var $order;
		var $filter;
		var $cat_id;
		var $total;

		var $debug = False;

		function bocategories()
		{
			if ($_REQUEST['appname'])
			{
				$this->cats =& CreateObject('phpgwapi.categories',-1,$_REQUEST['appname']);
			}
			else
			{
				$this->cats =& CreateObject('phpgwapi.categories',$GLOBALS['egw_info']['user']['account_id'],'phpgw');
			}

			$this->read_sessiondata();

			foreach(array('start','query','sort','order','cat_id') as $name)
			{
				if (isset($_REQUEST[$name])) $this->$name = $_REQUEST[$name];
			}
		}

		function save_sessiondata($data)
		{
			if($this->debug) { echo '<br>Save:'; _debug_array($data); }
			//echo '<p>'.__METHOD__."() start=$this->start, sort=$this->sort, order=$this->order, query=$this->query</p>\n";
			$GLOBALS['egw']->session->appsession('session_data','admin_cats',$data);
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['egw']->session->appsession('session_data','admin_cats');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			if(isset($data['cat_id']))
			{
				$this->cat_id = $data['cat_id'];
			}
			//echo '<p>'.__METHOD__."() start=$this->start, sort=$this->sort, order=$this->order, query=$this->query</p>\n";
		}

		function get_list()
		{
			if($this->debug) { echo '<br>querying: "' . $this->query . '"'; }
			//echo '<p>'.__METHOD__."() start=$this->start, sort=$this->sort, order=$this->order, query=$this->query</p>\n";
			return $this->cats->return_sorted_array($this->start,True,$this->query,$this->sort,$this->order,True);
		}

		function save_cat($values)
		{
			if ($values['id'] && $values['id'] != 0)
			{
				return $this->cats->edit($values);
			}
			else
			{
				return $this->cats->add($values);
			}
		}

		function exists($data)
		{
			$data['type']   = $data['type'] ? $data['type'] : '';
			$data['cat_id'] = $data['cat_id'] ? $data['cat_id'] : '';
			return $this->cats->exists($data['type'],$data['cat_name'],$data['type'] == 'subs' ? 0 : $data['cat_id'],$data['type'] != 'subs' ? 0 : $data['cat_id']);
		}

		function formatted_list($data)
		{
			return $this->cats->formatted_list($data['select'],$data['all'],$data['cat_parent'],True);
		}

		function delete($cat_id,$subs=False)
		{
			return $this->cats->delete($cat_id,$subs,!$subs);	// either delete the subs or modify them
		}

		function check_values($values)
		{
			if (strlen($values['descr']) >= 255)
			{
				$error[] = lang('Description can not exceed 255 characters in length !');
			}

			if (!$values['name'])
			{
				$error[] = lang('Please enter a name');
			}
			else
			{
				if (!$values['parent'])
				{
					$exists = $this->exists(array
					(
						'type'     => 'appandmains',
						'cat_name' => $values['name'],
						'cat_id'   => $values['id']
					));
				}
				else
				{
					$exists = $this->exists(array
					(
						'type'     => 'appandsubs',
						'cat_name' => $values['name'],
						'cat_id'   => $values['id']
					));
				}

				if ($exists == True)
				{
					$error[] = lang('That name has been used already');
				}
			}

			if (is_array($error))
			{
				return $error;
			}
		}
	}
