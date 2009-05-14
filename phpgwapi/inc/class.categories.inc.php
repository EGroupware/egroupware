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
 * @ToDo The cache now contains a backlink from the parent to it's children. Use that link to simplyfy return_all_children
 * 	and other functions needing to know if a cat has children. Be aware a user might not see all children, as they can
 * 	belong to other users.
 */
class categories
{
	/**
	 * Account id this class is instanciated for (-1 for global cats)
	 *
	 * @var int
	 */
	public $account_id;
	/**
	 * Application this class is instancated for ('phpgw' for application global cats)
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

	/**
	 * constructor for categories class
	 *
	 * @param int/string $accountid='' account id or lid, default to current user
	 * @param string $app_name='' app name defaults to current app
	 */
	function __construct($accountid='',$app_name = '')
	{
		if (!$app_name) $app_name = $GLOBALS['egw_info']['flags']['currentapp'];

		$this->account_id	= (int) get_account_id($accountid);
		$this->app_name		= $app_name;
		$this->db			= $GLOBALS['egw']->db;

		if (is_null(self::$cache))	// sould not be necessary, as cache is load and restored by egw object
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
	 * return_all_children
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
	 * @param boolean $globals includes the global egroupware categories or not
	 * @param array|int $parent_id=null return only subcats of $parent_id(s)
	 * @param int $lastmod = -1 if > 0 return only cats modified since then
	 * @param string $column='' if column-name given only that column is returned, not the full array with all cat-data
	 * @param array $filter=null array with column-name (without cat_-prefix) => value pairs (! negates the value)
	 * @return array of cat-arrays or $column values
	 */
	function return_array($type='all', $start=0, $limit=true, $query='', $sort='ASC',$order='',$globals=false, $parent_id=null, $lastmod=-1, $column='', $filter=null)
	{
		//error_log(__METHOD__."($type,$start,$limit,$query,$sort,$order,globals=$globals,parent=".array2string($parent_id).",$lastmod,$column) account_id=$this->account_id, appname=$this->app_name: ".function_backtrace());

		// load the grants
		if ($this->account_id != -1 && is_null($this->grants))
		{
			// resolving the group members/grants is very slow with ldap accounts backend
			// let's skip it for the addressbook, if we are using the ldap accounts backend
			$this->grants = $GLOBALS['egw']->acl->get_grants($app_name,
				$app_name != 'addressbook' || $GLOBALS['egw_info']['server']['account_repository'] != 'ldap');
		}
		$cats = array();
		foreach(self::$cache as $cat_id => $cat)
		{
			if ($filter) foreach($filter as $col => $val)
			{
				if (!is_array($val) && $val[0] === '!')
				{
					if ($cat[$col] == substr($val,1)) continue 2;	// 2 for BOTH foreach!
				}
				elseif (is_array($val))
				{
					if (!in_array($cat[$col],$val)) continue 2;
				}
				else
				{
					if ($cat[$col] != $val) continue 2;
				}
			}
			// check if certain parent required
			if ($parent_id && !in_array($cat['parent'],(array)$parent_id)) continue;

			// apply standard acl / grants: return only application global cats (if $globals) or
			if (!($globals && $cat['appname'] == 'phpgw' ||
				  $cat['appname'] == $this->app_name && ($cat['owner'] == -1 || $cat['owner'] == $this->account_id ||
				  	$this->account_id != -1 && $cat['access'] == 'public' && $this->grants && isset($this->grants[$cat['owner']]))))
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
		//error_log(__METHOD__."($type,$start,$limit,$query,$sort,$order,$globals,parent=$parent_id,$lastmod,$column) account_id=$this->account_id, appname=$this->app_name = ".array2string($cats));

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
	 * @param boolean $globals includes the global egroupware categories or not
	 * @param array|int $parent_id=0 return only subcats of $parent_id(s)
	 * @return array with cats
	 */
	function return_sorted_array($start=0,$limit=True,$query='',$sort='ASC',$order='cat_name',$globals=False, $parent_id=0)
	{
		if (!$sort)  $sort = 'ASC';
		if (!$order) $order = 'cat_name';

		//error_log(__METHOD__."($start,$limit,$query,$sort,$order,globals=$globals,parent=$parent_id) account_id=$this->account_id, appname=$this->app_name: ".function_backtrace());

		$parents = $cats = array();
		if (!($cats = $this->return_array('all',0,false,$query,$sort,$order,$globals,(array)$parent_id)))
		{
			$cats = array();
		}
		foreach($cats as $cat)
		{
			$parents[] = $cat['id'];
		}
		while (count($parents))
		{
			if (!($subs = $this->return_array('all',0,false,$query,$sort,$order,$globals,$parents)))
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
	 * read a single category
	 *
	 * We use a shared cache together with id2name
	 *
	 * @param int $id id of category
	 * @return array|boolean array with one array of cat-data or false if cat not found
	 */
	static function return_single($id)
	{
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
					if ($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
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
		$this->db->insert(self::TABLE,array(
			'cat_parent'  => $values['parent'],
			'cat_owner'   => $this->account_id,
			'cat_access'  => isset($values['access']) ? $values['access'] : 'public',
			'cat_appname' => $this->app_name,
			'cat_name'    => $values['name'],
			'cat_description' => isset($values['description']) ? $values['description'] : $values['descr'],	// support old name different from returned one
			'cat_data'    => $values['data'],
			'cat_main'    => $values['main'],
			'cat_level'   => $values['level'],
			'last_mod'    => time(),
		),(int)$values['id'] > 0 ? array('cat_id' =>  $values['id']) : array(),__LINE__,__FILE__);

		$id = (int)$values['id'] > 0 ? (int)$values['id'] : $this->db->get_last_insert_id(self::TABLE,'cat_id');

		if (!(int)$values['parent'] && $id != $values['main'])
		{
			$this->db->update(self::TABLE,array('cat_main' => $id),array('cat_id' => $id),__LINE__,__FILE__);
		}
		// update cache accordingly
		self::invalidate_cache($id);

		return $id;
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
			$where = array('cat_id='.(int)$cat_id.' OR cat_parent='.(int)$cat_id.' OR cat_main='.(int)$cat_id);
		}
		else
		{
			$where['cat_id'] = $cat_id;
		}
		$where['cat_appname'] = $this->app_name;

		$this->db->delete(self::TABLE,$where,__LINE__,__FILE__);

		// update cache accordingly
		self::invalidate_cache($cat_id);
	}

	/**
	 * edit / update a category
	 *
	 * Owner and appname are set from the values used to instanciate the class!
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
		$this->db->update(self::TABLE,array(
			'cat_name' => $values['name'],
			'cat_description' => isset($values['description']) ? $values['description'] : $values['descr'],	// support old name different from the one read
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

		// update cache accordingly
		self::invalidate_cache($values['id']);

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
			'appname' => array($this->app_name, 'phpgw'),
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
					10 * ($cat['owner'] == $this->account_id ? 3 : ($cat['owner'] == -1 ? 2 : 1)) +
					($cat['appname'] != 'phpgw');
			}
			// sort heighest weight to the top
			usort($cats,create_function('$a,$b',"return \$b['weight'] - \$a['weight'];"));
		}
		return $cache[$cat['cat_name']] = (int) $cats[0]['id'];
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
	 *
	 * @param array &$cache=null cache content to restore it (from the egw-object)
	 */
	public static function &init_cache(&$cache=null)
	{
		if (!is_null($cache))
		{
			//error_log(__METHOD__."() ".count($cache)." cats restored: ".function_backtrace());
			return self::$cache =& $cache;
		}
		self::$cache = array();
		// read all cats (cant use $this->db!)
		foreach($GLOBALS['egw']->db->select(self::TABLE,'*',false,__LINE__,__FILE__,
			false,'ORDER BY cat_main, cat_level, cat_name ASC') as $cat)
		{
			$cat = egw_db::strip_array_keys($cat,'cat_');
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
		//error_log(__METHOD__."() cache initialised: ".function_backtrace());
		return self::$cache;
	}

	/**
	 * Invalidate the cache
	 *
	 * Currently we dont care for $cat_id, as changing cats happens very infrequently and
	 * also changes child categories (!)
	 *
	 * @param int $cat_id concerned id or null for all cats
	 */
	public static function invalidate_cache($cat_id=null)
	{
		self::init_cache();
		// we need to invalidate the whole session cache, as it stores our cache
		egw::invalidate_session_cache();
	}
}
