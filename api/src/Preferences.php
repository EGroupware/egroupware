<?php
/**
 * EGroupware API - Preferences
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> merging prefs on runtime, session prefs and reworked the class
 * Copyright (C) 2000, 2001 Joseph Engo
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * preferences class used for setting application preferences
 *
 * preferences are read into following arrays:
 * - $data effective prefs used everywhere in EGroupware
 * Effective prefs are merged together in following precedence from:
 * - $forced forced preferences set by the admin, they take precedence over user or default prefs
 * - $session temporary prefs eg. language set on login just for session
 * - $user the stored user prefs, only used for manipulating and storeing the user prefs
 * - $group the stored prefs of all group-memberships of current user, can NOT be deleted or stored directly!
 * - $default the default preferences, always used when the user has no own preference set
 *
 * To update the prefs of a certain group, not just the primary group of the user, you have to
 * create a new instance of preferences class, with the given id of the group. This takes into
 * account the offset of DEFAULT_ID, we are using currently for groups (as -1, and -2) are already
 * taken!
 *
 * Preferences get now json-encoded and no longer PHP serialized and addslashed,
 * thought they only change when they get updated.
 */
class Preferences
{
	/**
	 * account_id for default prefs
	 */
	const DEFAULT_ID = -2;
	/**
	 * account_id for forced prefs
	 */
	const FORCED_ID = -1;

	/**
	 * account the class is instanciated for
	 * @var int
	 */
	var $account_id;
	/**
	 * account-type u or g
	 * @var string
	 */
	var $account_type;
	/**
	 * effectiv user prefs, used by all apps
	 * @var array
	 */
	var $data = array();
	/**
	 * set user prefs for saveing (no defaults/forced prefs merged)
	 * @var array
	 */
	var $user = array();
	/**
	 * primary group prefs
	 * @var array
	 */
	var $group = array();
	/**
	 * default prefs
	 * @var array
	 */
	var $default = array();
	/**
	 * forced prefs
	 * @var array
	 */
	var $forced = array();
	/**
	 * session / tempory prefs
	 * @var array
	 */
	var $session = array();
	/**
	 * @var Db
	 */
	var $db;
	/**
	 * table-name
	 */
	const TABLE = 'egw_preferences';
	var $table = self::TABLE;

	var $values,$vars;	// standard notify substitues, will be set by standard_substitues()

	/**
	 * Contstructor
	 *
	 * @param int|string $account_id =''
	 * @return preferences
	 */
	function __construct($account_id = '')
	{
		if (isset($GLOBALS['egw']->db))
		{
			$this->db = $GLOBALS['egw']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw_setup']->db;
			$this->table = $GLOBALS['egw_setup']->prefs_table;
		}
		$this->set_account_id($account_id);
	}

	/**
	 * Set account_id for class
	 *
	 * Takes care of offset for groups.
	 *
	 * @param int|string $account_id numeric account_id, "default", "forced" to load default or forced preferences
	 *	or account_lid (only if !== "default" or "forced"!)
	 */
	function set_account_id($account_id)
	{
		if ($account_id === 'default')
		{
			$this->account_id = self::DEFAULT_ID;
		}
		elseif ($account_id === 'forced')
		{
			$this->account_id = self::FORCED_ID;
		}
		// if we got instancated for a group, need to set offset of DEFAULT_ID!
		elseif ((int)$account_id < 0 || !is_numeric($account_id) && ($account_id = get_account_id($account_id)) < 0)
		{
			$this->account_id = $account_id + self::DEFAULT_ID;
		}
		else
		{
			$this->account_id = $account_id;
		}
		//error_log(__METHOD__."($account_id) setting this->account_id to $this->account_id");
	}

	/**
	 * Return account_id class is instanciated for or "default" or "forced"
	 *
	 * Takes care of offset for groups.
	 *
	 * @return string|int
	 */
	function get_account_id()
	{
		switch ($this->account_id)
		{
			case self::DEFAULT_ID:
				return 'default';
			case self::FORCED_ID:
				return 'forced';
		}
		return $this->account_id < self::DEFAULT_ID ? $this->account_id-self::DEFAULT_ID : $this->account_id;
	}

