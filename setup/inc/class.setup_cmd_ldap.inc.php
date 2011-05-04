<?php
/**
 * eGgroupWare setup - test or create the ldap connection and hierarchy
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package setup
 * @copyright (c) 2007-10 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * setup command: test or create the ldap connection and hierarchy
 *
 * All commands can be run via setup-cli eg:
 *
 * setup/setup-cli.php --setup_cmd_ldap stylite.de,config-user,config-pw sub_command=set_mailbox \
 * 	ldap_base=dc=local ldap_admin=cn=admin,dc=local ldap_admin_pw=secret ldap_host=localhost test=1
 */
class setup_cmd_ldap extends setup_cmd
{
	/**
	 * Allow to run this command via setup-cli
	 */
	const SETUP_CLI_CALLABLE = true;

	/**
	 * Instance of ldap object
	 *
	 * @var ldap
	 */
	private $test_ldap;

	/**
	 * Constructor
	 *
	 * @param string/array $domain domain-name to customize the defaults or array with all parameters
	 * @param string $ldap_host=null
	 * @param string $ldap_suffix=null base of the whole ldap install, default "dc=local"
	 * @param string $ldap_admin=null root-dn needed to create new entries in the suffix
	 * @param string $ldap_admin_pw=null
	 * @param string $ldap_base=null base of the instance, default "o=$domain,$suffix"
	 * @param string $ldap_root_dn=null root-dn used for the instance, default "cn=admin,$base"
	 * @param string $ldap_root_pw=null
	 * @param string $ldap_context=null ou for accounts, default "ou=accounts,$base"
	 * @param string $ldap_search_filter=null search-filter for accounts, default "(uid=%user)"
	 * @param string $ldap_group_context=null ou for groups, default "ou=groups,$base"
	 * @param string $sub_command='create_ldap' 'create_ldap', 'test_ldap', 'test_ldap_root', see exec method
	 * @param string $ldap_encryption_type='des'
	 */
	function __construct($domain,$ldap_host=null,$ldap_suffix=null,$ldap_admin=null,$ldap_admin_pw=null,
		$ldap_base=null,$ldap_root_dn=null,$ldap_root_pw=null,$ldap_context=null,$ldap_search_filter=null,
		$ldap_group_context=null,$sub_command='create_ldap',$ldap_encryption_type='des')
	{
		if (!is_array($domain))
		{
			$domain = array(
				'domain'        => $domain,
				'ldap_host'     => $ldap_host,
				'ldap_suffix'   => $ldap_suffix,
				'ldap_admin'    => $ldap_admin,
				'ldap_admin_pw' => $ldap_admin_pw,
				'ldap_base'     => $ldap_base,
				'ldap_root_dn'  => $ldap_root_dn,
				'ldap_root_pw'  => $ldap_root_pw,
				'ldap_context'  => $ldap_context,
				'ldap_search_filter' => $ldap_search_filter,
				'ldap_group_context' => $ldap_group_context,
				'sub_command'   => $sub_command,
				'ldap_encryption_type' => $ldap_encryption_type,
			);
		}
		//echo __CLASS__.'::__construct()'; _debug_array($domain);
		admin_cmd::__construct($domain);
	}

