<?php
/**
 * EGroupware API - managing custom-field definitions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @copyright 2014-16 by Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;
use EGroupware\Api\Json\Push;

/**
 * Managing custom-field definitions
 */
class Customfields implements \IteratorAggregate
{
	/**
	 * Name of the customfields table
	 */
	const TABLE = 'egw_customfields';
	/**
	 * Reference to the global db class
	 *
	 * @var Api\Db
	 */
	static protected $db;

	/**
	 * app the particular config class is instanciated for
	 *
	 * @var string
	 */
	protected $app;

	/**
	 * User account to filter custom field private
	 *
	 * @var int
	 */
	protected $account=false;

	/**
	 * Iterator initialised for custom fields
	 *
	 * @var \ADORecordSet
	 */
	protected $iterator;

	/**
	 * App name used to push custom field changes
	 */
	const PUSH_APP = 'api-cf';

	/**
	 * Constructor
	 *
	 * @param string $app
	 * @param int|boolean $account =false Filter private for given account_id,
	 *	false for current user or true for all the private fields be returned too, default current user
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @param int $start =0
	 * @param int $num_rows =null
	 * @param Api\Db $db =null reference to database instance to use
	 * @return array with customfields
	 */
	function __construct($app, $account=false, $only_type2=null, $start=0, $num_rows=null, Api\Db $db=null)
	{
		$this->app = $app;

		// If $account is true, no filtering otherwise use current user
		$this->account = $account === true ? false :
				(is_numeric($account) ? (int)$account : $GLOBALS['egw_info']['user']['account_id']);

		$query = array(
			'cf_app' => $app,
		);
		if ($this->account)
		{
			$memberships = $GLOBALS['egw']->accounts->memberships($this->account, true);
			$memberships[] = $this->account;
			$query[] = $this->commasep_match('cf_private', $memberships);
		}
		if ($only_type2)
		{
			$query[] = $this->commasep_match('cf_type2', $only_type2);
		}
		if (!$db) $db = self::$db;
		$this->iterator = $db->select(self::TABLE, '*', $query, __LINE__, __FILE__,
			!isset($num_rows) ? false : $start, 'ORDER BY cf_order ASC', 'phpgwapi', $num_rows);
	}

	/**
	 * Return iterator required for IteratorAggregate
	 *
	 * @return Api\Db\CallbackIterator
	 */
	function getIterator(): Api\Db\CallbackIterator
	{
		return new Api\Db\CallbackIterator($this->iterator, function($_row)
		{
			$row = Api\Db::strip_array_keys($_row, 'cf_');
			$row['private'] = $row['private'] ? explode(',', $row['private']) : array();
			$row['type2'] = $row['type2'] ? explode(',', $row['type2']) : array();
			$row['values'] = json_decode($row['values'], true);
			$row['needed'] = Api\Db::from_bool($row['needed']);

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
	 * @param int|boolean $account =false Filter private for given account_id,
	 *	false for current user or true for all the private fields be returned too, default current user
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @param Api\Db $db =null reference to database to use
	 * @return array with customfields
	 */
	public static function get($app, $account=false, $only_type2=null, Api\Db $db=null)
	{
		$account_key = $account === true ? 'all' :
				($account === false ? ($GLOBALS['egw_info']['user']['account_id']??null) :
				(int)$account);

		$cache_key = $app.':'.$account_key.':'.$only_type2;
		$cfs = Api\Cache::getInstance(__CLASS__, $cache_key);

		if (!isset($cfs))
		{
			$cfs = iterator_to_array(new Customfields($app, $account, $only_type2, 0, null, $db));

			Api\Cache::setInstance(__CLASS__, $cache_key, $cfs);
			$cached = Api\Cache::getInstance(__CLASS__, $app);
			if (!in_array($cache_key, (array)$cached))
			{
				$cached[] = $cache_key;
				Api\Cache::setInstance(__CLASS__, $app, $cached);
			}
		}
		//error_log(__METHOD__."('$app', $account, '$only_type2') returning fields: ".implode(', ', array_keys($cfs)));
		return $cfs;
	}

	/**
	 * Check if any customfield uses html (type == 'htmlarea')
	 *
	 * @param string $app
	 * @param int|boolean $account =false Filter private for given account_id,
	 *	false for current user or true for all the private fields be returned too, default current user
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @return boolean true: if there is a custom field useing html, false if not
	 */
	public static function use_html($app, $account=false, $only_type2=null)
	{
		foreach(self::get($app, $account, $only_type2) as $data)
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
	 * @param array $field field definition incl. type
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
						$values[] = is_numeric($value) ? Api\Accounts::username($value) : $value;
					}
					$value = implode(', ',$values);
				}
				break;

			case 'checkbox':
				$value = $value ? 'X' : '';
				break;

			case 'select':
			case 'radio':
				if ($field['values'] && count($field['values']) == 1 && isset($field['values']['@']))
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
					$value = Api\DateTime::to($value, $field['type'] == 'date' ? true : '');
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
					if ($value) $value = Api\Link::title($app, $value);
				}
				break;
		}
		restore_error_handler();
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
			$link_types = array_keys(array_intersect(Api\Link::app_list('query'),Api\Link::app_list('title')));
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

