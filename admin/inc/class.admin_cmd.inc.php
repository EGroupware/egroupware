<?php
/**
 * eGgroupWare admin - admin command base class
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

/**
 * admin comand base class
 */
abstract class admin_cmd
{
	const edit_user = 16;
	
	const deleted = 0;
	const scheduled = 1;
	const successful = 2;
	const failed = 3;
	/**
	 * The status of the command, one of either scheduled, successful, failed or deleted
	 * 
	 * @var int
	 */
	private $status;

	static $stati = array(
		admin_cmd::scheduled   => 'scheduled',
		admin_cmd::successful => 'successful',
		admin_cmd::failed     => 'failed',
		admin_cmd::deleted    => 'deleted',
	);

	protected $created;
	protected $creator;
	protected $creator_email;
	private $scheduled;
	private $modified;
	private $modifier;
	private $modifier_email;
	private $error;
	private $errno;
	public $requested;
	public $requested_email;
	public $comment;
	private $id;
	private $uid;
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
	 * @return string success message
	 * @throws Exception()
	 */
	abstract function exec();
	
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
			$this->$name = $name == 'data' && !is_array($value) ? unserialize($value) : $value;
		}
		//_debug_array($this); exit;
	}
	
	/**
	 * runs the command either immediatly ($time=null) or shedules it for the given time
	 *
	 * The command will be written to the database queue, incl. its scheduled start time or execution status
	 *
	 * @param $time=null timestamp to run the command or null to run it immediatly
	 * @param $set_modifier=null should the current user be set as modifier, default true
	 * @return mixed string with execution error or success message, false for other errors
	 */
	function run($time=null,$set_modifier=true)
	{
		if (!is_null($time))
		{
			$this->scheduled = $time;
			$this->status = admin_cmd::scheduled;
			$ret = lang('Command scheduled to run at %1',date('Y-m-d H:i',$time));
		}
		else
		{
			try {
				if (!$this->remote_id)
				{
					$ret = $this->exec();
				}
				else
				{
					$ret = $this->remote_exec();
				}
				$this->status = admin_cmd::successful;
			}
			catch (Exception $e) {
				$this->error = $e->getMessage();
				$ret = $this->errno = $e->getCode();
				$this->status = admin_cmd::failed;
			}
		}
		if (!$this->save($set_modifier))
		{
			return false;
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
	private function remote_exec()
	{
		admin_cmd::_instanciate_remote();
		
		if (!($remote = admin_cmd::$remote->read($this->remote_id)))
		{
			throw new Exception(lang('Invalid remote id or name "%1"!',$id_or_name),997);
		}
		if (!$this->uid)
		{
			$this->save();	// to get the uid
		}
		$secret = md5($this->uid.$remote['remote_hash']);
		
		$postdata = $GLOBALS['egw']->translation->convert($this->as_array(),$GLOBALS['egw']->translation->charset(),'utf-8');
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
		$message = file_get_contents($url, false, stream_context_create($opts));
		//echo "got: $message\n";
		
		$message = $GLOBALS['egw']->translation->convert($message,'utf-8');
		
		if (preg_match('/^([0-9]+) (.*)$/',$message,$matches))
		{
			throw new Exception($matches[2],(int)$matches[1]);
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
		
		if (!is_null($this->id))
		{
			$this->modified = time();
			$this->modifier = $set_modifier ? $GLOBALS['egw_info']['user']['account_id'] : 0;
			if ($set_modifier) $this->modifier_email = admin_cmd::user_email();
		}
		$vars = get_object_vars($this);
		$vars['data'] = serialize($vars['data']);	// data is stored serialized

		admin_cmd::$sql->init($vars);
		if (admin_cmd::$sql->save() != 0)
		{
			return false;
		}
		if (is_null($this->id))
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
	 * @throws Exception(lang('Unknown command %1!',$class),0);
	 * @throws Exception(lang('%1 is no command!',$class),0);
	 */
	static function instanciate(array $data)
	{
		if (isset($data['data']) && !is_array($data['data']))
		{
			$data['data'] = unserialize($data['data']);
		}
		if (!class_exists($class = $data['type']))
		{
			list($app) = explode('_',$class);
			@include_once(EGW_INCLUDE_ROOT.'/'.$app.'/inc/class.'.$class.'.inc.php');
			if (!class_exists($class))
			{
				throw new Exception(lang('Unknown command %1!',$class),0);
			}
		}
		$cmd = new $class($data);
		
		if ($cmd instanceof admin_cmd)	// dont allow others classes to be executed that way!
		{
			return $cmd;
		}
		throw new Exception(lang('%1 is no command!',$class),0);
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
			include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

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
			include_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.so_sql.inc.php');

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
		return $this->data[$property];
	}
	
	/**
	 * magic method to set a property, all non admin-cmd properties are stored in the data array
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return mixed
	 */
	protected function __set($property,$value)
	{
		$this->data[$property] = $value;
	}
	
	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * @return array
	 */
	function as_array()
	{
		$vars = get_object_vars($this);
		unset($vars['data']);
		if ($this->data) $vars += $this->data;
		
		return $vars;
	}
	
	/**
	 * Check if the creator is still admin and has the neccessary admin rights
	 *
	 * @param string $extra_acl=null further admin rights to check, eg. 'account_access'
	 * @param int $extra_deny=null further admin rights to check, eg. 16 = deny edit accounts
	 * @throws Exception(lang("Permission denied !!!"),2);
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
				throw new Exception(lang("Permission denied !!!"),2);
			}
		}
	}

	/**
	 * parse application names, titles or localised names and return array of app-names
	 *
	 * @param array $apps names, titles or localised names
	 * @return array of app-names
	 * @throws Exception(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
	 */
	protected static function _parse_apps(array $apps)
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
				throw new Exception(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
			}
		}
		return $apps;
	}
	
	/**
	 * parse account name or id
	 *
	 * @param string/int $account
	 * @param boolean $allow_only=null true=only user, false=only groups, default both
	 * @return int account_id
	 * @throws Exception(lang("Unknown account: %1 !!!",$this->account),15);
	 * @throws Exception(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only?lang('user'):lang('group')),15);
	 */
	protected static function _parse_account($account,$allow_only=null)
	{
		if (!($type = admin_cmd::$accounts->exists($account)) || 
			!is_numeric($id=$account) && !($id = admin_cmd::$accounts->name2id($account)))
		{
			throw new Exception(lang("Unknown account: %1 !!!",$account),15);
		}
		if (!is_null($allow_only) && $allow_only !== ($type == 1))
		{
			throw new Exception(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only?lang('user'):lang('group')),15);
		}
		if ($type == 2 && $id > 0) $id = -$id;	// groups use negative id's internally, fix it, if user given the wrong sign
	
		return $id;
	}
	
	/**
	 * Parses a date into an integer timestamp
	 *
	 * @param string $date
	 * @return int timestamp
	 * @throws Exception(lang('Invalid formated date "%1"!',$datein),998);
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
				throw new Exception(lang('Invalid formated date "%1"!',$datein),998);
			}
		}
		return (int)$date;
	}
	
	/**
	 * Parse a remote id or name and return the remote_id
	 *
	 * @param string $id_or_name
	 * @return int remote_id
	 * @throws Exception(lang('Invalid remote id or name "%1"!',$id_or_name),997);
	 */
	static function parse_remote($id_or_name)
	{
		admin_cmd::_instanciate_remote();
		
		if (!($remotes = admin_cmd::$remote->search(array(
			'remote_id' => $id_or_name,
			'remote_name' => $id_or_name,
		),true,'','','',false,'OR')) || count($remotes) != 1)
		{
			throw new Exception(lang('Invalid remote id or name "%1"!',$id_or_name),997);
		}
		return $remotes[0]['remote_id'];
	}

	/**
	 * Instanciated accounts class
	 *
	 * @todo accounts class instanciation for setup
	 * @throws Exception(lang('%1 class not instanciated','accounts'),999);
	 */
	protected function _instanciate_accounts()
	{
		if (!is_object(admin_cmd::$accounts))
		{
			if (!is_object($GLOBALS['egw']->accounts))
			{
				throw new Exception(lang('%1 class not instanciated','accounts'),999);
			}
			admin_cmd::$accounts = $GLOBALS['egw']->accounts;
		}
	}

	/**
	 * Instanciated acl class
	 *
	 * @todo acl class instanciation for setup
	 * @param int $account=null account_id the class needs to be instanciated for, default need only account-independent methods
	 * @throws Exception(lang('%1 class not instanciated','acl'),999);
	 */
	protected function _instanciate_acl($account=null)
	{
		if (!is_object(admin_cmd::$acl) || $account && admin_cmd::$acl->account_id != $account)
		{
			if (!is_object($GLOBALS['egw']->acl))
			{
				throw new Exception(lang('%1 class not instanciated','acl'),999);
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
		$async =& new asyncservice();
		
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
}