	/**
	 * run the command: test or create the ldap connection and hierarchy
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

		switch($this->sub_command)
		{
			case 'test_ldap_root':
				$msg = $this->connect($this->ldap_admin,$this->ldap_admin_pw);
				break;
			case 'test_ldap':
				$msg = $this->connect();
				break;
			case 'delete_ldap':
				$msg = $this->delete_base();
				break;
			case 'users_ldap':
				$msg = $this->users();
				break;
			case 'migrate_to_ldap':
			case 'migrate_to_sql':
				$msg = $this->migrate($this->sub_command == 'migrate_to_ldap');
				break;
			case 'set_mailbox':
				$msg = $this->set_mailbox();
				break;
			case 'create_ldap':
			default:
				$msg = $this->create();
				break;
		}
		return $msg;
	}

	/**
	 * Migrate to other account storage
	 *
	 * @param boolean $to_ldap true: sql --> ldap, false: ldap --> sql
	 * @return string with success message
	 * @throws Exception on error
	 */
	private function migrate($to_ldap)
	{
		$msg = array();
		// if migrating to ldap, check ldap and create context if not yet exiting
		if ($to_ldap && !empty($this->ldap_admin_pw))
		{
			$msg[] = $this->create();
		}
		else
		{
			$msg[] = $this->connect();
		}
		// read accounts from old store
		$accounts = $this->accounts(!$to_ldap);

		// instanciate accounts obj for new store
		$accounts_obj = $this->accounts_obj($to_ldap);

		$accounts_created = $groups_created = $errors = 0;
		$target = $to_ldap ? 'LDAP' : 'SQL';
		foreach($accounts as $account_id => $account)
		{
			if (isset($this->only) && !in_array($account_id,$this->only))
			{
				continue;
			}
			$what = ($account['account_type'] == 'u' ? lang('User') : lang('Group')).' '.
				$account_id.' ('.$account['account_lid'].')';

			if ($account['account_type'] == 'u')
			{
				if ($accounts_obj->exists($account_id))
				{
					$msg[] = lang('%1 already exists in %2.',$what,$target);
					$errors++;
					continue;
				}
				if ($to_ldap)
				{
					if ($GLOBALS['egw_info']['server']['ldap_extra_attributes'])
					{
						$account['homedirectory'] = $GLOBALS['egw_info']['server']['ldap_account_home'] . '/' . $account['account_lid'];
						$account['loginshell'] = $GLOBALS['egw_info']['server']['ldap_account_shell'];
					}
					$account['account_passwd'] = self::hash_sql2ldap($account['account_pwd']);
				}
				else
				{
					$account['account_passwd'] = self::hash_ldap2sql($account['account_pwd']);
				}
				unset($account['person_id']);

				if (!$accounts_obj->save($account))
				{
					$msg[] = lang('Creation of %1 in %2 failed !!!',$what,$target);
					$errors++;
					continue;
				}
				$accounts_obj->set_memberships($account['memberships'],$account_id);
				$msg[] = lang('%1 created in %2.',$what,$target);
				$accounts_created++;

				// should we run any or some addAccount hooks
				if ($this->add_account_hook)
				{
					// setting up egw_info array with new ldap information, so hook can use ldap::ldapConnect()
					if (!$egw_info_set++)
					{
						foreach(array('ldap_host','ldap_root_dn','ldap_root_pw','ldap_context','ldap_group_context','ldap_search_filter','ldap_encryptin_type','mail_suffix','mail_login_type') as $name)
						{
							 if (!empty($this->$name)) $GLOBALS['egw_info']['server'][$name] = $this->$name;
						}
						//error_log(__METHOD__."() setup up egw_info[server]: ldap_host='{$GLOBALS['egw_info']['server']['ldap_host']}', ldap_root_dn='{$GLOBALS['egw_info']['server']['ldap_root_dn']}', ldap_root_pw='{$GLOBALS['egw_info']['server']['ldap_root_pw']}', ldap_context='{$GLOBALS['egw_info']['server']['ldap_context']}', mail_suffix='{$GLOBALS['egw_info']['server']['mail_suffix']}', mail_logig_type='{$GLOBALS['egw_info']['server']['mail_login-type']}'");
					}
					try
					{
						$account['location'] = 'addAccount';
						// running all addAccount hooks (currently NOT working, as not all work in setup)
						if ($this->add_account_hook === true)
						{
							$GLOBALS['egw']->hooks->process($account,array(),true);
						}
						elseif(is_callable($this->add_account_hook))
						{
							call_user_func($this->add_account_hook,$account);
						}
					}
					catch(Exception $e)
					{
						$msg[] = $e->getMessage();
						$errors++;
					}
				}
			}
			else
			{
				// check if group already exists
				if (!$accounts_obj->exists($account_id))
				{
					if (!$accounts_obj->save($account))
					{
						$msg[] = lang('Creation of %1 in %2 failed !!!',$what,$target);
						++$errors;
						continue;
					}
					$msg[] = lang('%1 created in %2.',$what,$target);
					$groups_created++;
				}
				else
				{
					$msg[] = lang('%1 already exists in %2.',$what,$target);
					$errors++;

					if ($accounts_obj->id2name($account_id) != $account['account_lid'])
					{
						$msg[] = lang("==> different group '%1' under that gidNumber %2, NOT setting memberships!",$account['account_lid'],$account_id);
						++$errors;
						continue;	// different group under that gidnumber!
					}
				}
				// now saving / updating the memberships
				$accounts_obj->set_members($account['members'],$account_id);
			}
		}
		$this->restore_db();

		return lang('%1 users and %2 groups created, %3 errors',$accounts_created,$groups_created,$errors).
			($errors || $this->verbose ? "\n- ".implode("\n- ",$msg) : '');
	}

