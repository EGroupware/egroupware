<?php
/**
 * eGgroupWare admin - admin command base class
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * admin comand base class
 */
abstract class admin_cmd
{
	const deleted    = 0;
	const scheduled  = 1;
	const successful = 2;
	const failed     = 3;
	const pending    = 4;
	const queued     = 5;	// command waits to be fetched from remote

	/**
	 * The status of the command, one of either scheduled, successful, failed or deleted
	 *
	 * @var int
	 */
	protected $status;

	static $stati = array(
		admin_cmd::scheduled  => 'scheduled',
		admin_cmd::successful => 'successful',
		admin_cmd::failed     => 'failed',
		admin_cmd::deleted    => 'deleted',
		admin_cmd::pending    => 'pending',
		admin_cmd::queued     => 'queued',
	);

	protected $created;
	protected $creator;
	protected $creator_email;
	private $scheduled;
	private $modified;
	private $modifier;
	private $modifier_email;
	protected $error;
	protected $errno;
	public $requested;
	public $requested_email;
	public $comment;
	private $id;
	protected $uid;
	private $type = __CLASS__;
	public $remote_id;

	/**
	 * Stores the data of the derived classes
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Instance of the accounts class, after calling instanciate_accounts!
	 *
	 * @var accounts
	 */
	static protected $accounts;

	/**
	 * Instance of the acl class, after calling instanciate_acl!
	 *
	 * @var acl
	 */
	static protected $acl;

	/**
	 * Instance of so_sql for egw_admin_queue
	 *
	 * @var so_sql
	 */
	static private $sql;

	/**
	 * Instance of so_sql for egw_admin_remote
	 *
	 * @var so_sql
	 */
	static private $remote;

	/**
	 * Executes the command
	 *
	 * @param boolean $check_only=false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception()
	 */
	protected abstract function exec($check_only=false);

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __tostring()
	{
		return $this->type;
	}

	/**
	 * Constructor
	 *
	 * @param array $data class vars as array
	 */
	function __construct(array $data)
	{
		$this->created = time();
		$this->creator = $GLOBALS['egw_info']['user']['account_id'];
		$this->creator_email = admin_cmd::user_email();

		$this->type = get_class($this);

		foreach($data as $name => $value)
		{
			$this->$name = $name == 'data' && !is_array($value) ? json_php_unserialize($value) : $value;
		}
		//_debug_array($this); exit;
	}

	/**
	 * runs the command either immediatly ($time=null) or shedules it for the given time
	 *
	 * The command will be written to the database queue, incl. its scheduled start time or execution status
	 *
	 * @param int $time=null timestamp to run the command or null to run it immediatly
	 * @param boolean $set_modifier=null should the current user be set as modifier, default true
	 * @param booelan $skip_checks=false do not yet run the checks for a scheduled command
	 * @param boolean $dry_run=false only run checks, NOT command itself
	 * @return mixed return value of the command
	 * @throws Exceptions on error
	 */
	function run($time=null,$set_modifier=true,$skip_checks=false,$dry_run=false)
	{
		if (!is_null($time))
		{
			$this->scheduled = $time;
			$this->status = admin_cmd::scheduled;
			$ret = lang('Command scheduled to run at %1',date('Y-m-d H:i',$time));
			// running the checks of the arguments for local commands, if not explicitly requested to not run them
			if (!$this->remote_id && !$skip_checks)
			{
				try {
					$this->exec(true);
				}
				catch (Exception $e) {
					$this->error = $e->getMessage();
					$ret = $this->errno = $e->getCode();
					$this->status = admin_cmd::failed;
					$dont_save = true;
				}
			}
		}
		else
		{
			try {
				if (!$this->remote_id)
				{
					$ret = $this->exec($dry_run);
				}
				else
				{
					$ret = $this->remote_exec($dry_run);
				}
				if (is_null($this->status)) $this->status = admin_cmd::successful;
			}
			catch (Exception $e) {
				$this->error = $e->getMessage();
				$ret = $this->errno = $e->getCode();
				$this->status = admin_cmd::failed;
			}
		}
		if (!$dont_save && !$dry_run && !$this->save($set_modifier))
		{
			throw new egw_exception_db(lang('Error saving the command!'));
		}
		if ($e instanceof Exception)
		{
			throw $e;
		}
		return $ret;
	}

