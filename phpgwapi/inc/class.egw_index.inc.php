<?php
/**
 * API - eGW wide index over all applications (super-index)
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id$
 */

/**
 * eGW wide index over all applications (superindex)
 *
 * This index allows a fulltext search over all applications (or of cause also a single app).
 * Whenever an applications stores an entry it calls:
 *
 * 		boolean egw_index::save($app,$id,$owner,array $fields,array $cat_id=null),
 *
 * which calls, as the application do when is deletes an entry (!),
 *
 * 		boolean egw_index::delete($app,$id)
 *
 * and then splits all fields into keywords and add these to the index by
 *
 * 		boolean private egw_index::add($app,$id,$keyword).
 *
 * Applications can then use the index to search for a given keyword (and optional application):
 *
 * 		array egw_index::search($keyword,$app=null) or
 *
 * 		foreach(new egw_index($keyword,$app=null) as $app_id => $title)
 *
 * To also allow to search by a category or keyword part of it, the index also tracks the categories
 * of the entries. Applications can choose to only use it for category storage, or cat do it redundant in
 * there own table too. To retrieve the categories of one or multiple entries:
 *
 * 		array egw_index::cats($app,$ids)
 *
 * Applications can use a sql (sub-)query to get the id's of there app matching a certain keyword and
 * include that in there own queries:
 *
 * 		string egw_index::sql_ids_by_keyword($app,$keyword)
 *
 * Please note: the index knows nothing about ACL, so it's the task of the application to ensure ACL rights.
 */

class egw_index implements IteratorAggregate
{
	const INDEX_TABLE = 'egw_index';
	const INDEX_CAT_TABLE = 'egw_cat2entry';
	const CAT_TABLE = 'egw_categories';
	const SEPERATORS = "[ ,;.:\"'!/?=()+*><|\n\r-]";
	const MIN_KEYWORD_LEN = 4;

	/**
	 * Private reference to the global db object
	 *
	 * @var egw_db
	 */
	private static $db;
	/**
	 * Search parameters of the constructor
	 *
	 * @var array
	 */
	private $search_params;

	/**
	 * Constructor for the search iterator
	 *
	 * @param string $keyword
	 * @param string $app=null
	 * @param string $order='app' ordered by column: 'owner', 'id', 'app' (default)
	 * @param string $sort='ASC' sorting 'ASC' (default) or 'DESC'
	 * @param int $start=null if not null return limited resultset starting with row $start
	 * @param int $num_rows=0 number of rows for a limited resultset, defaul maxmatches from the user prefs
	 */
	function __construct($keyword,$app=null,$order='title',$sort='ASC',$start=null,$num_rows=0)
	{
		$this->search_params = func_get_args();
	}

	/**
	 * Return the result of egw_index::search() as ArrayIterator
	 *
	 * @return ArrayIterator
	 */
	function getIterator()
	{
		return new ArrayIterator(call_user_func_array(array(__CLASS__,'search'),$this->search_params));
	}

