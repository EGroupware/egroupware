<?php
/**
 * EGroupware API - managing custom-field definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @copyright 2014 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

/**
 * Managing custom-field definitions
 */
class egw_customfields implements IteratorAggregate
{
	/**
	 * Name of the customfields table
	 */
	const TABLE = 'egw_customfields';
	/**
	 * Reference to the global db class
	 *
	 * @var egw_db
	 */
	static protected $db;

	/**
	 * app the particular config class is instanciated for
	 *
	 * @var string
	 */
	protected $app;

	/**
	 * should all the private fields be returned too, default no
	 *
	 * @var boolean
	 */
	protected $all_private_too=false;

	/**
	 * Iterator initialised for custom fields
	 *
	 * @var ADORecordSet
	 */
	protected $iterator;

	/**
	 * Constructor
	 *
	 * @param string $app
	 * @param boolean $all_private_too=false should all the private fields be returned too, default no
	 * @param string $only_type2=null if given only return fields of type2 == $only_type2
	 * @param int $start=0
	 * @param int $num_rows=null
	 * @return array with customfields
	 */
	function __construct($app, $all_private_too=false, $only_type2=null, $start=0, $num_rows=null)
	{
		$this->app = $app;
		$this->all_private_too = $all_private_too;

		$query = array(
			'cf_app' => $app,
		);
		if (!$all_private_too)
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true);
			$memberships[] = $GLOBALS['egw_info']['user']['account_id'];
			$query[] = $this->commasep_match('cf_private', $memberships);
		}
		if ($only_type2)
		{
			$query[] = $this->commasep_match('cf_type2', $only_type2);
		}
		$this->iterator = self::$db->select(self::TABLE, '*', $query, __LINE__, __FILE__,
			!isset($num_rows) ? false : $start, 'ORDER BY cf_order ASC', 'phpgwapi', $num_rows);
	}

	/**
	 * Return iterator required for IteratorAggregate
	 *
	 * @return egw_db_callback_iterator
	 */
	function getIterator()
	{
		return new egw_db_callback_iterator($this->iterator, function($row)
		{
			$row = egw_db::strip_array_keys($row, 'cf_');
			$row['private'] = $row['private'] ? explode(',', $row['private']) : array();
			$row['type2'] = $row['type2'] ? explode(',', $row['type2']) : array();
			$row['values'] = json_decode($row['values'], true);
			$row['needed'] = egw_db::from_bool($row['needed']);

			return $row;
		}, array(), function($row)
		{
			return $row['cf_name'];
		});
	}

	/**
	 * Return SQL to match given values with comma-separated stored column
	 *
	 * @param string $column column name "cf_type2" or "cf_private"
	 * @param string|array $values
	 */
	protected function commasep_match($column, $values)
	{
		$to_or = array($column.' IS NULL');
		foreach((array) $values as $value)
		{
			$to_or[] = self::$db->concat("','", $column, "','").' LIKE '.self::$db->quote('%,'.$value.',%');
		}
		return '('.implode(' OR ', $to_or).')';
	}

	/**
	 * Get customfield array of an application
	 *
	 * @param string $app
	 * @param boolean $all_private_too=false should all the private fields be returned too, default no
	 * @param string $only_type2=null if given only return fields of type2 == $only_type2
	 * @return array with customfields
	 */
	public static function get($app, $all_private_too=false, $only_type2=null)
	{
		$cache_key = $app.':'.(bool)$all_private_too.':'.$only_type2;
		$cfs = egw_cache::getInstance(__CLASS__, $cache_key);

		if (!isset($cfs))
		{
			$cfs = iterator_to_array(new egw_customfields($app, $all_private_too, $only_type2));

			egw_cache::setInstance(__CLASS__, $cache_key, $cfs);
			$cached = egw_cache::getInstance(__CLASS__, $app);
			if (!in_array($cache_key, (array)$cached))
			{
				$cached[] = $cache_key;
				egw_cache::setInstance(__CLASS__, $app, $cached);
			}
		}
		//error_log(__METHOD__."('$app', $all_private_too, '$only_type2') returning fields: ".implode(', ', array_keys($cfs)));
		return $cfs;
	}

	/**
	 * Save a single custom field and invalidate cache
	 *
	 * @param array $cf
	 */
	public static function update(array $cf)
	{
		self::$db->update(self::TABLE, array(
			'cf_label' => $cf['label'],
			'cf_type' => $cf['type'],
			'cf_type2' => $cf['type2'] ? implode(',', $cf['type2']) : null,
			'cf_help' => $cf['help'],
			'cf_values' => $cf['values'] ? json_encode($cf['values']) : null,
			'cf_len' => (string)$cf['len'] !== '' ? $cf['len'] : null,
			'cf_rows' => (string)$cf['rows'] !== '' ? $cf['rows'] : null,
			'cf_order' => $cf['order'],
			'cf_needed' => $cf['needed'],
			'cf_private' => $cf['private'] ? implode(',', $cf['private']) : null,
			'cf_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			'cf_modified' => time(),
		), array(
			'cf_name' => $cf['name'],
			'cf_app' => $cf['app'],
		), __LINE__, __FILE__);

		self::invalidate_cache($cf['app']);
	}

	/**
	 * Save all custom fields of an app
	 *
	 * @param string $app
	 * @param array $cfs
	 */
	public static function save($app, array $cfs)
	{
		$query = array('cf_app' => $app);
		if ($cfs) $query[] = self::$db->expression(self::TABLE, 'NOT ', array('cf_name' => array_keys($cfs)));
		self::$db->delete(self::TABLE, $query, __LINE__, __FILE__);

		foreach($cfs as $name => $cf)
		{
			if (empty($cf['name'])) $cf['name'] = $name;
			if (empty($cf['app']))  $cf['app'] = $app;

			self::update($cf);
		}
		self::invalidate_cache($app);
	}

	/**
	 * Invalidate instance cache for all custom fields of given app
	 *
	 * @param string $app
	 */
	protected static function invalidate_cache($app)
	{
		if (($cached = egw_cache::getInstance(__CLASS__, $app)))
		{
			foreach($cached as $key)
			{
				egw_cache::unsetInstance(__CLASS__, $key);
			}
			egw_cache::unsetInstance(__CLASS__, $app);
		}
	}

	/**
	 * Change account_id's of private custom-fields
	 *
	 * @param string $app
	 * @param array $ids2change from-id => to-id pairs
	 * @return integer number of changed ids
	 */
	public static function change_account_ids($app, array $ids2change)
	{
		$total = 0;
		if (($cfs = self::get_customfields($app, true)))
		{
			foreach($cfs as &$data)
			{
				if ($data['private'])
				{
					$changed = 0;
					foreach($data['private'] as &$id)
					{
						if (isset($ids2change[$id]))
						{
							$id = $ids2change[$id];
							++$changed;
						}
					}
					if ($changed)
					{
						self::update($data);
						$total += $changed;
					}
				}
			}
		}
		return $total;
	}

	/**
	 * Return names of custom fields containing account-ids
	 *
	 * @param string $app
	 * @return array account[-commasep] => array of name(s) pairs
	 */
	public static function get_account_cfs($app)
	{
		$types = array();
		if (($cfs = self::get_customfields($app, true)))
		{
			foreach($cfs as $name => $data)
			{
				if ($data['type'] == 'select-account' || $data['type'] == 'home-accounts')
				{
					$types['account'.($data['rows'] > 1 ? '-commasep' : '')][] = $name;
				}
			}
		}
		return $types;
	}

	/**
	 * Initialise our db
	 *
	 * We use a reference here (no clone), as we no longer use egw_db::row() or egw_db::next_record()!
	 *
	 */
	public static function init_static()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			self::$db = $GLOBALS['egw']->db;
		}
		else
		{
			self::$db = $GLOBALS['egw_setup']->db;
		}
	}
}

egw_customfields::init_static();
