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
	static private $configs = array();

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
		if (!is_object(self::$db))
		{
			self::init_db();
		}
		if (is_array($this->config_data))
		{
			self::$db->lock(array(config::TABLE));
			foreach($this->config_data as $name => $value)
			{
				$this->save_value($name,$value);
			}
			foreach(self::$configs[$this->appname] as $name => $value)
			{
				if (!isset($this->config_data[$name]))	// has been deleted
				{
					self::$db->delete(config::TABLE,array('config_app'=>$this->appname,'config_name'=>$name),__LINE__,__FILE__);
				}
			}
			self::$db->unlock();

			if ($this->appname == 'phpgwapi' && method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
			{
				$GLOBALS['egw']->invalidate_session_cache();	// in case egw_info is cached in the session (phpgwapi is in egw_info[server])
			}
			self::$configs[$this->appname] = $this->config_data;
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
	/* static */ function save_value($name,$value,$app=null)
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
		//echo "<p>config::save_value('$name','".print_r($value,True)."','$app')</p>\n";
		if (isset(self::$configs[$app][$name]) && self::$configs[$app][$name] === $value)
		{
			return True;	// no change ==> exit
		}

		if (isset(self::$configs[$app]))
		{
			self::$configs[$app][$name] = $value;
		}
		if(is_array($value))
		{
			$value = serialize($value);
		}
		if (!is_object(self::$db))
		{
			self::init_db();
		}
		if (!isset($value) || $value === '')
		{
			if (isset(self::$configs[$app])) unset(self::$configs[$app][$name]);
			return self::$db->delete(config::TABLE,array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
		}
		return self::$db->insert(config::TABLE,array('config_value'=>$value),array('config_app'=>$app,'config_name'=>$name),__LINE__,__FILE__);
	}

	/**
	 * deletes the whole repository for $this->appname, appname has to be set via the constructor
	 *
	 */
	function delete_repository()
	{
		if (!is_object(self::$db))
		{
			self::init_db();
		}
		self::$db->delete(config::TABLE,array('config_app' => $this->appname),__LINE__,__FILE__);

		unset(self::$configs[$this->appname]);
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
		$config =& self::$configs[$app];

		if (!isset($config))
		{
			if (!is_object(self::$db))
			{
				self::init_db();
			}
			$config = array();
			foreach(self::$db->select(config::TABLE,'*',array('config_app' => $app),__LINE__,__FILE__) as $row)
			{
				$name = $row['config_name'];
				$value = $row['config_value'];

				$test = @unserialize($value);
				if($test === false)
				{
					// manually retrieve the string lengths of the serialized array if unserialize failed
					$test = @unserialize(preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.mb_strlen('$2','8bit').':\"$2\";'", $value));
				}
 
				$config[$name] = is_array($test) ? $test : $value;
			}
		}
		return $config;
	}

	/**
	 * get customfield array of an application
	 *
	 * @param string $app
	 * @param boolean $all_private_too=false should all the private fields be returned too, default no
	 * @return array with customfields
	 */
	static function get_customfields($app,$all_private_too=false)
	{
		$config = self::read($app);
		$config_name = isset($config['customfields']) ? 'customfields' : 'custom_fields';

		$cfs = is_array($config[$config_name]) ? $config[$config_name] : array();

		if (!$all_private_too)
		{
			foreach($cfs as $name => $field)
			{
				if ($field['private'] && !self::_check_private_cf($field['private']))
				{
					unset($cfs[$name]);
				}
			}
		}
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
	 * Initialise our db
	 *
	 * We use a reference here (no clone), as we no longer use egw_db::row() or egw_db::next_record()!
	 *
	 */
	private static function init_db()
	{
		if (is_object($GLOBALS['egw']->db))
		{
			config::$db = $GLOBALS['egw']->db;
		}
		else
		{
			config::$db = $GLOBALS['egw_setup']->db;
		}
	}
}