	/**
	 * Runs a command on a remote install
	 *
	 * This is a very basic remote procedure call to an other egw instance.
	 * The payload / command data is send as POST request to the remote installs admin/remote.php script.
	 * The remote domain (eGW instance) and the secret authenticating the request are send as GET parameters.
	 *
	 * To authenticate with the installation we use a secret, which is a md5 hash build from the uid
	 * of the command (to not allow to send new commands with an earsdroped secret) and the md5 hash
	 * of the md5 hash of the config password and the install_id (egw_admin_remote.remote_hash)
	 *
	 * @return string sussess message
	 * @throws Exception(lang('Invalid remote id or name "%1"!',$id_or_name),997) or other Exceptions reported from remote
	 */
	protected function remote_exec()
	{
		if (!($remote = $this->read_remote($this->remote_id)))
		{
			throw new egw_exception_wrong_userinput(lang('Invalid remote id or name "%1"!',$id_or_name),997);
		}
		if (!$this->uid)
		{
			$this->save();	// to get the uid
		}
		$secret = md5($this->uid.$remote['remote_hash']);

		$postdata = $this->as_array();
		if (is_object($GLOBALS['egw']->translation))
		{
			$postdata = $GLOBALS['egw']->translation->convert($postdata,$GLOBALS['egw']->translation->charset(),'utf-8');
		}
		// dont send the id's which have no meaning on the remote install
		foreach(array('id','creator','modifier','requested','remote_id') as $name)
		{
			unset($postdata[$name]);
		}
		$opts = array('http' =>
		    array(
		        'method'  => 'POST',
		        'header'  => 'Content-type: application/x-www-form-urlencoded',
		        'content' => http_build_query($postdata),
		    )
		);
		$url = $remote['remote_url'].'/admin/remote.php?domain='.urlencode($remote['remote_domain']).'&secret='.urlencode($secret);
		//echo "sending command to $url\n"; _debug_array($opts);
		if (!($message = @file_get_contents($url, false, stream_context_create($opts))))
		{
			throw new egw_exception(lang('Could not remote execute the command').': '.$http_response_header[0]);
		}
		//echo "got: $message\n";

		if (($value = json_php_unserialize($message)) !== false && $message !== serialize(false))
		{
			$message = $value;
		}
		if (is_object($GLOBALS['egw']->translation))
		{
			$message = $GLOBALS['egw']->translation->convert($message,'utf-8');
		}
		if (is_string($message) && preg_match('/^([0-9]+) (.*)$/',$message,$matches))
		{
			throw new egw_exception($matches[2],(int)$matches[1]);
		}
		return $message;
	}

	/**
	 * Delete / canncels a scheduled command
	 *
	 * @return boolean true on success, false otherwise
	 */
	function delete()
	{
		if ($this->status != admin_cmd::scheduled) return false;

		$this->status = admin_cmd::deleted;

		return $this->save();
	}

	/**
	 * Saving the object to the database
	 *
	 * @param boolean $set_modifier=true set the current user as modifier or 0 (= run by the system)
	 * @return boolean true on success, false otherwise
	 */
	function save($set_modifier=true)
	{
		admin_cmd::_instanciate_sql();

		// check if uid already exists --> set the id to not try to insert it again (resulting in SQL error)
		if (!$this->id && $this->uid && (list($other) = self::$sql->search(array('cmd_uid' => $this->uid))))
		{
			$this->id = $other['id'];
		}
		if (!is_null($this->id))
		{
			$this->modified = time();
			$this->modifier = $set_modifier ? $GLOBALS['egw_info']['user']['account_id'] : 0;
			if ($set_modifier) $this->modifier_email = admin_cmd::user_email();
		}
		if (version_compare(PHP_VERSION,'5.1.2','>'))
		{
			$vars = get_object_vars($this);	// does not work in php5.1.2 due a bug
		}
		else
		{
			foreach(array_keys(get_class_vars(__CLASS__)) as $name)
			{
				$vars[$name] = $this->$name;
			}
		}
		$vars['data'] = json_encode($this->data);	// data is stored serialized

		admin_cmd::$sql->init($vars);
		if (admin_cmd::$sql->save() != 0)
		{
			return false;
		}
		if (!$this->id)
		{
			$this->id = admin_cmd::$sql->data['id'];
			// if the cmd has no uid yet, we create one from our id and the install-id of this eGW instance
			if (!$this->uid)
			{
				$this->uid = $this->id.'-'.$GLOBALS['egw_info']['server']['install_id'];
				admin_cmd::$sql->save(array('uid' => $this->uid));
			}
		}
		// install an async job, if we saved a scheduled job
		if ($this->status == admin_cmd::scheduled)
		{
			admin_cmd::_set_async_job();
		}
		return true;
	}

