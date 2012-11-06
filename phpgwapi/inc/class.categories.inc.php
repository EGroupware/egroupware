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
 * Reworked 12/2008 by RalfBecker-AT-outdoor-training.de to operate only on a catergory cache, no longer the db direct
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage categories
 * @access public
 * @version $Id$
 */

/**
 * class to manage categories in eGroupWare
 *
 * Categories are read now once from the database into a static cache variable (by the static init_cache method).
 * The egw object fills that cache ones per session, stores it in a private var, from which it restores it for each
 * request of that session.
 *
 * $cat['data'] array:
 * ------------------
 * $cat['data'] array is stored serialized in the database to allow applications to simply add all
 * sorts of values there (without the hassel of a DB schema change).
 * Static methods categories::read($cat_id) and categories::id2name now returns data already unserialized
 * and add() or edit() methods automatically serialize $cat['data'], if it's not yet serialized.
 * return*() methods still return $cat['data'] serialized by default, but have a parameter to return
 * it as array(). That default might change in future too, so better check if it's
 * not already an array, before unserialize!
 *
 * @ToDo The cache now contains a backlink from the parent to it's children. Use that link to simplyfy return_all_children
 * 	and other functions needing to know if a cat has children. Be aware a user might not see all children, as they can
 * 	belong to other users.
 */
class categories
{
	/**
	 * Account id this class is instanciated for (self::GLOBAL_ACCOUNT for global cats)
	 *
	 * @var int
	 */
	public $account_id;
	/**
	 * Application this class is instancated for (self::GLOBAL_APPNAME for application global cats)
	 *
	 * @var string
	 */
	public $app_name;
	/**
	 * @var egw_db
	 */
	private $db;
	/**
	 * Total number of records of return_(sorted_)array (returning only a limited number of rows)
	 *
	 * @var int
	 */
	public $total_records;
	/**
	 * Grants from other users for account_id and app_name (init by return array)
	 *
	 * @var array
	 */
	public $grants;
	/**
	 * Name of the categories table
	 */
	const TABLE = 'egw_categories';
	/**
	 * @deprecated use categories::TABLE
	 * @var string
	 */
	public $table = self::TABLE;
	/**
	 * Cache holding all categories, set via init_cache() method
	 *
	 * @var array cat_id => array of data
	 */
	private static $cache;
	const CACHE_APP = 'phpgwapi';
	const CACHE_NAME = 'cat_cache';

	/**
	 * Appname for global categories
	 */
	const GLOBAL_APPNAME = 'phpgw';

	/**
	 * account_id for global categories
	 */
	const GLOBAL_ACCOUNT = 0;

	/**
	 * Owners for global accounts
	 *
	 * Usually the users group memberships and self::GLOBAL_ACCOUNT
	 *
	 * @var array
	 */
	private $global_owners = array(self::GLOBAL_ACCOUNT);

	/**
	 * constructor for categories class
	 *
	 * @param int|string $accountid='' account id or lid, default to current user
	 * @param string $app_name='' app name defaults to current app
	 */
	function __construct($accountid='',$app_name = '')
	{
		if (!$app_name) $app_name = $GLOBALS['egw_info']['flags']['currentapp'];

		if ($accountid === self::GLOBAL_ACCOUNT ||
			$accountid < 0 && $GLOBALS['egw']->accounts->exists($accountid) == 2)
		{
			$this->account_id = (int) $accountid;
		}
		else
		{
			$this->account_id	= (int) get_account_id($accountid);
			$this->global_owners = $this->account_id ? $GLOBALS['egw']->accounts->memberships($this->account_id, true) : array();
			$this->global_owners[] = self::GLOBAL_ACCOUNT;
		}
		$this->app_name		= $app_name;
		$this->db			= $GLOBALS['egw']->db;

		if (is_null(self::$cache))	// should not be necessary, as cache is load and restored by egw object
		{
			self::init_cache();
		}
	}

	/**
	 * php4 constructor
	 *
	 * @deprecated
	 */
	function categories($accountid='',$app_name='')
	{
		self::__construct($accountid,$app_name);
	}

	/**
	 * returns array with id's of all children from $cat_id and $cat_id itself!
	 *
	 * @param int|array $cat_id (array of) integer cat-id to search for
	 * @return array of cat-id's
	 */
	function return_all_children($cat_id)
	{
		$all_children = (array) $cat_id;

		$children = $this->return_array('subs',0,False,'','','',True,$cat_id,-1,'id');
		if (is_array($children) && count($children))
		{
			$all_children = array_merge($all_children,$this->return_all_children($children));
		}
		//echo "<p>categories::return_all_children($cat_id)=(".implode(',',$all_children).")</p>\n";
		return $all_children;
	}

