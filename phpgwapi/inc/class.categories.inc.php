<?php
/**
 * API - Categories
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Bettina Gille <ceb@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * Copyright (C) 2000, 2001 Joseph Engo, Bettina Gille
 * Copyright (C) 2002, 2003 Bettina Gille
 * Reworked 11/2005 by RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage categories
 * @access public
 * @version $Id$
 */

/**
 * class to manage categories in eGroupWare
 */
class categories
{
	var $account_id;
	var $app_name;
	/**
	 * @var egw_db
	 */
	var $db;
	var $total_records;
	var $grants;
	var $table = 'egw_categories';
	var $cache_id2cat_data = array();	// a little bit of caching for id2name and return_single

	/**
	 * constructor for categories class
	 *
	 * @param int/string $accountid='' account id or lid, default to current user
	 * @param string $app_name='' app name defaults to current app
	 */
	function categories($accountid='',$app_name = '')
	{
		if (!$app_name) $app_name = $GLOBALS['egw_info']['flags']['currentapp'];

		$this->account_id	= (int) get_account_id($accountid);
		$this->app_name		= $app_name;
		$this->db			= $GLOBALS['egw']->db;
		####################################################
		# resolving the group members/grants is very slow with ldap accounts backend
		# let's skip it for the addressbook, if we are using the ldap accounts backend
		####################################################
		if($app_name == 'addressbook' && $GLOBALS['egw_info']['server']['account_repository'] == 'ldap') {
			$this->grants = $GLOBALS['egw']->acl->get_grants($app_name, false);
		} else {
			$this->grants = $GLOBALS['egw']->acl->get_grants($app_name);
		}
	}

	/**
	 * return sql for predifined filters
	 *
	 * @param string $type eiterh subs, mains, appandmains, appandsubs, noglobal or noglobalapp
	 * @return string with sql to add to the where clause
	 */
	function filter($type)
	{
		switch ($type)
		{
			case 'subs':		$where = 'cat_parent != 0'; break;
			case 'mains':		$where = 'cat_parent = 0'; break;
			case 'appandmains':	$where = 'cat_appname='.$this->db->quote($this->app_name).' AND cat_parent = 0'; break;
			case 'appandsubs':	$where = 'cat_appname='.$this->db->quote($this->app_name).' AND cat_parent != 0'; break;
			case 'noglobal':	$where = 'cat_appname != '.$this->db->quote($this->app_name); break;
			case 'noglobalapp':	$where = 'cat_appname='.$this->db->quote($this->app_name).' AND cat_owner != '.(int)$this->account_id; break;
			default:			return False;
		}
		return $where;
	}

	/**
	 * returns the total number of categories for app, subs or mains
	 *
	 * @param $for one of either 'app' 'subs' or 'mains'
	 * @return integer count of categories
	 */
	function total($for = 'app')
	{
		switch($for)
		{
			case 'app':			$where = array('cat_appname' => $this->app_name); break;
			case 'appandmains':	$where = array('cat_appname' => $this->app_name,'cat_parent' => 0); break;
			case 'appandsubs':	$where = array('cat_appname' => $this->app_name,'cat_parent != 0'); break;
			case 'subs':		$where = 'cat_parent != 0'; break;
			case 'mains':		$where = 'cat_parent = 0'; break;
			default:			return False;
		}
		return $this->db->select($this->table,'COUNT(*)',$where,__LINE__,__FILE__)->fetchSingle();
	}

	/**
	 * return_all_children
	 * returns array with id's of all children from $cat_id and $cat_id itself!
	 *
	 * @param $cat_id integer cat-id to search for
	 * @return array of cat-id's
	 */
	function return_all_children($cat_id)
	{
		$all_children = (array) $cat_id;

		$children = $this->return_array('subs',0,False,'','','',True,$cat_id,-1,'id');
		if (is_array($children) && count($children))
		{
			foreach($children as $child)
			{
				$all_children = array_merge($all_children,$this->return_all_children($child['id']));
			}
		}
		//echo "<p>categories::return_all_children($cat_id)=(".implode(',',$all_children).")</p>\n";
		return $all_children;
	}

