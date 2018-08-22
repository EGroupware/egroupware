<?php
/**
 * EGroupware API - application configuration
 *
 * @link www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org> original class Copyright (C) 2000, 2001 Joseph Engo
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 */

namespace EGroupware\Api;

/**
 * Application configuration
 *
 * New config values are stored JSON serialized now instead of PHP serialized before 14.1.
 */
class Config
{
	/**
	 * Name of the config table
	 *
	 */
	const TABLE = 'egw_config';
	/**
	 * Reference to the global db class
	 *
	 * @var Db
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
	public $config_data = array();

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
	 * You can also use the static Config::read($app) method, without instanciating the class.
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
			foreach($this->config_data as $name => $value)
			{
				self::save_value($name, $value, $this->appname, false);
			}
			foreach(self::$configs[$this->appname] as $name => $value)
			{
				if (!isset($this->config_data[$name]))	// has been deleted
				{
					self::save_value($name, null, $this->appname, false);
					//self::$db->delete(self::TABLE,array('config_app'=>$this->appname,'config_name'=>$name),__LINE__,__FILE__);
				}
			}

			if ($this->appname == 'phpgwapi' && method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
			{
				$GLOBALS['egw']->invalidate_session_cache();	// in case egw_info is cached in the session (phpgwapi is in egw_info[server])
			}
			self::$configs[$this->appname] = $this->config_data;

			Cache::setInstance(__CLASS__, 'configs', self::$configs);
		}
	}

	/**
	 * updates or insert a single config-value direct into the database
	 *
	 * Can (under recent PHP version) only be used static!
	 * Use $this->value() or $this->delete_value() together with $this->save_repository() for non-static usage.
	 *
	 * @param string $name name of the config-value
	 * @param mixed $value content, empty or null values are not saved, but deleted
	 * @param string $app app-name
	 * @param boolean $update_cache =true update instance cache and for phpgwapi invalidate session-cache
	 * @throws Exception\WrongParameter if no $app parameter given for static call
	 * @return boolean|int true if no change, else number of affected rows
	 */
	static function save_value($name, $value, $app, $update_cache=true)
	{
		if (!$app)
		{
			throw new Exception\WrongParameter('$app parameter required for static Config::save_value($name, $value, $app)!');
		}
		if (!isset(self::$configs))
		{
			self::init_static();
		}

		if (isset(self::$configs[$app][$name]) && self::$configs[$app][$name] === $value)
		{
			return True;	// no change ==> exit
		}

		if (!isset($value) || $value === '')
		{
			if (isset(self::$configs[$app])) unset(self::$configs[$app][$name]);
			self::$db->delete(self::TABLE,array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}
		else
		{
			self::$configs[$app][$name] = $value;
			if(is_array($value)) $value = json_encode($value);
			self::$db->insert(self::TABLE,array('config_value'=>$value),array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}
		if ($update_cache)
		{
			if ($app == 'phpgwapi' && method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
			{
				$GLOBALS['egw']->invalidate_session_cache();	// in case egw_info is cached in the session (phpgwapi is in egw_info[server])
			}
			Cache::setInstance(__CLASS__, 'configs', self::$configs);
		}
		return self::$db->affected_rows();
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
		self::$db->delete(self::TABLE,array('config_app' => $this->appname),__LINE__,__FILE__);

		unset(self::$configs[$this->appname]);
		Cache::setInstance(__CLASS__, 'configs', self::$configs);
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
		return (array)self::$configs[$app];
	}

	/**
	 * get customfield array of an application
	 *
	 * @param string $app
	 * @param boolean $all_private_too =false should all the private fields be returned too, default no
	 * @param string $only_type2 =null if given only return fields of type2 == $only_type2
	 * @deprecated use Api\Storage\Customfields::get()
	 * @return array with customfields
	 */
	static function get_customfields($app, $all_private_too=false, $only_type2=null)
	{
		//error_log(__METHOD__."('$app', $all_private_too, $only_type2) deprecated, use Storage\Customfields::get() in ".  function_backtrace());
		return Storage\Customfields::get($app, $all_private_too, $only_type2);
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
				'markuntranslated','link_list_thumbnail','enabled_spellcheck','debug_minify',
				'call_link','call_popup','geolocation_url',	// addressbook
				'hide_birthdays','calview_no_consolidate', 'egw_tutorial_disable','fw_mobile_app_list'),	// calendar
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
			// currently not used, therefore no need to add it
			//$client_config[$app]['customfields'] = egw_customfields::get($app);
		}
		// some things need on client-side which are not direct configs
		$client_config['phpgwapi']['max_lang_time'] = Translation::max_lang_time();

		return $client_config;
	}

	/**
	 * Unserialize data from either json_encode or PHP serialize
	 *
	 * @param string $str serialized prefs
	 * @return array
	 */
	protected static function unserialize($str)
	{
		// handling of new json-encoded arrays
		if ($str[0] == '{' || $str[0] == '[')
		{
			return json_decode($str, true);
		}
		// handling of not serialized strings
		if ($str[0] != 'a' || $str[1] != ':')
		{
			return $str;
		}
		// handling of old PHP serialized config values
		$data = php_safe_unserialize($str);
		if($data === false)
		{
			// manually retrieve the string lengths of the serialized array if unserialize failed (iso / utf-8 conversation)
			$data = php_safe_unserialize(preg_replace_callback('!s:(\d+):"(.*?)";!s', function($matches)
			{
				return 's:'.mb_strlen($matches[2],'8bit').':"'.$matches[2].'";';
			}, $str));
		}
		// returning original string, if unserialize failed, eg. for "a:hello"
		return $data === false ? $str : $data;
	}

	/**
	 * Initialise class: reference to db and self::$configs cache
	 */
	public static function init_static()
	{
		// we use a reference here (no clone), as we no longer use Db::row() or Db::next_record()!
		if (isset($GLOBALS['egw_setup']) && $GLOBALS['egw_setup']->db instanceof Db)
		{
			self::$db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			self::$db = $GLOBALS['egw']->db;
		}
		// if item is not cached or cache is not looking alright --> query config from database
		if (!(self::$configs = Cache::getInstance(__CLASS__, 'configs')) || !is_array(self::$configs['phpgwapi']))
		{
			self::$configs = array();
			foreach(self::$db->select(self::TABLE,'*',false,__LINE__,__FILE__) as $row)
			{
				self::$configs[$row['config_app']][$row['config_name']] = self::unserialize($row['config_value']);
				//error_log(__METHOD__."() configs[$row[config_app]][$row[config_name]]=".array2string(self::$configs[$row['config_app']][$row['config_name']]));
			}
			Cache::setInstance(__CLASS__, 'configs', self::$configs);
		}
	}
}