	/**
	 * Convert SQL hash to LDAP hash
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function hash_sql2ldap($hash)
	{
		if (!($type = $GLOBALS['egw_info']['server']['sql_encryption_type'])) $type = 'md5';

		if (preg_match('/^\\{(.*)\\}(.*)$/',$hash,$matches))
		{
			list(,$type,$hash) = $matches;
		}
		switch(strtolower($type))
		{
			case 'plain':
				// ldap stores plaintext passwords without {plain} prefix
				break;

			case 'md5':
				$hash = base64_encode(pack("H*",$hash));
				// fall through
			default:
				$hash = '{'.strtoupper($type).'}'.$hash;
		}
		return $hash;
	}

	/**
	 * Convert LDAP hash to SQL hash
	 *
	 * @param string $hash
	 * @return string
	 */
	public static function hash_ldap2sql($hash)
	{
		if ($hash[0] != '{')	// plain has to be explicitly specified for sql, in ldap it's the default
		{
			$hash = '{PLAIN}'.$hash;
		}
		return $hash;
	}

	/**
	 * Read all accounts from sql or ldap
	 *
	 * @param boolean $from_ldap=true true: ldap, false: sql
	 * @return array
	 */
	public function accounts($from_ldap=true)
	{
		$accounts_obj = $this->accounts_obj($from_ldap);
		//error_log(__METHOD__."(from_ldap=".array2string($from_ldap).') get_class(accounts_obj->backend)='.get_class($accounts_obj->backend));

		$accounts = $accounts_obj->search(array('type' => 'both'));

		foreach($accounts as $account_id => &$account)
		{
			if ($account_id != $account['account_id'])	// not all backends have as key the account_id
			{
				unset($account);
				$account_id = $account['account_id'];
			}
			$account = $accounts_obj->read($account_id);

			if ($account['account_type'] == 'g')
			{
				$account['members'] = $accounts_obj->members($account_id,true);
			}
			else
			{
				$account['memberships'] = $accounts_obj->memberships($account_id,true);
			}
		}
		accounts::cache_invalidate();

		return $accounts;
	}

	/**
	 * Instancate accounts object from either sql of ldap
	 *
	 * @param boolean $ldap true: ldap, false: sql
	 * @return accounts
	 */
	private function accounts_obj($ldap)
	{
		static $enviroment_setup;
		if (!$enviroment_setup)
		{
			parent::_setup_enviroment($this->domain);
			$enviroment_setup = true;
		}
		if ($ldap) $this->connect();	// throws exception, if it can NOT connect

		// otherwise search does NOT work, as accounts_sql uses addressbook_bo for it
		$GLOBALS['egw_info']['server']['account_repository'] = $ldap ? 'ldap' : 'sql';

		if (!self::$egw_setup->setup_account_object(
			array(
				'account_repository' => $GLOBALS['egw_info']['server']['account_repository'],
			) + $this->as_array()) ||
			!is_a(self::$egw_setup->accounts,'accounts') ||
			!is_a(self::$egw_setup->accounts->backend,'accounts_'.($ldap?'ldap':'sql')))
		{
			throw new Exception(lang("Can NOT instancate accounts object for %1",$ldap?'LDAP':'SQL'));
		}
		return self::$egw_setup->accounts;
	}