	/**
	 * reading a command from the queue returning the comand object
	 *
	 * @static
	 * @param int/string $id id or uid of the command
	 * @return admin_cmd or null if record not found
	 * @throws Exception(lang('Unknown command %1!',$class),0);
	 */
	static function read($id)
	{
		admin_cmd::_instanciate_sql();

		$keys = is_numeric($id) ? array('id' => $id) : array('uid' => $id);
		if (!($data = admin_cmd::$sql->read($keys)))
		{
			return $data;
		}
		return admin_cmd::instanciate($data);
	}

	/**
	 * Instanciated the object / subclass using the given data
	 *
	 * @static
	 * @param array $data
	 * @return admin_cmd
	 * @throws egw_exception_wrong_parameter if class does not exist or is no instance of admin_cmd
	 */
	static function instanciate(array $data)
	{
		if (isset($data['data']) && !is_array($data['data']))
		{
			$data['data'] = json_php_unserialize($data['data']);
		}
		if (!class_exists($class = $data['type']) || $class == 'admin_cmd')
		{
			throw new egw_exception_wrong_parameter(lang('Unknown command %1!',$class),0);
		}
		$cmd = new $class($data);

		if ($cmd instanceof admin_cmd)	// dont allow others classes to be executed that way!
		{
			return $cmd;
		}
		throw new egw_exception_wrong_parameter(lang('%1 is no command!',$class),0);
	}

	/**
	 * calling get_rows of our static so_sql instance
	 *
	 * @static
	 * @param array $query
	 * @param array &$rows
	 * @param array $readonlys
	 * @return int
	 */
	static function get_rows($query,&$rows,$readonlys)
	{
		admin_cmd::_instanciate_sql();

		if ((string)$query['col_filter']['remote_id'] === '0')
		{
			$query['col_filter']['remote_id'] = null;
		}
		return admin_cmd::$sql->get_rows($query,$rows,$readonlys);
	}

	/**
	 * calling search method of our static so_sql instance
	 *
	 * @static
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string/array $only_keys=true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @return array
	 */
	static function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		admin_cmd::_instanciate_sql();

