<?php
	/**************************************************************************\
	* eGroupWare - resources                                                   *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	* --------------------------------------------                             *
	\**************************************************************************/

	/* $Id$ */
	
	class bo_acl
	{
		/*! @var $permissions Holds alls permissions for resources of user */
		var $permissions;
		
		var $acl;
		var $start = 0;
		var $query = '';
		var $sort  = '';
		var $total = 0;
		var $accounts;
		var $cats;

		var $debug;
		var $use_session = False;

		function bo_acl($session=False)
		{
			define('PHPGW_ACL_CAT_ADMIN',64);
			define('PHPGW_ACL_DIRECT_BOOKING',128);
//			define('PHPGW_ACL_CUSTOM_3',256);

			$this->so = CreateObject('resources.so_acl');
			$this->permissions = $this->so->get_permissions($GLOBALS['phpgw_info']['user']['account_id'],true);
			$this->egw_cats = createobject('phpgwapi.categories');
			$this->accounts = $GLOBALS['phpgw']->accounts->get_list();
			$this->debug = False;
			
			//all this is only needed when called from uiacl.
			if($session)
			{
				$this->read_sessiondata();
				$this->use_session = True;
				foreach(array('start','query','sort','order') as $var)
				{
					if (isset($_POST[$var]))
					{
						$this->$var = $_POST[$var];
					}
					elseif (isset($_GET[$var]))
					{
						$this->$var = $_GET[$var];
					}
				}
				$this->save_sessiondata();
				$this->cats = $this->egw_cats->return_array('all',$this->start,True,$this->query,$this->sort,'cat_name',True);
			}
		}

		/*!
			@function get_cats
			@abstract get list of cats where current user has given rights
			@author Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
			@param int $perm_type one of PHPGW_ACL_READ, PHPGW_ACL_ADD, PHPGW_ACL_EDIT, PHPGW_ACL_DELETE, PHPGW_ACL_DIRECT_BOOKING
			@return array cat_name => cat_id
			TODO mark subcats and so on!
		*/
		function get_cats($perm_type)
		{
			$cats = $this->egw_cats->return_sorted_array(0,False,'','','',!$type);
			while (list(,$cat) = @each($cats))
			{
				if($this->is_permitted($cat['id'],$perm_type))
				{
					for ($j=0,$s=''; $j < $cat['level']; $j++)
					{
						$s .= '&nbsp;';
					}
					$s .= $GLOBALS['phpgw']->strip_html($cat['name']);
					if ($cat['app_name'] == 'phpgw')
					{
						$s .= '&nbsp;&lt;' . lang('Global') . '&gt;';
					}
					if ($cat['owner'] == '-1')
					{
						$s .= '&nbsp;&lt;' . lang('Global') . '&nbsp;' . lang($cat['app_name']) . '&gt;';
					}
					$perm_cats[$cat['id']] = $s;
				}
			}
			return $perm_cats;
		}
		
		/*!
			@function get_cat_admin
			@abstract gets userid of admin for given category
			@author Cornelius Weiﬂ <egw@von-und-zu-weiss.de>
			@param int $cat_id
			@return int userid of cat admin
		*/
		function get_cat_admin($cat_id)
		{
			return array_search (PHPGW_ACL_CAT_ADMIN, $this->get_rights($cat_id));
		}
		
		/*!
			@function is_permitted
			@abstract cheks one of the following rights for current user:
			@abstract PHPGW_ACL_READ, PHPGW_ACL_ADD, PHPGW_ACL_EDIT, PHPGW_ACL_DELETE, PHPGW_ACL_DIRECT_BOOKING
			@param int $cat_id
			@param int $right
			@return bool user is permitted or not for right
		*/
		function is_permitted($cat_id,$right)
		{
			return $this->permissions['L'.$cat_id] & $right;
		}
		
		/*!
			@function get_rights
			@abstract gets all rights from all user for given cat
			@param int $cat_id
			@return array userid => right
		*/
		function get_rights($cat_id)
		{
			return $this->so->get_rights('L'.$cat_id);
		}


// privat functions from here on -------------------------------------------------------------------------
		function save_sessiondata()
		{
			$data = array(
				'start' => $this->start,
				'query' => $this->query,
				'sort'  => $this->sort,
				'order' => $this->order,
				'limit' => $this->limit,
			);
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }
			$GLOBALS['phpgw']->session->appsession('session_data','resources_acl',$data);
		}

		function read_sessiondata()
		{
			$data = $GLOBALS['phpgw']->session->appsession('session_data','resources_acl');
			if($this->debug) { echo '<br>Read:'; _debug_array($data); }

			$this->start  = $data['start'];
			$this->query  = $data['query'];
			$this->sort   = $data['sort'];
			$this->order  = $data['order'];
			$this->limit = $data['limit'];
		}

		function set_rights($cat_id,$read,$write,$book,$admin)
		{
			$readcat = $read ? $read : array();
			$writecat = $write ? $write : array();
			$bookcat = $book ? $book : array();
			$admincat = $admin ? $admin : array();

			$this->so->remove_location('L' . $cat_id);
			reset($this->accounts);
			while (list($null,$account) = each($this->accounts))
			{
				$account_id = $account['account_id'];
				//write implies read
				$rights = in_array($account_id,$writecat) ?
					(PHPGW_ACL_READ | PHPGW_ACL_ADD | PHPGW_ACL_EDIT | PHPGW_ACL_DELETE) :
					(in_array($account_id,$readcat) ? PHPGW_ACL_READ : False);
				$rights = in_array($account_id,$bookcat) ? ($rights | PHPGW_ACL_DIRECT_BOOKING) : $rights;
				$rights = in_array($account_id,$admincat) ? ($rights | PHPGW_ACL_CAT_ADMIN) : $rights;
				if ($rights)
				{
					$GLOBALS['phpgw']->acl->add_repository('resources','L'.$cat_id,$account_id,$rights);
				}
			}
		}
	}
