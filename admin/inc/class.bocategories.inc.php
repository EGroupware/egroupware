<?php
	/**************************************************************************\
	* phpGroupWare - Admin - Global categories                                 *
	* http://www.phpgroupware.org                                              *
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
			if ($GLOBALS['appname'])
			{
				$this->cats = CreateObject('phpgwapi.categories',-1,$GLOBALS['appname']);
			}
			else
			{
				$this->cats = CreateObject('phpgwapi.categories',$GLOBALS['phpgw_info']['user']['account_id'],'phpgw');
			}

			$this->read_sessiondata();

			/* _debug_array($GLOBALS['HTTP_POST_VARS']); */
			/* Might change this to '' at the end---> */
			$start  = $GLOBALS['HTTP_POST_VARS']['start']  ? $GLOBALS['HTTP_POST_VARS']['start']  : $GLOBALS['HTTP_GET_VARS']['start'];
			$query  = $GLOBALS['HTTP_POST_VARS']['query']  ? $GLOBALS['HTTP_POST_VARS']['query']  : $GLOBALS['HTTP_GET_VARS']['query'];
			$sort   = $GLOBALS['HTTP_POST_VARS']['sort']   ? $GLOBALS['HTTP_POST_VARS']['sort']   : $GLOBALS['HTTP_GET_VARS']['sort'];
			$order  = $GLOBALS['HTTP_POST_VARS']['order']  ? $GLOBALS['HTTP_POST_VARS']['order']  : $GLOBALS['HTTP_GET_VARS']['order'];
			$cat_id = $GLOBALS['HTTP_POST_VARS']['cat_id'] ? $GLOBALS['HTTP_POST_VARS']['cat_id'] : $GLOBALS['HTTP_GET_VARS']['cat_id'];

			if(!empty($start) || $start == '0' || $start == 0)
			{
				if($this->debug) { echo '<br>overriding start: "' . $this->start . '" now "' . $start . '"'; }
				$this->start = $start;
			}
			if((empty($query) && !empty($this->query)) || !empty($query))
			{
				if($this->debug) { echo '<br>setting query to: "' . $query . '"'; }
				$this->query = $query;
			}

			if(isset($cat_id))
			{
				$this->cat_id = $cat_id;
			}
			if($cat_id == '0' || $cat_id == 0 || $cat_id == '')
			{
				unset($this->cat_id);
			}
			if(isset($sort) && !empty($sort))
			{
				$this->sort = $sort;
			}
			if(isset($order) && !empty($order))
			{
				$this->order = $order;
			}
		}

		function save_sessiondata($data)
		{
			if($this->debug) { echo '<br>Save:'; _debug_array($data); }
			$GLOBALS['phpgw']->session->appsession('session_data','admin_cats',$data);
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','admin_cats');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			if(isset($data['cat_id']))
			{
				$this->cat_id = $data['cat_id'];
			}
		}

		function get_list($global_cats=False)
		{
			if($this->debug) { echo '<br>querying: "' . $this->query . '"'; }

			if ($global_cats)
			{
				return $this->cats->return_sorted_array($this->start,True,$this->query,$this->sort,$this->order,True);
			}
			else
			{
				return $this->cats->return_sorted_array($this->start,True,$this->query,$this->sort,$this->order);
			}
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
			return $this->cats->exists($data['type'],$data['cat_name'],$data['cat_id']);
		}

		function formatted_list($data)
		{
			if ($data['global_cats'])
			{
				return $this->cats->formated_list($data['select'],$data['all'],$data['cat_parent'],True);
			}
			else
			{
				return $this->cats->formated_list($data['select'],$data['all'],$data['cat_parent']);
			}
		}

		function delete($cat_id,$drop_subs=False,$modify_subs=True)
		{
			$this->cats->delete($cat_id,$drop_subs,$modify_subs);
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