	/**
	 * Connect to ldap server
	 *
	 * @param string $dn=null default $this->ldap_root_dn
	 * @param string $pw=null default $this->ldap_root_pw
	 * @throws egw_exception_wrong_userinput Can not connect to ldap ...
	 */
	private function connect($dn=null,$pw=null)
	{
		if (is_null($dn)) $dn = $this->ldap_root_dn;
		if (is_null($pw)) $pw = $this->ldap_root_pw;

		if (!$pw)	// ldap::ldapConnect use the current eGW's pw otherwise
		{
			throw new egw_exception_wrong_userinput(lang('You need to specify a password!'));
		}
		$this->test_ldap = new ldap();

		$error_rep = error_reporting();
		//error_reporting($error_rep & ~E_WARNING);	// switch warnings of, in case they are on
		ob_start();
		$ds = $this->test_ldap->ldapConnect($this->ldap_host,$dn,$pw);
		ob_end_clean();
		error_reporting($error_rep);

		if (!$ds)
		{
			throw new egw_exception_wrong_userinput(lang('Can not connect to LDAP server on host %1 using DN %2!',
				$this->ldap_host,$dn).($this->test_ldap->ds ? ' ('.ldap_error($this->test_ldap->ds).')' : ''));
		}
		return lang('Successful connected to LDAP server on %1 using DN %2.',$this->ldap_host,$dn);
	}