	/**
	 * Search for keywords
	 *
	 * @param string $keyword
	 * @param string $app=null
	 * @param string $order='app' ordered by column: 'keyword', 'id', 'app' (default)
	 * @param string $sort='ASC' sorting 'ASC' (default) or 'DESC'
	 * @param int $start=null if not null return limited resultset starting with row $start
	 * @param int $num_rows=null number of rows for a limited resultset, defaul maxmatches from the user prefs
	 * @return array with "$app:$id" or $id => $title pairs
	 */
	static function &search($keyword,$app=null,$order='title',$sort='ASC',$start=null,$num_rows=null)
	{
		if (!in_array(strtoupper($sort),array('ASC','DESC'))) $sort = 'ASC';
		if (substr($order,0,3) != 'si_') $order = 'si_'.$order;
		if (!in_array($order,array('si_app','si_id','si_owner'))) $order = 'si_app';

		$rs = self::$db->union(array(
			array(
				'table' => self::INDEX_TABLE,
				'cols'  => 'si_app,si_app_id,si_owner',
				'where' => array('keyword' => $keyword)+
					($app ? array('ce_app' => $app) : array()),
			),
			array(
				'table' => self::INDEX_CAT_TABLE,
				'cols'  => 'ce_app,ce_app_id,ce_owner',
				'where' => array('cat_id IN (SELECT cat_id FROM '.self::CAT_TABLE.' WHERE cat_title '.
					self::$db->capabilities['case_insensitive_like'].' '.self::$db->quote('%'.$keyword.'%').')')+
					($app ? array('ce_app' => $app) : array()),
			),
		),__LINE__,__FILE__,$order.' '.$sort,$start,$num_rows);

		// agregate the ids by app
		$app_ids = $titles = $rows = array();
		foreach($rs as $row)
		{
			$app_ids[$row['si_app']] = $row['si_app_id'];
			$rows[] = $row;
		}
		unset($rs);

		// query the titles app-wise
		foreach($app_ids as $id_app => $ids)
		{
			$titles[$id_app] = bolink::titles($id_app,$ids);
		}
		$matches = array();
		foreach($rows as $row)
		{
			$key = $app ? $row['si_app_id'] : $row['si_app'].':'.$row['si_app_id'];
			$title = $titles[$row['si_app']][$row['si_app_id']];
			if (is_null($title))	// entry does not exist
			{
				self::delete($row['si_app'],$row['si_app_id']);
				error_log(__METHOD__.": not existing entry (is_null(title($row[si_app],$row[si_app_id])) deleted from index!");
				continue;
			}
			elseif($title === false)
			{
				$title = lang('Not readable %1 entry of user %2',lang($row['si_app']),$GLOBALS['egw']->common->grab_owner_name($row['si_owner']));
			}
			$matches[$key] = $title;
		}
		return $matches;
		//return iterator_to_array(new egw_index($keyword,$app,$order,$sort,$start,$num_rows),true);
	}

	/**
	 * Stores the keywords for an entry in the index
	 *
	 * @param string $app
	 * @param string/int $id
	 * @param string $owner eGW account_id of the owner of the entry, used to create a "private entry of ..." title
	 * @param array $fields
	 * @param array/int/string $cat_ids=null optional cat_id(s) either comma-separated or as array
	 * @return int/boolean false on error, othwerwise number off added keywords
	 */
	static function save($app,$id,$owner,array $fields,$cat_ids=null)
	{
		if (!$app || !$id)
		{
			return false;
		}
		// collect the keywords of all fields
		$keywords = array();
		foreach($fields as $field)
		{
			$tmpArray = @preg_split(self::SEPERATORS,$field);
			if (is_array($tmpArray)) {
				foreach($tmpArray as $keyword)
				{
					if (!in_array($keyword,$keywords) && strlen($keyword) >= self::MIN_KEYWORD_LEN && !is_numeric($keyword))
					{
						$keywords[] = $keyword;
					}
				}
			}
		}
		// delete evtl. existing current keywords
		self::delete($app,$id);

		// add the keywords
		foreach($keywords as $key => &$keyword)
		{
			if (!self::add($app,$id,$keyword,$owner))	// add can reject keywords
			{
				unset($keywords[$key]);
			}
		}

		// delete the existing cats
		self::delete_cats($app,$id);

		// add the cats
		if ($cat_ids)
		{
			self::add_cats($app,$id,$cat_ids,$owner);
		}
		return count($keywords);
	}

	/**
	 * Delete the keywords for an entry or an entire application
	 *
	 * @param string $app
	 * @param string/int $id=null
	 */
	static function delete($app,$id=null)
	{
		if (!$app)
		{
			return false;
		}
		$where = array('si_app' => $app);
		if ($id)
		{
			$where['si_app_id'] = $id;
		}
		return !!self::$db->delete(self::INDEX_TABLE,$where,__LINE__,__FILE__);
	}