	/**
	 * return an array populated with categories
	 *
	 * @param string $type defaults to 'all'
	 * @param int $start see $limit
	 * @param boolean $limit if true limited query starting with $start
	 * @param string $query='' query-pattern
	 * @param string $sort='' sort order, either defaults to 'ASC'
	 * @param string $order='' order by
	 * @param boolean $globals includes the global egroupware categories or not
	 * @param int $parent_id=0 if > 0 return subcats or $parent_id
	 * @param int $lastmod = -1 if > 0 return only cats modified since then
	 * @param string $column='' if column-name given only that column is returned, not the full array with all cat-data
	 * @return array or cats
	 */
	function return_array($type,$start,$limit = True,$query = '',$sort = '',$order = '',$globals = False, $parent_id = '', $lastmod = -1, $column = '')
	{
		if ($globals)
		{
			$global_cats = " OR cat_appname='phpgw'";
		}

		if (($filter = $this->filter($type))) $filter = ' AND '.$filter;

		if (!$sort) $sort = 'ASC';

		if (!empty($order) && preg_match('/^[a-zA-Z_(), ]+$/',$order) && (empty($sort) || preg_match('/^(ASC|DESC|asc|desc)$/',$sort)))
		{
			$ordermethod = 'ORDER BY '.$order.' '.$sort . ', cat_access ASC';
		}
		else
		{
			$ordermethod = 'ORDER BY cat_main, cat_level, cat_name, cat_access ASC';
		}

		if ($this->account_id == '-1')
		{
			$grant_cats = ' cat_owner=-1 ';
		}
		else
		{
			if (is_array($this->grants))
			{
				$grant_cats = ' (cat_owner=' . $this->account_id . " OR cat_owner=-1 OR cat_access='public' AND cat_owner IN (" . implode(',',array_keys($this->grants)) . ')) ';
			}
			else
			{
				$grant_cats = ' (cat_owner=' . $this->account_id . ' OR cat_owner=-1) ';
			}
		}

		if ($parent_id > 0)
		{
			$parent_filter = ' AND cat_parent=' . (int)$parent_id;
		}

		if ($query)
		{
			$query = $this->db->quote('%'.$query.'%');
			$querymethod = " AND (cat_name LIKE $query OR cat_description LIKE $query) ";
		}

		if($lastmod > 0)
		{
			$querymethod .= ' AND last_mod > ' . (int)$lastmod;
		}

		$where = '(cat_appname=' . $this->db->quote($this->app_name) . ' AND ' . $grant_cats . $global_cats . ')'
			. $parent_filter . $querymethod . $filter;

		$this->total_records = $this->db->select($this->table,'COUNT(*)',$where,__LINE__,__FILE__)->fetchSingle();

		if (!$this->total_records) return false;

		foreach($this->db->select($this->table,'*',$where,__LINE__,__FILE__,$limit ? (int) $start : false,$ordermethod) as $cat)
		{
			$cat = egw_db::strip_array_keys($cat,'cat_');
			$cat['app_name'] = $cat['appname'];
			$this->cache_id2cat_data[$cat['id']] = $cat;

			if ($column)
			{
				$cats[] = array($column => isset($cat[$column]) ? $cat[$column] : $cat['id']);
			}
			else
			{
				$cats[] = $cat;
			}
		}
		return $cats;
	}

