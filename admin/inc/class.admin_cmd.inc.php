<?php
/**
 * EGroupware admin - admin command base class
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-18 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Acl;

/**
 * Admin comand base class
 *
 * Admin commands should be used to implement and log (!) all actions admins carry
 * out using the administrative rights (regular users cant do).
 *
 * They are stored in DB table egw_admin_queue which builds a persitent log
 * of administrative actions cared out on an EGroupware installation.
 * Commands can be marked deleted (canceled for scheduled commands),
 * but they are never deleted for the table to implement a persistent log!
 *
 * All administrative actions are encapsulated in classes derived from this
 * abstract base class implementing an exec method to carry out the command.
 *
 * @property-read int $created Creation timestamp
 * @property-read int $creator Creator user-id
 * @property-read string $creator_email rfc822 address ("Name <email@domain.com>") of creator
 * @property-read int $modified Modification timestamp
 * @property-read int|NULL $scheduled timestamp if command is not run immediatly,
 *	but scheduled to run automatic by the system at a later point in time
 * @property-read int $modifier Modifier user-id
 * @property-read string $modifier_email rfc822 address ("Name <email@domain.com>") of modifier
 * @property int|NULL $requested User who requested the change (not current user!)
 * @property string|NULL $requested_email rfc822 address ("Name <email@domain.com>") of requested
 * @property string|NULL $comment comment, eg. reasoning why change was requested
 * @property-read int|NULL $errno Numerical error-code or NULL on success
 * @property-read string|NULL $error Error message or NULL on success
 * @property array|string|NULL $result Result message indicating what happened, or NULL on failure
 * @property-read int $id $id of command/row in egw_admin_queue table
 * @property-read string $uid uuid of command (necessary if command is send to a remote system to execute)
 * @property int|NULL $remote_id id of remote system, if command is not meant to run on local system
 *  foreign key into egw_admin_remote (table of remote systems administrated by this one)
 * @property-read int $account account_id of user affected by this cmd or NULL
 * @property-read string $app app-name affected by this cmd or NULL
 * @property-read string $parent parent cmd (with rrule) of single periodic execution
 * @property-read string $rrule rrule for periodic execution
 * @property int $rrule_start optional start timestamp for rrule, default $created time
 * @property string async_job_id optional name of async job for periodic-run, default "admin-cmd-$id"
 * @property array set optional New values set by the command
 * @property array old optional Previous values before the command was run
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
	 * Status which stil need passwords available
	 *
	 * @var array
	 */
	static $require_pw_stati = array(self::scheduled,self::pending,self::queued);

	/**
	 * The status of the command, one of either scheduled, successful, failed or deleted
	 *
	 * @var int
	 */
	protected $status = self::successful;

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
	protected $account;
	protected $app;
	protected $rrule;
	protected $parent;

	/**
	 * Display name of command, default ucfirst(str_replace(['_cmd_', '_'], ' ', __CLASS__))
	 */
	const NAME = null;

	/**
	 * Stores the data of the derived classes
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Instance of the Api\Accounts class, after calling instanciate_accounts!
	 *
	 * @var Api\Accounts
	 */
	static protected $accounts;

	/**
	 * Instance of the Acl class, after calling instanciate_acl!
	 *
	 * @var Acl
	 */
	static protected $acl;

	/**
	 * Instance of Api\Storage\Base for egw_admin_queue
	 *
	 * @var Api\Storage\Base
	 */
	static private $sql;

	/**
	 * Instance of Api\Storage\Base for egw_admin_remote
	 *
	 * @var Api\Storage\Base
	 */
	static private $remote;

	/**
	 * Executes the command
	 *
	 * @param boolean $check_only =false only run the checks (and throw the exceptions), but not the command itself
	 * @return string success message
	 * @throws Exception()
	 */
	protected abstract function exec($check_only=false);

	/**
	 * Return a title / string representation for a given command, eg. to display it
	 *
	 * @return string
	 */
	function __toString()
	{
		return $this->type;
	}

	/**
	 * Generate human readable name of object
	 *
	 * @return string
	 */
	public static function name()
	{
		if (self::NAME) return self::NAME;

		return ucfirst(str_replace(['_cmd_', '_', '\\'], ' ', get_called_class()));
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
	 * runs the command either immediately ($time=null) or schedules it for the given time
	 *
	 * The command will be written to the database queue, incl. its scheduled start time or execution status
	 *
	 * @param int $time =null timestamp to run the command or null to run it immediatly
	 * @param boolean $set_modifier =null should the current user be set as modifier, default true
	 * @param booelan $skip_checks =false do not yet run the checks for a scheduled command
	 * @param boolean $dry_run =false only run checks, NOT command itself
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
					_egw_log_exception($e);
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
				_egw_log_exception($e);
				$this->error = $e->getMessage();
				$ret = $this->errno = $e->getCode();
				$this->status = admin_cmd::failed;
			}
		}
		$this->result = $ret;
		if (!$dont_save && !$dry_run && !$this->save($set_modifier))
		{
			throw new Api\Db\Exception(lang('Error saving the command!'));
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
	 * @return string success message
	 * @throws Exception(lang('Invalid remote id or name "%1"!',$this->remote_id),997) or other Exceptions reported from remote
	 */
	protected function remote_exec()
	{
		if (!($remote = $this->read_remote($this->remote_id)))
		{
			throw new Api\Exception\WrongUserinput(lang('Invalid remote id or name "%1"!',$this->remote_id),997);
		}
		if (!$this->uid)
		{
			$this->save();	// to get the uid
		}
		$secret = md5($this->uid.$remote['remote_hash']);

		$postdata = $this->as_array();
		if (is_object($GLOBALS['egw']->translation))
		{
			$postdata = Api\Translation::convert($postdata,Api\Translation::charset(),'utf-8');
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
		$http_response_header = null;
		if (!($message = @file_get_contents($url, false, stream_context_create($opts))))
		{
			throw new Api\Exception(lang('Could not remote execute the command').': '.$http_response_header[0]);
		}
		//echo "got: $message\n";

		if (($value = json_php_unserialize($message)) !== false && $message !== serialize(false))
		{
			$message = $value;
		}
		if (is_object($GLOBALS['egw']->translation))
		{
			$message = Api\Translation::convert($message,'utf-8');
		}
		$matches = null;
		if (is_string($message) && preg_match('/^([0-9]+) (.*)$/',$message,$matches))
		{
			throw new Api\Exception($matches[2],(int)$matches[1]);
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
		$this->cancel_periodic_job();
		if ($this->status != admin_cmd::scheduled) return false;

		$this->status = admin_cmd::deleted;

		return $this->save();
	}

	/**
	 * Saving the object to the database
	 *
	 * @param boolean $set_modifier =true set the current user as modifier or 0 (= run by the system)
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
		$vars = get_object_vars($this);	// does not work in php5.1.2 due a bug

		// data is stored serialized
		// paswords are masked / removed, if we dont need them anymore
		$vars['data'] = in_array($this->status, self::$require_pw_stati) ?
			json_encode($this->data) : self::mask_passwords($this->data);

		// skip EGroupware\\ prefix in new class-names, as value gets too long for column otherwise
		if (strpos($this->type, 'EGroupware\\') === 0)
		{
			$vars['type'] = substr($this->type, 11);
		}

		try
		{
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
					$this->uid = $this->id . '-' . $GLOBALS['egw_info']['server']['install_id'];
					admin_cmd::$sql->save(array('uid' => $this->uid));
				}
			}
			// install an async job, if we saved a scheduled job
			if ($this->status == admin_cmd::scheduled && empty($this->rrule))
			{
				admin_cmd::_set_async_job();
			}
			// schedule periodic execution, if we have a rrule
			elseif (!empty($this->rrule) && $this->status != admin_cmd::deleted)
			{
				$this->set_periodic_job();
			}
			// existing object with no rrule, cancel evtl. running periodic job
			elseif ($vars['id'])
			{
				$this->cancel_periodic_job();
			}
		}
		catch (Api\Db\Exception $e) {
			_egw_log_exception($e);
			return false;
		}
		return true;
	}

	/**
	 * Mask / remove passwords in $data
	 *
	 * @param string|array $data json or php-encoded string or array
	 * @param boolean $return_serialized =true true: return json serialized string, false: return array
	 * @return string|array see $return_serialized
	 */
	static function mask_passwords($data, $return_serialized=true)
	{
		if (!is_array($data))
		{
			$data = json_php_unserialize($data);
		}
		foreach($data as $key => &$value)
		{
			if (is_array($value))
			{
				$value = self::mask_passwords($value, false);
			}
			elseif (preg_match('/(pw|passwd_?\d*|(?<!change)password|db_pass|secret)$/i', $key))
			{
				$value = str_repeat('*', strlen($value));
			}
		}
		return $return_serialized ? json_encode($data) : $data;
	}

	/**
	 * Reading a command from the queue returning the comand object
	 *
	 * @static
	 * @param int|string $id id or uid of the command
	 * @return admin_cmd or null if record not found
	 * @throws Api\Exception\WrongParameter if class does not exist or is no instance of admin_cmd
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
	 * Instantiate the object / subclass using the given data
	 *
	 * @static
	 * @param array $data
	 * @return admin_cmd
	 * @throws Api\Exception\WrongParameter if class does not exist or is no instance of admin_cmd
	 */
	static function instanciate(array $data)
	{
		if (isset($data['data']) && !is_array($data['data']))
		{
			$data['data'] = json_php_unserialize($data['data']);
		}
		// "Readonly" policy need to be renamed (to "Readonlys") as readonly is a reserved word in PHP 8.1+
		if ($data['type'] === 'Policy\\Policies\\Readonly')
		{
			$data['type'] .= 's';
			$data['policy'] .= 's';
		}
		if (!(class_exists($class = 'EGroupware\\'.$data['type']) ||	// namespaced class
			class_exists($class = $data['type'])) || $data['type'] == 'admin_cmd')
		{
			throw new Api\Exception\WrongParameter(lang('Unknown command %1!',$class), 10);
		}
		$cmd = new $class($data);

		if ($cmd instanceof admin_cmd)	// dont allow others classes to be executed that way!
		{
			return $cmd;
		}
		throw new Api\Exception\WrongParameter(lang('%1 is no command!',$class), 10);
	}

	/**
	 * calling get_rows of our static Api\Storage\Base instance
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
		if ((string)$query['col_filter']['periodic'] === '0')
		{
			$query['col_filter']['rrule'] = null;
		}
		else if ((string)$query['col_filter']['periodic'] === '1')
		{
			$query['col_filter'][] = 'cmd_rrule IS NOT NULL';
		}
		unset($query['col_filter']['periodic']);
		if($query['col_filter']['parent'])
		{
			$query['col_filter']['parent'] = (int)$query['col_filter']['parent'];
		}

		$total = admin_cmd::$sql->get_rows($query,$rows,$readonlys);

		if (!$rows) return 0;

		$async = new Api\Asyncservice();
		foreach($rows as &$row)
		{
			try {
				$cmd = admin_cmd::instanciate($row);
				$row['title'] = $cmd->__tostring();	// we call __tostring explicit, as a cast to string requires php5.2+
			}
			catch (Exception $e) {
				$row['title'] = $e->getMessage();
			}

			$row['value'] = $cmd->value;

			if(method_exists($cmd, 'summary'))
			{
				$row['data'] = $cmd->summary();
			}
			else
			{
				$row['data'] = !($data = json_php_unserialize($row['data'])) ? '' :
					json_encode($data+(empty($row['rrule'])?array():array('rrule' => $row['rrule'])),
						JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			}
			if($row['rrule'])
			{
				$rrule = calendar_rrule::event2rrule(calendar_rrule::parseRrule($row['rrule'],true)+array(
					'start' => time(),
					'tzid'=> Api\DateTime::$server_timezone->getName()
				));
				$row['rrule'] = ''.$rrule;
			}
			if(!$row['scheduled'] && $cmd && $cmd->async_job_id)
			{
				$job = $async->read($cmd->async_job_id);

				$row['scheduled'] = $job ? $job[$cmd->async_job_id]['next'] : null;
			}
			if ($row['status'] == admin_cmd::scheduled)
			{
				$row['class'] = 'AllowDelete';
			}
		}
		return $total;
	}

	/**
	 * Get list of stored or available (admin) cmd classes/types
	 *
	 * @return array class => label pairs
	 */
	static function get_cmd_labels()
	{
		return Api\Cache::getInstance(__CLASS__, 'cmd_labels', function()
		{
			admin_cmd::_instanciate_sql();

			// Need a new one to avoid column name modification
			$sql = new Api\Storage\Base('admin','egw_admin_queue',null);
			$labels = $sql->query_list('cmd_type');

			// for admin app we also add all available cmd objects
			foreach(scandir(__DIR__) as $file)
			{
				$matches = null;
				if (preg_match('/^class\.(admin_cmd_.*)\.inc\.php$/', $file, $matches))
				{
					if (!isset($labels[$matches[1]]))
					{
						$labels[$matches[1]] = $matches[1];
					}
				}
			}
			foreach($labels as $class => &$label)
			{
				if(class_exists($class))
				{
					$label = $class::name();
				}
				elseif (class_exists('EGroupware\\' . $class) ||
					// "Readonly" policy need to be renamed (to "Readonlys") as readonly is a reserved word in PHP 8.1+
					class_exists('EGroupware\\' . ($class .= 's')))
				{
					$class = 'EGroupware\\' . $class;
					$label = $class::name();
				}
				else
				{
					unset($labels[$class]);
				}
			}

			// sort them alphabetic
			uasort($labels, function($a, $b)
			{
				return strcasecmp($a, $b);
			});

			return $labels;
		});
	}

	/**
	 * calling search method of our static Api\Storage\Base instance
	 *
	 * @static
	 * @param array|string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean|string|array $only_keys =true True returns only keys, False returns all cols. or
	 *	comma seperated list or array of columns to return
	 * @param string $order_by ='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string|array $extra_cols ='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard ='' appended befor and after each criteria
	 * @param boolean $empty =false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op ='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start =false if != false, return only maxmatch rows begining with start, or array($start,$num), or 'UNION' for a part of a union query
	 * @param array $filter =null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @return array
	 */
	static function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null)
	{
		admin_cmd::_instanciate_sql();

		return admin_cmd::$sql->search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter);
	}

	/**
	 * Instanciate our static Api\Storage\Base object for egw_admin_queue
	 *
	 * @static
	 */
	private static function _instanciate_sql()
	{
		if (is_null(admin_cmd::$sql))
		{
			admin_cmd::$sql = new Api\Storage\Base('admin','egw_admin_queue',null,'cmd_');
		}
	}

	/**
	 * Instanciate our static Api\Storage\Base object for egw_admin_remote
	 *
	 * @static
	 */
	private static function _instanciate_remote()
	{
		if (is_null(admin_cmd::$remote))
		{
			admin_cmd::$remote = new Api\Storage\Base('admin','egw_admin_remote');
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
			case 'data':
				return $this->data;
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
	 * @param string $extra_acl =null further admin rights to check, eg. 'account_access'
	 * @param int $extra_deny =null further admin rights to check, eg. 16 = deny edit Api\Accounts
	 * @throws Api\Exception\NoPermission\Admin
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
				throw new Api\Exception\NoPermission\Admin();
			}
		}
	}

	/**
	 * parse application names, titles or localised names and return array of app-names
	 *
	 * @param array $apps names, titles or localised names
	 * @return array of app-names
	 * @throws Api\Exception\WrongUserinput lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8
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
				throw new Api\Exception\WrongUserinput(lang("Application '%1' not found (maybe not installed or misspelled)!",$name),8);
			}
		}
		return $apps;
	}

	/**
	 * parse account name or id
	 *
	 * @param string|int $account account_id or account_lid
	 * @param boolean $allow_only_user =null true=only user, false=only groups, default both
	 * @return int/array account_id
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$account), 15);
	 * @throws Api\Exception\WrongUserinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only_user?lang('user'):lang('group')), 16);
	 */
	static function parse_account($account,$allow_only_user=null)
	{
		admin_cmd::_instanciate_accounts();

		if (!($type = admin_cmd::$accounts->exists($account)) ||
			!is_numeric($id=$account) && !($id = admin_cmd::$accounts->name2id($account)))
		{
			throw new Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$account), 15);
		}
		if (!is_null($allow_only_user) && $allow_only_user !== ($type == 1))
		{
			throw new Api\Exception\WrongUserinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only_user?lang('user'):lang('group')), 16);
		}
		if ($type == 2 && $id > 0) $id = -$id;	// groups use negative id's internally, fix it, if user given the wrong sign

		return $id;
	}

	/**
	 * parse account names or ids
	 *
	 * @param string|int|array $accounts array or comma-separated account_id's or account_lid's
	 * @param boolean $allow_only_user =null true=only user, false=only groups, default both
	 * @return array of account_id's or null if none specified
	 * @throws Api\Exception\WrongUserinput(lang("Unknown account: %1 !!!",$account), 15);
	 * @throws Api\Exception\WrongUserinput(lang("Wrong account type: %1 is NO %2 !!!",$account,$allow_only?lang('user'):lang('group')), 16);
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
	 * @throws Api\Exception\WrongUserinput(lang('Invalid formated date "%1"!',$datein),6);
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
				throw new Api\Exception\WrongUserinput(lang('Invalid formated date "%1"!',$datein),6);
			}
		}
		return (int)$date;
	}

	/**
	 * Parse a boolean value
	 *
	 * @param string|boolean|int $value
	 * @param boolean $default =null
	 * @return boolean
	 * @throws Api\Exception\WrongUserinput(lang('Invalid value "%1" use yes or no!',$value),998);
	 */
	static function parse_boolean($value,$default=null)
	{
		if (is_bool($value) || is_int($value))
		{
			return (boolean)$value;
		}
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
		throw new Api\Exception\WrongUserinput(lang('Invalid value "%1" use yes or no!',$value),998);
	}

	/**
	 * Parse a remote id or name and return the remote_id
	 *
	 * @param string $id_or_name
	 * @return int remote_id
	 * @throws Api\Exception\WrongUserinput(lang('Invalid remote id or name "%1"!',$id_or_name),997);
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
			throw new Api\Exception\WrongUserinput(lang('Invalid remote id or name "%1"!',$id_or_name),997);
		}
		return $remotes[0]['remote_id'];
	}

	/**
	 * Instanciated Api\Accounts class
	 *
	 * @todo Api\Accounts class instanciation for setup
	 * @throws Api\Exception\AssertionFailed(lang('%1 class not instanciated','accounts'),999);
	 */
	static function _instanciate_accounts()
	{
		if (!is_object(admin_cmd::$accounts))
		{
			if (!is_object($GLOBALS['egw']->accounts))
			{
				throw new Api\Exception\AssertionFailed(lang('%1 class not instanciated','accounts'),999);
			}
			admin_cmd::$accounts = $GLOBALS['egw']->accounts;
		}
	}

	/**
	 * Instanciated Acl class
	 *
	 * @todo Acl class instanciation for setup
	 * @param int $account =null account_id the class needs to be instanciated for, default need only account-independent methods
	 * @throws Api\Exception\AssertionFailed(lang('%1 class not instanciated','acl'),999);
	 */
	protected function _instanciate_acl($account=null)
	{
		if (!is_object(admin_cmd::$acl) || $account && admin_cmd::$acl->account_id != $account)
		{
			if (!is_object($GLOBALS['egw']->acl))
			{
				throw new Api\Exception\AssertionFailed(lang('%1 class not instanciated','acl'),999);
			}
			if ($account && $GLOBALS['egw']->acl->account_id != $account)
			{
				admin_cmd::$acl = new Acl($account);
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
	 * @param $account_id =null account_id, default current user
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
		$async = new Api\Asyncservice();

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
				_egw_log_exception($e);
				admin_cmd::$sql->init($job);
				admin_cmd::$sql->save(array(
					'status' => admin_cmd::failed,
					'error'  => $e->getMessage(),
					'errno'  => $e->getcode(),
					'data'   => self::mask_passwords($job['data']),
				));
			}
		}
		admin_cmd::$running_queued_jobs = false;

		return admin_cmd::_set_async_job();
	}

	const PERIOD_ASYNC_ID_PREFIX = 'admin-cmd-';

	/**
	 * Schedule next execution of a periodic job
	 *
	 * @return boolean
	 */
	public function set_periodic_job()
	{
		if (empty($this->rrule)) return false;

		// parse rrule and calculate next execution time
		$event = calendar_rrule::parseRrule($this->rrule, true);	// true: allow HOURLY or MINUTELY
		// rrule can depend on start-time, use policy creation time by default, if rrule_start is not set
		$event['start'] = empty($this->rrule_start) ? $this->created : $this->rrule_start;
		$event['tzid'] = Api\DateTime::$server_timezone->getName();
		$rrule = calendar_rrule::event2rrule($event, false);	// false = server timezone
		$rrule->rewind();
		while((($time = $rrule->current()->format('ts'))) <= time())
		{
			$rrule->next();
		}

		// schedule run_periodic_job to run at that time
		$async = new Api\Asyncservice();
		$job_id = empty($this->async_job_id) ? self::PERIOD_ASYNC_ID_PREFIX.$this->id : $this->async_job_id;
		$async->cancel_timer($job_id);	// we delete it in case a job already exists
		return $async->set_timer($time, $job_id, __CLASS__.'::run_periodic_job', $this->as_array(), $this->creator);
	}

	/**
	 * Cancel evtl. existing periodic job
	 *
	 * @return boolean true if job was canceled, false otherwise
	 */
	public function cancel_periodic_job()
	{
		$async = new Api\Asyncservice();
		$job_id = empty($this->async_job_id) ? self::PERIOD_ASYNC_ID_PREFIX.$this->id : $this->async_job_id;
		$async->cancel_timer($job_id);	// we delete it in case a job already exists
	}

	/**
	 * Run a periodic job, record it's result and schedule next run
	 */
	static function run_periodic_job($data)
	{
		$cmd = admin_cmd::read($data['id']);

		// schedule next execution
		$cmd->set_periodic_job();

		// instanciate single periodic execution object
		$single = $cmd->as_array();
		$single['parent'] = $single['id'];
		$args = array_diff_key($single, array_flip(array(
			'id','uid',
			'created','modified','modifier',
			'async_job_id','rrule','scheduled',
			'status', 'set', 'old','value','result'
		)));

		$periodic = admin_cmd::instanciate($args);

		try {
			$value = $periodic->run(null, false);
		}
		catch (Exception $ex) {
			_egw_log_exception($ex);
			error_log(__METHOD__."(".array2string($data).") periodic execution failed: ".$ex->getMessage());
		}
		$periodic->result = $value;
		$periodic->save();
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
		if (($remote = admin_cmd::$remote->query_list('remote_name','remote_id')))
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
	 * @param array|int $keys
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
			throw new Api\Exception\WrongUserinput(lang('Either Install ID AND config password needed OR the remote hash!'));
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
			throw new Api\Db\Exception(lang('Error saving to db:').' '.$this->sql->db->Error.' ('.$this->sql->db->Errno.')',$this->sql->db->Errno);
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
			throw new Api\Exception\WrongParameter(empty($config_passwd)?'Empty Api\Config password':'install_id no md5 hash');
		}
		if (!self::is_md5($config_passwd)) $config_passwd = md5($config_passwd);

		return md5($config_passwd.$install_id);
	}

	/**
	 * displays an account specified by it's id or lid
	 *
	 * We show the value given by the user, plus the full name in brackets.
	 *
	 * @param int|string $account
	 * @return string
	 */
	static function display_account($account)
	{
		$id = is_numeric($account) ? $account : Api\Accounts::getInstance()->name2id($account);

		return $account.' ('.Api\Accounts::username($id).')';
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
	 * Api\Config password and the install_id (egw_admin_remote.remote_hash)
	 *
	 * @param string $secret hash used to authenticate the command (
	 * @param string $config_passwd of the current domain
	 * @throws Api\Exception\NoPermission
	 */
	function check_remote_access($secret,$config_passwd)
	{
		// as a security measure remote administration need to be enabled under Admin > Site configuration
		list(,$remote_admin_install_id) = explode('-',$this->uid);
		$allowed_remote_admin_ids = $GLOBALS['egw_info']['server']['allow_remote_admin'] ? explode(',',$GLOBALS['egw_info']['server']['allow_remote_admin']) : array();

		// to authenticate with the installation we use a secret, which is a md5 hash build from the uid
		// of the command (to not allow to send new commands with an earsdroped secret) and the md5 hash
		// of the md5 hash of the Api\Config password and the install_id (egw_admin_remote.remote_hash)
		if (is_null($config_passwd) || is_numeric($this->uid) || !in_array($remote_admin_install_id,$allowed_remote_admin_ids) ||
			$secret != ($md5=md5($this->uid.$this->remote_hash($GLOBALS['egw_info']['server']['install_id'],$config_passwd))))
		{
			//die("secret='$secret' != '$md5', is_null($config_passwd)=".is_null($config_passwd).", uid=$this->uid, remote_install_id=$remote_admin_install_id, allowed: ".implode(', ',$allowed_remote_admin_ids));
			unset($md5);
			$msg = lang('Permission denied!');
			if (!in_array($remote_admin_install_id,$allowed_remote_admin_ids))
			{
				$msg .= "\n".lang('Remote administration need to be enabled in the remote instance under Admin > Site configuration!');
			}
			throw new Api\Exception\NoPermission($msg,0);
		}
	}

	/**
	 * Return a rand string, eg. to generate passwords
	 *
	 * @param int $len =16
	 * @return string
	 */
	static function randomstring($len=16)
	{
		return Api\Auth::randomstring($len);
	}

	/**
	 * Get name of eTemplate used to make the change to derive UI for history
	 *
	 * @return string|null etemplate name
	 */
	protected function get_etemplate_name()
	{
		return null;
	}

	/**
	 * Return eTemplate used to make the change to derive UI for history
	 *
	 * @return Api\Etemplate|null
	 */
	protected function get_etemplate()
	{
		static $tpl = null;	// some caching to not instanciate it twice

		$name = $this->get_etemplate_name();
		if (!isset($tpl) || $tpl->id !== $name)
		{
			if (empty($name))
			{
				$tpl = false;
			}
			else
			{
				$tpl = Api\Etemplate::instance($name);
				Api\Etemplate::reset_request();
			}
		}
		return $tpl ? $tpl : null;
	}

	/**
	 * Return (human readable) labels for keys of changes
	 *
	 * @return array
	 */
	function get_change_labels()
	{
 		$labels = [];
		$label = null;
		if (($tpl = $this->get_etemplate()))
		{
			$tpl->run(function($cname, $expand, $widget) use (&$labels, &$label)
			{
				switch($widget->type)
				{
					// remember label from last description widget
					case 'description':
						if (!empty($widget->attrs['value'])) $label = $widget->attrs['value'];
						break;
					// ignore non input-widgets
					case 'hbox': case 'vbox': case 'box': case 'groupbox':
					case 'grid': case 'columns': case 'column': case 'rows': case 'row':
					case 'template': case 'tabbox': case 'tabs': case 'tab':
					// ignore buttons too
					case 'button': case 'buttononly':
						break;
					default:
						if (!empty($widget->id))
						{
							if (!empty($widget->attrs['label'])) $label = $widget->attrs['label'];
							if (!empty($label)) $labels[$widget->id] = $label;
							$label = null;
						}
						break;
				}
				unset($cname, $expand);
			}, ['', []]);
		}
		return $labels;
	}

	/**
	 * Return widget types (indexed by field key) for changes
	 *
	 * Used by historylog widget to show the changes the command recorded.
	 */
	function get_change_widgets()
	{
		static $selectboxes = ['select', 'listbox', 'menupopup', 'taglist'];

		$widgets = [];
		$last_select = null;
		if (($tpl = $this->get_etemplate()))
		{
			$tpl->run(function($cname, $expand, $widget) use (&$widgets, &$last_select, $selectboxes)
			{
				switch($widget->type)
				{
					// ignore non input-widgets
					case 'hbox': case 'vbox': case 'box': case 'groupbox':
					case 'grid': case 'columns': case 'column': case 'rows': case 'row':
					case 'template': case 'tabbox': case 'tabs': case 'tab':
					// No need for these
					case 'textbox': case 'int': case 'float':
					// ignore widgets that can't go in the historylog
					case 'button': case 'buttononly': case 'taglist-thumbnail':
						break;
					case 'radio':
						if (!is_array($widgets[$widget->id])) $widgets[$widget->id] = [];
						$label = (string)$widget->attrs['label'];
						// translate "{something} {else}" type options
						if (strpos($label, '{') !== false)
						{
							$label = preg_replace_callback('/{([^}]+)}/', function($matches)
							{
								return lang($matches[1]);
							}, $label);
						}
						$widgets[$widget->id][(string)$widget->attrs['set_value']] = $label;
						break;
					// config templates have options in the template
					case 'option':
						if (!is_array($widgets[$last_select])) $widgets[$last_select] = [];
						$label = (string)$widget->attrs['#text'];
						// translate "{something} {else}" type options
						if (strpos($label, '{') !== false)
						{
							$label = preg_replace_callback('/{([^}]+)}/', function($matches)
							{
								return lang($matches[1]);
							}, $label);
						}
						$widgets[$last_select][(string)$widget->attrs['value']] = $label;
						break;
					default:
						$last_select = null;
						if (!empty($widget->id))
						{
							$widgets[$widget->id] = $widget->type;
							if (in_array($widget->type, $selectboxes))
							{
								$last_select = $widget->id;
							}
						}
						break;
				}
				unset($cname, $expand);
			}, ['', []]);

			// remove pure selectboxes, as they would show nothing without having options
			$widgets = array_diff($widgets, $selectboxes);
		}
		return $widgets;
	}

	/**
	 * Get the result of executing the command.
	 * Should be some kind of success or results message indicating what was done.
	 */
	public function get_result()
	{
		if($this->result)
		{
			return is_array($this->result) ? implode("\n", $this->result) : $this->result;
		}
		return lang("Command was run %1 on %2",
				static::$stati[ $this->status ],
				Api\DateTime::to($this->created));
	}
}