	/**
	 * Magic function to avoid storing perferences in session, as they get re-read on each request by egw_session::verify()
	 *
	 * @return array with class vars to store
	 */
	function __sleep()
	{
		$vars = array_keys(get_object_vars($this));

		return array_diff($vars, array('data', 'user', 'group', 'default', 'forced', 'session'));
	}

	/**
	 * Lifetime in seconds of cached items 1d
	 */
	const CACHE_LIFETIME = 86400;

	/**
	 * Read preferences of requested id(s)
	 *
	 * @param int|array $ids
	 * @return array id => app => preference data
	 */
	function cache_read($ids)
	{
		$prefs = Cache::getInstance(__CLASS__, $ids);
		$db_read = array();
		foreach((array)$ids as $id)
		{
			// if prefs are not returned, null or not an array, read them from db
			if (!isset($prefs[$id]) || !is_array($prefs[$id])) $db_read[] = $id;
		}
		if ($db_read)
		{
			foreach($this->db->select($this->table,'*',array('preference_owner' => $db_read),__LINE__,__FILE__) as $row)
			{
				// The following replacement is required for PostgreSQL to work
				$app = trim($row['preference_app']);

				$prefs[$row['preference_owner']][$app] = self::unserialize($row['preference_value']);
			}
			foreach($db_read as $id)
			{
				if (!isset($prefs[$id])) $prefs[$id] = array();
				Cache::setInstance(__CLASS__, $id, $prefs[$id]);
			}
		}
		//error_log(__METHOD__.'('.array2string($ids).') read-from-db='.array2string($db_read));
		return $prefs;
	}

	/**
	 * parses a notify and replaces the substitutes
	 *
	 * @param string $msg message to parse / substitute
	 * @param array $values =array() extra vars to replace in addition to $this->values, vars are in an array with \
	 * 	$key => $value pairs, $key does not include the $'s and is the *untranslated* name
	 * @param boolean $use_standard_values =true should the standard values are used
	 * @return string with parsed notify-msg
	 */
	function parse_notify($msg,$values=array(),$use_standard_values=True)
	{
		$vals = $values ? $values : array();

		if ($use_standard_values && is_array($this->values))
		{
			$vals += $this->values;
		}
		$replace = $with = array();
		foreach($vals as $key => $val)
		{
			if (!empty($this->debug)) error_log(__METHOD__." replacing \$\$$key\$\$ with $val  ");
			$replace[] = '$$'.$key.'$$';
			$with[]    = $val;
		}
		return str_replace($replace,$with,$msg);
	}

	/**
	 * replaces the english key's with translated ones, or if $un_lang the opposite
	 *
	 * @param string $msg message to translate
	 * @param array $vals =array() extra vars to replace in addition to $this->values, vars are in an array with \
	 * 	$key => $value pairs, $key does not include the $'s and is the *untranslated* name
	 * @param boolean $un_lang =false if true translate back
	 * @return string
	 */
	function lang_notify($msg,$vals=array(),$un_lang=False)
	{
		foreach(array_keys($vals) as $key)
		{
			$lname = ($lname = lang($key)) == $key.'*' ? $key : $lname;
			if ($un_lang)
			{
				$langs[$lname] = '$$'.$key.'$$';
			}
			else
			{
				$langs[$key] = '$$'.$lname.'$$';
			}
		}
		return $this->parse_notify($msg,$langs,False);
	}