		foreach(self::get($own_app) as $name => $data)
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
				Api\Link::unlink(false,$own_app,$values[$id_name],'',$app,$id);
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
				Api\Link::link($own_app,$values[$id_name],$app,$id);
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

		// Check to see if field order needs to be re-done
		$update = array();

		$cfs = self::get($cf['app'], true);
		$old = $cfs[$cf['name']];

		// Add new one in for numbering
		if(!$cf['id'])
		{
			// Make sure name is safe
			$cf['name'] = str_replace(array(">", "<", '"', "&"), "", $cf['name']);
			$cfs[$cf['name']] = $cf;
		}

		if($old['order'] != $cf['order'] || $cf['order'] % 10 !== 0)
		{
			$cfs[$cf['name']]['order'] = $cf['order'];
			uasort($cfs, function($a1, $a2){
				return $a1['order'] - $a2['order'];
			});
			$n = 0;
			foreach($cfs as $old_cf)
			{
				$n += 10;
				if($old_cf['order'] != $n)
				{
					$old_cf['order'] = $n;
					if($old_cf['name'] != $cf['name'])
					{
						$update[] = $old_cf;
					}
					else
					{
						$cf['order'] = $n;
					}
				}
			}
		}

		self::$db->$op(self::TABLE, array(
			'cf_label'    => $cf['label'],
			'cf_type'     => $cf['type'],
			'cf_type2'    => $cf['type2'] ? (is_array($cf['type2']) ? implode(',', $cf['type2']) : $cf['type2']) : null,
			'cf_help'     => $cf['help'],
			'cf_values'   => $cf['values'] ? json_encode($cf['values']) : null,
			'cf_len'      => (string)$cf['len'] !== '' ? $cf['len'] : null,
			'cf_rows'     => (string)$cf['rows'] !== '' ? $cf['rows'] : null,
			'cf_order'    => $cf['order'],
			'cf_needed'   => $cf['needed'],
			'cf_private'  => $cf['private'] ? implode(',', $cf['private']) : null,
			'cf_modifier' => $GLOBALS['egw_info']['user']['account_id'],
			'cf_modified' => time(),
		), array(
			'cf_name' => $cf['name'],
			'cf_app' => $cf['app'],
		), __LINE__, __FILE__);

		foreach($update as $old_cf)
		{
			self::$db->$op(self::TABLE, array(
				'cf_order' => $old_cf['order'],
			), array(
				'cf_name' => $old_cf['name'],
				'cf_app' => $old_cf['app'],
			), __LINE__, __FILE__);
		}

		self::invalidate_cache($cf['app']);