	/**
	 * return an array populated with categories
	 *
	 * @param string $type='all' 'subs','mains','appandmains','appandsubs','noglobal','noglobalapp', defaults to 'all'
	 * @param int $start=0 see $limit
	 * @param boolean|int $limit if true limited query to maxmatches rows (starting with $start)
	 * @param string $query='' query-pattern
	 * @param string $sort='ASC' sort order, defaults to 'ASC'
	 * @param string $order='' order by, default cat_main, cat_level, cat_name ASC
	 * @param boolean|string $globals includes the global egroupware categories or not,
	 * 	'all_no_acl' to return global and all non-private user categories independent of ACL
	 * @param array|int $parent_id=null return only subcats of $parent_id(s)
	 * @param int $lastmod = -1 if > 0 return only cats modified since then
	 * @param string $column='' if column-name given only that column is returned, not the full array with all cat-data
	 * @param array $filter=null array with column-name (without cat_-prefix) => value pairs (! negates the value)
	 * @param boolean $unserialize_data=false return $cat['data'] as array (not serialized array)
	 * @return array of cat-arrays or $column values
	 */
	function return_array($type='all', $start=0, $limit=true, $query='', $sort='ASC',$order='',$globals=false, $parent_id=null, $lastmod=-1, $column='', $filter=null,$unserialize_data=false)
	{
		//error_log(__METHOD__."($type,$start,$limit,$query,$sort,$order,globals=$globals,parent=".array2string($parent_id).",$lastmod,$column,filter=".array2string($filter).",$unserialize_data) account_id=$this->account_id, appname=$this->app_name: ".function_backtrace());
		$cats = array();
		foreach(self::$cache as $cat_id => $cat)
		{
			if ($filter) foreach($filter as $col => $val)
			{
				if (!is_array($val) && $val[0] === '!')
				{
					// also match against trimmed database entry on name and description fields
					if (($col == 'name' || $col == 'description') && is_string($cat[$col]))
					{
						if ($cat[$col] == substr($val,1) || trim($cat[$col]) == substr($val,1)) continue 2;
					}
					else
					{
						if ($cat[$col] == substr($val,1)) continue 2;
					}
				}
				elseif (is_array($val))
				{
					// also match against trimmed database entry on name and description fields
					if (($col == 'name' || $col == 'description') && is_string($cat[$col]))
					{
						if (!in_array($cat[$col],$val) && !in_array(trim($cat[$col]),$val)) continue 2;
					}
					else
					{
						if (!in_array($cat[$col],$val)) continue 2;
					}
				}
				else
				{
					// also match against trimmed database entry on name and description fields
					if (($col == 'name' || $col == 'description') && is_string($cat[$col]))
					{
						if ($cat[$col] != $val && trim($cat[$col]) != $val) continue 2;
					}
					else
					{
						if ($cat[$col] != $val) continue 2;
					}
				}
			}
			// check if certain parent required
			if ($parent_id && !in_array($cat['parent'],(array)$parent_id)) continue;

			// return global categories just if $globals is set
			if (!$globals && $cat['appname'] == self::GLOBAL_APPNAME)
			{
				continue;
			}

			// check for read permission
			if(!$this->check_perms(EGW_ACL_READ, $cat, $globals === 'all_no_acl'))
			{
				continue;
			}

			// check if we have the correct type
			switch ($type)
			{
				case 'subs':
					if (!$cat['parent']) continue 2;	// 2 for switch AND foreach!
					break;
				case 'mains':
					if ($cat['parent']) continue 2;
					break;
				case 'appandmains':
					if ($cat['appname'] != $this->app_name || $cat['parent']) continue 2;
					break;
				case 'appandsubs':
					if ($cat['appname'] != $this->app_name || !$cat['parent']) continue 2;
					break;
				case 'noglobal':
					if ($cat['appname'] == $this->app_name) continue 2;
					break;
				case 'noglobalapp':
					if ($cat['appname'] != $this->app_name || $cat['owner'] == (int)$this->account_id) continue 2;
					break;
			}

			// check name and description for $query
			if ($query && stristr($cat['name'],$query) === false && stristr($cat['description'],$query) === false) continue;

			// check if last modified since
			if ($lastmod > 0 && $cat['last_mod'] <= $lastmod) continue;

			if ($unserialize_data) $cat['data'] = $cat['data'] ? unserialize($cat['data']) : array();

			$cats[] = $cat;
		}
		if (!($this->total_records = count($cats)))
		{
			//error_log(__METHOD__."($type,$start,$limit,$query,$sort,$order,$globals,parent=$parent_id,$lastmod,$column) account_id=$this->account_id, appname=$this->app_name = FALSE");
			return false;
		}
		if (!$sort) $sort = 'ASC';
		// order the entries if necessary (cache is already ordered in or default order: cat_main, cat_level, cat_name ASC)
		if ($this->total_records > 1 && !empty($order) &&
			preg_match('/^[a-zA-Z_(), ]+$/',$order) && preg_match('/^(ASC|DESC|asc|desc)$/',$sort))
		{
			if (strstr($order,'cat_data') !== false) $order = 'cat_data';	// sitemgr orders by round(cat_data)!
			if (substr($order,0,4) == 'cat_') $order = substr($order,4);
			$sign = strtoupper($sort) == 'DESC' ? '-' : '';
			usort($cats,create_function('$a,$b',$func=(in_array($order,array('name','description','appname','app_name')) ?
				"return ${sign}strcasecmp(\$a['$order'],\$b['$order']);" : "return $sign(int)\$a['$order'] - $sign(int)\$b['$order'];")));
		}
		// limit the number of returned rows
		if ($limit)
		{
			if (!is_int($limit)) $limit = (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			$cats = array_slice($cats,(int)$start,$limit);
		}
		// return only a certain column (why not return is as value?)
		if ($column)
		{
			foreach($cats as $k => $cat)
			{
				$cats[$k] = $cat[$column];
			}
		}
		//error_log(__METHOD__."($type,$start,$limit,$query,$sort,$order,$globals,parent=".array2string($parent_id).",$lastmod,$column,filter=".array2string($filter).",$unserialize_data) account_id=$this->account_id, appname=$this->app_name = ".array2string($cats));

		reset($cats);	// some old code (eg. sitemgr) relies on the array-pointer!
		return $cats;
	}

	/**
	 * return a sorted array populated with categories (main sorting criteria is hierachy!)
	 *
	 * @param int $start=0 see $limit
	 * @param boolean|int $limit if true limited query to maxmatches rows (starting with $start)
	 * @param string $query='' query-pattern
	 * @param string $sort='ASC' sort order, either defaults to 'ASC'
	 * @param string $order='cat_name' order by
	 * @param boolean|string $globals includes the global egroupware categories or not,
	 * 	'all_no_acl' to return global and all non-private user categories independent of ACL
	 * @param array|int $parent_id=0 return only subcats of $parent_id(s)
	 * @param boolean $unserialize_data=false return $cat['data'] as array (not serialized array)
	 * @return array with cats
	 */
	function return_sorted_array($start=0,$limit=True,$query='',$sort='ASC',$order='cat_name',$globals=False, $parent_id=0,$unserialize_data=false,$filter=null)
	{
		if (!$sort)  $sort = 'ASC';
		if (!$order) $order = 'cat_name';

		//error_log(__METHOD__."($start,$limit,$query,$sort,$order,globals=$globals,parent=$parent_id,$unserialize_data) account_id=$this->account_id, appname=$this->app_name: ".function_backtrace());

		$parents = $cats = array();

		// Cast parent_id to array, but only if there is one
		if($parent_id !== false && $parent_id !== null) $parent_id = (array)$parent_id;
		if (!($cats = $this->return_array('all',0,false,$query,$sort,$order,$globals,$parent_id,-1,'',$filter,$unserialize_data)))
		{
			$cats = array();
		}
		foreach($cats as $cat)
		{
			$parents[] = $cat['id'];
		}
		
		if($parent_id || !$cats) // Avoid wiping search results
		{
			// Go find the children
			while (count($parents))
			{
				if (!($subs = $this->return_array('all',0,false,$query,$sort,$order,$globals,$parents,-1,'',$filter,$unserialize_data)))
				{
					break;
				}
				$parents = $children = array();
				foreach($subs as $cat)
				{
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
		}
		$this->total_records = count($cats);

		// limit the number of returned rows
		if ($limit)
		{
			if (!is_int($limit)) $limit = (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			$cats = array_slice($cats,(int)$start,$limit);
		}
		reset($cats);	// some old code (eg. sitemgr) relies on the array-pointer!
		return $cats;
	}

	/**
	 * Read a category
	 *
	 * We use a shared cache together with id2name
	 *
	 * Data array get automatically unserialized, if it was serialized!
	 *
	 * @param int $id id of category
	 * @return array|boolean array with cat-data or false if cat not found
	 */
	static function read($id)
	{
		if (is_null(self::$cache)) self::init_cache();

		if (!isset(self::$cache[$id])) return false;

		$cat = self::$cache[$id];
		$cat['data'] = $cat['data'] ? ((($arr=unserialize($cat['data'])) !== false || $cat['data'] === 'b:0;') ?
			$arr : $cat['data']) : array();

		return $cat;
	}

	/**
	 * Add a category
	 *
	 * Owner and appname are set from the values used to instanciate the class!
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
		else
		{
			$values['level'] = 0;
		}
		$this->db->insert(self::TABLE,$cat=array(
			'cat_parent'  => $values['parent'],
			'cat_owner' => isset($values['owner']) ? $values['owner'] : $this->account_id,
			'cat_access'  => isset($values['access']) ? $values['access'] : 'public',
			'cat_appname' => $this->app_name,
			'cat_name'    => $values['name'],
			'cat_description' => isset($values['description']) ? $values['description'] : $values['descr'],	// support old name different from returned one
			'cat_data'    => is_array($values['data']) ? serialize($values['data']) : $values['data'],
			'cat_main'    => $values['main'],
			'cat_level'   => $values['level'],
			'last_mod'    => time(),
		),(int)$values['id'] > 0 ? array('cat_id' =>  $values['id']) : array(),__LINE__,__FILE__);

		$cat['cat_id'] = $id = (int)$values['id'] > 0 ? (int)$values['id'] : $this->db->get_last_insert_id(self::TABLE,'cat_id');

		if (!(int)$values['parent'] && $id != $values['main'])
		{
			$this->db->update(self::TABLE,array('cat_main' => $id),array('cat_id' => $id),__LINE__,__FILE__);
			$cat['cat_main'] = $id;
		}
		// update cache accordingly
		self::invalidate_cache(egw_db::strip_array_keys($cat, 'cat_'));

		return $id;
	}

	/**
	 * Checks category permissions for a given list of commaseparated category ids
	 * and truncates it by the ones the user does not have the requested permission on
	 *
	 * @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	 * @param string $cat_list commaseparated list of category ids
	 * @return string truncated commaseparated list of category ids
	 */
	function check_list($needed, $cat_list)
 	{
		if (empty($cat_list)) return $cat_list;

		$cat_arr = explode(',',$cat_list);
		if (!empty($cat_arr) && is_array($cat_arr) && count($cat_arr) > 0)
		{
			foreach($cat_arr as $id=>$cat_id)
			{
				if (!$this->check_perms($needed, $cat_id))
				{
					unset($cat_arr[$id]);
				}
			}
			$cat_list = implode(',',$cat_arr);
		}

		return $cat_list;
	}

	/**
	 * Checks if the current user has the necessary ACL rights
	 *
	 * If the access of a category is set to private, one needs a private grant for the application
	 *
	 * @param int $needed necessary ACL right: EGW_ACL_{READ|EDIT|DELETE}
	 * @param mixed $category category as array or the category_id
	 * @param boolean $no_acl_check=false if true, grants are NOT checked, gives access to all non-private categories of all users
	 * @return boolean true permission granted, false for permission denied, null for category does not exist
	 */
	public function check_perms($needed, $category, $no_acl_check=false)
	{
		if (!is_array($category) && !($category = self::read($category)))
		{
			return null;
		}

		// The user for the global cats has id self::GLOBAL_ACCOUNT, this one has full access to all global cats
		if ($this->account_id == self::GLOBAL_ACCOUNT && ($category['appname'] == self::GLOBAL_APPNAME ||
			$category['appname'] == $this->app_name && self::is_global($category)))
		{
			//echo "<p>".__METHOD__."($needed,$category[name]) access because class instanciated for GLOBAL ACCOUNT</p>\n";
			return true;
		}

		// Read access to global categories
		if ($needed == EGW_ACL_READ && (array_intersect(explode(',',$category['owner']),$this->global_owners) ||
			$no_acl_check && $category['access'] == 'public') &&	// no_acl_check only means public cats
			($category['appname'] == self::GLOBAL_APPNAME || $category['appname'] == $this->app_name))
		{
			//echo "<p>".__METHOD__."($needed,$category[name]) access because global via memberships</p>\n";
			return true;
		}

		// Full access to own categories
		if ($category['appname'] == $this->app_name && $category['owner'] == $this->account_id)
		{
			return true;
		}

		// if $no_acl_check is set, allow access to all public (non-private) categories
		if ($no_acl_check && $category['access'] == 'public' && $this->account_id != self::GLOBAL_ACCOUNT && $category['appname'] == $this->app_name)
		{
			return true;
		}

		// Load the application grants
		if ($category['appname'] == $this->app_name && is_null($this->grants))
		{
			$this->grants = $GLOBALS['egw']->acl->get_grants($this->app_name,true);
		}

		// Check for ACL granted access, the self::GLOBAL_ACCOUNT user must not get access by ACL to keep old behaviour
		$acl_grant = $this->account_id != self::GLOBAL_ACCOUNT && $category['appname'] == $this->app_name;
		$owner_grant = false;
		foreach(explode(',',$category['owner']) as $owner)
		{
			$owner_grant = $owner_grant || (($this->grants[$owner] & $needed) &&
				($category['access'] == 'public' ||  ($this->grants[$owner] & EGW_ACL_PRIVATE)));
		}
		return $acl_grant && $owner_grant;
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
		//error_log(__METHOD__."(".array2string($cat_id).', drop_subs='.array2string($drop_subs).', modify_subs='.array2string($modify_subs).') '.function_backtrace());
		if ($modify_subs)
		{
			$new_parent = $this->id2name($cat_id,'parent');

			foreach ((array) $this->return_sorted_array('',False,'','','',False, $cat_id) as $cat)
			{
				if ($cat['level'] == 1)
				{
					$this->db->update(self::TABLE,array(
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

					$this->db->update(self::TABLE,$update,array(
						'cat_id' => $cat['id'],
						'cat_appname' => $this->app_name,
					),__LINE__,__FILE__);
				}
			}
		}
		if ($drop_subs)
		{
			$where['cat_id'] = $this->return_all_children($cat_id);
		}
		else
		{
			$where['cat_id'] = $cat_id;
		}
		$where['cat_appname'] = $this->app_name;

		$GLOBALS['hook_values'] = array(
			'cat_id'  => $cat_id,
			'cat_name' => self::id2name($cat_id),
			'drop_subs' => $drop_subs,
			'modify_subs' => $modify_subs,
			'location'    => 'delete_category'
		);
		if($this->is_global($cat_id, true))	// true = application global (otherwise eg. global addressbook categories call all apps)
		{
			$GLOBALS['egw']->hooks->process($GLOBALS['hook_values'],False,True);  // called for every app now, not only enabled ones)
		}
		else
		{
			$GLOBALS['egw']->hooks->single($GLOBALS['hook_values'], self::id2name($cat_id,'appname'));
		}

		$this->db->delete(self::TABLE,$where,__LINE__,__FILE__);

		// update cache accordingly
		self::invalidate_cache($modify_subs ? null : $where['cat_id']);
	}

	/**
	 * adapt_level_in_subtree of a category
	 *
	 * Owner and appname are set from the values used to instanciate the class!
	 *
	 * @param array $values array with cat-data (it need to be complete, as everything get's written)
	 * @return void
	 */
	function adapt_level_in_subtree($values)
	{
		foreach ((array) $this->return_sorted_array('',False,'','','',False, $values['id']) as $cat)
		{
			if ($cat['parent'] == $values['id'])
			{
				$this->db->update(self::TABLE,array(
					'cat_level' => $values['level']+1,
					'last_mod' => time(),
				),array(
					'cat_id' => $cat['id'],
					'cat_appname' => $this->app_name,
				),__LINE__,__FILE__);
				$cat['level'] = $values['level'] + 1;
				self::invalidate_cache($cat['id']);
				$this->adapt_level_in_subtree($cat);
			}
			else
			{
				continue;
			}
		}
	}

	/**
	 * check_consistency4update - for edit
	 *
	 * @param array $values array with cat-data (it need to be complete, as everything get's written)
	 * @return mixed string/boolean errorstring if consitency check failed / true if the consistency check did not fail
	 */
	function check_consistency4update($values)
	{
		// check if we try to move an element down its own subtree, which will fail
		foreach ($this->return_sorted_array('',False,'','','',False, $values['id']) as $cat) if ($cat['id'] == $values['parent']) return lang('Cannot set a category as parent, which is part of this categorys subtree!');
		// check if we try to be our own parent
		if ($values['parent']==$values['id']) return lang('Cannot set this cat as its own parent!'); // deny to be our own parent
		// check if parent still exists
		if ((int)$values['parent']>0 && !$this->read($values['parent'])) return lang('Chosen parent category no longer exists');
		return true;
	}

	/**
	 * edit / update a category
	 *
	 * Owner and appname are set from the values used to instanciate the class!
	 *
	 * @param array $values array with cat-data (it need to be complete, as everything get's written)
	 * @return int cat-id or false if it failed
	 */
	function edit($values)
	{
		if (isset($values['old_parent']) && (int)$values['old_parent'] != (int)$values['parent'])
		{
			$ret = $this->check_consistency4update($values);
			if ($ret !== true) throw new egw_exception_wrong_userinput($ret);
			// everything seems in order -> proceed
			$values['level'] = ($values['parent'] ? $this->id2name($values['parent'],'level')+1:0);
			$this->adapt_level_in_subtree($values);

			return $this->add($values);
		}
		else
		{
			//echo "old parent not set <br>";
			if ($values['parent'] > 0)
			{
				$ret = $this->check_consistency4update($values);
				if ($ret !== true) throw new egw_exception_wrong_userinput($ret);

				// everything seems in order -> proceed
				$values['main']  = $this->id2name($values['parent'],'main');
				$values['level'] = $this->id2name($values['parent'],'level') + 1;
			}
			else
			{
				//echo "new parent not set <br>";
				$values['main']  = $values['id'];
				$values['level'] = 0;
			}
			// adapt the level info in each child
			$this->adapt_level_in_subtree($values);
		}
		$this->db->update(self::TABLE,$cat=array(
			'cat_name' => $values['name'],
			'cat_description' => isset($values['description']) ? $values['description'] : $values['descr'],	// support old name different from the one read
			'cat_data'    => is_array($values['data']) ? serialize($values['data']) : $values['data'],
			'cat_parent' => $values['parent'],
			'cat_access' => $values['access'],
			'cat_owner' => isset($values['owner']) ? $values['owner'] : $this->account_id,
			'cat_main' => $values['main'],
			'cat_level' => $values['level'],
			'last_mod' => time(),
		),array(
			'cat_id' => $values['id'],
			'cat_appname' => $this->app_name,
		),__LINE__,__FILE__);

		$cat['cat_id'] = $values['id'];
		$cat['cat_appname'] = $this->app_name;

		// update cache accordingly
		self::invalidate_cache(egw_db::strip_array_keys($cat, 'cat_'));

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
	 * @param boolean|string $strip=false if true, strips 'X-'  ($strip) from category names added by some SyncML clients
	 * @return int cat-id or 0 if not found
	 */
	function name2id($cat_name, $strip=false)
	{
		static $cache = array();	// a litle bit of caching

		if (isset($cache[$cat_name])) return $cache[$cat_name];

		if ($strip === true)
		{
			$strip = 'X-';
		}

		$cats = array($cat_name);
		if ($strip && strncmp($strip, $cat_name, strlen($strip)) == 0)
		{
			$stripped_cat_name = substr($cat_name, strlen($strip));
			if (isset($cache[$stripped_cat_name]))
			{
				$cache[$cat_name] = $cache[$stripped_cat_name];
				return $cache[$stripped_cat_name];
			}
			$cats[] = $stripped_cat_name;
		}

		if (!($cats = $this->return_array('all',0,false,'','','',true,null,-1,'',array(
			'name' => $cats,
			'appname' => array($this->app_name, self::GLOBAL_APPNAME),
		))))
		{
			return 0;	// cat not found, dont cache it, as it might be created in this request
		}
		if (count($cats) > 1)
		{
			// if more the one cat matches we weight them by: exact name match; own, global, other users cat; appplication cats
			foreach($cats as $k => $cat)
			{
				$cats[$k]['weight'] = 100 * ($cat['name'] == $cat_name) +
					10 * ($cat['owner'] == $this->account_id ? 3 : ($cat['owner'] <= self::GLOBAL_ACCOUNT ? 2 : 1)) +
					($cat['appname'] != self::GLOBAL_APPNAME);
			}
			// sort heighest weight to the top
			usort($cats,create_function('$a,$b',"return \$b['weight'] - \$a['weight'];"));
		}
		return $cache[$cat['cat_name']] = (int) $cats[0]['id'];
	}

	/**
	 * Check if category is global (owner <= 0 || appname == 'phpgw')
	 *
	 * @param int|array $cat
	 * @param boolean $application_global=false true check for application global categories only (appname == 'phpgw')
	 * @return boolean
	 */
	static function is_global($cat,$application_global=false)
	{
		if (!is_array($cat) && !($cat = self::read($cat))) return null;	// cat not found

		$global_owner = false;
		foreach(explode(',',$cat['owner']) as $owner)
		{
			$global_owner = $global_owner || $owner <= self::GLOBAL_ACCOUNT;
		}
		return $global_owner && !$application_global || $cat['appname'] == self::GLOBAL_APPNAME;
	}

	/**
	 * return category information for a given id
	 *
	 * We use a shared cache together with read
	 * $item == 'data' is returned as array (not serialized array)!
	 *
	 * @param int $cat_id=0 cat-id
	 * @param string $item='name' requested information, 'path' = / delimited path of category names (incl. parents)
	 * @return string information or '--' if not found or !$cat_id
	 */
	static function id2name($cat_id=0, $item='name')
	{
		if(!$cat_id) return '--';
		if (!$item) $item = 'parent';

		if (is_null(self::$cache)) self::init_cache();

		$cat = self::$cache[$cat_id];
		if ($item == 'path')
		{
			if ($cat['parent'])
			{
				return self::id2name($cat['parent'],'path').' / '.$cat['name'];
			}
			$item = 'name';
		}
		if ($item == 'data')
		{
			return $cat['data'] ? unserialize($cat['data']) : array();
		}
		elseif ($cat[$item])
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
	 * check if a category id and/or name exists, if id AND name are given the check is for a category with same name and different id (!)
	 *
	 * @param string $type subs or mains
	 * @param string $cat_name='' category name
	 * @param int $cat_id=0 category id
	 * @param int $parent=0 category id of parent
	 * @return int/boolean cat_id or false if cat not exists
	 */
	function exists($type,$cat_name = '',$cat_id = 0,$parent = 0)
	{
		if ($cat_name)
		{
			$filter['name'] = $cat_name;
			if ($cat_id) $filter['id'] = '!'.(int)$cat_id;
		}
		elseif ($cat_id)
		{
			$filter['id'] = $cat_id;
		}
		if (!($cats = $this->return_array($type,0,false,'','','',true,$parent,-1,'id',$filter)))
		{
			$ret = false;
		}
		else
		{
			$ret = $cats[0];
		}
		//error_log(__METHOD__."($type,$cat_name,$cat_id,$parent) = ".$ret);
		return $ret;
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
			$this->db->update(self::TABLE,array('cat_owner' => $to),$where,__LINE__,__FILE__);
		}
		else
		{
			$this->db->delete(self::TABLE,$where,__LINE__,__FILE__);
		}
		// update cache accordingly
		self::invalidate_cache();
	}

	/**
	 * Initialise or restore the categories cache
	 *
	 * We use the default ordering of return_array to avoid doing it again there
	 */
	public static function init_cache()
	{
		self::$cache = egw_cache::getInstance(self::CACHE_APP, self::CACHE_NAME);

		if (is_null(self::$cache))
		{
			// check if we are already updated to global owner == 0, if not do it now
			if (!$GLOBALS['egw']->db->select(self::TABLE,'COUNT(*)',array('cat_owner'=>'0'),__LINE__,__FILE__)->fetchColumn())
			{
				$GLOBALS['egw']->db->update(self::TABLE,array('cat_owner'=>'0'),"(cat_owner='-1' OR cat_appname='phpgw')",__LINE__,__FILE__);
				$GLOBALS['egw']->db->insert(self::TABLE,array(
					'cat_main'    => 0,
					'cat_parent'  => 0,
					'cat_level'   => 0,
					'cat_owner'   => 0,
					'cat_appname' => '*update*',
					'cat_name'    => 'global=0',
					'cat_description' => 'global=0',
				),false,__LINE__,__FILE__);
			}
			self::$cache = array();
			// read all cats (cant use $this->db!)
			foreach($GLOBALS['egw']->db->select(self::TABLE,'*',false,__LINE__,__FILE__,
				false,'ORDER BY cat_main, cat_level, cat_name ASC') as $cat)
			{
				$cat = egw_db::strip_array_keys($cat,'cat_');
				if ($cat['appname'] == '*update*') continue;	// --> ignore update marker
				$cat['app_name'] = $cat['appname'];
				// backlink children to their parent
				if ($cat['parent'])
				{
					self::$cache[$cat['parent']]['children'][] = $cat['id'];
				}
				if (isset(self::$cache[$cat['id']]))
				{
					$cat['children'] = self::$cache[$cat['id']]['children'];
					unset(self::$cache[$cat['id']]);	// otherwise the order gets messed up!
				}
				self::$cache[$cat['id']] = $cat;
			}
			egw_cache::setInstance(self::CACHE_APP, self::CACHE_NAME, self::$cache);
		}
		//error_log(__METHOD__."() cache initialised: ".function_backtrace());
	}

	/**
	 * Invalidate the cache
	 *
	 * Currently we dont care for $cat_id, as changing cats happens very infrequently and
	 * also changes child categories (!)
	 *
	 * @param int|array $cat concerned id(s) or array with cat-data or null for all cats
	 */
	public static function invalidate_cache($cat=null)
	{
		//error_log(__METHOD__."(".array2string($cat).') '.function_backtrace());

		// allways invalidate instance-global cache, as updating our own cache is not perfect and does not help other sessions
		egw_cache::unsetInstance(self::CACHE_APP, self::CACHE_NAME);

		// if cat given update our own cache, to work around failed sitemgr install via setup (cant read just added categories)
		if ($cat)
		{
			if (!is_array($cat) || isset($cat[0]))
			{
				foreach((array)$cat as $cat_id)
				{
					unset(self::$cache[$cat_id]);
				}
			}
			elseif($cat['id'])
			{
				self::$cache[$cat['id']] = $cat;
			}
		}
		else
		{
			self::init_cache();
		}
	}

	/**
	 * Delete categories belonging to a given account, when account got deleted
	 *
	 * @param int $account_id
	 * @param int $new_owner=null for users data can be transfered to new owner
	 * @return int number of deleted or modified categories
	 */
	public static function delete_account($account_id, $new_owner=null)
	{
		if (is_null(self::$cache)) self::init_cache();

		$deleted = 0;
		foreach(self::$cache as $cat_id => $data)
		{
			if ($data['owner'] && ($owners = explode(',', $data['owner'])) && ($owner_key = array_search($account_id, $owners)) !== false)
			{
				// delete category if account_id is single owner and no new owner or owner is a group
				if (count($owners) == 1 && (!$new_owner || $account_id < 0))
				{
					if (!isset($cat))
					{
						$cat = new categories($new_owner, $data['appname']);
					}
					$cat->delete($cat_id, false, true);
				}
				else
				{
					unset($owners[$owner_key]);
					if ($new_owner && $account_id > 0) $owners[] = $new_owner;
					$data['owner'] = implode(',', $owners);
					// app_name have to match cat to update!
					if (!isset($cat) || $cat->app_name != $data['appname'])
					{
						$cat = new categories($new_owner, $data['appname']);
					}
					$cat->add($data);
				}
				++$deleted;
			}
		}
		return $deleted;
	}

	/**
	 * Delete categories with not (longer) existing owners
	 *
	 * @return int number of deleted categories
	 */
	public static function delete_orphans()
	{
		if (is_null(self::$cache)) self::init_cache();

		$checked = array();
		$deleted = 0;
		foreach(self::$cache as $cat_id => $data)
		{
			foreach(explode(',', $data['owner']) as $owner)
			{
				if ($owner && !in_array($owner, $checked))
				{
					if (!$GLOBALS['egw']->accounts->exists($owner))
					{
						$deleted += self::delete_account($owner);
					}
					$checked[] = $owner;
				}
			}
		}
		return $deleted;
	}

	/**
	 ******************************** old / deprecated functions ***********************************
	 */

	/**
	 * read a single category
	 *
	 * We use a shared cache together with id2name
	 *
	 * @deprecated use read($id) returning just the category array not an array with one element
	 * @param int $id id of category
	 * @return array|boolean array with one array of cat-data or false if cat not found
	 */
	static function return_single($id)
	{
		if (is_null(self::$cache)) self::init_cache();

		return isset(self::$cache[$id]) ? array(self::$cache[$id]) : false;
	}

	/**
	 * return into a select box, list or other formats
	 *
	 * @param string/array $format string 'select' or 'list', or array with all params
	 * @param string $type='' subs or mains
	 * @param int/array $selected - cat_id or array with cat_id values
	 * @param boolean $globals True or False, includes the global egroupware categories or not
	 * @deprecated use html class to create selectboxes
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
					if (self::is_global($cat))
					{
						$s .= ' &#9830;';
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
}