	/**
	 * define some standard substitues-values and use them on the prefs, if needed
	 */
	function standard_substitutes()
	{
		if (!empty($this->debug)) error_log(__METHOD__." is called ");
		if (!is_array(@$GLOBALS['egw_info']['user']['preferences']))
		{
			$GLOBALS['egw_info']['user']['preferences'] = $this->data;	// else no lang()
		}
		// we cant use egw_info/user/fullname, as it's not set when we run
		$this->values = array(	// standard notify replacements
			'fullname'  => Accounts::id2name($this->account_id, 'account_fullname'),
			'firstname' => Accounts::id2name($this->account_id, 'account_firstname'),
			'lastname'  => Accounts::id2name($this->account_id, 'account_lastname'),
			'domain'    => $GLOBALS['egw_info']['server']['mail_suffix'],
			'email'     => $this->email_address($this->account_id),
			'date'      => DateTime::to('now',$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']),
		);
		// do this first, as it might be already contain some substitues
		//
		$this->values['email'] = $this->parse_notify($this->values['email']);

		$this->vars = array(	// langs have to be in common !!!
			'fullname'  => lang('name of the user, eg. "%1"',$this->values['fullname']),
			'firstname' => lang('first name of the user, eg. "%1"',$this->values['firstname']),
			'lastname'  => lang('last name of the user, eg. "%1"',$this->values['lastname']),
			'domain'    => lang('domain name for mail-address, eg. "%1"',$this->values['domain']),
			'email'     => lang('email-address of the user, eg. "%1"',$this->values['email']),
			'date'      => lang('todays date, eg. "%1"',$this->values['date']),
		);
		if (!empty($this->debug)) error_log(__METHOD__.print_r($this->vars,true));
		// do the substituetion in the effective prefs (data)
		//
		foreach($this->data as $app => $data)
		{
			if(!is_array($data)) continue;
			foreach($data as $key => $val)
			{
				if (!is_array($val) && strpos($val,'$$') !== False)
				{
					$this->data[$app][$key] = $this->parse_notify($val);
				}
				elseif (is_array($val))
				{
					foreach($val as $k => $v)
					{
						if (!is_array($v) && strpos($v,'$$') !== False)
						{
							$this->data[$app][$key][$k] = $this->parse_notify($v);
						}
					}
				}
			}
		}
	}

	/**
	 * Unserialize data from either json_encode or PHP serialize and addslashes
	 *
	 * @param string $str serialized prefs
	 * @return array
	 */
	protected static function unserialize($str)
	{
		// handling of new json-encoded prefs
		if ($str[0] != 'a' && $str[1] != ':')
		{
			return json_decode($str, true);
		}
		// handling of old PHP serialized and addslashed prefs
		$data = php_safe_unserialize($str);
		if($data === false)
		{
			// manually retrieve the string lengths of the serialized array if unserialize failed
			$data = php_safe_unserialize(preg_replace_callback('!s:(\d+):"(.*?)";!s', function($matches)
			{
				return 's:'.mb_strlen($matches[2],'8bit').':"'.$matches[2].'";';
			}, $str));
		}
		self::unquote($data);
		return $data;
	}

	/**
	 * unquote (stripslashes) recursivly the whole array
	 *
	 * @param array &$arr array to unquote (var-param!)
	 */
	protected static function unquote(&$arr)
	{
		if (!is_array($arr))
		{
			$arr = stripslashes($arr);
			return;
		}
		foreach($arr as $key => $value)
		{
			if (is_array($value))
			{
				self::unquote($arr[$key]);
			}
			else
			{
				$arr[$key] = stripslashes($value);
			}
		}
	}

	/**
	 * read preferences from the repository
	 *
	 * the function ready all 3 prefs user/default/forced and merges them to the effective ones
	 *
	 * @param boolean $use_session =true should the session prefs get used (default true) or not (false)
	 * @return array with effective prefs ($this->data)
	 */
	function read_repository($use_session=true)
	{
		$this->session = $use_session ? Cache::getSession('preferences','preferences') : array();
		if (!is_array($this->session))
		{
			$this->session = array();
		}
		$this->forced = $this->default = $this->user = $this->group = array();
		$to_read = array(self::DEFAULT_ID,self::FORCED_ID,$this->account_id);
		if ($this->account_id > 0)
		{
			$primary_group = Accounts::id2name($this->account_id, 'account_primary_group');
			foreach((array)$GLOBALS['egw']->accounts->memberships($this->account_id, true) as $gid)
			{
				if ($gid != $primary_group) $to_read[] = $gid + self::DEFAULT_ID;	// need to offset it with DEFAULT_ID = -2!
			}
			$to_read[] = $primary_group + self::DEFAULT_ID;
		}
		foreach($this->cache_read($to_read) as $id => $values)
		{
			switch($id)
			{
				case self::FORCED_ID:
					$this->forced = $values;
					break;
				case self::DEFAULT_ID:
					$this->default = $values;
					break;
				case $this->account_id:	// user
					$this->user = $values;
					break;
				default:
					foreach($values as $app => $vals)
					{
						$this->group[$app] = (array)$vals + ($this->group[$app] ?? []);
					}
					break;
			}
		}
		$this->data = $this->user;

		// let the (temp.) session prefs. override the user prefs.
		//
		foreach($this->session as $app => $values)
		{
			foreach($values as $var => $value)
			{
				$this->data[$app][$var] = $value;
			}
		}

		// now use (primary) group defaults if needed (user-value unset or empty)
		//
		foreach((array)$this->group as $app => $values)
		{
			foreach((array)$values as $var => $value)
			{
				if (!isset($this->data[$app][$var]) || $this->data[$app][$var] === '')
				{
					$this->data[$app][$var] = $value;
				}
			}
		}
		// now use defaults if needed (user-value unset or empty)
		//
		foreach((array)$this->default as $app => $values)
		{
			foreach((array)$values as $var => $value)
			{
				if (!isset($this->data[$app][$var]) || $this->data[$app][$var] === '')
				{
					//if ($var=='remote_application_url') error_log(__METHOD__.__LINE__.' default for '.$var.' with '.$value);
					$this->data[$app][$var] = $value;
				}
			}
		}
		// now set/force forced values
		//
		foreach((array)$this->forced as $app => $values)
		{
			foreach((array)$values as $var => $value)
			{
				$this->data[$app][$var] = $value;
			}
		}
		// setup the standard substitutes and substitutes the data in $this->data
		//
		if (!empty($GLOBALS['egw_info']['flags']['load_translations']))
		{
			$this->standard_substitutes();
		}
		// This is to supress warnings during login
		if (is_array($this->data))
		{
			reset($this->data);
		}
		if (isset($this->debug) && substr($GLOBALS['egw_info']['flags']['currentapp'],0,3) != 'log')
		{
			echo 'user<pre>';     print_r($this->user); echo "</pre>\n";
			echo 'forced<pre>';   print_r($this->forced); echo "</pre>\n";
			echo 'default<pre>';  print_r($this->default); echo "</pre>\n";
			echo 'group<pre>';    print_r($this->group); echo "</pre>\n";
			echo 'effectiv<pre>'; print_r($this->data); echo "</pre>\n";
		}
		$this->check_set_tz_offset();

		return $this->data;
	}

	/**
	 * Get default preferences (also taking forced preferences into account!)
	 *
	 * @param string $app =null
	 * @param string $name =null
	 * @return mixed
	 */
	function default_prefs($app=null,$name=null)
	{
		// Etemplate::complete_array_merge() is identical to PHP >= 5.3 array_replace_recursive()
		$default = Etemplate::complete_array_merge($this->default, $this->forced);

		if ($app) $default = $default[$app];

		if ($name && is_array($default)) $default = $default[$name];

		return $default;
	}

	/**
	 * Checking new timezone ('tz') pref and setting old tz_offset pref from it
	 *
	 */
	function check_set_tz_offset()
	{
		$prefs =& $this->data['common'];

		if (!empty($prefs['tz']))
		{
			DateTime::setUserPrefs($prefs['tz'],$prefs['dateformat'],$prefs['timeformat']);
			// set the old preference for compatibilty with old code
			$prefs['tz_offset'] = DateTime::tz_offset_s()/3600;
		}
	}

	/**
	 * Set user timezone, if we get restored from session
	 *
	 */
	function __wakeup()
	{
		$this->check_set_tz_offset();
	}

	/**
	 * read preferences from repository and stores in an array
	 *
	 * @return array containing the effective user preferences
	 */
	function read()
	{
		if (count($this->data) == 0)
		{
			$this->read_repository();
		}
		reset ($this->data);
		return $this->data;
	}

	/**
	 * add preference to $app_name a particular app
	 *
	 * the effective prefs ($this->data) are updated to reflect the change
	 *
	 * @param string $app_name name of the app
	 * @param string $var name of preference to be stored
	 * @param mixed $value ='##undef##' value of the preference, if not given $GLOBALS[$var] is used
	 * @param string $type ='user' of preference to set: forced, default, user
	 * @return array with new effective prefs (even when forced or default prefs are set !)
	 */
	function add($app_name,$var,$value = '##undef##',$type='user')
	{
		//echo "<p>add('$app_name','$var','$value')</p>\n";
		if ($value === '##undef##')
		{
			$value = $GLOBALS[$var];
		}

		switch ($type)
		{
			case 'session':
				if (!isset($this->forced[$app_name][$var]) || $this->forced[$app_name][$var] === '')
				{
					$this->session[$app_name][$var] = $this->data[$app_name][$var] = $value;
					Cache::setSession('preferences','preferences',$this->session);
					if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
					{
						$GLOBALS['egw']->invalidate_session_cache();	// in case with cache the egw_info array in the session
					}
				}
				break;

			case 'forced':
				$this->data[$app_name][$var] = $this->forced[$app_name][$var] = $value;
				break;

			case 'default':
				$this->default[$app_name][$var] = $value;
				if ((!isset($this->forced[$app_name][$var]) || $this->forced[$app_name][$var] === '') &&
					(!isset($this->user[$app_name][$var]) || $this->user[$app_name][$var] === ''))
				{
					$this->data[$app_name][$var] = $value;
				}
				break;

			case 'user':
			default:
				$this->user[$app_name][$var] = $value;
				if (!isset($this->forced[$app_name][$var]) || $this->forced[$app_name][$var] === '')
				{
					$this->data[$app_name][$var] = $value;
				}
				break;
		}
		reset($this->data);
		return $this->data;
	}

	/**
	 * delete preference from $app_name
	 *
	 * the effektive prefs ($this->data) are updated to reflect the change
	 *
	 * @param string $app_name name of app
	 * @param string $var =false variable to be deleted
	 * @param string $type ='user' of preference to set: forced, default, user
	 * @return array with new effective prefs (even when forced or default prefs are deleted!)
	 */
	function delete($app_name, $var = False,$type = 'user')
	{
		//echo "<p>delete('$app_name','$var','$type')</p>\n";
		$set_via = array(
			'forced'  => array('user','default'),
			'default' => array('forced','user'),
			'user'    => array('forced','group','default'),
			'group'   => array('forced'),
		);
		if (!isset($set_via[$type]))
		{
			$type = 'user';
		}
		$pref = &$this->$type;

		if (($all = empty($var))) // to check if $var is regarded as empty (false, 0, '', null, array() should do the trick
		{
			unset($pref[$app_name]);
			unset($this->data[$app_name]);
		}
		else
		{
			unset($pref[$app_name][$var]);
			unset($this->data[$app_name][$var]);
		}
		// set the effectiv pref again if needed
		//
		foreach ($set_via[$type] as $set_from)
		{
			$arr = &$this->$set_from;
			if ($all)
			{
				if (isset($arr[$app_name]))
				{
					$this->data[$app_name] = $arr[$app_name];
					break;
				}
			}
			else
			{
				if($var && @isset($arr[$app_name][$var]) && $arr[$app_name][$var] !== '')
				{
					$this->data[$app_name][$var] = $arr[$app_name][$var];
					break;
				}
			}
			unset($arr);
		}
		reset ($this->data);
		return $this->data;
	}

	/**
	 * delete all prefs of a given user
	 *
	 * @param int $accountid
	 */
	function delete_user($accountid)
	{
		if ($accountid > 0)
		{
			$this->db->delete($this->table,array('preference_owner' => $accountid),__LINE__,__FILE__);

			Cache::unsetInstance(__CLASS__, $accountid);
		}
	}

	/**
	 * delete all prefs of a given group
	 *
	 * @param int $accountid
	 */
	function delete_group($accountid)
	{
		if ($accountid < 0)
		{
			$this->db->delete($this->table,array('preference_owner' => $accountid+self::DEFAULT_ID),__LINE__,__FILE__);

			Cache::unsetInstance(__CLASS__, $accountid+self::DEFAULT_ID);
		}
	}

	/**
	 * Change single value in preferences of all users (incl. groups, default and forced)
	 *
	 * @param string $app app-name or null for all apps
	 * @param string $name attribute name or regular expression (enclosed in /) to match attribute-name eg. '/^favorite_/'
	 * @param string|callable $value new value to set, or null or '' to delete it or callable returning new value: function($attr, $old_value, $owner, $prefs)
	 * @param string $old_value if given, only change if that's current value
	 * @param string $type if given limit to "user", "forced", "default", "group"
	 */
	public static function change_preference($app, $name, $value, $old_value=null, $type=null)
	{
		$db = isset($GLOBALS['egw_setup']->db) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;

		$where = array();
		if ($app) $where['preference_app'] = $app;

		switch($type)
		{
			case 'forced':
				$where['preference_owner'] = self::FORCED_ID;
				break;
			case 'default':
				$where['preference_owner'] = self::DEFAULT_ID;
				break;
			case 'user':
				$where[] = 'preference_owner > 0';
				break;
			case 'group':
				$where[] = 'preference_owner < '.self::DEFAULT_ID;
				break;
		}
		foreach($db->select(self::TABLE, '*', $where, __LINE__, __FILE__) as $row)
		{
			$prefs = self::unserialize($row['preference_value']);
			if (!is_array($prefs)) $prefs = array();	// would stall update otherwise

			if ($name[0] == '/' && substr($name, -1) == '/')
			{
				$attrs = array_filter(array_keys($prefs), function($n) use ($name)
				{
					return preg_match($name, $n);
				});
			}
			else
			{
				$attrs = array($name);
			}

			$updated = false;
			foreach($attrs as $attr)
			{
				if (isset($old_value) && $prefs[$attr] != $old_value) continue;

				$val = is_callable($value) ? call_user_func($value, $attr, $prefs[$attr], $row['preference_owner'], $prefs) : $value;
				if ($val === $prefs[$attr]) continue;

				$updated = true;
				if ((string)$val !== '')
				{
					$prefs[$attr] = $val;
				}
				else
				{
					unset($prefs[$attr]);
				}
			}
			// if somethings changed or old row was php-serialized --> store it again json-encoded
			if ($updated || $row['preference_value'][0] == 'a' && $row['preference_value'][1] == ':')
			{
				$db->update(self::TABLE, array(
					'preference_value' => json_encode($prefs),
				), array(
					'preference_owner' => $row['preference_owner'],
					'preference_app'   => $row['preference_app'],
				), __LINE__, __FILE__);

				// update instance-wide cache
				$cached = Cache::getInstance(__CLASS__, $row['preference_owner']);
				if($cached && $cached[$row['preference_app']])
				{
					$cached[$row['preference_app']] = $prefs;
					Cache::setInstance(__CLASS__, $row['preference_owner'], $cached);
				}
			}
		}
	}

	/**
	 * Completely delete the specified preference name from all users
	 *
	 * @param string $app Application name
	 * @param string $name Preference name
	 * @param string $type ='user' of preference to set: forced, default, user
	 */
	public static function delete_preference($app, $name, $type='user')
	{
		self::change_preference($app, $name, null, null, $type);
	}

	/**
	 * Copy preferences from one app to an other
	 *
	 * @param string $from_app
	 * @param string $to_app
	 * @param array $names =null array of names to copy or null for all
	 */
	public static function copy_preferences($from_app, $to_app, array $names=null)
	{
		//error_log(__METHOD__."('$from_app', '$to_app', ".array2string($names).')');
		$db = isset($GLOBALS['egw_setup']->db) ? $GLOBALS['egw_setup']->db : $GLOBALS['egw']->db;

		foreach($db->select(self::TABLE, '*', array('preference_app' => $from_app), __LINE__, __FILE__) as $row)
		{
			$prefs = self::unserialize($row['preference_value']);

			if ($names)
			{
				$prefs = array_intersect_key($prefs, array_flip($names));
			}
			if (!$prefs) continue;	// nothing to change, as nothing set

			$row['preference_app'] = $to_app;
			unset($row['preference_value']);

			if (($values = $db->select(self::TABLE, 'preference_value', $row, __LINE__, __FILE__)->fetchColumn()))
			{
				$prefs = array_merge(self::unserialize($values), $prefs);
			}
			unset($row['preference_id']);
			//error_log(__LINE__.': '.__METHOD__."() inserting app=$row[preference_app], owner=$row[preference_owner]: ".array2string($prefs));
			$db->insert(self::TABLE, array(
				'preference_value' => json_encode($prefs)
			), $row, __LINE__, __FILE__);

			// update instance-wide cache
			if (($cached = Cache::getInstance(__CLASS__, $row['prefences_owner'])))
			{
				$cached[$from_app] = $prefs;
				Cache::setInstance(__CLASS__, $row['preference_owner'], $cached);
			}
		}
	}

	/**
	 * Save the the preferences to the repository
	 *
	 * User prefs for saveing are in $this->user not in $this->data, which are the effectiv prefs only!
	 *
	 * @param boolean $update_session_info =false old param, seems not to be used (not used anymore)
	 * @param string $type ='user' which prefs to update: user/default/forced
	 * @param boolean $invalid_cache =true should we invalidate the cache, default true (not used anymore)
	 * @return array with new effective prefs (even when forced or default prefs are deleted!)
	 */
	function save_repository($update_session_info = False,$type='user',$invalid_cache=true)
	{
		unset($update_session_info, $invalid_cache);	// no longer used

		switch($type)
		{
			case 'forced':
				$account_id = self::FORCED_ID;
				$prefs = &$this->forced;
				break;
			case 'default':
				$account_id = self::DEFAULT_ID;
				$prefs = &$this->default;
				break;
			case 'group':
				throw new Exception\WrongParameter("Can NOT save group preferences, as they are from multiple groups!");

			default:
				$account_id = (int)$this->account_id;
				$prefs = &$this->user;	// we use the user-array as data contains default values too
				break;
		}
		//echo "<p>preferences::save_repository(,$type): account_id=$account_id, prefs="; print_r($prefs); echo "</p>\n";

		if (isset($GLOBALS['egw_setup']) || !$GLOBALS['egw']->acl->check('session_only_preferences',1,'preferences') &&
			(!($old_prefs = $this->cache_read($account_id)) || $old_prefs != $prefs))
		{
			//error_log(__METHOD__."(type=$type) saved, because old_prefs[$account_id] != prefs=".array2string($prefs));
			$changed = 0;
			foreach($prefs as $app => $value)
			{
				// check if app preferences have changed, if not no need to save them
				// we use two seemingly identical conditions (== and !array_diff_assoc()), as both are necessary
				// to handle values like "" and "0" correctly (not skip updating the prefs)
				if (!empty($old_prefs[$app]) && $old_prefs[$app] == $value &&
					!array_diff_assoc($old_prefs[$app] ?? [], $value))
				{
					continue;
				}
				if (!$changed++) $this->db->transaction_begin();

				if (!is_array($value) || !$value)
				{
					$this->db->delete($this->table, array(
						'preference_owner' => $account_id,
						'preference_app'   => $app,
					), __LINE__, __FILE__);
					unset($prefs[$app]);
				}
				else
				{
					$this->db->insert($this->table,array(
						'preference_value' => json_encode($value),
					),array(
						'preference_owner' => $account_id,
						'preference_app'   => $app,
					),__LINE__,__FILE__);
				}
			}
			if ($changed)
			{
				$this->db->transaction_commit();

				// update instance-wide cache
				Cache::setInstance(__CLASS__, $account_id, $prefs);
			}
		}
		//else error_log(__METHOD__."(type=$type) NOT saved because old_prefs[$account_id] == prefs=".array2string($prefs));
		return $this->data;
	}

	/**
	 * returns the custom email-address (if set) or generates a default one
	 *
	 * This will generate the appropriate email address used as the "From:"
	 * email address when the user sends email, the localpert * part. The "personal"
	 * part is generated elsewhere.
	 * In the absence of a custom ['email']['address'], this function should be used to set it.
	 *
	 * @access public
	 * @param int $account_id as determined in and/or passed to "create_email_preferences"
	 * @return string with email-address
	 */
	function email_address($account_id='')
	{
		if (isset($this->data['email']['address']))
		{
			return $this->data['email']['address'];
		}
		// if email-address is set in the account, return it
		if (($email = Accounts::id2name($account_id,'account_email')))
		{
			return $email;
		}
		$prefs_email_address = Accounts::id2name($account_id);
		if ($prefs_email_address && strpos($prefs_email_address,'@') === False)
		{
			$prefs_email_address .= '@' . $GLOBALS['egw_info']['server']['mail_suffix'];
		}
		return $prefs_email_address;
	}

	/**
	 * Try to guess and set a locale supported by the server, with fallback to 'en_EN' and 'C'
	 *
	 * This method uses the language and nationalty set in the users common prefs.
	 *
	 * @param $category =LC_MESSAGES category to set, see setlocal function
	 * @param $charset =null default system charset
	 * @return string the local (or best estimate) set
	 */
	static function setlocale($category=LC_MESSAGES,$charset=null)
	{
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$country = $GLOBALS['egw_info']['user']['preferences']['common']['country'];

		if (strlen($lang) == 2)
		{
			$country_from_lang = strtoupper($lang);
		}
		else
		{
			list($lang,$lang2) = explode('-',$lang);
			$country_from_lang = strtoupper($lang2);
		}
		if (is_null($charset)) $charset = Translation::charset();

		foreach(array(
			$lang.'_'.$country,
			$lang.'_'.$country_from_lang,
			$lang.'_'.$country.'.utf8',
			$lang.'_'.$country_from_lang.'.utf8',
			$lang,
			'en_US',
			'C',
		) as $local)
		{
			if (($ret = setlocale($category,$local.'.'.$charset)) ||
				($ret = setlocale($category,$local.'@'.$charset)) ||
				($ret = setlocale($category,$local)))
			{
				//error_log(__METHOD__."($category,$charset) lang=$lang, country=$country, country_from_lang=$country_from_lang: returning '$ret'");
				return $ret;
			}
		}
		error_log(__METHOD__."($category,$charset) lang=$lang, country=$country, country_from_lang=$country_from_lang: Could not set local!");
		return false;	// should not happen, as the 'C' local should at least be available everywhere
	}

	/**
	 * Get preferences by calling various hooks to supply them
	 *
	 * @param string $_appname appname or 'common'
	 * @param string $type ='user' 'default', 'forced', 'user' or 'group'
	 * @param int|string $account_id =null account_id for user or group prefs, or "forced" or "default"
	 * @return array
	 */
	public static function settings($_appname, $type='user', $account_id=null)
	{
		$all_settings = [];
		$appname = $_appname == 'common' ? 'preferences' : $_appname;

		// make type available, to hooks from applications can use it, eg. activesync
		$hook_data = array(
			'location' => 'settings',
			'type' => $type,
			'account_id' => $account_id,
		);
		$GLOBALS['type'] = $type;	// old global variable

		// calling app specific settings hook
		$settings = Hooks::single($hook_data, $appname);
		// it either returns the settings or save it in $GLOBALS['settings'] (deprecated!)
		if (isset($settings) && is_array($settings) && $settings)
		{
			$all_settings = array_merge($all_settings, $settings);
		}
		elseif(isset($GLOBALS['settings']) && is_array($GLOBALS['settings']) && $GLOBALS['settings'])
		{
			$all_settings = array_merge($all_settings, $GLOBALS['settings']);
		}
		else
		{
			return False;	// no settings returned
		}

		// calling settings hook all apps can answer (for a specific app)
		$hook_data['location'] = 'settings_'.$appname;
		foreach(Hooks::process($hook_data, $appname,true) as $settings)
		{
			if (isset($settings) && is_array($settings) && $settings)
			{
				$all_settings = array_merge($all_settings,$settings);
			}
		}
		return $all_settings;
	}
}