	/**
	 * Returns the cats of an entry or multiple entries
	 *
	 * @param string $app
	 * @param string/int/array $ids
	 * @return array with cats or single id or id => array with cats pairs
	 */
	static function cats($app,$ids)
	{
		if (!$app || !$ids)
		{
			return array();
		}
		$cats = array();
		foreach(self::$db->select(self::INDEX_CAT_TABLE,'cat_id,ce_app_id',array(
			'ce_app' => $app,
			'ce_app_id' => $ids,
		),__LINE__,__FILE__) as $row)
		{
			$cats[$row['ce_app_id']][] = $row['cat_id'];
		}
		return is_array($ids) ? $cats : $cats[(int)$ids];
	}

	/**
	 * Get the SQL to fetch (eg. as subquery) the id's of a given app matching a keyword
	 *
	 * @param string $keyword
	 * @param string $app
	 * @return string
	 */
	static function sql_ids_by_keyword($keyword,$app)
	{
		return '(SELECT si_id FROM '.self::INDEX_TABLE.' WHERE si_app='.self::$db->quote($app).
			' AND si_keyword = '.self::$db->quote($keyword).') UNION '.
			'(SELECT ce_id FROM '.self::INDEX_CAT_TABLE.' WHERE si_app='.self::$db->quote($app).
			' AND cat_id IN (SELECT cat_id FROM '.self::CAT_TABLE.' WHERE cat_title '.self::$db->capabilities['case_insensitive_like'].' '.
			self::$db->quote('%'.$keyword.'%').'))';
	}

	/**
	 * Stores one keyword for an entry in the index
	 *
	 * @todo reject keywords which are common words ...
	 * @param string $app
	 * @param string/int $id
	 * @param string $keyword
	 * @param int $owner=null
	 * @return boolean true if keyword added, false if it was rejected in future
	 */
	static private function add($app,$id,$keyword,$owner=null)
	{
		// todo: reject keywords which are common words, not sure how to do that for all languages
		// maybey we can come up with some own little statistic analysis:
		// all keywords more common then N % of the entries get deleted and moved to a separate table ...

		self::$db->insert(self::INDEX_TABLE,array(
			'si_keyword' => $keyword,
			'si_app' => $app,
			'si_app_id' => $id,
			'si_owner' => $owner,
		),false,__LINE__,__FILE__);

		return true;
	}

	/**
	 * Stores the cat_id(s) for an entry
	 *
	 * @param string $app
	 * @param string/int $id
	 * @param array/int/string $cat_ids=null optional cat_id(s) either comma-separated or as array
	 * @param int $owner=null
	 * @return boolean true on success, false on error
	 */
	static private function add_cats($app,$id,$cat_ids,$owner=null)
	{
		if (!$app)
		{
			return false;
		}
		if (!$cat_ids)
		{
			return true;	// nothing to do
		}
		foreach(is_array($cat_ids) ? $cat_ids : explode(',',$cat_ids) as $cat_id)
		{
			self::$db->insert(self::INDEX_CAT_TABLE,array(
				'cat_id' => $cat_id,
				'ce_app' => $app,
				'ce_app_id' => $id,
				'ce_owner' => $owner,
			),false,__LINE__,__FILE__);
		}
		return true;
	}

	/**
	 * Delete the cat for an entry or an entire application
	 *
	 * @param string $app
	 * @param string/int $id=null
	 */
	static private function delete_cats($app,$id=null)
	{
		if (!$app)
		{
			return false;
		}
		$where = array('ce_app' => $app);
		if ($id)
		{
			$where['ce_app_id'] = $id;
		}
		return !!self::$db->delete(self::INDEX_CAT_TABLE,$where,__LINE__,__FILE__);
	}

	/**
	 * Init our static vars
	 */
	static function _init_static()
	{
		if (!is_object($GLOBALS['egw_setup']))
		{
			self::$db = $GLOBALS['egw']->db;
		}
		else
		{
			self::$db = $GLOBALS['egw_setup']->db;
		}
	}
}

egw_index::_init_static();
