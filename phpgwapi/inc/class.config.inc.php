<?php
/**
 * eGW's application configuration in a centralized location
 *
 * This allows eGroupWare to use php or database sessions
 *
 * @link www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org> original class Copyright (C) 2000, 2001 Joseph Engo
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eGW's application configuration in a centralized location
 */
class config
{
	/**
	 * Name of the config table
	 *
	 */
	const TABLE = 'egw_config';
	/**
	 * Reference to the global db class
	 *
	 * @var egw_db
	 */
	static private $db;
	/**
	 * Cache for the config data shared by all instances of this class
	 *
	 * @var array
	 */
	static private $configs;

	/**
	 * app the particular config class is instanciated for
	 *
	 * @var string
	 */
	private $appname;
	/**
	 * actual config-data of the instanciated class
	 *
	 * @deprecated dont use direct
	 * @var array
	 */
	public $config_data;

	/**
	 * Constructor for the old non-static use
	 *
	 * @param string $appname
	 */
	function __construct($appname = '')
	{
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}
		$this->appname = $appname;
	}

	/**
	 * reads the whole repository for $this->appname, appname has to be set via the constructor
	 *
	 * You can also use the static config::read($app) method, without instanciating the class.
	 *
	 * @return array the whole config-array for that app
	 */
	function read_repository()
	{
		$this->config_data = self::read($this->appname);

		//echo __CLASS__.'::'.__METHOD__."() this->appname=$this->appname\n"; _debug_array($this->config_data);

		return $this->config_data;
	}

	/**
	 * updates the whole repository for $this->appname, you have to call read_repository() before (!)
	 */
	function save_repository()
	{
		if (is_array($this->config_data))
		{
			self::$db->lock(array(config::TABLE));
			foreach($this->config_data as $name => $value)
			{
				$this->save_value($name,$value,null,false);
			}
			foreach(self::$configs[$this->appname] as $name => $value)
			{
				if (!isset($this->config_data[$name]))	// has been deleted
				{
					$this->save_value($name,null,null,false);
					//self::$db->delete(config::TABLE,array('config_app'=>$this->appname,'config_name'=>$name),__LINE__,__FILE__);
				}
			}
			self::$db->unlock();

			if ($this->appname == 'phpgwapi' && method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
			{
				$GLOBALS['egw']->invalidate_session_cache();	// in case egw_info is cached in the session (phpgwapi is in egw_info[server])
			}
			self::$configs[$this->appname] = $this->config_data;

			egw_cache::setInstance(__CLASS__, 'configs', self::$configs);
		}
	}

	/**
	 * updates or insert a single config-value direct into the database
	 *
	 * Can be used static, if $app given!
	 *
	 * @param string $name name of the config-value
	 * @param mixed $value content, empty or null values are not saved, but deleted
	 * @param string $app=null app-name, defaults to $this->appname set via the constructor
	 */
	/* static */ function save_value($name,$value,$app=null,$update_cache=true)
	{
		if (!$app && (!isset($this) || !is_a($this,__CLASS__)))
		{
			throw new egw_exception_assertion_failed('$app parameter required for static call of config::save_value($name,$value,$app)!');
		}
		//echo "<p>config::save_value('$name','".print_r($value,True)."','$app')</p>\n";
		if (!$app || isset($this) && is_a($this,__CLASS__) && $app == $this->appname)
		{
			$app = $this->appname;
			$this->config_data[$name] = $value;
		}
		if (!isset(self::$configs))
		{
			self::init_static();
		}
		//echo "<p>config::save_value('$name','".print_r($value,True)."','$app')</p>\n";
		if (isset(self::$configs[$app][$name]) && self::$configs[$app][$name] === $value)
		{
			return True;	// no change ==> exit
		}

		if(is_array($value))
		{
			$value = serialize($value);
		}
		if (!isset($value) || $value === '')
		{
			if (isset(self::$configs[$app])) unset(self::$configs[$app][$name]);
			$ok = self::$db->delete(config::TABLE,array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}
		else
		{
			self::$configs[$app][$name] = $value;
			$ok = self::$db->insert(config::TABLE,array('config_value'=>$value),array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}
		if ($update_cache) egw_cache::setInstance(__CLASS__, 'configs', self::$configs);

		return $ok;
	}

	/**
	 * deletes the whole repository for $this->appname, appname has to be set via the constructor
	 *
	 */
	function delete_repository()
	{
		if (!isset(self::$configs))
		{
			self::init_static();
		}
		self::$db->delete(config::TABLE,array('config_app' => $this->appname),__LINE__,__FILE__);

		unset(self::$configs[$this->appname]);
		egw_cache::setInstance(__CLASS__, 'configs', self::$configs);
	}

	/**
	 * deletes a single value from the repository, you need to call save_repository after
	 *
	 * @param $variable_name string name of the config
	 */
	function delete_value($variable_name)
	{
		unset($this->config_data[$variable_name]);
	}

	/**
	 * sets a single value in the repositry, you need to call save_repository after
	 *
	 * @param $variable_name string name of the config
	 * @param $variable_data mixed the content
	 */
	function value($variable_name,$variable_data)
	{
		$this->config_data[$variable_name] = $variable_data;
	}

	/**
	 * Reads the configuration for an applications
	 *
	 * Does some caching to not read it twice (in the same request)
	 *
	 * @param string $app
	 * @return array
	 */
	static function read($app)
	{
		if (!isset(self::$configs))
		{
			self::init_static();
		}
		return self::$configs[$app];
	}

	/**
	 * get customfield array of an application
	 *
	 * @param string $app
	 * @param boolean $all_private_too=false should all the private fields be returned too, default no
	 * @param string $only_type2=null if given only return fields of type2 == $only_type2
	 * @return array with customfields
	 */
	static function get_customfields($app,$all_private_too=false, $only_type2=null)
	{
		$config = self::read($app);
		$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';

		$cfs = is_array($config[$config_name]) ? $config[$config_name] : array();

		foreach($cfs as $name => $field)
		{
			if (!$all_private_too && $field['private'] && !self::_check_private_cf($field['private']) ||
				$only_type2 && $field['type2'] && !in_array($only_type2, explode(',', $field['type2'])))
			{
				unset($cfs[$name]);
			}
		}
		//error_log(__METHOD__."('$app', $all_private_too, '$only_type2') returning fields: ".implode(', ', array_keys($cfs)));
		return $cfs;
	}

	/**
	 * Check if user is allowed to see a certain private cf
	 *
	 * @param string $private comma-separated list of user- or group-id's
	 * @return boolean true if user has access, false otherwise
	 */
	private static function _check_private_cf($private)
	{
		static $user_and_memberships;

		if (!$private)
		{
			return true;
		}
		if (is_null($user_and_memberships))
		{
			$user_and_memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true);
			$user_and_memberships[] = $GLOBALS['egw_info']['user']['account_id'];
		}
		if (!is_array($private)) $private = explode(',',$private);

		return (boolean) array_intersect($private,$user_and_memberships);
	}

	/**
	 * get_content_types of using application
	 *
	 * @param string $app
	 * @return array with content-types
	 */
	static function get_content_types($app)
	{
		$config = self::read($app);

		return is_array($config['types']) ? $config['types'] : array();
	}

	/**
	 * Return configuration for all apps, save to be transmitted to browser
	 *
	 * You can add further values to the white-list, but keep in mind they are publicly visible (eg. via anon user of sitemgr)!!!
	 *
	 * @return array
	 */
	static public function clientConfigs()
	{
		static $white_list = array(
			'all' => array('customfields', 'types'),
			'phpgwapi' => array('webserver_url','server_timezone','enforce_ssl','system_charset',
				'checkfornewversion','checkappversions','email_address_format',	// admin >> site config
				'site_title','login_logo_file','login_logo_url','login_logo_title','favicon_file',
				'markuntranslated','link_list_thumbnail','enabled_spellcheck',
				'call_link','call_popup',	// addressbook
				'hide_birthdays'),	// calendar
			'projectmanager' => array('hours_per_workday', 'duration_units'),
			'manual' => array('manual_remote_egw_url'),
			'infolog' => array('status'),
			'timesheet' => array('status_labels'),
		);
		if (!isset(self::$configs))
		{
			self::init_static();
		}
		$client_config = array();
		foreach(self::$configs as $app => $config)
		{
			foreach($config as $name => $value)
			{
				if (strpos($name, 'pass') !== false) continue;

				if (in_array($name, $white_list['all']) || isset($white_list[$app]) && in_array($name, $white_list[$app]))
				{
					$client_config[$app][$name] = $value;
				}
			}
		}
		return $client_config;
	}

	/**
	 * Initialise our db
	 *
	 * We use a reference here (no clone), as we no longer use egw_db::row() or egw_db::next_record()!
	 *
	 */
	private static function init_static()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			config::$db = $GLOBALS['egw']->db;
		}
		else
		{
			config::$db = $GLOBALS['egw_setup']->db;
		}
		if (!(self::$configs = egw_cache::getInstance(__CLASS__, 'configs')))
		{
			self::$configs = array();
			foreach(self::$db->select(config::TABLE,'*',false,__LINE__,__FILE__) as $row)
			{
				$app = $row['config_app'];
				$name = $row['config_name'];
				$value = $row['config_value'];

				$test = @unserialize($value);
				if($test === false)
				{
					// manually retrieve the string lengths of the serialized array if unserialize failed
					$test = @unserialize(preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.mb_strlen('$2','8bit').':\"$2\";'", $value));
				}
				self::$configs[$app][$name] = is_array($test) ? $test : $value;
			}
			egw_cache::setInstance(__CLASS__, 'configs', self::$configs);
		}
	}
}
