<?php
/**
 * eGgroupWare setup - test or create the database
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: test or create the database
 */
class setup_cmd_database extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Maximum length of database name (at least for MySQL this is the limit)
	 *
	 * @var int
	 */
	const MAX_DB_NAME_LEN = 16;

	/**
	 * Instance of egw_db to connect or create the db
	 *
	 * @var egw_db
	 */
	private $test_db;

	/**
	 * Constructor
	 *
	 * @param string/array $domain domain-name to customize the defaults or array with all parameters
	 * @param string $db_type db-type (mysql, pgsql, ...)
	 * @param string $db_host=null
	 * @param string $db_port=null
	 * @param string $db_name=null
	 * @param string $db_user=null
	 * @param string $db_pass=null
	 * @param string $db_root=null
	 * @param string $db_root_pw=null
	 * @param string $sub_command='create_db' 'create_db', 'test_db', 'test_db_root'
	 * @param string $db_grant_host='localhost' host/ip of webserver for grant
	 * @param boolean $make_db_name_unique=false true: if create fails because db exists,
	 * 	try creating a unique name by shortening the name and adding a number to it
	 */
	function __construct($domain,$db_type=null,$db_host=null,$db_port=null,$db_name=null,$db_user=null,$db_pass=null,
		$db_root=null,$db_root_pw=null,$sub_command='create_db',$db_grant_host='localhost',$make_db_name_unique=false)
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'  => $domain,
				'db_type' => $db_type,
				'db_host' => $db_host,
				'db_port' => $db_port,
				'db_name' => $db_name,
				'db_user' => $db_user,
				'db_pass' => $db_pass,
				'db_root' => $db_root,
				'db_root_pw' => $db_root_pw,
				'sub_command' => $sub_command,
				'db_grant_host' => $db_grant_host,
				'make_db_name_unique' => $make_db_name_unique,
			);
		}
		//error_log(__METHOD__.'('.array2string($domain).") make_db_name_unique=".array2string($domain['make_db_name_unique']));
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: test or create database
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception(lang('Wrong credentials to access the header.inc.php file!'),2);
	 * @throws Exception('header.inc.php not found!');
	 */
	protected function exec($check_only=false)
	{
		if (!empty($this->domain) && !preg_match('/^([a-z0-9_-]+\.)*[a-z0-9]+/i',$this->domain))
		{
			throw new egw_exception_wrong_userinput(lang("'%1' is no valid domain name!",$this->domain));
		}
		if ($this->remote_id && $check_only) return true;	// further checks can only done locally

		$this->_merge_defaults();
		//_debug_array($this->as_array());

		try {
			switch($this->sub_command)
			{
				case 'test_db_root':
					$msg = $this->connect($this->db_root,$this->db_root_pw,$this->db_meta);
					break;
				case 'test_db':
					$msg = $this->connect();
					break;
				case 'drop':
					$msg = $this->drop();
					break;
				case 'create_db':
				default:
					$msg = $this->create();
					break;
			}
		}
		catch (Exception $e) {
			// we catch the exception to properly restore the db
		}
		$this->restore_db();

		if ($e)
		{
			throw $e;
		}
		return $msg;
	}

	/**
	 * Connect to database
	 *
	 * @param string $user=null default $this->db_user
	 * @param string $pass=null default $this->db_pass
	 * @param string $name=null default $this->db_name
	 * @throws egw_exception_wrong_userinput Can not connect to database ...
	 */
	private function connect($user=null,$pass=null,$name=null)
	{
		if (is_null($user)) $user = $this->db_user;
		if (is_null($pass)) $pass = $this->db_pass;
		if (is_null($name)) $name = $this->db_name;

		$this->test_db = new egw_db();

		$error_rep = error_reporting();
		error_reporting($error_rep & ~E_WARNING);	// switch warnings of, in case they are on
		try {
			$this->test_db->connect($name,$this->db_host,$this->db_port,$user,$pass,$this->db_type);
		}
		catch (Exception $e) {
			// just give a nicer error, after switching error_reporting on again
		}
		error_reporting($error_rep);

		if ($e)
		{
			throw new egw_exception_wrong_userinput(lang('Can not connect to %1 database %2 on host %3 using user %4!',
				$this->db_type,$name,$this->db_host.($this->db_port?':'.$this->db_port:''),$user).' ('.$e->getMessage().')');
		}
		return lang('Successful connected to %1 database %2 on %3 using user %4.',
				$this->db_type,$name,$this->db_host.($this->db_port?':'.$this->db_port:''),$user);
	}

	/**
	 * Check and if does not yet exist create the new database and user
	 *
	 * The check will fail if the database exists, but already contains tables
	 *
	 * if $this->make_db_name_unique is set, a decrementing nummeric prefix gets
	 * added to $this->db_name AND $this->db_user, if db already exists.
	 *
	 * @return string with success message
	 * @throws egw_exception_wrong_userinput
	 */
	private function create()
	{
		static $try_make_unique = 0;	// to limit trials to create a unique name

		// shorten db-name/-user to self::MAX_DB_NAME_LEN chars
		if ($this->make_db_name_unique && strlen($this->db_name) > self::MAX_DB_NAME_LEN)
		{
			$this->set_defaults['db_name'] = $this->db_name =
			$this->set_defaults['db_user'] = $this->db_user = // change user too (otherwise existing user/db could not connect any more!)
				substr(str_replace(array('.', '-'), '_', $this->db_name),0,self::MAX_DB_NAME_LEN);
		}
		try {
			$msg = $this->connect();
		}
		catch (egw_exception_wrong_userinput $e) {
			// db or user not working --> connect as root and create it
			try {
				$this->test_db->create_database($this->db_root,$this->db_root_pw,$this->db_charset,$this->db_grant_host);
				$this->connect();
			}
			catch(egw_exception_db $e) {	// catches failed to create database
				// try connect as root to check if wrong root/root_pw is the problem
				$this->connect($this->db_root,$this->db_root_pw,$this->db_meta);

				// if we should create a db with a unique name (try it only N times, not endless!)
				if ($this->make_db_name_unique && $try_make_unique++ < 20)
				{
					// check if we can connect as root to the db to create --> db exists already
					try {
						$this->connect($this->db_root,$this->db_root_pw);
						// create new db_name by incrementing an existing numeric postfix
						if (preg_match('/([0-9]+)$/',$this->db_name,$matches))
						{
							$num = (string)(++$matches[1]);
						}
						else	// or adding one starting with 2
						{
							$num = '2';
						}
						$this->set_defaults['db_name'] = $this->db_name =
						$this->set_defaults['db_user'] = $this->db_user = // change user too (otherwise existing user/db could not connect any more!)
							substr($this->db_name,0,self::MAX_DB_NAME_LEN-strlen($num)).$num;

						return $this->create();
					}
					catch (egw_exception_wrong_userinput $e2)
					{
						// we can NOT connect to db as root --> ignore exception to give general error
					}
				}
				// if not give general error
				throw new egw_exception_wrong_userinput(lang('Can not create %1 database %2 on %3 for user %4!',
					$this->db_type,$this->db_name,$this->db_host.($this->db_port?':'.$this->db_port:''),$this->db_user));
			}
			$msg = lang('Successful connected to %1 on %3 and created database %2 for user %4.',
					$this->db_type,$this->db_name,$this->db_host.($this->db_port?':'.$this->db_port:''),$this->db_user);
		}
		// check if it already contains tables
		if (($tables = $this->test_db->table_names()))
		{
			foreach($tables as &$table)
			{
				$table = $table['table_name'];
			}
			throw new egw_exception_wrong_userinput(lang('%1 database %2 on %3 already contains the following tables:',
				$this->db_type,$this->db_name,$this->db_host.($this->db_port?':'.$this->db_port:'')).' '.
				implode(', ',$tables));
		}
		return $msg;
	}

	/**
	 * Drop database and user
	 *
	 * @return string with success message
	 * @throws egw_exception_wrong_userinput
	 * @throws egw_db_exception if database not exist
	 */
	private function drop()
	{
		$this->connect($this->db_root,$this->db_root_pw,$this->db_meta);
		$this->test_db->query('DROP DATABASE '.$this->test_db->name_quote($this->db_name),__LINE__,__FILE__);
		$msg = lang('Datebase %1 droped.',$this->db_name);
		try {
			$this->test_db->query('DROP USER '.$this->test_db->quote($this->db_user).'@'.
				$this->test_db->quote($this->db_grant_host?$this->db_grant_host:'%'),__LINE__,__FILE__);
		}
		catch (egw_db_exception $e) {
			// we make this no fatal error, as the granthost might be something else ...
			$msg .= ' '.lang('Error dropping User!');
		}
		return $msg;
	}

	/**
	 * Return default database settings for a given domain
	 *
	 * @param string $db_type='mysql'
	 * @return array
	 */
	static function defaults($db_type='mysqli')
	{
		switch($db_type)
		{
			case 'mysql':
			default:
				$db_type = $meta_db = 'mysql';
				break;
			case 'pgsql':
				$meta_db = 'template1';
				break;
		}
		return array(
			'db_type' => $db_type,
			'db_host' => 'localhost',
			'db_port' => 3306,
			'db_name' => 'egw_$domain',
			'db_user' => 'egw_$domain',
			'db_pass' => self::randomstring(),
			'db_root' => 'root',
			'db_root_pw' => '',	// not really a default
			'db_meta' => $meta_db,
			'db_charset' => 'utf-8',
			'db_grant_host' => 'localhost',
		);
	}

	/**
	 * Merges the default into the current properties, if they are empty or contain placeholders
	 */
	private function _merge_defaults()
	{
		foreach(self::defaults() as $name => $default)
		{
			if (!$this->$name)
			{
				//echo "<p>setting $name='{$this->$name}' to it's default='$default'</p>\n";
				$this->set_defaults[$name] = $this->$name = $default;
			}
			if (strpos($this->$name,'$domain') !== false)
			{
				// limit names to 16 chars (16 char is user-name limit in MySQL)
				$this->set_defaults[$name] = $this->$name =
					substr(str_replace(array('$domain','.','-'),array($this->domain,'_','_'),$this->$name),
					0,self::MAX_DB_NAME_LEN);
			}
		}
	}
}