		// push category change
		$type = 'update';
		if(!$cf['id'])
		{
			$cfs = self::get($cf['app'], true);
			$cf = $cfs[$cf['name']];
			$type = 'add';
		}
		$accounts = Push::ALL;
		if(is_array($cf['private']) && count($cf['private']) > 0)
		{
			$accounts = [];
			foreach($cf['private'] as $account_id)
			{
				$accounts = array_merge(
					$account_id > 0 ? [$account_id] :
						$GLOBALS['egw']->accounts->members($account_id, true)
				);
			}
		}
		$push = new Push($accounts);
		$push->apply("egw.push", [[
									  'app'        => self::PUSH_APP,
									  'id'         => $cf['id'],
									  'type'       => $type,
									  'acl'        => ['private' => $cf['private']],
									  'account_id' => $GLOBALS['egw_info']['user']['account_id']
								  ]]);
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
		if (($cached = Api\Cache::getInstance(__CLASS__, $app)))
		{
			foreach($cached as $key)
			{
				Api\Cache::unsetInstance(__CLASS__, $key);
			}
			Api\Cache::unsetInstance(__CLASS__, $app);
		}
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
		if (($cfs = self::get($app, true)))
		{
			foreach($cfs as $name => $data)
			{
				if ($data['type'] == 'select-account' || $data['type'] == 'api-accounts')
				{
					$types['account'.($data['rows'] > 1 ? '-commasep' : '')][] = $name;
				}
			}
		}
		return $types;
	}

	/**
	 * Return names of custom fields containing url-email
	 *
	 * @param string $app
	 * @return array of url-email fields
	 */
	public static function get_email_cfs($app)
	{
		$fields = array();
		if (($cfs = self::get($app, true)))
		{
			foreach($cfs as $name => $data)
			{
				if ($data['type'] == 'url-email')
				{
					$fields[] = $name;
				}
			}
		}
		return $fields;
	}

	/**
	 * Initialise our db
	 *
	 * We use a reference here (no clone), as we no longer use Api\Db::row() or Api\Db::next_record()!
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

	/**
	 * Handle any uploaded files that weren't dealt with immediately when uploaded.
	 * This usually happens for new entries, where we don't have the entry's ID
	 * to properly file it in the VFS.  Files are stored temporarily until we
	 * have the ID, then here we move the files to their proper location.
	 *
	 * @staticvar array $_customfields List of custom field data, kept to avoid
	 *	loading it multiple times if called again.
	 *
	 * @param string $app Current application
	 * @param string $entry_id Application ID of the new entry
	 * @param array $values Array of entry data, including custom fields.
	 *	File information from the VFS widget (via self::validate()) will be found &
	 *	dealt with.  Successful or not, the value is cleared to avoid trying to insert it into
	 *	the database, which would generate errors.
	 * @param array $customfields Pass the custom field list if you have it to avoid loading it again
	 */
	public static function handle_files($app, $entry_id, &$values, &$customfields = array())
	{
		if(!is_array($values) || !$entry_id) return;

		if(!$customfields)
		{
			static $_customfields = array();
			if(!$_customfields[$app])
			{
				$_customfields[$app] = Api\Storage\Customfields::get($app);
			}
			$customfields = $_customfields[$app];
		}
		foreach ($customfields as $field_name => $field)
		{
			if($field['type'] == 'filemanager' && $value =& $values[Api\Storage::CF_PREFIX.$field_name])
			{
				static::handle_file($entry_id, $field, $value);
				unset($values[Api\Storage::CF_PREFIX.$field_name]);
			}
		}
	}

	protected static function handle_file($entry_id, $field, $value)
	{
		$path = Api\Etemplate\Widget\Vfs::get_vfs_path($field['app'].":$entry_id:".$field['label']);
		if($path)
		{
			foreach($value as $file)
			{
				$file['tmp_name'] = Api\Vfs::PREFIX.$file['path'];
				Api\Etemplate\Widget\Vfs::store_file($path, $file);
			}
		}
	}
}

Customfields::init_static();