<?php
	/**************************************************************************\
	* eGroupWare - Preferences - categories                                    *
	* http://www.egroupware.org                                                *
	* Written by Bettina Gille [ceb@phpgroupware.org]                          *
	* -----------------------------------------------                          *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/
	/* $Id$ */

	class bocategories
	{
		var $cats;

		var $start;
		var $query;
		var $sort;
		var $order;

		function bocategories($cats_app='')
		{
			$this->cats           =& CreateObject('phpgwapi.categories','',$cats_app);

			$this->read_sessiondata($cats_app);

			$start  = $_POST['start']  ? $_POST['start']  : $_GET['start'];
			$query  = $_POST['query']  ? $_POST['query']  : $_GET['query'];
			$sort   = $_POST['sort']   ? $_POST['sort']   : $_GET['sort'];
			$order  = $_POST['order']  ? $_POST['order']  : $_GET['order'];

			if(!empty($start) || $start == '0' || $start == 0)
			{
				$this->start = $start;
			}
			if((empty($query) && !empty($this->query)) || !empty($query))
			{
				$this->query = $query;
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

		function save_sessiondata($data, $cats_app)
		{
			$GLOBALS['egw']->session->appsession($cats_app . '_cats','preferences',$data);
		}

		function read_sessiondata($cats_app)
		{
			$data = $GLOBALS['egw']->session->appsession($cats_app . '_cats','preferences');

			$this->start  = $data['start'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->referer = $data['referer'];
		}

		function get_list($global_cats)
		{
			return $this->cats->return_sorted_array($this->start,True,$this->query,$this->sort,$this->order,$global_cats);
		}

		function save_cat($values)
		{
			if ($values['access'])
			{
				$values['access'] = 'private';
			}
			else
			{
				$values['access'] = 'public';
			}

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
			return $this->cats->exists($data['type'],$data['cat_name'],$data['type'] == 'subs' ? 0 : $data['cat_id'],$data['type'] != 'subs' ? 0 : $data['cat_id'], $data['cat_access']);
		}

		function formatted_list($format,$type,$cat_parent,$global_cats)
		{
			return $this->cats->formatted_list($format,$type,$cat_parent,$global_cats);
		}

		function delete($cat_id,$subs)
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
						'type'       => 'appandmains',
						'cat_name'   => $values['name'],
						'cat_id'     => $values['id'],
						'cat_access' => $values['access']
					));
				}
				else
				{
					$exists = $this->exists(array
					(
						'type'       => 'appandsubs',
						'cat_name'   => $values['name'],
						'cat_id'     => $values['id'],
						'cat_access' => $values['access']
					));
				}

				if ($exists == True)
				{
					$error[] = lang('This name has been used already');
				}
			}

			if (is_array($error))
			{
				return $error;
			}
		}
	}
