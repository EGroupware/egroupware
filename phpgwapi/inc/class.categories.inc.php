<?php
	/**************************************************************************\
	* phpGroupWare API - Categories                                            *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	*                  and Bettina Gille [ceb@phpgroupware.org]                *
	* Category manager                                                         *
	* Copyright (C) 2000 - 2002 Joseph Engo                                    *
	* ------------------------------------------------------------------------ *
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
	/* $Source$ */

	/*!
	@class categories
	@abstract class adds ability for applications to make use of categories
	@discussion examples can be found in notes app
	*/
	class categories
	{
		var $account_id;
		var $app_name;
		var $cats;
		var $db;
		var $total_records;
		var $grants;

		/*!
		@function categories
		@abstract constructor for categories class
		@param $accountid account id
		@param $app_name app name defaults to current app
		*/
		function categories($accountid = '',$app_name = '')
		{
			$account_id = get_account_id($accountid);

			if (! $app_name)
			{
				$app_name = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}

			$this->account_id = $account_id;
			$this->app_name   = $app_name;
			$this->db         = $GLOBALS['phpgw']->db;
			$this->grants     = $GLOBALS['phpgw']->acl->get_grants($app_name);
		}

		/*!
		@function filter
		@abstract ?
		@param $type string
		@result string either subs or mains
		*/
		function filter($type)
		{
			switch ($type)
			{
				case 'subs':		$s = " AND cat_parent != '0'"; break;
				case 'mains':		$s = " AND cat_parent = '0'"; break;
				case 'appandmains':	$s = " AND cat_appname='" . $this->app_name . "' AND cat_parent ='0'"; break;
				case 'appandsubs':	$s = " AND cat_appname='" . $this->app_name . "' AND cat_parent !='0'"; break;
				case 'noglobal':	$s = " AND cat_appname != '" . $this->app_name . "'"; break;
				case 'noglobalapp':	$s = " AND cat_appname = '" . $this->app_name . "' AND cat_owner != '" . $this->account_id . "'"; break;
				default:            return False;
			}
			return $s;
		}

		/*!
		@function total
		@abstract returns the total number of categories for app, subs or mains
		@param $for one of either 'app' 'subs' or 'mains'
		@result integer count of categories
		*/
		function total($for = 'app')
		{
			switch($for)
			{
				case 'app':         $w = " WHERE cat_appname='" . $this->app_name . "'"; break;
				case 'appandmains': $w = " WHERE cat_appname='" . $this->app_name . "' AND cat_parent ='0'"; break;
				case 'appandsubs':  $w = " WHERE cat_appname='" . $this->app_name . "' AND cat_parent !='0'"; break;
				case 'subs':        $w = " WHERE cat_parent != '0'"; break;
				case 'mains':       $w = " WHERE cat_parent = '0'"; break;
				default:            return False;
			}

			$this->db->query("SELECT COUNT(cat_id) FROM phpgw_categories $w",__LINE__,__FILE__);
			$this->db->next_record();

			return $this->db->f(0);
		}

		/*!
		@function return_array
		@abstract return an array populated with categories
		@param $type string defaults to 'all'
		@param $start ?
		@param $limit ?
		@param $query string defaults to ''
		@param $sort string sort order, either defaults to 'ASC'
		@param $order order by
		@param $globals True or False, includes the global phpgroupware categories or not
		@result $cats array
		*/
		function return_array($type,$start,$limit = True,$query = '',$sort = '',$order = '',$globals = False, $parent_id = '')
		{
			if ($globals)
			{
				$global_cats = " OR cat_appname='phpgw'";
			}

			$filter = $this->filter($type);

			if (!$sort)
			{
				$sort = 'ASC';
			}

			if ($order)
			{
				$ordermethod = " ORDER BY $order $sort";
			}
			else
			{
				$ordermethod = ' ORDER BY cat_main, cat_level, cat_name ASC';
			}

			if ($this->account_id == '-1')
			{
				$grant_cats = " cat_owner='-1' ";
			}
			else
			{
				if (is_array($this->grants))
				{
					$grants = $this->grants;
					while(list($user) = each($grants))
					{
						$public_user_list[] = $user;
					}
					reset($public_user_list);
					$grant_cats = " (cat_owner='" . $this->account_id . "' OR cat_owner='-1' OR cat_access='public' AND cat_owner in(" . implode(',',$public_user_list) . ")) ";
				}
				else
				{
					$grant_cats = " cat_owner='" . $this->account_id . "' OR cat_owner='-1' ";
				}
			}

			if ($parent_id)
			{
				$parent_filter = " AND cat_parent='$parent_id'";
			}

			if ($query)
			{
				$querymethod = " AND (cat_name LIKE '%$query%' OR cat_description LIKE '%$query%') ";
			}

			$sql = "SELECT * from phpgw_categories WHERE (cat_appname='" . $this->app_name . "' AND" . $grant_cats . $global_cats . ")"
				. $parent_filter . $querymethod . $filter;

			if ($limit)
			{
				$this->db->limit_query($sql . $ordermethod,$start,__LINE__,__FILE__);
			}
			else
			{
				$this->db->query($sql . $ordermethod,__LINE__,__FILE__);
			}

			$this->total_records = $this->db->num_rows();

			$i = 0;
			while ($this->db->next_record())
			{
				$cats[$i]['id']          = $this->db->f('cat_id');
				$cats[$i]['owner']       = $this->db->f('cat_owner');
				$cats[$i]['access']      = $this->db->f('cat_access');
				$cats[$i]['app_name']    = $this->db->f('cat_appname');
				$cats[$i]['main']        = $this->db->f('cat_main');
				$cats[$i]['level']       = $this->db->f('cat_level');
				$cats[$i]['parent']      = $this->db->f('cat_parent');
				$cats[$i]['name']        = $this->db->f('cat_name');
				$cats[$i]['description'] = $this->db->f('cat_description');
				$cats[$i]['data']        = $this->db->f('cat_data');
				$i++;
			}
			return $cats;
		}

		function return_sorted_array($start,$limit = True,$query = '',$sort = '',$order = '',$globals = False, $parent_id = '')
		{
			if ($globals)
			{
				$global_cats = " OR cat_appname='phpgw'";
			}

			if (!$sort)
			{
				$sort = 'ASC';
			}

			if ($order)
			{
				$ordermethod = " ORDER BY $order $sort";
			}
			else
			{
				$ordermethod = ' ORDER BY cat_name ASC';
			}

			if ($this->account_id == '-1')
			{
				$grant_cats = " cat_owner='-1' ";
			}
			else
			{
				if (is_array($this->grants))
				{
					$grants = $this->grants;
					while(list($user) = each($grants))
					{
						$public_user_list[] = $user;
					}
					reset($public_user_list);
					$grant_cats = " (cat_owner='" . $this->account_id . "' OR cat_owner='-1' OR cat_access='public' AND cat_owner in(" . implode(',',$public_user_list) . ")) ";
				}
				else
				{
					$grant_cats = " cat_owner='" . $this->account_id . "' or cat_owner='-1' ";
				}
			}

			if ($parent_id)
			{
				$parent_select = " AND cat_parent='$parent_id'";
			}
			else
			{
				$parent_select = " AND cat_parent='0'";
			}

			if ($query)
			{
				$querymethod = " AND (cat_name LIKE '%$query%' OR cat_description LIKE '%$query%') ";
			}

			$sql = "SELECT * from phpgw_categories WHERE (cat_appname='" . $this->app_name . "' AND" . $grant_cats . $global_cats . ")"
					. $querymethod;

			if ($limit)
			{
				$this->db->limit_query($sql . $parent_select . $ordermethod,$start,__LINE__,__FILE__);
			}
			else
			{
				$this->db->query($sql . $parent_select . $ordermethod,__LINE__,__FILE__);
			}

			$i = 0;
			while ($this->db->next_record())
			{
				$cats[$i]['id']          = $this->db->f('cat_id');
				$cats[$i]['owner']       = $this->db->f('cat_owner');
				$cats[$i]['access']      = $this->db->f('cat_access');
				$cats[$i]['app_name']    = $this->db->f('cat_appname');
				$cats[$i]['main']        = $this->db->f('cat_main');
				$cats[$i]['level']       = $this->db->f('cat_level');
				$cats[$i]['parent']      = $this->db->f('cat_parent');
				$cats[$i]['name']        = $this->db->f('cat_name');
				$cats[$i]['description'] = $this->db->f('cat_description');
				$cats[$i]['data']        = $this->db->f('cat_data');
				$i++;
			}

			$num_cats = count($cats);
			for ($i=0;$i < $num_cats;$i++)
			{
				$sub_select = " AND cat_parent='" . $cats[$i]['id'] . "' AND cat_level='" . ($cats[$i]['level']+1) . "'";

				if ($limit)
				{
					$this->db->limit_query($sql . $sub_select . $ordermethod,$start,__LINE__,__FILE__);
				}
				else
				{
					$this->db->query($sql . $sub_select . $ordermethod,__LINE__,__FILE__);
				}

				$subcats = array();
				$j = 0;
				while ($this->db->next_record())
				{
					$subcats[$j]['id']          = $this->db->f('cat_id');
					$subcats[$j]['owner']       = $this->db->f('cat_owner');
					$subcats[$j]['access']      = $this->db->f('cat_access');
					$subcats[$j]['app_name']    = $this->db->f('cat_appname');
					$subcats[$j]['main']        = $this->db->f('cat_main');
					$subcats[$j]['level']       = $this->db->f('cat_level');
					$subcats[$j]['parent']      = $this->db->f('cat_parent');
					$subcats[$j]['name']        = $this->db->f('cat_name');
					$subcats[$j]['description'] = $this->db->f('cat_description');
					$subcats[$j]['data']        = $this->db->f('cat_data');
					$j++;
				}

				$num_subcats = count($subcats);
				if ($num_subcats != 0)
				{
					$newcats = array();
					for ($k = 0; $k <= $i; $k++)
					{
						$newcats[$k] = $cats[$k];
					}
					for ($k = 0; $k < $num_subcats; $k++)
					{
						$newcats[$k+$i+1] = $subcats[$k];
					}
					for ($k = $i+1; $k < $num_cats; $k++)
					{
						$newcats[$k+$num_subcats] = $cats[$k];
					}
					$cats = $newcats;
					$num_cats = count($cats);
				}
			}
			$this->total_records = count($cats);
			return $cats;
		}

		/*!
		@function return_single
		@abstract return single
		@param $id integer id of category
		@result $cats  array populated with
		*/
		function return_single($id = '')
		{
			$this->db->query('SELECT * FROM phpgw_categories WHERE cat_id=' . intval($id),__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				$cats[0]['id']          = $this->db->f('cat_id');
				$cats[0]['owner']       = $this->db->f('cat_owner');
				$cats[0]['access']      = $this->db->f('cat_access');
				$cats[0]['app_name']    = $this->db->f('cat_appname');
				$cats[0]['main']        = $this->db->f('cat_main');
				$cats[0]['level']       = $this->db->f('cat_level');
				$cats[0]['parent']      = $this->db->f('cat_parent');
				$cats[0]['name']        = $this->db->f('cat_name');
				$cats[0]['description'] = $this->db->f('cat_description');
				$cats[0]['data']        = $this->db->f('cat_data');
			}
			return $cats;
		}

		/*!
		@function formated_list
		@abstract return into a select box, list or other formats
		@param $format currently only supports select (select box)
		@param $type string - subs or mains
		@param $selected - cat_id or array with cat_id values 
		@param $globals True or False, includes the global phpgroupware categories or not
		@result $s array - populated with categories
		*/
		function formatted_list($format,$type = 'all',$selected = '',$globals = False,$site_link = 'site')
		{
			return $this->formated_list($format,$type,$selected,$globals,$site_link);
		}

		function formated_list($format,$type = 'all',$selected = '',$globals = False,$site_link = 'site')
		{
			if(is_array($format))
			{
				$temp_format = $format['format'];
				$type = (isset($format['type'])?$format['type']:'all');
				$selected = (isset($format['selected'])?$format['selected']:'');
				$globals = (isset($format['globals'])?$format['globals']:False);
				$site_link = (isset($format['site_link'])?$format['site_link']:'site');
				settype($format,'string');
				$format = $temp_format;
				unset($temp_format);
			}

			if (!is_array($selected))
			{
				$selected = explode(',',$selected);
			}

			if ($type != 'all')
			{
				$cats = $this->return_array($type,$start,False,$query,$sort,$order,$globals);
			}
			else
			{
				$cats = $this->return_sorted_array($start,False,$query,$sort,$order,$globals);
			}

			if ($format == 'select')
			{
				for ($i=0;$i<count($cats);$i++)
				{
					$s .= '<option value="' . $cats[$i]['id'] . '"';
					if (in_array($cats[$i]['id'],$selected))
					{
						$s .= ' selected';
					}
					$s .= '>';
					for ($j=0;$j<$cats[$i]['level'];$j++)
					{
						$s .= '&nbsp;';
					}
					$s .= $GLOBALS['phpgw']->strip_html($cats[$i]['name']);
					if ($cats[$i]['app_name'] == 'phpgw')
					{
						$s .= '&nbsp;&lt;' . lang('Global') . '&gt;';
					}
					if ($cats[$i]['owner'] == '-1')
					{
						$s .= '&nbsp;&lt;' . lang('Global') . '&nbsp;' . lang($this->app_name) . '&gt;';
					}

					$s .= '</option>' . "\n";
				}
				return $s;
			}

			if ($format == 'list')
			{
				$space = '&nbsp;&nbsp;';

				$s  = '<table border="0" cellpadding="2" cellspacing="2">' . "\n";

				if ($this->total_records > 0)
				{
					for ($i=0;$i<count($cats);$i++)
					{
						$image_set = '&nbsp;';

						if (in_array($cats[$i]['id'],$selected))
						{
							$image_set = '<img src="' . $GLOBALS['phpgw']->common->image('phpgwapi','roter_pfeil') . '">';
						}

						if (($cats[$i]['level'] == 0) && !in_array($cats[$i]['id'],$selected))
						{
							$image_set = '<img src="' . $GLOBALS['phpgw']->common->image('phpgwapi','grauer_pfeil') . '">';
						}

						$space_set = str_repeat($space,$cats[$i]['level']);

						$s .= '<tr>' . "\n";
						$s .= '<td width="8">' . $image_set . '</td>' . "\n";
						$s .= '<td>' . $space_set . '<a href="' . $GLOBALS['phpgw']->link($site_link,'cat_id=' . $cats[$i]['id']) . '">'
							. $GLOBALS['phpgw']->strip_html($cats[$i]['name'])
							. '</a></td>' . "\n"
							. '</tr>' . "\n";
					}
				}
				$s .= '</table>' . "\n";
				return $s;
			}
		}

		function formatted_xslt_list($data)
		{
			if(is_array($data))
			{
				$format = (isset($data['format'])?$data['format']:'select');
				$type = (isset($data['type'])?$data['type']:'all');
				$selected = (isset($data['selected'])?$data['selected']:'');
				$globals = (isset($data['globals'])?$data['globals']:False);
				$site_link = (isset($data['site_link'])?$data['site_link']:'site');
			}

			if (!is_array($selected))
			{
				$selected = explode(',',$selected);
			}

			if ($type != 'all')
			{
				$cats = $this->return_array($type,$start,False,$query,$sort,$order,$globals);
			}
			else
			{
				$cats = $this->return_sorted_array($start,False,$query,$sort,$order,$globals);
			}

			if ($format == 'select')
			{
				while (is_array($cats) && list(,$cat) = each($cats))
				{
					$sel_cat = '';
					if (in_array($cat['id'],$selected))
					{
						$sel_cat = 'selected';
					}

					$name = '';
					for ($i=0;$i<$cat['level'];$i++)
					{
						$name .= '-';
					}
					$name .= $GLOBALS['phpgw']->strip_html($cat['name']);

					if ($cat['app_name'] == 'phpgw')
					{
						$name .= ' <' . lang('Global') . '>';
					}
					if ($cat['owner'] == '-1')
					{
						$name .= ' <' . lang('Global') . ' ' . lang($this->app_name) . '>';
					}

					$cat_list[] = array
					(
						'id'		=> $cat['id'],
						'name'		=> $name,
						'selected'	=> $sel_cat
					);
				}

				for ($i=0;$i<count($cat_list);$i++)
				{
					if ($cat_list[$i]['selected'] != 'selected')
					{
						unset($cat_list[$i]['selected']);
					}
				}
				return $cat_list;
			}
		}

		/*!
		@function add
		@abstract add categories
		@param $cat_name category name
		@param $cat_parent category parent
		@param $cat_description category description defaults to ''
		@param $cat_data category data defaults to ''
		*/
		function add($cat_values)
		{
			if ($cat_values['parent'] && $cat_values['parent'] != 0)
			{
				$cat_values['main'] = $this->id2name($cat_values['parent'],'main');
				$cat_values['level'] = $this->id2name($cat_values['parent'],'level')+1;
			}

			$cat_values['descr'] = $this->db->db_addslashes($cat_values['descr']);
			$cat_values['name'] = $this->db->db_addslashes($cat_values['name']);

			$this->db->query("INSERT INTO phpgw_categories (cat_parent,cat_owner,cat_access,cat_appname,cat_name,cat_description,cat_data,"
				. "cat_main,cat_level) VALUES ('" . $cat_values['parent'] . "','" . $this->account_id . "','" . $cat_values['access']
				. "','" . $this->app_name . "','" . $cat_values['name'] . "','" . $cat_values['descr'] . "','" . $cat_values['data']
				. "','" . $cat_values['main'] . "','" . $cat_values['level'] . "')",__LINE__,__FILE__);

			$max = $this->db->get_last_insert_id('phpgw_categories','cat_id');

			if (!$cat_values['parent'] || $cat_values['parent'] == 0)
			{
				$this->db->query("UPDATE phpgw_categories SET cat_main='" . $max . "' WHERE cat_id='"
								. $max . "'",__LINE__,__FILE__);
			}
			return $max;
		}

		/*!
		@function delete
		@abstract delete category
		@param $cat_id int - category id
		*/
		function delete($data)
		{
			if(is_array($data))
			{
				$cat_id			= $data['cat_id'];
				$drop_subs		= (isset($data['drop_subs'])?$data['drop_subs']:False);
				$modify_subs	= (isset($data['modify_subs'])?$data['modify_subs']:False);
			}

			if ($cat_id > 0)
			{
				if ($drop_subs)
				{
					$subdelete = ' OR cat_parent=' . $cat_id . ' OR cat_main=' . $cat_id; 
				}

				if ($modify_subs)
				{
					$cats = $this->return_sorted_array('',False,'','','',False, $cat_id);

					$new_parent = $this->id2name($cat_id,'parent');

					for ($i=0;$i<count($cats);$i++)
					{
						if ($cats[$i]['level'] == 1)
						{
							$this->db->query("UPDATE phpgw_categories set cat_level=0, cat_parent=0, cat_main='" . intval($cats[$i]['id'])
										. "' WHERE cat_id='" . intval($cats[$i]['id']) . "' AND cat_appname='" . $this->app_name . "'",__LINE__,__FILE__);
							$new_main = $cats[$i]['id'];
						}
						else
						{
							if ($new_main)
							{
								$update_main = ',cat_main=' . $new_main;
							}

							if ($cats[$i]['parent'] == $cat_id)
							{
								$update_parent = ',cat_parent=' . $new_parent;
							}

							$this->db->query("UPDATE phpgw_categories set cat_level='" . ($cats[$i]['level']-1) . "'" . $update_main . $update_parent 
											. " WHERE cat_id='" . intval($cats[$i]['id']) . "' AND cat_appname='" . $this->app_name . "'",__LINE__,__FILE__);
						}
					}
				}

				$this->db->query("DELETE FROM phpgw_categories WHERE cat_id='" . $cat_id . $subdelete . "'AND cat_appname='"
								. $this->app_name . "'",__LINE__,__FILE__);
			}
		}

		/*!
		@function edit
		@abstract edit a category
		@param $cat_id int - category id
		@param $cat_parent category parent
		@param $cat_description category description defaults to ''
		@param $cat_data category data defaults to ''
		*/
		function edit($cat_values)
		{
			if ($cat_values['parent'] && ($cat_values['parent'] != 0))
			{
				$cat_values['main']  = $this->id2name($cat_values['parent'],'main');
				$cat_values['level'] = $this->id2name($cat_values['parent'],'level')+1;
			}
			else
			{
				$cat_values['main']  = $cat_values['id'];
				$cat_values['level'] = 0;
			}

			$cat_values['descr'] = $this->db->db_addslashes($cat_values['descr']);
			$cat_values['name'] = $this->db->db_addslashes($cat_values['name']);

			$sql = "UPDATE phpgw_categories SET cat_name='" . $cat_values['name'] . "', cat_description='" . $cat_values['descr']
				. "', cat_data='" . $cat_values['data'] . "', cat_parent='" . $cat_values['parent'] . "', cat_access='"
				. $cat_values['access'] . "', cat_main='" . $cat_values['main'] . "', cat_level='" . $cat_values['level'] . "' "
				. "WHERE cat_appname='" . $this->app_name . "' AND cat_id='" . $cat_values['id'] . "'";

			$this->db->query($sql,__LINE__,__FILE__);
		}

		function name2id($cat_name)
		{
			$this->db->query("SELECT cat_id FROM phpgw_categories WHERE cat_name='" . $this->db->db_addslashes($cat_name) . "' "
							."AND cat_appname='" . $this->app_name . "' AND cat_owner=" . $this->account_id,__LINE__,__FILE__);

			if(!$this->db->num_rows())
			{
				return 0;
			}

			$this->db->next_record();

			return $this->db->f('cat_id');
		}

		function id2name($cat_id = '', $item = 'name')
		{
			if ($cat_id == '')
			{
				return '--';
			}
			switch($item)
			{
				case 'name':	$value = 'cat_name'; break;
				case 'owner':	$value = 'cat_owner'; break;
				case 'main':	$value = 'cat_main'; break;
				case 'level':	$value = 'cat_level'; break;
				case 'data':	$value = 'cat_data'; break;
				case 'parent':	$value = 'cat_parent'; break;
			}

			$this->db->query("SELECT $value FROM phpgw_categories WHERE cat_id='" . $cat_id . "'",__LINE__,__FILE__);
			$this->db->next_record();

			if ($this->db->f($value))
			{
				return $this->db->f($value);
			}
			else
			{
				if ($item == 'name')
				{
					return '--';
				}
			}
		}

		/*!
		@function return_name
		@abstract return category name given $cat_id
		@param $cat_id
		@result cat_name category name
		*/
		// NOTE: This is only a temp wrapper, use id2name() to keep things matching across the board. (jengo)
		function return_name($cat_id)
		{
			return $this->id2name($cat_id);
		}

		/*!
		@function exists
		@abstract used for checking if a category name exists
		@param $type subs or mains
		@param $cat_name category name
		@result boolean true or false
		*/
		function exists($type,$cat_name = '',$cat_id = '')
		{
			if(is_array($type))
			{
				$temp_type = $type['type'];
				$cat_name = $type['cat_name'] ? $type['cat_name'] : '';
				$cat_id = $type['cat_id'] ? $type['cat_id'] : '';
				settype($type,'string');
				$type = $temp_type;
				unset($temp_type);
			}

			$filter = $this->filter($type);

			if ($cat_name)
			{
				$cat_exists = " cat_name='" . $this->db->db_addslashes($cat_name) . "' "; 
			}

			if ($cat_id)
			{
				$cat_exists = " cat_parent='$cat_id' ";
			}

			if ($cat_name && $cat_id)
			{
				$cat_exists = " cat_name='" . $this->db->db_addslashes($cat_name) . "' AND cat_id != '$cat_id' ";
			}

			$this->db->query("SELECT COUNT(cat_id) FROM phpgw_categories WHERE $cat_exists $filter",__LINE__,__FILE__);

			$this->db->next_record();

			if ($this->db->f(0))
			{
				return True;
			}
			else
			{
				return False;
			}
		}
	}
?>
