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
				case 'subs':  $s = " and cat_parent != '0'"; break;
				case 'mains': $s = " and cat_parent = '0'"; break;
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
				case 'app':		$w = " cat_appname='" . $this->app_name . "'"; break;
				case 'subs':		$w = " cat_parent != '0'"; break;
				case 'mains':	$w = " cat_parent = '0'"; break;
				default:	return False;
			
			}
			$this->db->query("select count(*) from phpgw_categories $w");
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
		function return_array($type = 'all',$start,$limit,$query = '',$sort = '',$order = '',$public = 'False')
		{
			global $phpgw, $phpgw_info;

			$this->db2 = $this->db;
			
			if ($public == 'True') 
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
				$ordermethod = " order by cat_parent asc";
			}

			if ($query)
			{
				$sql = "select * from phpgw_categories where cat_appname='" . $this->app_name . "' $public_cats and "
		 		   . "(cat_name like '%$query%' or cat_description like '%$query%') $filter $ordermethod";
			}
			else
			{
				$sql = "select * from phpgw_categories where cat_appname='" . $this->app_name . "'" 
					. "$public_cats $filter $ordermethod";
			}

			$this->db2->query($sql,__LINE__,__FILE__);
			$this->total_records = $this->db2->num_rows();
			$this->db->query($sql. " " . $this->db->limit($start,$limit),__LINE__,__FILE__);

			$i = 0;
			while ($this->db->next_record())
			{
				$cats[$i]['id']          = $this->db->f('cat_id');
				$cats[$i]['owner']       = $this->db->f('cat_owner');
				$cats[$i]['access']      = $this->db->f('cat_access');
				$cats[$i]['app_name']    = $this->db->f('cat_appname');
				$cats[$i]['parent']      = $this->db->f('cat_parent');
				$cats[$i]['name']        = $this->db->f('cat_name');
				$cats[$i]['description'] = $this->db->f('cat_description');
				$cats[$i]['data']        = $this->db->f('cat_data');
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

			$this->db->query("select * from phpgw_categories where cat_id='$id' and "
				. "cat_appname='" . $this->app_name . "'",__LINE__,__FILE__);
	    
			while ($this->db->next_record()) {
			    $cats[0]['id']          = $this->db->f('cat_id');
        		    $cats[0]['owner']       = $this->db->f('cat_owner');
        		    $cats[0]['access']      = $this->db->f('cat_access');
        		    $cats[0]['parent']      = $this->db->f('cat_parent');
        		    $cats[0]['name']        = $this->db->f('cat_name');
        		    $cats[0]['description'] = $this->db->f('cat_description');
        		    $cats[0]['data']        = $this->db->f('cat_data');
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

			$this->account_id	= $account_id;
			$this->app_name		= $app_name;
			$this->db		= $phpgw->db;
			$this->total_records	= $this->db->num_rows();
			$this->cats		= $this->return_array($type,$start,$limit,$query,$sort,$order,$public);
		}

		// Return into a select box, list or other formats
		/*!
		@function formated_list
		@abstract return into a select box, list or other formats
		@param $format currently only supports select (select box)
		@param $type string - subs or mains
		@param $selected ?
		@result $s array - populated with categories
		*/
		function formated_list($format,$type,$selected = '',$public = 'False')
		{
			global $phpgw;
			$filter = $this->filter($type);

			if ($public == 'True')
			{
			$public_cats = " OR cat_appname='phpgw' ";
			}

			if ($format == 'select')
			{
			$cats = $this->return_array($type,$start,$limit,$query,$sort,$order,$public);

				for ($i=0;$i<count($cats);$i++)
				{
					$s .= '<option value="' . $cats[$i]['id'] . '"';
					if ($cats[$i]['id'] == $selected)
					{
						$s .= ' selected';
					}
					$s .= '>' . $phpgw->strip_html($cats[$i]['name']);
					if ($cats[$i]['app_name'] == 'phpgw') 
					{
					$s .=  '&lt;' . lang('Global') . '&gt;';
					}
					$s .=  '</option>';
				}
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
		function add($cat_name,$cat_parent,$cat_description = '', $cat_data = '',$cat_access = '')
		{
			$this->db->query('insert into phpgw_categories (cat_parent,cat_owner,cat_access,cat_appname,cat_name,'
                       . "cat_description,cat_data) values ('$cat_parent','" . $this->account_id . "','$cat_access','"
                       . $this->app_name . "','" . addslashes($cat_name) . "','" . addslashes($cat_description)
                       . "','$cat_data')",__LINE__,__FILE__);
		}
		/*!
		@function delete
		@abstract delete category
		@param $cat_id int - category id
		*/
		function delete($cat_id)
		{
			$this->db->query("delete from phpgw_categories where cat_id='$cat_id' and cat_appname='"
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
		function edit($cat_id,$cat_parent,$cat_name,$cat_description = '',$cat_data = '',$cat_access = '')
		{
			$this->db->query("update phpgw_categories set cat_name='" . addslashes($cat_name) . "', "
                        . "cat_description='" . addslashes($cat_description) . "', cat_data='"
                        . "$cat_data', cat_parent='$cat_parent', cat_access='$cat_access' where cat_appname='"
                        . $this->app_name . "' and cat_id='$cat_id'",__LINE__,__FILE__);
		}
		/*!
		@function return_name
		@abstract return category name given $cat_id
		@param $cat_id
		@result cat_name category name
		*/
		function return_name($cat_id)
		{
			$this->db->query("select cat_name from phpgw_categories where cat_id='"
                        . "$cat_id'",__LINE__,__FILE__);
			$this->db->next_record();

			if ($this->db->f('cat_name'))
			{
				return $this->db->f('cat_name');
			}
			else
			{
				return '--';
			}
		}
		/*!
		@function exists
		@abstract used for checking if a category name exists
		@param $type subs or mains
		@param $cat_name category name
		@result boolean true or false
		*/
		function exists($type,$cat_name)
		{
			$filter = $this->filter($type);

			$this->db->query("select count(*) from phpgw_categories where cat_name='"
                       . addslashes($cat_name) . "' and cat_appname='"
                       . $this->app_name . "' $filter",__LINE__,__FILE__);
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