	/**
	 * Count active (not expired) users
	 *
	 * @return int number of active users
	 * @throws egw_exception_wrong_userinput
	 */
	private function users()
	{
		$this->connect();

		$sr = ldap_list($this->test_ldap->ds,$this->ldap_context,'ObjectClass=posixAccount',array('dn','shadowExpire'));
		if (!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new egw_exception('Error listing "dn=%1"!',$this->ldap_context);
		}
		$num = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;
			if (isset($entry['shadowexpire']) && $entry['shadowexpire'][0]*24*3600 < time()) continue;
			++$num;
		}
		return $num;
	}

	/**
	 * Check and if does not yet exist create the new database and user
	 *
	 * @return string with success message
	 * @throws egw_exception_wrong_userinput
	 */
	private function create()
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		foreach(array(
			$this->ldap_base => array(),
			$this->ldap_context => array(),
			$this->ldap_group_context => array(),
			$this->ldap_root_dn => array('userPassword' => auth::encrypt_ldap($this->ldap_root_pw,'ssha')),
		) as $dn => $extra)
		{
			if (!$this->_create_node($dn,$extra,$check_only) && $dn == $this->ldap_root_dn)
			{
				// ldap_root already existed, lets check the pw is correct
				$this->connect();
			}
		}
		return lang('Successful connected to LDAP server on %1 and created/checked required structur %2.',
			$this->ldap_host,$this->ldap_base);
	}

	/**
	 * Delete whole LDAP tree of an instance dn=$this->ldap_base using $this->ldap_admin/_pw
	 *
	 * @return string with success message
	 * @throws egw_exception if dn not found, not listable or delete fails
	 */
	private function delete_base()
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		// if base not set, use context minus one hierarchy, eg. ou=accounts,(o=domain,dc=local)
		if (empty($this->ldap_base) && $this->ldap_context)
		{
			list(,$this->ldap_base) = explode(',',$this->ldap_context,2);
		}
		// some precausion to not delete whole ldap tree!
		if (count(explode(',',$this->ldap_base)) < 2)
		{
			throw new egw_exception_assertion_failed(lang('Refusing to delete dn "%1"!',$this->ldap_base));
		}
		// check if base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new egw_exception_wrong_userinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}
		return lang('LDAP dn="%1" with %2 entries deleted.',
			$this->ldap_base,$this->rdelete($this->ldap_base));
	}

	/**
	 * Recursive delete a dn
	 *
	 * @param string $dn
	 * @return int integer number of deleted entries
	 * @throws egw_exception if dn not listable or delete fails
	 */
	private function rdelete($dn)
	{
		if (!($sr = ldap_list($this->test_ldap->ds,$dn,'ObjectClass=*',array(''))) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new egw_exception(lang('Error listing "dn=%1"!',$dn));
		}
		$deleted = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;
			$deleted += $this->rdelete($entry['dn']);
		}
		if (!ldap_delete($this->test_ldap->ds,$dn))
		{
			throw new egw_exception(lang('Error deleting "dn=%1"!',$dn));
		}
		return ++$deleted;
	}

	/**
	 * Set mailbox attribute in $this->ldap_base according to given format
	 *
	 * Uses $this->ldap_host, $this->ldap_admin and $this->ldap_admin_pw to connect.
	 *
	 * @param string $this->object_class='qmailUser'
	 * @param string $this->mbox_attr='mailmessagestore' lowercase!!!
	 * @param string $this->mail_login_type='email' 'email', 'vmailmgr', 'standard' or 'uidNumber'
	 * @return string with success message N entries modified
	 * @throws egw_exception if dn not found, not listable or delete fails
	 */
	private function set_mailbox()
	{
		$this->connect($this->ldap_admin,$this->ldap_admin_pw);

		// if base not set, use context minus one hierarchy, eg. ou=accounts,(o=domain,dc=local)
		if (empty($this->ldap_base) && $this->ldap_context)
		{
			list(,$this->ldap_base) = explode(',',$this->ldap_context,2);
		}
		// check if base does exist
		if (!@ldap_read($this->test_ldap->ds,$this->ldap_base,'objectClass=*'))
		{
			throw new egw_exception_wrong_userinput(lang('Base dn "%1" NOT found!',$this->ldap_base));
		}
		$object_class = $this->object_class ? $this->object_class : 'qmailUser';
		$mbox_attr = $this->mbox_attr ? $this->mbox_attr : 'mailmessagestore';
		$mail_login_type = $this->mail_login_type ? $this->mail_login_type : 'email';

		if (!($sr = ldap_search($this->test_ldap->ds,$this->ldap_base,
				'objectClass='.$object_class,array('mail','uidNumber','uid',$mbox_attr))) ||
			!($entries = ldap_get_entries($this->test_ldap->ds, $sr)))
		{
			throw new egw_exception(lang('Error listing "dn=%1"!',$this->ldap_base));
		}
		$modified = 0;
		foreach($entries as $n => $entry)
		{
			if ($n === 'count') continue;

			$mbox = emailadmin_smtp_ldap::mailbox_addr(array(
				'account_id' => $entry['uidnumber'][0],
				'account_lid' => $entry['uid'][0],
				'account_email' => $entry['mail'][0],
			),$this->domain,$mail_login_type);

			if ($mbox === $entry[$mbox_attr][0]) continue;	// nothing to change

			if (!$this->test && !ldap_modify($this->test_ldap->ds,$entry['dn'],array(
				$mbox_attr => $mbox,
			)))
			{
				throw new egw_exception(lang("Error modifying dn=%1: %2='%3'!",$dn,$mbox_attr,$mbox));
			}
			++$modified;
			if ($this->test) echo "$modified: $entry[dn]: $mbox_attr={$entry[$mbox_attr][0]} --> $mbox\n";
		}
		return $this->test ? lang('%1 entries would have been modified.',$modified) :
			lang('%1 entries modified.',$modified);
	}

	/**
	 * array with objectclasses for the objects we can create
	 *
	 * @var array of name => objectClass pairs (or array with multiple)
	 */
	static $requiredObjectclasses = array(
		'o' => 'organization',
		'ou' => 'organizationalUnit',
		'cn' => array('organizationalRole','simpleSecurityObject'),
		'uid' => array('uidObject','organizationalRole','simpleSecurityObject'),
		'dc' => array('organization','dcObject'),
	);

	/**
	 * Create a new node in the ldap tree
	 *
	 * @param string $dn dn to create, eg. "cn=admin,dc=local"
	 * @param array $extra=array() extra attributes to set
	 * @return boolean true if the node was create, false if it was already there
	 * @throws egw_exception_wrong_userinput
	 */
	private function _create_node($dn,$extra=array())
	{
		//echo "<p>_create_node($dn,".array2string($extra).")</p>\n";
		// check if the node already exists and return if it does
		if (@ldap_read($this->test_ldap->ds,$dn,'objectClass=*'))
		{
			return false;
		}
		list($node,$base) = explode(',',$dn,2);

		if (!@ldap_read($this->test_ldap->ds,$base,'objectClass=*'))
		{
			$this->_create_node($base);		// create the base if it's not already there
		}
		// now we need to create the node itself
		list($name,$value) = explode('=',$node);

		if (!isset(self::$requiredObjectclasses[$name]))
		{
			throw new egw_exception_wrong_userinput(lang('Can not create DN %1!',$dn).' '.
				lang('Supported node types:').implode(', ',array_keys(self::$requiredObjectclasses)));
		}
		if ($name == 'dc') $extra['o'] = $value;	// required by organisation
		if ($name == 'uid') $extra['cn'] = $value;	// required by organizationalRole

		if (!@ldap_add($this->test_ldap->ds,$dn,$attr = array(
			$name => $value,
			'objectClass' => self::$requiredObjectclasses[$name],
		)+$extra))
		{
			throw new egw_exception_wrong_userinput(lang('Can not create DN %1!',$dn).
				' ('.ldap_error($this->test_ldap->ds).', attributes='.print_r($attr,true).')');
		}
		return true;
	}

	/**
	 * Return default database settings for a given domain
	 *
	 * @return array
	 */
	static function defaults()
	{
		return array(
			'ldap_host'     => 'localhost',
			'ldap_suffix'   => 'dc=local',
			'ldap_admin'    => 'cn=admin,$suffix',
			'ldap_admin_pw' => '',
			'ldap_base'     => 'o=$domain,$suffix',
			'ldap_root_dn'  => 'cn=admin,$base',
			'ldap_root_pw'  => self::randomstring(),
			'ldap_context'  => 'ou=accounts,$base',
			'ldap_search_filter' => '(uid=%user)',
			'ldap_group_context' => 'ou=groups,$base',
		);
	}

	/**
	 * Merges the default into the current properties, if they are empty or contain placeholders
	 */
	private function _merge_defaults()
	{
		foreach(self::defaults() as $name => $default)
		{
			if ($this->sub_command == 'delete_ldap' && in_array($name,array('ldap_base','ldap_context')))
			{
				continue;	// no default on what to delete!
			}
			if (!$this->$name)
			{
				//echo "<p>setting $name='{$this->$name}' to it's default='$default'</p>\n";
				$this->set_defaults[$name] = $this->$name = $default;
			}
			if (strpos($this->$name,'$') !== false)
			{
				$this->set_defaults[$name] = $this->$name = str_replace(array(
					'$domain',
					'$suffix',
					'$base',
					'$admin_pw',
				),array(
					$this->domain,
					$this->ldap_suffix,
					$this->ldap_base,
					$this->ldap_admin_pw,
				),$this->$name);
			}
		}
	}
}
