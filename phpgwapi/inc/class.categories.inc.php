<?php
	/**************************************************************************\
	* phpGroupWare API - Categories                                            *
	* This file written by Joseph Engo <jengo@phpgroupware.org>                *
	* Category manager                                                         *
	* Copyright (C) 2000, 2001 Joseph Engo                                     *
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
		@function filter
		@abstract ?
		@param $type string
		@result string either subs or mains
		*/
		function filter($type)
		{
			switch ($type)
			{
				case 'subs':		 $s = " and cat_parent != '0'"; break;
				case 'mains':		 $s = " and cat_parent = '0'"; break;
				case 'appandmains':	 $s = " and cat_appname='" . $this->app_name . "' and cat_parent ='0'"; break;
				case 'appandsubs':	 $s = " and cat_appname='" . $this->app_name . "' and cat_parent !='0'"; break;
				default: return False;
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
				case 'app':			$w = " where cat_appname='" . $this->app_name . "'"; break;
				case 'appandmains':	$w = " where cat_appname='" . $this->app_name . "' and cat_parent ='0'";
				case 'appandsubs':	$w = " where cat_appname='" . $this->app_name . "' and cat_parent !='0'";
				case 'subs':		$w = " where cat_parent != '0'"; break;
				case 'mains':		$w = " where cat_parent = '0'"; break;
				default:			return False;
			
			}
			$this->db->query("select count(cat_id) from phpgw_categories $w",__LINE__,__FILE__);
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
		@result $cats array
		*/
		function return_array($type,$start,$limit = True,$query = '',$sort = '',$order = '',$public = False, $parent_id = '')
		{
			global $phpgw, $phpgw_info;

			if ($public)
			{
				$public_cats = " OR cat_appname='phpgw' ";
			}

			$filter = $this->filter($type);

			if (!$sort)
			{
				$sort = "ASC";
			}

			if ($order)
			{
				$ordermethod = " order by $order $sort";
			}
			else
			{
				$ordermethod = " order by cat_main, cat_level, cat_name asc";
			}

			if (is_array($this->grants))
			{
				$grants = $this->grants;
				while(list($user) = each($grants))
				{
					$public_user_list[] = $user;
				}
				reset($public_user_list);
				$grant_cats = " (cat_owner='" . $this->account_id . "' OR cat_access='public' AND cat_owner in(" . implode(',',$public_user_list) . ")) ";
			}
			else
			{
				$grant_cats = " (cat_owner='" . $this->account_id . "') ";
			}

			if ($parent_id)
			{
				$parent_filter = " and cat_parent='$parent_id'";
			}

			if ($query)
			{
				$querymethod = " AND (cat_name like '%$query%' OR cat_description like '%$query%') ";
			}

			$sql = "SELECT * from phpgw_categories WHERE (cat_appname='" . $this->app_name . "' $parent_filter AND "
				. " $grant_cats) $public_cats $querymethod $filter";

			$this->db2->query($sql,__LINE__,__FILE__);

			$this->total_records = $this->db2->num_rows();

			if ($limit)
			{
				$this->db->limit_query($sql . $ordermethod,$start,__LINE__,__FILE__);
			}
			else
			{
				$this->db->query($sql . $ordermethod,__LINE__,__FILE__);
			}

			//echo '<b>TEST:</b>' . $sql;

			$i = 0;
			while ($this->db->next_record())
			{
				$cats[$i]['id']				= $this->db->f('cat_id');
				$cats[$i]['owner']			= $this->db->f('cat_owner');
				$cats[$i]['access']			= $this->db->f('cat_access');
				$cats[$i]['app_name']		= $this->db->f('cat_appname');
				$cats[$i]['main']			= $this->db->f('cat_main');
				$cats[$i]['level']			= $this->db->f('cat_level');
				$cats[$i]['parent']			= $this->db->f('cat_parent');
				$cats[$i]['name']			= $this->db->f('cat_name');
				$cats[$i]['description']	= $this->db->f('cat_description');
				$cats[$i]['data']			= $this->db->f('cat_data');
				$i++;
			}
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
			$this->db->query("select * from phpgw_categories where cat_id='$id'",__LINE__,__FILE__);

			if ($this->db->next_record())
			{
				$cats[0]['id']				= $this->db->f('cat_id');
				$cats[0]['owner']			= $this->db->f('cat_owner');
				$cats[0]['access']			= $this->db->f('cat_access');
				$cats[0]['app_name']		= $this->db->f('cat_appname');
				$cats[0]['main']			= $this->db->f('cat_main');
				$cats[0]['level']			= $this->db->f('cat_level');
				$cats[0]['parent']			= $this->db->f('cat_parent');
				$cats[0]['name']			= $this->db->f('cat_name');
				$cats[0]['description']		= $this->db->f('cat_description');
				$cats[0]['data']			= $this->db->f('cat_data');
			}
			return $cats;
		}
		/*!
		@function categories
		@abstract constructor for categories class
		@param $accountid account id
		@param $app_name app name defaults to current app
		*/
		function categories($accountid = '',$app_name = '')
		{
			global $phpgw, $phpgw_info;

			$account_id = get_account_id($accountid);

			if (! $app_name)
			{
				$app_name   = $phpgw_info['flags']['currentapp'];
			}

			$this->account_id		= $account_id;
			$this->app_name			= $app_name;
			$this->db				= $phpgw->db;
			$this->db2				= $this->db;
			$this->total_records	= $this->db2->num_rows();
			$this->grants			= $phpgw->acl->get_grants($app_name);
			$this->cats				= $this->return_array($type,$start,$limit,$query,$sort,$order,$public);
		}

		function in_array($needle,$haystack)
		{
			if (function_exists('in_array'))
			{
				return in_array($needle,$haystack);
			}
			while (list ($k,$v) = each($haystack))
			{
				if ($v == $needle)
				{
					return True;
				}
			}
			return False;
		}

		// Return into a select box, list or other formats
		/*!
		@function formated_list
		@abstract return into a select box, list or other formats
		@param $format currently only supports select (select box)
		@param $type string - subs or mains
		@param $selected - cat_id or array with cat_id values 
		@result $s array - populated with categories
		*/
		function formated_list($format,$type,$selected = '',$public = False,$site_link = 'site')
		{
			global $phpgw;
			$filter = $this->filter($type);

			if (!is_array($selected))
			{
				$selected = explode(',',$selected);
			}

			if ($format == 'select')
			{
				$cats = $this->return_array($type,$start,False,$query,$sort,$order,$public);

				for ($i=0;$i<count($cats);$i++)
				{
					$s .= '<option value="' . $cats[$i]['id'] . '"';
					if ($this->in_array($cats[$i]['id'],$selected))
					{
						$s .= ' selected';
					}
					$s .= '>' . $phpgw->strip_html($cats[$i]['name']);
					if ($cats[$i]['app_name'] == 'phpgw')
					{
						$s .= '&lt;' . lang('Global') . '&gt;';
					}
					$s .= '</option>' . "\n";
				}
				return $s;
			}

			if ($format == 'list')
			{
				$space = '&nbsp;&nbsp;';

				$cats = $this->return_array($type,$start,False,$query,$sort,$order,$public);

				$this->total_records = $this->db2->num_rows();

				$s  = '<table border="0" cellpadding="2" cellspacing="2">' . "\n";

				if ($this->total_records > 0)
				{
					for ($i=0;$i<count($cats);$i++)
					{
						$image_set = '&nbsp;';

						if ($this->in_array($cats[$i]['id'],$selected))
						{
							$image_set = '<img src="' . PHPGW_IMAGES_DIR . '/roter_pfeil.gif">';
						}

						if (($cats[$i]['level'] == 0) && !$this->in_array($cats[$i]['id'],$selected))
						{
							$image_set = '<img src="' . PHPGW_IMAGES_DIR . '/grauer_pfeil.gif">';
						}

						$space_set = str_repeat($space,$cats[$i]['level']);

						$s .= '<tr>' . "\n";
						$s .= '<td width="8">' . $image_set . '</td>' . "\n";
						$s .= '<td>' . $space_set . '<a href="' . $phpgw->link($site_link,'cat_id=' . $cats[$i]['id']) . '">' . $phpgw->strip_html($cats[$i]['name'])
									. '</a></td>' . "\n";
						$s .= '</tr>' . "\n";
					}
				}
				$s .= '</table>' . "\n";
				return $s;
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

			$cat_values[descr] = addslashes($cat_values['descr']);
			$cat_values['name'] = addslashes($cat_values['name']);

			$this->db->query("insert into phpgw_categories (cat_parent,cat_owner,cat_access,cat_appname,cat_name,"
							. "cat_description,cat_data,cat_main,cat_level) values ('" . $cat_values['parent'] . "','" . $this->account_id . "','" . $cat_values['access'] . "','"
							. $this->app_name . "','" . $cat_values['name'] . "','" . $cat_values['descr']
							. "','" . $cat_values['data'] . "','" . $cat_values['main'] . "','" . $cat_values['level'] . "')",__LINE__,__FILE__);

			if (!$cat_values['parent'] || $cat_values['parent'] == 0)
			{
				$this->db2->query("select max(cat_id) as max from phpgw_categories",__LINE__,__FILE__);
				$this->db2->next_record();
				$this->db->query("update phpgw_categories set cat_main='" . $this->db2->f('max') . "' where cat_id='"
								. $this->db2->f('max') . "'",__LINE__,__FILE__);
			}
		}

		/*!
		@function delete
		@abstract delete category
		@param $cat_id int - category id
		*/
		function delete($cat_id,$subs = False)
		{
			if ($subs)
			{
				$subdelete = " OR cat_parent='$cat_id' OR cat_main='$cat_id' "; 
			}

			$this->db->query("delete from phpgw_categories where cat_id='$cat_id' $subdelete and cat_appname='"
							. $this->app_name . "'",__LINE__,__FILE__);
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
				$cat_values['main'] = $this->id2name($cat_values['parent'],'main');
				$cat_values['level'] = $this->id2name($cat_values['parent'],'level')+1;
			}
			else
			{
				$cat_values['main'] = $cat_values['id'];
				$cat_values['level'] = 0;
			}

			$cat_values['descr'] = addslashes($cat_values['descr']);
			$cat_values['name'] = addslashes($cat_values['name']);

			$this->db->query("update phpgw_categories set cat_name='" . $cat_values['name'] . "', cat_description='"
							. $cat_values['descr'] . "', cat_data='" . $cat_values['data'] . "', cat_parent='"
							. $cat_values['parent'] . "', cat_access='" . $cat_values['access'] . "', cat_main='"
							. $cat_values['main'] . "', cat_level='" . $cat_values['level'] . "' "
							. "where cat_appname='" . $this->app_name . "' and cat_id='" . $cat_values['id'] . "'",__LINE__,__FILE__);
		}

		function name2id($cat_name)
		{
			$this->db->query("select cat_id from phpgw_categories where cat_name='"
				. "$cat_name'",__LINE__,__FILE__);
			$this->db->next_record();

			return $this->db->f('cat_id');
		}

		function id2name($cat_id, $item = 'name')
		{
			switch($item)
			{
				case 'name':	$value = 'cat_name'; break;
				case 'owner':	$value = 'cat_owner'; break;
				case 'main':	$value = 'cat_main'; break;
				case 'level':	$value = 'cat_level'; break;
			 }

			$this->db->query("select $value from phpgw_categories where cat_id='"
							. "$cat_id'",__LINE__,__FILE__);
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
			$filter = $this->filter($type);

			if ($cat_name)
			{
				$cat_exists = " cat_name='" . addslashes($cat_name) . "' "; 
			}

			if ($cat_id)
			{
				$cat_exists = " cat_parent='$cat_id' ";
			}

			if ($cat_name && $cat_id)
			{
				$cat_exists = " cat_name='" . addslashes($cat_name) . "' AND cat_id != '$cat_id' ";
			}

			$this->db->query("select count(cat_id) from phpgw_categories where $cat_exists $filter",__LINE__,__FILE__);

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