		return admin_cmd::$sql->search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
	}

	/**
	 * Instanciate our static so_sql object for egw_admin_queue
	 *
	 * @static
	 */
	private static function _instanciate_sql()
	{
		if (is_null(admin_cmd::$sql))
		{
			admin_cmd::$sql = new so_sql('admin','egw_admin_queue',null,'cmd_');
		}
	}

	/**
	 * Instanciate our static so_sql object for egw_admin_remote
	 *
	 * @static
	 */
	private static function _instanciate_remote()
	{
		if (is_null(admin_cmd::$remote))
		{
			admin_cmd::$remote = new so_sql('admin','egw_admin_remote');
		}
	}

	/**
	 * magic method to read a property, all non admin-cmd properties are stored in the data array
	 *
	 * @param string $property
	 * @return mixed
	 */
	function __get($property)
	{
		if (property_exists('admin_cmd',$property))
		{
			return $this->$property;	// making all (non static) class vars readonly available
		}
		switch($property)
		{
			case 'accounts':
				self::_instanciate_accounts();
				return self::$accounts;
		}
		return $this->data[$property];
	}

	/**
	 * magic method to check if a property is set, all non admin-cmd properties are stored in the data array
	 *
	 * @param string $property
	 * @return boolean
	 */
	function __isset($property)
	{
		if (property_exists('admin_cmd',$property))
		{
			return isset($this->$property);	// making all (non static) class vars readonly available
		}
		return isset($this->data[$property]);
	}

	/**
	 * magic method to set a property, all non admin-cmd properties are stored in the data array
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return mixed
	 */
	function __set($property,$value)
	{
		$this->data[$property] = $value;
	}

	/**
	 * magic method to unset a property, all non admin-cmd properties are stored in the data array
	 *
	 * @param string $property
	 */
	function __unset($property)
	{
		unset($this->data[$property]);
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * @return array
	 */
	function as_array()
	{
		if (version_compare(PHP_VERSION,'5.1.2','>'))
		{
			$vars = get_object_vars($this);	// does not work in php5.1.2 due a bug
		}
		else
		{
			foreach(array_keys(get_class_vars(__CLASS__)) as $name)
			{
				$vars[$name] = $this->$name;
			}
		}
		unset($vars['data']);
		if ($this->data) $vars = array_merge($this->data,$vars);

		return $vars;
	}

	/**
	 * Check if the creator is still admin and has the neccessary admin rights
	 *
	 * @param string $extra_acl=null further admin rights to check, eg. 'account_access'
	 * @param int $extra_deny=null further admin rights to check, eg. 16 = deny edit accounts
	 * @throws egw_exception_no_admin
	 */
	protected function _check_admin($extra_acl=null,$extra_deny=null)
	{
		if ($this->creator)
		{
			admin_cmd::_instanciate_acl($this->creator);
			// todo: check only if and with $this->creator
			if (!admin_cmd::$acl->check('run',1,'admin') &&		// creator is no longer admin
				$extra_acl && $extra_deny && admin_cmd::$acl->check($extra_acl,$extra_deny,'admin'))	// creator is explicitly forbidden to do something
			{
				throw new egw_exception_no_permission_admin();
			}
		}
	}

	/**
	 * parse application names, titles or localised names and return array of app-names
	 *
	 * @param array $apps names, titles or localised names
	 * @return array of app-names
	 * @throws egw_exception_wrong_userinput lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8
	 */
	static function parse_apps(array $apps)
	{
		foreach($apps as $key => $name)
		{
			if (!isset($GLOBALS['egw_info']['apps'][$name]))
			{
				foreach($GLOBALS['egw_info']['apps'] as $app => $data)	// check against title and localised name
				{
					if (!strcasecmp($name,$data['title']) || !strcasecmp($name,lang($app)))
					{
						$apps[$key] = $name = $app;
						break;
					}
				}
			}
			if (!isset($GLOBALS['egw_info']['apps'][$name]))
			{
				throw new egw_exception_wrong_userinput(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
			}
		}
		return $apps;
	}

	/**
	 * parse account name or id
	 *
	 * @param string/int $account account_id or account_lid
	 * @param boolean $allow_only_user=null true=only user, false=only groups, default both
	 * @return int/array account_id
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$account),15);
	 * @throws egw_exception_wrong_userinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only_user?lang('user'):lang('group')),15);
	 */
	static function parse_account($account,$allow_only_user=null)
	{
		admin_cmd::_instanciate_accounts();

		if (!($type = admin_cmd::$accounts->exists($account)) ||
			!is_numeric($id=$account) && !($id = admin_cmd::$accounts->name2id($account)))
		{
			throw new egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$account),15);
		}
		if (!is_null($allow_only_user) && $allow_only_user !== ($type == 1))
		{
			throw new egw_exception_wrong_userinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only_user?lang('user'):lang('group')),15);
		}
		if ($type == 2 && $id > 0) $id = -$id;	// groups use negative id's internally, fix it, if user given the wrong sign

		return $id;
	}

	/**
	 * parse account names or ids
	 *
	 * @param string/int/array $accounts array or comma-separated account_id's or account_lid's
	 * @param boolean $allow_only_user=null true=only user, false=only groups, default both
	 * @return array of account_id's or null if none specified
	 * @throws egw_exception_wrong_userinput(lang("Unknown account: %1 !!!",$account),15);
	 * @throws egw_exception_wrong_userinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only?lang('user'):lang('group')),15);
	 */
	static function parse_accounts($accounts,$allow_only_user=null)
	{
		if (!$accounts) return null;

		$ids = array();
		foreach(is_array($accounts) ? $accounts : explode(',',$accounts) as $account)
		{
			$ids[] = admin_cmd::parse_account($account,$allow_only_user);
		}
		return $ids;
	}

	/**
	 * Parses a date into an integer timestamp
	 *
	 * @param string $date
	 * @return int timestamp
	 * @throws egw_exception_wrong_userinput(lang('Invalid formated date "%1"!',$datein),6);
	 */
	static function parse_date($date)
	{
		if (!is_numeric($date))	// we allow to input a timestamp
		{
			$datein = $date;
			// convert german DD.MM.YYYY format into ISO YYYY-MM-DD format
			$date = preg_replace('/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})$/','\3-\2-\1',$date);

			if (($date = strtotime($date))  === false)
			{
				throw new egw_exception_wrong_userinput(lang('Invalid formated date "%1"!',$datein),6);
			}
		}
		return (int)$date;
	}

	/**
	 * Parse a boolean value
	 *
	 * @param string $value
	 * @param boolean $default=null
	 * @return boolean
	 * @throws egw_exception_wrong_userinput(lang('Invalid value "%1" use yes or no!',$value),998);
	 */
	static function parse_boolean($value,$default=null)
	{
		if (is_null($value) || (string)$value === '')
		{
			return $default;
		}
		if (in_array($value,array('1','yes','true',lang('yes'),lang('true'))))
		{
			return true;
		}
		if (in_array($value,array('0','no','false',lang('no'),lang('false'))))
		{
			return false;
		}
		throw new egw_exception_wrong_userinput(lang('Invalid value "%1" use yes or no!',$value),998);
	}

	/**
	 * Parse a remote id or name and return the remote_id
	 *
	 * @param string $id_or_name
	 * @return int remote_id
	 * @throws egw_exception_wrong_userinput(lang('Invalid remote id or name "%1"!',$id_or_name),997);
	 */
	static function parse_remote($id_or_name)
	{
		admin_cmd::_instanciate_remote();

		if (!($remotes = admin_cmd::$remote->search(array(
			'remote_id' => $id_or_name,
			'remote_name' => $id_or_name,
			'remote_domain' => $id_or_name,
		),true,'','','',false,'OR')) || count($remotes) != 1)
		{
			throw new egw_exception_wrong_userinput(lang('Invalid remote id or name "%1"!',$id_or_name),997);
		}
		return $remotes[0]['remote_id'];
	}

	/**
	 * Instanciated accounts class
	 *
	 * @todo accounts class instanciation for setup
	 * @throws egw_exception_assertion_failed(lang('%1 class not instanciated','accounts'),999);
	 */
	static function _instanciate_accounts()
	{
		if (!is_object(admin_cmd::$accounts))
		{
			if (!is_object($GLOBALS['egw']->accounts))
			{
				throw new egw_exception_assertion_failed(lang('%1 class not instanciated','accounts'),999);
			}
			admin_cmd::$accounts = $GLOBALS['egw']->accounts;
		}
	}

	/**
	 * Instanciated acl class
	 *
	 * @todo acl class instanciation for setup
	 * @param int $account=null account_id the class needs to be instanciated for, default need only account-independent methods
	 * @throws egw_exception_assertion_failed(lang('%1 class not instanciated','acl'),999);
	 */
	protected function _instanciate_acl($account=null)
	{
		if (!is_object(admin_cmd::$acl) || $account && admin_cmd::$acl->account_id != $account)
		{
			if (!is_object($GLOBALS['egw']->acl))
			{
				throw new egw_exception_assertion_failed(lang('%1 class not instanciated','acl'),999);
			}
			if ($account && $GLOBALS['egw']->acl->account_id != $account)
			{
				admin_cmd::$acl = new acl($account);
				admin_cmd::$acl->read_repository();
			}
			else
			{
				admin_cmd::$acl = $GLOBALS['egw']->acl;
			}
		}
	}

	/**
	 * RFC822 email address of the an account, eg. "Ralf Becker <RalfBecker@egroupware.org>"
	 *
	 * @param $account_id=null account_id, default current user
	 * @return string
	 */
	static function user_email($account_id=null)
	{
		if ($account_id)
		{
			admin_cmd::_instanciate_accounts();
			$fullname = admin_cmd::$accounts->id2name($account_id,'account_fullname');
			$email = admin_cmd::$accounts->id2name($account_id,'account_email');
		}
		else
		{
			$fullname = $GLOBALS['egw_info']['user']['account_fullname'];
			$email = $GLOBALS['egw_info']['user']['account_email'];
		}
		return $fullname . ($email ? ' <'.$email.'>' : '');
	}

	/**
	 * Semaphore to not permanently set new jobs, while we running the current ones
	 *
	 * @var boolean
	 */
	private static $running_queued_jobs = false;
	const async_job_id = 'admin-command-queue';

	/**
	 * Setup an async job to run the next scheduled command
	 *
	 * Only needs to be called if a new command gets scheduled
	 *
	 * @return boolean true if job installed, false if not necessary
	 */
	private static function _set_async_job()
	{
		if (admin_cmd::$running_queued_jobs)
		{
			return false;
		}
		if (!($jobs = admin_cmd::search(array(),false,'cmd_scheduled','','',false,'AND',array(0,1),array(
			'cmd_status' => admin_cmd::scheduled,
		))))
		{
			return false;		// no schduled command, no need to setup the job
		}
		$next = $jobs[0];
		if (($time = $next['scheduled']) < time())	// should run immediatly
		{
			return admin_cmd::run_queued_jobs();
		}
		include_once(EGW_API_INC.'/class.asyncservice.inc.php');
		$async = new asyncservice();

		// we cant use this class as callback, as it's abstract and ExecMethod used by the async service instanciated the class!
		list($app) = explode('_',$class=$next['type']);
		$callback = $app.'.'.$class.'.run_queued_jobs';

		$async->cancel_timer(admin_cmd::async_job_id);	// we delete it in case a job already exists
		return $async->set_timer($time,admin_cmd::async_job_id,$callback,null,$next['creator']);
	}

	/**
	 * Callback for our async job
	 *
	 * @return boolean true if new job got installed, false if not necessary
	 */
	static function run_queued_jobs()
	{
		if (!($jobs = admin_cmd::search(array(),false,'cmd_scheduled','','',false,'AND',false,array(
			'cmd_status' => admin_cmd::scheduled,
			'cmd_scheduled <= '.time(),
		))))
		{
			return false;		// no schduled commands, no need to setup the job
		}
		admin_cmd::$running_queued_jobs = true;	// stop admin_cmd::run() which calls admin_cmd::save() to install a new job

		foreach($jobs as $job)
		{
			try {
				$cmd = admin_cmd::instanciate($job);
				$cmd->run(null,false);	// false = dont set current user as modifier, as job is run by the queue/system itself
			}
			catch (Exception $e) {	// we need to mark that command as failed, to prevent further execution
				admin_cmd::$sql->init($job);
				admin_cmd::$sql->save(array(
					'status' => admin_cmd::failed,
					'error'  => lang('Unknown command %1!',$job['type']),
					'errno'  => 0,
				));
			}
		}
		admin_cmd::$running_queued_jobs = false;

		return admin_cmd::_set_async_job();
	}

	/**
	 * Return a list of defined remote instances
	 *
	 * @return array remote_id => remote_name pairs, plus 0 => local
	 */
	static function remote_sites()
	{
		admin_cmd::_instanciate_remote();

		$sites = array(lang('local'));
		if ($remote = admin_cmd::$remote->query_list('remote_name','remote_id'))
		{
			$sites = array_merge($sites,$remote);
		}
		return $sites;
	}

	/**
	 * get_rows for remote instances
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	static function get_remotes($query,&$rows,&$readonlys)
	{
		admin_cmd::_instanciate_remote();

		return admin_cmd::$remote->get_rows($query,$rows,$readonlys);
	}

	/**
	 * Read data of a remote instance
	 *
	 * @param array/int $keys
	 * @return array
	 */
	static function read_remote($keys)
	{
		admin_cmd::_instanciate_remote();

		return admin_cmd::$remote->read($keys);
	}

	/**
	 * Save / adds a remote instance
	 *
	 * @param array $data
	 * @return int remote_id
	 */
	static function save_remote(array $data)
	{
		admin_cmd::_instanciate_remote();

		if ($data['install_id'] && $data['config_passwd'])	// calculate hash
		{
			$data['remote_hash'] = self::remote_hash($data['install_id'],$data['config_passwd']);
		}
		elseif (!$data['remote_hash'] && !($data['install_id'] && $data['config_passwd']))
		{
			throw new egw_exception_wrong_userinput(lang('Either Install ID AND config password needed OR the remote hash!'));
		}
		//_debug_array($data);
		admin_cmd::$remote->init($data);

		// check if a unique key constrain would be violated by saving the entry
		if (($num = admin_cmd::$remote->not_unique()))
		{
			$col = admin_cmd::$remote->table_def['uc'][$num-1];	// $num is 1 based!
			throw new egw_exception_db_not_unique(lang('Value for column %1 is not unique!',$this->table_name.'.'.$col),$num);
		}
		if (admin_cmd::$remote->save() != 0)
		{
			throw new egw_exception_db(lang('Error saving to db:').' '.$this->sql->db->Error.' ('.$this->sql->db->Errno.')',$this->sql->db->Errno);
		}
		return admin_cmd::$remote->data['remote_id'];
	}

	/**
	 * Calculate the remote hash from install_id and config_passwd
	 *
	 * @param string $install_id
	 * @param string $config_passwd
	 * @return string 32char md5 hash
	 */
	static function remote_hash($install_id,$config_passwd)
	{
		if (empty($config_passwd) || !self::is_md5($install_id))
		{
			throw new egw_exception_wrong_parameter(empty($config_passwd)?'Empty config password':'install_id no md5 hash');
		}
		if (!self::is_md5($config_passwd)) $config_passwd = md5($config_passwd);

		return md5($config_passwd.$install_id);
	}

	/**
	 * displays an account specified by it's id or lid
	 *
	 * We show the value given by the user, plus the full name in brackets.
	 *
	 * @param int/string $account
	 * @return string
	 */
	static function display_account($account)
	{
		$id = is_numeric($account) ? $account : $GLOBALS['egw']->accounts->id2name($account);

		return $account.' ('.$GLOBALS['egw']->common->grab_owner_name($id).')';
	}

	/**
	 * Check if string is a md5 hash (32 chars of 0-9 or a-f)
	 *
	 * @param string $str
	 * @return boolean
	 */
	static function is_md5($str)
	{
		return preg_match('/^[0-9a-f]{32}$/',$str);
	}

	/**
	 * Check if the current command has the right crediential to be excuted remotely
	 *
	 * Command can reimplement that method, to allow eg. anonymous execution.
	 *
	 * This default implementation use a secret to authenticate with the installation,
	 * which is a md5 hash build from the uid of the command (to not allow to send new
	 * commands with an earsdroped secret) and the md5 hash of the md5 hash of the
	 * config password and the install_id (egw_admin_remote.remote_hash)
	 *
	 * @param string $secret hash used to authenticate the command (
	 * @param string $config_passwd of the current domain
	 * @throws egw_exception_no_permission
	 */
	function check_remote_access($secret,$config_passwd)
	{
		// as a security measure remote administration need to be enabled under Admin > Site configuration
		list(,$remote_admin_install_id) = explode('-',$this->uid);
		$allowed_remote_admin_ids = $GLOBALS['egw_info']['server']['allow_remote_admin'] ? explode(',',$GLOBALS['egw_info']['server']['allow_remote_admin']) : array();

		// to authenticate with the installation we use a secret, which is a md5 hash build from the uid
		// of the command (to not allow to send new commands with an earsdroped secret) and the md5 hash
		// of the md5 hash of the config password and the install_id (egw_admin_remote.remote_hash)
		if (is_null($config_passwd) || is_numeric($this->uid) || !in_array($remote_admin_install_id,$allowed_remote_admin_ids) ||
			$secret != ($md5=md5($this->uid.$this->remote_hash($GLOBALS['egw_info']['server']['install_id'],$config_passwd))))
		{
			//die("secret='$secret' != '$md5', is_null($config_passwd)=".is_null($config_passwd).", uid=$this->uid, remote_install_id=$remote_admin_install_id, allowed: ".implode(', ',$allowed_remote_admin_ids));
			$msg = lang('Permission denied!');
			if (!in_array($remote_admin_install_id,$allowed_remote_admin_ids))
			{
				$msg .= "\n".lang('Remote administration need to be enabled in the remote instance under Admin > Site configuration!');
			}
			throw new egw_exception_no_permission($msg,0);
		}
	}

	/**
	 * Return a rand string, eg. to generate passwords
	 *
	 * @param int $len=16
	 * @return string
	 */
	static function randomstring($len=16)
	{
		static $usedchars = array(
			'0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f',
			'g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v',
			'w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L',
			'M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
			'@','!','$','%','&','/','(',')','=','?',';',':','#','_','-','<',
			'>','|','{','[',']','}',	// dont add \,'" as we have problems dealing with them
		);

		$str = '';
		for($i=0; $i < $len; $i++)
		{
			$str .= $usedchars[mt_rand(0,count($usedchars)-1)];
		}
		return $str;
	}
}