	/**
	 * return a sorted array populated with categories
	 *
	 * @param int $start see $limit
	 * @param boolean $limit if true limited query starting with $start
	 * @param string $query='' query-pattern
	 * @param string $sort='' sort order, either defaults to 'ASC'
	 * @param string $order='' order by
	 * @param boolean $globals includes the global egroupware categories or not
	 * @param int $parent_id=0 if > 0 return subcats or $parent_id
	 * @return array with cats
	 */
	function return_sorted_array($start,$limit=True,$query='',$sort='',$order='',$globals=False, $parent_id=0)
	{
		if ($globals)
		{
			$global_cats = " OR cat_appname='phpgw'";
		}

		if (!$sort) $sort = 'ASC';

		if (!empty($order) && preg_match('/^[a-zA-Z_, ]+$/',$order) && (empty($sort) || preg_match('/^(ASC|DESC|asc|desc)$/',$sort)))
		{
			$ordermethod = 'ORDER BY '.$order.' '.$sort . ', cat_access ASC';
		}
		else
		{
			$ordermethod = ' ORDER BY cat_name ASC, cat_access ASC';
		}

		if ($this->account_id == '-1')
		{
			$grant_cats = " cat_owner='-1' ";
		}
		else
		{
			if (is_array($this->grants))
			{
				$grant_cats = " (cat_owner='" . $this->account_id . "' OR cat_owner='-1' OR cat_access='public' AND cat_owner IN (" . implode(',',array_keys($this->grants)) . ")) ";
			}
			else
			{
				$grant_cats = " (cat_owner='" . $this->account_id . "' OR cat_owner='-1') ";
			}
		}
		$parent_select = ' AND cat_parent=' . (int)$parent_id;

		if ($query)
		{
			$query = $this->db->quote('%'.$query.'%');
			$querymethod = " AND (cat_name LIKE $query OR cat_description LIKE $query) ";
		}

		$where = '(cat_appname=' . $this->db->quote($this->app_name) . ' AND ' . $grant_cats . $global_cats . ')' . $querymethod;

		$parents = $cats = array();
		foreach($this->db->select($this->table,'*',$where . $parent_select,__LINE__,__FILE__,false,$ordermethod) as $cat)
		{
			$cat = egw_db::strip_array_keys($cat,'cat_');
			$cat['app_name'] = $cat['appname'];
			$this->cache_id2cat_data[$cat['id']] = $cat;

			$cats[] = $cat;
			$parents[] = $cat['id'];
		}
		while (count($parents))
		{
			$sub_select = ' AND cat_parent IN (' . implode(',',$parents) . ')';
			$parents = $children = array();
			foreach($this->db->select($this->table,'*',$where . $sub_select,__LINE__,__FILE__,false, $ordermethod) as $cat)
			{
				$cat = egw_db::strip_array_keys($cat,'cat_');
				$cat['app_name'] = $cat['appname'];
				$this->cache_id2cat_data[$cat['id']] = $cat;

				$parents[] = $cat['id'];
				$children[$cat['parent']][] = $cat;
			}
			// sort the cats into the mains
			if (count($children))
			{
				$cats2 = $cats;
				$cats = array();
				foreach($cats2 as $cat)
				{
					$cats[] = $cat;
					if (isset($children[$cat['id']]))
					{
						foreach($children[$cat['id']] as $child)
						{
							$cats[] = $child;
						}
					}
				}
			}
		}
		$this->total_records = count($cats);

		if ($limit)
		{
			return array_slice($cats,(int)$start,$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']);
		}
		return $cats;
	}

	/**
	 * read a single category
	 *
	 * We use a shared cache together with id2name
	 *
	 * @param int $id id of category
	 * @return array/boolean array with one array of cat-data or false if cat not found
	 */
	function return_single($id = '')
	{
		if (!isset($this->cache_id2cat_data[$id]))
		{
			if (($cat = $this->db->select($this->table,'*',array('cat_id' => $id),__LINE__,__FILE__)->fetch()))
			{
				$cat = egw_db::strip_array_keys($cat,'cat_');
				$cat['app_name'] = $cat['appname'];
			}
			$this->cache_id2cat_data[$id] = $cat;
		}
		return $this->cache_id2cat_data[$id] ? array($this->cache_id2cat_data[$id]) : false;
	}

	/**
	 * return into a select box, list or other formats
	 *
	 * @param string/array $format string 'select' or 'list', or array with all params
	 * @param string $type='' subs or mains
	 * @param int/array $selected - cat_id or array with cat_id values
	 * @param boolean $globals True or False, includes the global egroupware categories or not
	 * @return string populated with categories
	 */
	function formatted_list($format,$type='',$selected = '',$globals = False,$site_link = 'site')
	{
		if(is_array($format))
		{
			$type = ($format['type']?$format['type']:'all');
			$selected = (isset($format['selected'])?$format['selected']:'');
			$self = (isset($format['self'])?$format['self']:'');
			$globals = (isset($format['globals'])?$format['globals']:True);
			$site_link = (isset($format['site_link'])?$format['site_link']:'site');
			$format = $format['format'] ? $format['format'] : 'select';
		}

		if (!is_array($selected))
		{
			$selected = explode(',',$selected);
		}

		if ($type != 'all')
		{
			$cats = $this->return_array($type,0,False,'','','',$globals);
		}
		else
		{
			$cats = $this->return_sorted_array(0,False,'','','',$globals);
		}

		if (!$cats) return '';

		if($self)
		{
			foreach($cats as $key => $cat)
			{
				if ($cat['id'] == $self)
				{
					unset($cats[$key]);
				}
			}
		}

		switch ($format)
		{
			case 'select':
				foreach($cats as $cat)
				{
					$s .= '<option value="' . $cat['id'] . '"';
					if (in_array($cat['id'],$selected))
					{
						$s .= ' selected="selected"';
					}
					$s .= '>'.str_repeat('&nbsp;',$cat['level']);
					$s .= $GLOBALS['egw']->strip_html($cat['name']);
					if ($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
					{
						$s .= ' &#9830;';
					}
					elseif ($cat['owner'] != $this->account_id)
					{
						if ($GLOBALS['egw_info']['user']['preferences']['common']['show_category_owner'])
						{
							$s .= ' &lt;' . $GLOBALS['egw']->accounts->id2name($cat['owner'], 'account_fullname') . '&gt;';
						}
					}
					elseif ($cat['access'] == 'private')
					{
						$s .= ' &#9829;';
					}
					$s .= '</option>' . "\n";
				}
				break;

			case 'list':
				$space = '&nbsp;&nbsp;';

				$s  = '<table border="0" cellpadding="2" cellspacing="2">' . "\n";

				foreach($cats as $cat)
				{
					$image_set = '&nbsp;';

					if (in_array($cat['id'],$selected))
					{
						$image_set = '<img src="' . EGW_IMAGES_DIR . '/roter_pfeil.gif">';
					}
					if (($cat['level'] == 0) && !in_array($cat['id'],$selected))
					{
						$image_set = '<img src="' . EGW_IMAGES_DIR . '/grauer_pfeil.gif">';
					}
					$space_set = str_repeat($space,$cat['level']);

					$s .= '<tr>' . "\n";
					$s .= '<td width="8">' . $image_set . '</td>' . "\n";
					$s .= '<td>' . $space_set . '<a href="' . $GLOBALS['egw']->link($site_link,'cat_id=' . $cat['id']) . '">'
						. $GLOBALS['egw']->strip_html($cat['name'])
						. '</a></td>' . "\n"
						. '</tr>' . "\n";
				}
				$s .= '</table>' . "\n";
				break;
		}
		return $s;
	}

	/**
	 * add a category
	 *
	 * @param array $value cat-data
	 * @return int new cat-id
	 */
	function add($values)
	{
		if ((int)$values['parent'] > 0)
		{
			$values['level'] = $this->id2name($values['parent'],'level')+1;
			$values['main'] = $this->id2name($values['parent'],'main');
		}

		$values = array_merge(
			array(
				'app_name'	=> $this->app_name,
				'access'	=> 'public',
			),
			$values);

		$this->db->insert($this->table,array(
			'cat_parent'  => $values['parent'],
			'cat_owner'   => $this->account_id,
			'cat_access'  => $values['access'],
			'cat_appname' => $values['app_name'],
			'cat_name'    => $values['name'],
			'cat_description' => $values['descr'],
			'cat_data'    => $values['data'],
			'cat_main'    => $values['main'],
			'cat_level'   => $values['level'],
			'last_mod'    => time(),
		),(int)$values['id'] > 0 ? array('cat_id' =>  $values['id']) : array(),__LINE__,__FILE__);

		$id = (int)$values['id'] > 0 ? (int)$values['id'] : $this->db->get_last_insert_id($this->table,'cat_id');

		if (!(int)$values['parent'])
		{
			$this->db->update($this->table,array('cat_main' => $id),array('cat_id' => $id),__LINE__,__FILE__);
		}
		return $id;
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * If the access of a category is set to private, one needs a private grant for the application
	 *
	 * @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	 * @param mixed $category category as array or the category_id
	 * @return boolean true permission granted, false for permission denied, null for category does not exist
	 */
	public function check_perms($needed,$category)
	{
		if (!is_array($category))
		{
			if (!isset($this->cache_id2cat_data[$category]))
			{
				if (($cat = $this->db->select($this->table,'*',array('cat_id' => $category),__LINE__,__FILE__)->fetch()))
				{
					$cat = egw_db::strip_array_keys($cat,'cat_');
					$cat['app_name'] = $cat['appname'];
					$this->cache_id2cat_data[$category] = $cat;
				}
				else return null;
			}
			$category = $this->cache_id2cat_data[$category];
		}

		// The user for the global cats has id -1, this one has full access to all global cats
		if ($this->account_id == -1 && ($category['appname'] == 'phpgw'
				|| $category['appname'] == $this->app_name && $category['owner'] == -1))
		{
			return true;
		}

		// Read access to global categories
		if ($needed == EGW_ACL_READ && ($category['appname'] == 'phpgw'
				|| $category['appname'] == $this->app_name && $category['owner'] == -1))
		{
			return true;
		}

		// Full access to own categories
		if ($category['appname'] == $this->app_name && $category['owner'] == $this->account_id)
		{
			return true;
		}

		// Check for ACL granted access, the -1 user must not get access by ACL to keep old behaviour
		return ($this->account_id != -1 && $category['appname'] == $this->app_name && ($this->grants[$category['owner']] & $needed) &&
					($category['access'] == 'public' ||  ($this->grants[$category['owner']] & EGW_ACL_PRIVATE)));
	}


	/**
	 * delete a category
	 *
	 * @param int $cat_id category id
	 * @param boolean $drop_subs=false if true delete sub-cats too
	 * @param boolean $modify_subs=false if true make the subs owned by the parent of $cat_id
	 */
	function delete($cat_id, $drop_subs = False, $modify_subs = False)
	{
		if ($modify_subs)
		{
			$new_parent = $this->id2name($cat_id,'parent');
			$new_main = 0;

			foreach ((array) $this->return_sorted_array('',False,'','','',False, $cat_id) as $cat)
			{
				if ($cat['level'] == 1)
				{
					$this->db->update($this->table,array(
						'cat_level'  => 0,
						'cat_parent' => 0,
						'cat_main'   => $cat['id'],
					),array(
						'cat_id' => $cat['id'],
						'cat_appname' => $this->app_name,
					),__LINE__,__FILE__);

					$new_main = $cat['id'];
				}
				else
				{
					$update = array('cat_level' => $cat['level']-1);

					if ($new_main) $update['cat_main'] = $new_main;

					if ($cat['parent'] == $cat_id) $update['cat_parent'] = $new_parent;

					$this->db->update($this->table,$update,array(
						'cat_id' => $cat['id'],
						'cat_appname' => $this->app_name,
					),__LINE__,__FILE__);
				}
			}
		}
		if ($drop_subs)
		{
			$where = array('cat_id='.(int)$cat_id.' OR cat_parent='.(int)$cat_id.' OR cat_main='.(int)$cat_id);
		}
		else
		{
			$where['cat_id'] = $cat_id;
		}
		$where['cat_appname'] = $this->app_name;

		$this->db->delete($this->table,$where,__LINE__,__FILE__);
	}

	/**
	 * edit / update a category
	 *
	 * @param array $values array with cat-data (it need to be complete, as everything get's written)
	 * @return int cat-id
	 */
	function edit($values)
	{
		if (isset($values['old_parent']) && (int)$values['old_parent'] != (int)$values['parent'])
		{
			$this->delete($values['id'],False,True);

			return $this->add($values);
		}
		else
		{
			if ($values['parent'] > 0)
			{
				$values['main']  = $this->id2name($values['parent'],'main');
				$values['level'] = $this->id2name($values['parent'],'level') + 1;
			}
			else
			{
				$values['main']  = $values['id'];
				$values['level'] = 0;
			}
		}
		$this->db->update($this->table,array(
			'cat_name' => $values['name'],
			'cat_description' => $values['descr'],
			'cat_data' => $values['data'],
			'cat_parent' => $values['parent'],
			'cat_access' => $values['access'],
			'cat_main' => $values['main'],
			'cat_level' => $values['level'],
			'last_mod' => time(),
		),array(
			'cat_id' => $values['id'],
			'cat_appname' => $this->app_name,
		),__LINE__,__FILE__);

		return (int)$values['id'];
	}

	/**
	 * return category id for a given name
	 *
	 * Cat's with the given name are returned in this order:
	 * - personal cats first
	 * - then application global categories
	 * - global categories
	 * - cat's of other user
	 *
	 * @param string $cat_name cat-name
	 * @param boolean $strip=false if true, strips 'X-' from category names added by some SyncML clients
	 * @return int cat-id or 0 if not found
	 */
	function name2id($cat_name,$strip=false)
	{
		static $cache = array();	// a litle bit of caching

		if (isset($cache[$cat_name])) return $cache[$cat_name];

		if ($strip === true)
		{
			$strip = 'X-';
		}

		$cats = array($cat_name);
		if (isset($strip) && strncmp($strip, $cat_name, strlen($strip)) == 0)
		{
			$stripped_cat_name = substr($cat_name, strlen($strip));
			if (isset($cache[$stripped_cat_name]))
			{
				$cache[$cat_name] = $cache[$stripped_cat_name];
				return $cache[$stripped_cat_name];
			}
			$cats[] = $stripped_cat_name;
		}


		$cat = $this->db->select($this->table,array('cat_name','cat_id'),array(
			'cat_name' => $cats,
			'cat_appname' => array($this->app_name, 'phpgw'),
		),__LINE__,__FILE__,0,
		'ORDER BY cat_name<>'.$this->db->quote($cat_name).',(CASE cat_owner WHEN '.(int)$this->account_id." THEN 1 WHEN -1 THEN 2 ELSE 3 END),cat_appname='phpgw'",
		false,1)->fetch();

		if (!$cat) return 0;	// cat not found, dont cache it, as it might be created in this request

		return $cache[$cat['cat_name']] = (int) $cat['cat_id'];
	}

	/**
	 * return category information for a given id
	 *
	 * We use a shared cache together with return_single
	 *
	 * @param int $cat_id=0 cat-id
	 * @param string $item='name requested information, 'path' = / delimited path of category names (incl. parents)
	 * @return string information or '--' if not found or !$cat_id
	 */
	function id2name($cat_id=0, $item='name')
	{
		if(!$cat_id) return '--';

		if (!isset($this->cache_id2cat_data[$cat_id])) $this->return_single($cat_id);

		if (!$item) $item = 'parent';

		$cat = $this->cache_id2cat_data[$cat_id];
		if ($item == 'path')
		{
			if ($cat['parent'])
			{
				return $this->id2name($cat['parent'],'path').' / '.$cat['name'];
			}
			$item = 'name';
		}
		if ($cat[$item])
		{
			return $cat[$item];
		}
		elseif ($item == 'name')
		{
			return '--';
		}
		return null;
	}

	/**
	 * return category name for a given id
	 *
	 * @deprecated This is only a temp wrapper, use id2name() to keep things matching across the board. (jengo)
	 * @param int $cat_id
	 * @return string cat_name category name
	 */
	function return_name($cat_id)
	{
		return $this->id2name($cat_id);
	}

	/**
	 * check if a category id and/or name exists, if id AND name are given the check is for a category with same name and different id (!)
	 *
	 * @param string  $type subs or mains
	 * @param string  $cat_name='' category name
	 * @param int     $cat_id=0 category id
	 * @param int     $parent=0 category id of parent
	 * @param boolean $private=false limit to private categories
	 * @return int/boolean cat_id or false if cat not exists
	 */
	function exists($type,$cat_name = '',$cat_id = 0,$parent = 0, $private = false)
	{
		static $cache = array();	// a litle bit of caching

		if (isset($cache[$type][$cat_name][$cat_id][$private])) return $cache[$type][$cat_name][$cat_id][$private];

		$where = array($this->filter($type));

		if ($cat_name)
		{
			$where['cat_name'] = $cat_name;

			if ($cat_id) $where[] = 'cat_id != '.(int)$cat_id;
		}
		elseif ($cat_id)
		{
			$where['cat_id'] = $cat_id;
		}
		if ($parent){
			$where['cat_parent'] = $parent;
		}
		$grant_cats = "(";
		if ($private) {
			$grant_cats .= "cat_owner='" . $this->account_id . "' AND cat_access='private'";
		} else {
			$grant_cats .= "cat_owner='" . $this->account_id
				. "' OR cat_owner='-1' OR cat_access='public'";
			if (is_array($this->grants)) {
				$grant_cats .= " AND cat_owner IN (" . implode(',',array_keys($this->grants)) . ")";
			}
		}
		$where[] = $grant_cats . ")";
		return $cache[$type][$cat_name][$cat_id][$private] = $this->db->select($this->table,'cat_id',$where,__LINE__,__FILE__)->fetchSingle();
	}

	/**
	 * Change the owner of all cats owned by $owner to $to OR deletes them if !$to
	 *
	 * @param int $owner owner or the cats to delete or change
	 * @param int $to=0 new owner or 0 to delete the cats
	 * @param string $app='' if given only cats matching $app are modifed/deleted
	 */
	function change_owner($owner,$to=0,$app='')
	{
		$where = array('cat_owner' => $owner);

		if ($app) $where['cat_appname'] = $app;

		if ((int)$to)
		{
			$this->db->update($this->table,array('cat_owner' => $to),$where,__LINE__,__FILE__);
		}
		else
		{
			$this->db->delete($this->table,$where,__LINE__,__FILE__);
		}
	}
}
