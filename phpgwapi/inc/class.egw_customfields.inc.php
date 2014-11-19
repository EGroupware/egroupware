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
	 * @param boolean $all_private_too =false should all the private fields be returned too, default no
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @param int $start =0
	 * @param int $num_rows =null
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
		return new egw_db_callback_iterator($this->iterator, function($_row)
		{
			$row = egw_db::strip_array_keys($_row, 'cf_');
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
	 * @param boolean $all_private_too =false should all the private fields be returned too, default no
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @return array with customfields
	 */
	public static function get($app, $all_private_too=false, $only_type2=null)
	{
		$cache_key = $app.':'.($all_private_too?'all':$GLOBALS['egw_info']['user']['account_id']).':'.$only_type2;
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
	 * Check if any customfield uses html (type == 'htmlarea')
	 *
	 * @param string $app
	 * @param boolean $all_private_too =false should all the private fields be returned too, default no
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @return boolen true: if there is a custom field useing html, false if not
	 */
	public static function use_html($app, $all_private_too=false, $only_type2=null)
	{
		foreach(self::get($app, $all_private_too, $only_type2) as $data)
		{
			if ($data['type'] == 'htmlarea') return true;
		}
		return false;
	}

	/**
	 * Non printable custom fields eg. UI elements
	 *
	 * @var array
	 */
	public static $non_printable_fields = array('button');

	/**
	 * Format a single custom field value as string
	 *
	 * @param array $field field defintion incl. type
	 * @param string $value field value
	 * @return string formatted value
	 */
	public static function format(array $field, $value)
	{
		switch($field['type'])
		{
			case 'select-account':
				if ($value)
				{
					$values = array();
					foreach($field['rows'] > 1 ? explode(',', $value) : (array) $value as $value)
					{
						$values[] = common::grab_owner_name($value);
					}
					$value = implode(', ',$values);
				}
				break;

			case 'checkbox':
				$value = $value ? 'X' : '';
				break;

			case 'select':
			case 'radio':
				if (count($field['values']) == 1 && isset($field['values']['@']))
				{
					$field['values'] = self::get_options_from_file($field['values']['@']);
				}
				$values = array();
				foreach($field['rows'] > 1 ? explode(',', $value) : (array) $value as $value)
				{
					$values[] = isset($field['values'][$value]) ? $field['values'][$value] : '#'.$value;
				}
				$value = implode(', ', $values);
				break;

			case 'date':
			case 'date-time':
				if ($value)
				{
					$format = $field['len'] ? $field['len'] : ($field['type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
					$formats = preg_split('/[\\/. :-]/',$format);
					$values = preg_split('/[\\/. :-]/', is_numeric($value) ? egw_time::to($value, $format) : $value);
					if (count($formats) != count($values))
					{
						//error_log(__METHOD__."(".array2string($field).", value='$value') format='$format', formats=".array2string($formats).", values=".array2string($values));
						$values = array_slice($values, 0, count($formats));
					}
					$date = array_combine($formats, $values);
					$value = common::dateformatorder($date['Y'], $date['m'], $date['d'],true);
					if (isset($date['H'])) $value .= ' '.common::formattime($date['H'], $date['i']);
				}
				break;

			case 'htmlarea':	// ToDo: EMail probably has a nicer html2text method
				if ($value) $value = strip_tags(preg_replace('/<(br|p)[^>]*>/i', "\r\n", str_replace(array("\r", "\n"), '', $value)));
				break;

			case 'ajax_select':	// ToDo: returns unchanged value for now
				break;

			default:
				// handling for several link types
				if ($value && in_array($field['type'], self::get_link_types()))
				{
					if ($field['type'] == 'link-entry' || strpos($value, ':') !== false)
					{
						list($app, $value) = explode(':', $value);
					}
					else
					{
						$app = $field['type'];
					}
					if ($value) $value = egw_link::title($app, $value);
				}
				break;
		}
		return $value;
	}

	/**
	 * Read the options of a 'select' or 'radio' custom field from a file
	 *
	 * For security reasons that file has to be relative to the eGW root
	 * (to not use that feature to explore arbitrary files on the server)
	 * and it has to be a php file setting one variable called options,
	 * (to not display it to anonymously by the webserver).
	 * The $options var has to be an array with value => label pairs, eg:
	 *
	 * <?php
	 * $options = array(
	 *      'a' => 'Option A',
	 *      'b' => 'Option B',
	 *      'c' => 'Option C',
	 * );
	 *
	 * @param string $file file name inside the eGW server root, either relative to it or absolute
	 * @return array in case of an error we return a single option with the message
	 */
	public static function get_options_from_file($file)
	{
		$options = array();

		if (!($path = realpath($file[0] == '/' ? $file : EGW_SERVER_ROOT.'/'.$file)) ||	// file does not exist
			substr($path,0,strlen(EGW_SERVER_ROOT)+1) != EGW_SERVER_ROOT.'/' ||	// we are NOT inside the eGW root
			basename($path,'.php').'.php' != basename($path) ||	// extension is NOT .php
			basename($path) == 'header.inc.php')	// dont allow to include our header again
		{
			return array(lang("'%1' is no php file in the eGW server root (%2)!".': '.$path,$file,EGW_SERVER_ROOT));
		}
		include($path);

		return $options;
	}

	/**
	 * Get the customfield types containing links
	 *
	 * @return array with customefield types as values
	 */
	public static function get_link_types()
	{
		static $link_types = null;

		if (is_null($link_types))
		{
			$link_types = array_keys(array_intersect(egw_link::app_list('query'),egw_link::app_list('title')));
			$link_types[] = 'link-entry';
		}
		return $link_types;
	}

	/**
	 * Check if there are links in the custom fields and update them
	 *
	 * This function have to be called manually by an application, if cf's linking
	 * to other entries should be stored as links too (beside as cf's).
	 *
	 * @param string $own_app own appname
	 * @param array $values new values including the custom fields
	 * @param array $old =null old values before the update, if existing
	 * @param string $id_name ='id' name/key of the (link-)id in $values
	 */
	public static function update_links($own_app,array $values,array $old=null,$id_name='id')
	{
		$link_types = self::get_link_types();

		foreach(egw_customfields::get($own_app) as $name => $data)
		{
			if (!in_array($data['type'],$link_types)) continue;

			// do we have a different old value --> delete that link
			if ($old && $old['#'.$name] && $old['#'.$name] != $values['#'.$name])
			{
				if ($data['type'] == 'link-entry')
				{
					list($app,$id) = explode(':',$old['#'.$name]);
				}
				else
				{
					$app = $data['type'];
					$id = $old['#'.$name];
				}
				egw_link::unlink(false,$own_app,$values[$id_name],'',$app,$id);
			}
			if ($data['type'] == 'link-entry')
			{
				list($app,$id) = explode(':',$values['#'.$name]);
			}
			else
			{
				$app = $data['type'];
				$id = $values['#'.$name];
			}
			if ($id)	// create new link, does nothing for already existing links
			{
				egw_link::link($own_app,$values[$id_name],$app,$id);
			}
		}
	}

	/**
	 * Save a single custom field and invalidate cache
	 *
	 * @param array $cf
	 */
	public static function update(array $cf)
	{
		$op = $cf['id'] ? 'update' : 'insert';

		self::$db->$op(self::TABLE, array(
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
