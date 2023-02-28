<?php
/**
 * EGroupware API - Accounts
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 *
 * Implements the (now depricated) interfaces on the former accounts class written by
 * Joseph Engo <jengo@phpgroupware.org> and Bettina Gille <ceb@phpgroupware.org>
 * Copyright (C) 2000 - 2002 Joseph Engo, Copyright (C) 2003 Joseph Engo, Bettina Gille
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api;

use EGroupware\Api\Accounts\Sql;
use EGroupware\Api\Exception\AssertionFailed;

/**
 * API - accounts
 *
 * This class uses a backend class (at them moment SQL or LDAP) and implements some
 * caching on to top of the backend functions:
 *
 * a) instance-wide account-data cache queried by account_id including also members(hips)
 *    implemented by self::cache_read($account_id) and self::cache_invalidate($account_ids)
 *
 * b) session based cache for search, split_accounts and name2id
 *    implemented by self::setup_cache() and self::cache_invalidate()
 *
 * The backend only implements the read, save, delete, name2id and the {set_}members{hips} methods.
 * The account class implements all other (eg. name2id, id2name) functions on top of these.
 *
 * read and search return timestamps (account_(created|modified|lastlogin) in server-time!
 */
class Accounts
{
	/**
	 * Enables the session-cache, currently switched on independent of the backend
	 *
	 * @var boolean
	 */
	static $use_session_cache = true;

	/**
	 * Cache, stored in sesssion
	 *
	 * @var array
	 */
	static $cache;

	/**
	 * Keys for which both versions with 'account_' prefix and without (depricated!) can be used, if requested.
	 * Migrate your code to always use the 'account_' prefix!!!
	 *
	 * @var array
	 */
	var $depricated_names = array('firstname','lastname','fullname','email','type',
		'status','expires','lastlogin','lastloginfrom','lastpasswd_change');

	/**
	 * List of all config vars accounts depend on and therefore should be passed in when calling contructor with array syntax
	 *
	 * @var array
	 */
	static public $config_vars = array(
		'account_repository', 'auth_type',	// auth_type if fallback if account_repository is not set
		'install_id',	// instance-specific caching
		'auto_create_expire', 'default_group_lid',	// auto-creation of accounts
		'ldap_host','ldap_root_dn','ldap_root_pw','ldap_context','ldap_group_context','ldap_search_filter',	// ldap backend
		'ads_domain', 'ads_host', 'ads_admin_user', 'ads_admin_passwd', 'ads_connection', 'ads_context',	// ads backend
	);

	/**
	 * Querytypes for the account-search
	 *
	 * @var array
	 */
	var $query_types = array(
		'all' => 'all fields',
		'firstname' => 'firstname',
		'lastname' => 'lastname',
		'lid' => 'LoginID',
		'email' => 'email',
		'start' => 'start with',
		'exact' => 'exact',
	);

	/**
	 * Backend to use
	 *
	 * @var Accounts\Sql|Accounts\Ldap|Accounts\Ads|Accounts\Univention
	 */
	var $backend;

	/**
	 * total number of found entries
	 *
	 * @var int
	 */
	var $total;

	/**
	 * Current configuration
	 *
	 * @var array
	 */
	var $config;

	/**
	 * hold an instance of the accounts class
	 *
	 * @var Accounts the instance of the accounts class
	 */
	private static $_instance = NULL;

	/**
	 * Singleton
	 *
	 * @return Accounts
	 */
	public static function getInstance()
	{
		if (self::$_instance === NULL)
		{
			self::$_instance = new Accounts();
		}
		return self::$_instance;
	}

	/**
	 * Constructor
	 *
	 * @param string|array $backend =null string with backend 'sql'|'ldap', or whole config array, default read from global egw_info
	 * @param Sql|Ldap|Ads|Univention|null $backend_object
	 */
	public function __construct($backend=null, $backend_object=null)
	{
		if (is_array($backend))
		{
			$this->config = $backend;
			$backend = null;
			self::$_instance = $this;	// also set instance returned by singleton
			self::$cache = array();		// and empty our internal (session) cache
		}
		else
		{
			$this->config =& $GLOBALS['egw_info']['server'];

			if (!isset(self::$_instance)) self::$_instance = $this;
		}
		if (is_null($backend))
		{
			if (empty($this->config['account_repository']))
			{
				if (!empty($this->config['auth_type']))
				{
					$this->config['account_repository'] = $this->config['auth_type'];
				}
				else
				{
					$this->config['account_repository'] = 'sql';
				}
			}
			$backend = $this->config['account_repository'];
		}
		$backend_class = 'EGroupware\\Api\\Accounts\\'.ucfirst($backend);

		if ($backend_object && !is_a($backend_object, $backend_class))
		{
			throw new AssertionFailed("Invalid backend object, not a $backend_class object!");
		}
		$this->backend = $backend_object ?: new $backend_class($this);
	}

	/**
	 * Get cache-key for search parameters
	 *
	 * @param array $params
	 * @param ?string& $unlimited on return key for unlimited search
	 * @return string
	 */
	public static function cacheKey(array $params, string &$unlimited=null)
	{
		// normalize our cache-key by not storing anything, plus adding default the default sort (if none requested)
		$keys = array_filter($params)+['order' => 'account_lid', 'sort' => 'ASC'];
		if (isset($keys['account_id'])) $keys['account_id'] = md5(json_encode($keys['account_id']));
		// sort keys
		ksort($keys);
		$key = json_encode($keys);
		unset($keys['start'], $keys['offset']);
		$unlimited = json_encode($keys);
		return $key;
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @ToDo improve and limit caching:
	 * - only cache user-specific stuff in session (owngroups, accounts with account_selection="groupmembers")
	 * - cache everything else for whole instance (groups, accounts unless account_selection="groupmembers")
	 * - only cache unlimited queries independent of sorting (limiting and sorting can be done quickly on unlimited queries)
	 * - stop caching in backends (with exception of backends which cant/dont do sorted limited queries like currently LDAP, where it makes sense to cache the unlimited query result)
	 * - apply reasonable short time-limit for instance-wide caching, as we have no invalidation for non-SQL systems eg. 2 hours
	 *
	 * @param array with the following keys:
	 * @param $param['type'] string|int 'accounts', 'groups', 'owngroups' (groups the user is a member of), 'both',
	 * 	'groupmembers' (members of groups the user is a member of), 'groupmembers+memberships' (incl. memberships too)
	 *	or integer group-id for a list of members of that group
	 * @param $param['start'] int first account to return (returns offset or max_matches entries) or all if not set
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @param $param['order'] string column to sort after, default account_lid if unset
	 * @param $param['sort'] string 'ASC' or 'DESC', default 'ASC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['app'] string with an app-name, to limit result on accounts with run-right for that app
	 * @param $param['active']=true boolean - true: return only acctive accounts, false: return expired or deactivated too
	 * @param $param['account_id'] int[] return only given account_id's
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//error_log(__METHOD__.'('.array2string($param).') '.function_backtrace());
		if (!isset($param['active'])) $param['active'] = true;	// default is true = only return active accounts
		if (!empty($param['offset']) && !isset($param['start'])) $param['start'] = 0;

		// Check for lang(Group) in search - if there, we search all groups
		$group_index = array_search(strtolower(lang('Group')), array_map('strtolower', $query = explode(' ',$param['query'] ?? '')));
		if($group_index !== FALSE && !(
				in_array($param['type'], array('accounts', 'groupmembers')) || is_int($param['type'])
		))
		{
			// do not return any groups for account-selection == "none"
			if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] === 'none' &&
				!isset($GLOBALS['egw_info']['user']['apps']['admin']))
			{
				$this->total = 0;
				return array();
			}
			// only return own memberships for account-selection == "groupmembers"
			$param['type'] = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] === 'groupmembers' &&
				!isset($GLOBALS['egw_info']['user']['apps']['admin']) ? 'owngroups' : 'groups';
			// Remove the 'group' from the query, but only one (eg: Group NoGroup -> NoGroup)
			unset($query[$group_index]);
			$param['query'] = implode(' ', $query);
		}
		self::setup_cache();
		$account_search = &self::$cache['account_search'];
		$serial = self::cacheKey($param, $serial_unlimited);

		// cache list of all groups on instance level (not session)
		if ($serial_unlimited === self::cacheKey(['type'=>'groups','active'=>true]))
		{
			$result = Cache::getCache($this->config['install_id'], __CLASS__, 'groups', function() use ($param)
			{
				return $this->backend->search($param);
			}, [], self::READ_CACHE_TIMEOUT);
			$this->total = count($result);
			if (!empty($param['offset']))
			{
				return array_slice($result, $param['start'], $param['offset'], true);
			}
			return $result;
		}
		elseif (isset($account_search[$serial]))
		{
			$this->total = $account_search[$serial]['total'];
		}
		// if we already have an unlimited search, we can always return only a part of it
		elseif (isset($account_search[$serial_unlimited]))
		{
			$this->total = $account_search[$serial_unlimited]['total'];
			return array_slice($account_search[$serial_unlimited]['data'], $param['start'], $param['offset'], true);
		}
		// no backend understands $param['app'], only sql understands type owngroups or groupmemember[+memberships]
		// --> do an full search first and then filter and limit that search
		elseif(!empty($param['app']) || $this->config['account_repository'] != 'sql' &&
			in_array($param['type'], array('owngroups','groupmembers','groupmembers+memberships')))
		{
			$app = $param['app'];
			unset($param['app']);
			$start = $param['start'];
			unset($param['start']);
			$offset = $param['offset'] ?: $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			unset($param['offset']);
			$stop = $start + $offset;

			if ($param['type'] == 'owngroups')
			{
				$members = $this->memberships($GLOBALS['egw_info']['user']['account_id'],true);
				$param['type'] = 'groups';
			}
			elseif(in_array($param['type'],array('groupmembers','groupmembers+memberships')))
			{
				$members = array();
				foreach((array)$this->memberships($GLOBALS['egw_info']['user']['account_id'],true) as $grp)
				{
					if (isset($this->backend->ignore_membership) && in_array($grp, $this->backend->ignore_membership)) continue;
					$members = array_unique(array_merge($members, (array)$this->members($grp,true,$param['active'])));
					if ($param['type'] == 'groupmembers+memberships') $members[] = $grp;
				}
				$param['type'] = $param['type'] == 'groupmembers+memberships' ? 'both' : 'accounts';
			}
			// call ourself recursive to get (evtl. cached) full search
			$full_search = $this->search($param);

			// filter search now on accounts with run-rights for app or a group
			$valid = array();
			if ($app)
			{
				// we want the result merged, whatever it takes, as we only care for the ids
				$valid = $this->split_accounts($app,!in_array($param['type'],array('accounts','groups')) ? 'merge' : $param['type'],$param['active']);
			}
			if (isset($members))
			{
				//error_log(__METHOD__.'() members='.array2string($members));
				if (!$members) $members = array();
				$valid = !$app ? $members : array_intersect($valid,$members);	// use the intersection
			}
			//error_log(__METHOD__."() limiting result to app='$app' and/or group=$group valid-ids=".array2string($valid));
			$n = 0;
			$account_search[$serial]['data'] = array();
			foreach ($full_search as $id => $data)
			{
				if (!in_array($id,$valid))
				{
					$this->total--;
					continue;
				}
				// now we have a valid entry
				if (!is_int($start) || $start <= $n && $n < $stop)
				{
					$account_search[$serial]['data'][$id] = $data;
				}
				$n++;
			}
			$account_search[$serial]['total'] = $this->total;
		}
		// direct search via backend
		else
		{
			$account_search[$serial] = [
				'data'  => $this->backend->search($param),
				'total' => $this->total = $this->backend->total,
			];
			// check if all rows have been returned --> cache as unlimited query
			if ($serial !== $serial_unlimited && count($account_search[$serial]['data']) === (int)$this->backend->total)
			{
				$account_search[$serial_unlimited] = $account_search[$serial];
				unset($account_search[$serial]);
				$serial = $serial_unlimited;
			}
			if ($param['type'] !== 'accounts' && !is_numeric($param['type']))
			{
				foreach($account_search[$serial]['data'] as &$account)
				{
					// add default description for Admins and Default group
					if ($account['account_type'] === 'g')
					{
						self::add_default_group_description($account);
					}
				}
			}
		}
		return $account_search[$serial]['data'];
	}

	/**
	 * Query for accounts
	 *
	 * @param string|array $pattern
	 * @param array $options
	 *  $options['filter']['group'] only return members of that group
	 *  $options['account_type'] "accounts", "groups", "both" or "groupmembers"
	 *  $options['tag_list'] true: return array of values for keys "value", "label" and "icon"
	 * @return array with id - title pairs of the matching entries
	 */
	public static function link_query($pattern, array &$options = array())
	{
		if (isset($options['filter']) && !is_array($options['filter']))
		{
			$options['filter'] = (array)$options['filter'];
		}
		switch($GLOBALS['egw_info']['user']['preferences']['common']['account_display'])
		{
			case 'firstname':
			case 'firstall':
			case 'firstgroup':
				$order = 'account_firstname,account_lastname';
				break;
			case 'lastname':
			case 'lastall':
			case 'firstgroup':
				$order = 'account_lastname,account_firstname';
				break;
			default:
				$order = 'account_lid';
				break;
		}
		$only_own = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] === 'groupmembers' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']);
		switch($options['account_type'])
		{
			case 'accounts':
				$type = $only_own ? 'groupmembers' : 'accounts';
				break;
			case 'groups':
				$type = $only_own ? 'owngroups' : 'groups';
				break;
			case 'memberships':
				$type = 'owngroups';
				break;
			case 'owngroups':
			case 'groupmembers':
				$type = $options['account_type'];
				break;
			case 'both':
			default:
				$type = $only_own ? 'groupmembers+memberships' : 'both';
				break;
		}
		$accounts = array();
		foreach(self::getInstance()->search(array(
												'type'       => $options['filter']['group'] < 0 ? $options['filter']['group'] : $type,
												'query'      => $pattern,
												'query_type' => 'all',
												'order'      => $order,
												'offset'     => $options['num_rows']
											)) as $account)
		{
			$displayName = self::format_username($account['account_lid'],
				$account['account_firstname'],$account['account_lastname'],$account['account_id']);

			if (!empty($options['tag_list']))
			{
				$result = [
					'value' => $account['account_id'],
					'label' => $displayName,
					// Send what lavatar needs to skip a server-side request
					'lname' => $account['account_id'] < 0 ? $account['account_lid'] : $account['account_lastname'],
					'fname' => $account['account_id'] < 0 ? lang('group') : $account['account_firstname']
				];
				// only if we have a real photo, send avatar-url, otherwise we use the above set lavatar (f|l)name
				if(!empty($account['account_has_photo']))
				{
					$result['icon'] = Framework::link('/api/avatar.php', [
						'account_id' => $account['account_id'],
						'modified'   => $account['account_modified'],
					]);
				}
				$accounts[$account['account_id']] = $result;
			}
			else
			{
				$accounts[$account['account_id']] = $displayName;
			}
		}
		// If limited rows were requested, send the total number of rows
		if(array_key_exists('num_rows', $options))
		{
			$options['total'] = self::getInstance()->total;
		}
		return $accounts;
	}

	/**
	 * Reads the data of one account
	 *
	 * All key of the returned array use the 'account_' prefix.
	 * For backward compatibility some values are additionaly availible without the prefix, using them is depricated!
	 *
	 * @param int|string $id numeric account_id or string with account_lid
	 * @param boolean $set_depricated_names =false set _additionaly_ the depricated keys without 'account_' prefix
	 * @return array/boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	function read($id, $set_depricated_names=false)
	{
		if (!is_int($id) && !is_numeric($id))
		{
			$id = $this->name2id($id);
		}
		if (!$id) return false;

		$data = self::cache_read($id);

		// add default description for Admins and Default group
		if ($data && $data['account_type'] === 'g')
		{
			self::add_default_group_description($data);
		}

		if ($set_depricated_names && $data)
		{
			foreach($this->depricated_names as $name)
			{
				$data[$name] =& $data['account_'.$name];
			}
		}
		return $data;
	}

	/**
	 * Get an account as json, returns only whitelisted fields:
	 * - 'account_id','account_lid','person_id','account_status','memberships'
	 * - 'account_firstname','account_lastname','account_email','account_fullname','account_phone'
	 *
	 * @param int|string $id
	 * @return string|boolean json or false if not found
	 */
	function json($id)
	{
		static $keys = array(
			'account_id','account_lid','person_id','account_status','memberships','account_has_photo',
			'account_firstname','account_lastname','account_email','account_fullname','account_phone',
		);
		if (($account = $this->read($id)))
		{
			if (isset($account['memberships'])) $account['memberships'] = array_keys($account['memberships']);
			$account = array_intersect_key($account, array_flip($keys));
		}
		// for current user, add the apps available to him
		if ($id == $GLOBALS['egw_info']['user']['account_id'])
		{
			foreach((array)$GLOBALS['egw_info']['user']['apps'] as $app => $data)
			{
				unset($data['table_defs']);	// no need for that on the client
				$account['apps'][$app] = $data;
			}
		}
		return json_encode($account, JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Format lid, firstname, lastname according to use preferences
	 *
	 * @param $lid ='' account loginid
	 * @param $firstname ='' firstname
	 * @param $lastname ='' lastname
	 * @param $accountid =0 id, to check if it's a user or group, otherwise the lid will be used
	 */
	static function format_username($lid = '', $firstname = '', $lastname = '', $accountid=0)
	{
		if (!$lid && !$firstname && !$lastname)
		{
			$lid       = $GLOBALS['egw_info']['user']['account_lid'];
			$firstname = $GLOBALS['egw_info']['user']['account_firstname'];
			$lastname  = $GLOBALS['egw_info']['user']['account_lastname'];
			$accountid = $GLOBALS['egw_info']['user']['account_id'];
		}
		$is_group = $GLOBALS['egw']->accounts->get_type($accountid ? $accountid : $lid) == 'g';

		if (empty($firstname)) $firstname = $lid;
		if (empty($lastname) || $is_group)
		{
			$lastname  = $is_group ? lang('Group') : lang('User');
		}
		$display = $GLOBALS['egw_info']['user']['preferences']['common']['account_display'];

		if ($firstname && $lastname)
		{
			$delimiter = $is_group ? ' ' : ', ';
		}
		else
		{
			$delimiter = '';
		}

		$name = '';
		switch($display)
		{
			case 'firstname':
				$name = $firstname . ' ' . $lastname;
				break;
			case 'lastname':
				$name = $lastname . $delimiter . $firstname;
				break;
			case 'username':
				$name = $lid;
				break;
			case 'firstall':
				$name = $firstname . ' ' . $lastname . ' ['.$lid.']';
				break;
			case 'lastall':
				$name = $lastname . $delimiter . $firstname . ' ['.$lid.']';
				break;
			case 'allfirst':
				$name = '['.$lid.'] ' . $firstname . ' ' . $lastname;
				break;
			case 'firstgroup':
				$group = Accounts::id2name($lid, 'account_primary_group');
				$name = $firstname . ' ' . $lastname . ($is_group ? '' : ' ('.Accounts::id2name($group).')');
				break;
			case 'lastgroup':
				$group = Accounts::id2name($lid, 'account_primary_group');
				$name = $lastname . $delimiter . $firstname . ($is_group ? '' : ' ('.Accounts::id2name($group).')');
				break;
			case 'firstinital':
				$name = $firstname.' '.mb_substr($lastname, 0, 1).'.';
				break;
			case 'firstid':
				$name = $firstname.' ['.$accountid.']';
				break;
			case 'all':
				/* fall through */
			default:
				$name = '['.$lid.'] ' . $lastname . $delimiter . $firstname;
		}
		return $name;
	}

	/**
	 * Return formatted username for a given account_id
	 *
	 * @param ?int $account_id account id, default current user
	 * @return string full name of user or "#$account_id" if user not found
	 */
	static function username(int $account_id=null)
	{
		if (empty($account_id))
		{
			$account_id = $GLOBALS['egw_info']['user']['account_id'];
		}
		if (!($account = self::cache_read($account_id)))
		{
			return '#'.$account_id;
		}
		return self::format_username($account['account_lid'],
			$account['account_firstname'] , $account['account_lastname'], $account_id);
	}

	/**
	 * Return formatted username for Links, does NOT throw if $account_id is not int
	 *
	 * @param $account_id
	 */
	static function title($account_id)
	{
		if (empty($account_id) || !is_numeric($account_id) && !($id = self::getInstance()->name2id($account_id)))
		{
			return '#'.$account_id;
		}
		return self::username($id ?? $account_id);
	}

	/**
	 * Format an email address according to the system standard
	 *
	 * Convert all european special chars to ascii and fallback to the accountname, if nothing left eg. chiniese
	 *
	 * @param string $first firstname
	 * @param string $last lastname
	 * @param string $account account-name (lid)
	 * @param string $domain =null domain-name or null to use eGW's default domain $GLOBALS['egw_info']['server']['mail_suffix]
	 * @return string with email address
	 */
	static function email($first,$last,$account,$domain=null)
	{
		if ($GLOBALS['egw_info']['server']['email_address_format'] === 'none')
		{
			return null;
		}
		foreach (array('first','last','account') as $name)
		{
			$$name = Translation::to_ascii($$name);
		}
		//echo " --> ('$first', '$last', '$account')";
		if (!$first && !$last)	// fallback to the account-name, if real names contain only special chars
		{
			$first = '';
			$last = $account;
		}
		if (!$first || !$last)
		{
			$dot = $underscore = '';
		}
		else
		{
			$dot = '.';
			$underscore = '_';
		}
		if (!$domain) $domain = $GLOBALS['egw_info']['server']['mail_suffix'];
		if (!$domain) $domain = $_SERVER['SERVER_NAME'];

		$email = str_replace(array('first','last','initial','account','dot','underscore','-'),
			array($first,$last,substr($first,0,1),$account,$dot,$underscore,''),
			$GLOBALS['egw_info']['server']['email_address_format'] ? $GLOBALS['egw_info']['server']['email_address_format'] : 'first-dot-last').
			($domain ? '@'.$domain : '');

		if (!empty($GLOBALS['egw_info']['server']['email_address_lowercase']))
		{
			$email = strtolower($email);
		}
		//echo " = '$email'</p>\n";
		return $email;
	}

	/**
	 * Add a default description for stock groups: Admins, Default, NoGroup
	 *
	 * @param array &$data
	 */
	protected static function add_default_group_description(array &$data)
	{
		if (empty($data['account_description']))
		{
			switch($data['account_lid'])
			{
				case 'Default':
					$data['account_description'] = lang('EGroupware all users group, do NOT delete');
					break;
				case 'Admins':
					$data['account_description'] = lang('EGroupware administrators group, do NOT delete');
					break;
				case 'NoGroup':
					$data['account_description'] = lang('EGroupware anonymous users group, do NOT delete');
					break;
			}
		}
		else
		{
			$data['account_description'] = lang($data['account_description']);
		}
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @param boolean $check_depricated_names =false check _additionaly_ the depricated keys without 'account_' prefix
	 * @return int|boolean the account_id or false on error
	 */
	function save(&$data,$check_depricated_names=false)
	{
		if ($check_depricated_names)
		{
			foreach($this->depricated_names as $name)
			{
				if (isset($data[$name]) && !isset($data['account_'.$name]))
				{
					$data['account_'.$name] =& $data[$name];
				}
			}
		}
		$update_type = "update";
		// add default description for Admins and Default group
		if ($data['account_type'] === 'g' && empty($data['account_description']))
		{
			self::add_default_group_description($data);
		}
		if (($id = $this->backend->save($data)) && $data['account_type'] != 'g')
		{
			// if we are not on a pure LDAP system, we have to write the account-date via the contacts class now
			if (($this->config['account_repository'] == 'sql' || $this->config['contact_repository'] == 'sql-ldap') &&
				(!($old = $this->read($data['account_id'])) ||	// only for new account or changed contact-data
				$old['account_firstname'] != $data['account_firstname'] ||
				$old['account_lastname'] != $data['account_lastname'] ||
				$old['account_email'] != $data['account_email']))
			{
				if (!$data['person_id'])
				{
					$data['person_id'] = $old['person_id'];
				}

				// Include previous contact information to avoid blank history rows
				$contact = array_merge((array)$GLOBALS['egw']->contacts->read($data['person_id'], true), array(
						'n_given' => $data['account_firstname'],
						'n_family' => $data['account_lastname'],
						'email' => $data['account_email'],
						'account_id' => $data['account_id'],
						'id' => $data['person_id'],
						'owner' => 0,
				));
				$GLOBALS['egw']->contacts->save($contact, true);    // true = ignore addressbook acl
			}
			// save primary group if necessary
			if ($data['account_primary_group'] && (!($memberships = $this->memberships($id,true)) ||
				!in_array($data['account_primary_group'],$memberships)))
			{
				$memberships[] = $data['account_primary_group'];
				$this->set_memberships($memberships, $id);	// invalidates cache for account_id and primary group
			}
		}
		// as some backends set (group-)members in save, we need to invalidate their members too!
		$invalidate = isset($data['account_members']) ? $data['account_members'] : array();
		$invalidate[] = $data['account_id'];
		self::cache_invalidate($invalidate);

		// Notify linked apps about changes in the account data
		Link::notify_update('admin',  $id, $data, $update_type);

		return $id;
	}

	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int|string $id numeric account_id or string with account_lid
	 * @return boolean true on success, false otherwise
	 */
	function delete($id)
	{
		if (!is_int($id) && !is_numeric($id))
		{
			$id = $this->name2id($id);
		}
		if (!$id) return false;

		if ($this->get_type($id) == 'u')
		{
			$invalidate = $this->memberships($id, true);
		}
		else
		{
			$invalidate = $this->members($id, true, false);
		}
		$invalidate[] = $id;

		$this->backend->delete($id);

		self::cache_invalidate($invalidate);

		// delete all acl_entries belonging to that user or group
		$GLOBALS['egw']->acl->delete_account($id);

		// delete all categories belonging to that user or group
		Categories::delete_account($id);

		// Notify linked apps about changes in the account data
		Link::notify_update('admin',  $id, null, 'delete');

		return true;
	}

	/**
	 * Test if given an account is expired
	 *
	 * @param int|string|array $data account_(l)id or array with account-data
	 * @return boolean true=expired (no more login possible), false otherwise
	 */
	static function is_expired($data)
	{
		if (is_null($data))
		{
			throw new Exception\WrongParameter('Missing parameter to Accounts::is_active()');
		}
		if (!is_array($data)) $data = self::getInstance()->read($data);

		$expires = isset($data['account_expires']) ? $data['account_expires'] : $data['expires'];

		return $expires != -1 && $expires < time();
	}

	/**
	 * Test if an account is active - NOT deactivated or expired
	 *
	 * @param int|string|array $data account_(l)id or array with account-data
	 * @return boolean false if account does not exist, is expired or decativated, true otherwise
	 */
	static function is_active($data)
	{
		if (!is_array($data)) $data = self::getInstance()->read($data);

		return $data && !(self::is_expired($data) || $data['account_status'] != 'A');
	}

	/**
	 * convert an alphanumeric account-value (account_lid, account_email) to the account_id
	 *
	 * Please note:
	 * - if a group and an user have the same account_lid the group will be returned (LDAP only)
	 * - if multiple user have the same email address, the returned user is undefined
	 *
	 * @param string $name value to convert
	 * @param string $which ='account_lid' type of $name: account_lid (default), account_email, person_id, account_fullname
	 * @param string $account_type =null u = user or g = group, or default null = try both
	 * @return int|false numeric account_id or false on error ($name not found)
	 */
	function name2id($name,$which='account_lid',$account_type=null)
	{
		// Don't bother searching for empty or non-scalar account_lid
		if(empty($name) || !is_scalar($name))
		{
			return False;
		}

		self::setup_cache();
		$name_list = &self::$cache['name_list'];

		if (isset($name_list[$which][$name]))
		{
			return $name_list[$which][$name];
		}

		return $name_list[$which][$name] = $this->backend->name2id($name,$which,$account_type);
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 *
	 * Uses the read method to fetch all data.
	 *
	 * @param int|string $account_id numeric account_id or account_lid
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @param boolean $generate_email =false true: generate an email address, if user has none
	 * @return string|boolean converted value or false on error ($account_id not found)
	 */
	static function id2name($account_id, $which='account_lid', $generate_email=false)
	{
		if (!is_numeric($account_id) && !($account_id = self::getInstance()->name2id($account_id)))
		{
			return false;
		}
		try {
			if (!($data = self::cache_read($account_id))) return false;
		}
		catch (Exception $e) {
			unset($e);
			return false;
		}
		if ($generate_email && $which === 'account_email' && empty($data[$which]))
		{
			return self::email($data['account_firstname'], $data['account_lastname'], $data['account_lid']);
		}
		return $data[$which];
	}

	/**
	 * get the type of an account: 'u' = user, 'g' = group
	 *
	 * @param int|string $account_id numeric account-id or alphanum. account-lid,
	 *	if !$accountid account of the user of this session
	 * @return string/false 'u' = user, 'g' = group or false on error ($accountid not found)
	 */
	function get_type($account_id)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		return $account_id > 0 ? 'u' : ($account_id < 0 ? 'g' : false);
	}

	/**
	 * check if an account exists and if it is an user or group
	 *
	 * @param int|string $account_id numeric account_id or account_lid
	 * @return int 0 = acount does not exist, 1 = user, 2 = group
	 */
	function exists($account_id)
	{
		if (!$account_id || !($data = $this->read($account_id)))
		{
			// non sql backends might NOT show EGw all users, but backend->id2name/name2id does
			if (is_a($this->backend, __CLASS__.'\\Univention'))
			{
				if (!is_numeric($account_id) ?
					($account_id = $this->backend->name2id($account_id)) :
					$this->backend->id2name($account_id))
				{
					return $account_id > 0 ? 1 : 2;
				}
			}
			return 0;
		}
		return $data['account_type'] == 'u' ? 1 : 2;
	}

	/**
	 * Checks if a given account is visible to current user
	 *
	 * Not all existing accounts are visible because off account_selection preference: 'none' or 'groupmembers'
	 *
	 * @param int|string $account_id nummeric account_id or account_lid
	 * @return boolean true = account is visible, false = account not visible, null = account does not exist
	 */
	function visible($account_id)
	{
		if (!is_numeric($account_id))	// account_lid given
		{
			$account_lid = $account_id;
			if (!($account_id = $this->name2id($account_lid))) return null;
		}
		else
		{
			if (!($account_lid = $this->id2name($account_id))) return null;
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
			// do NOT allow other user, if account-selection is none
			($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
				$account_lid != $GLOBALS['egw_info']['user']['account_lid'] ||
			// only allow group-members for account-selection is groupmembers
			$GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'groupmembers' &&
				!array_intersect((array)$this->memberships($account_id,true),
					(array)$this->memberships($GLOBALS['egw_info']['user']['account_id'],true))))
		{
			//error_log(__METHOD__."($account_id='$account_lid') returning FALSE");
			return false;	// user is not allowed to see given account
		}
		return true;	// user allowed to see given account
	}

	/**
	 * Get all memberships of an account $account_id / groups the account is a member off
	 *
	 * @param int|string $account_id numeric account-id or alphanum. account-lid
	 * @param boolean $just_id =false return just account_id's or account_id => account_lid pairs
	 * @return array with account_id's ($just_id) or account_id => account_lid pairs (!$just_id)
	 */
	function memberships($account_id, $just_id=false)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id,'account_lid','u');
		}
		if ($account_id && ($data = self::cache_read($account_id)))
		{
			$ret = $just_id && $data['memberships'] ? array_keys($data['memberships']) : ($data['memberships'] ?? []);
		}
		//error_log(__METHOD__."($account_id, $just_id) data=".array2string($data)." returning ".array2string($ret));
		return $ret ?? [];
	}

	/**
	 * Sets the memberships of a given account
	 *
	 * @param array $groups array with gidnumbers
	 * @param int $account_id uidnumber
	 * @return boolean true: membership changed, false: no change necessary
	 */
	function set_memberships($groups,$account_id)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		if (($old_memberships = $this->memberships($account_id, true)) == $groups)
		{
			return false;	// nothing changed
		}
		$this->backend->set_memberships($groups, $account_id);

		if (!$old_memberships) $old_memberships = array();
		self::cache_invalidate(array_unique(array_merge(
			array($account_id),
			array_diff($old_memberships, $groups),
			array_diff($groups, $old_memberships)
		)));
		return true;
	}

	/**
	 * Get all members of the group $account_id
	 *
	 * @param int|string $account_id ='' numeric account-id or alphanum. account-lid,
	 *	default account of the user of this session
	 * @param boolean $just_id =false return just an array of id's and not id => lid pairs, default false
	 * @param boolean $active =false true: return only active (not expired or deactived) members, false: return all accounts
	 * @return array with account_id ($just_id) or account_id => account_lid pairs (!$just_id)
	 */
	function members($account_id, $just_id=false, $active=true)
	{
		if (!is_int($account_id) && !is_numeric($account_id))
		{
			$account_id = $this->name2id($account_id);
		}
		if ($account_id && ($data = self::cache_read($account_id, $active)))
		{
			$members = $active ? $data['members-active'] : $data['members'];

			return $just_id && $members ? array_keys($members) : $members;
		}
		return null;
	}

	/**
	 * Set the members of a group
	 *
	 * @param array $members array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 */
	function set_members($members,$gid)
	{
		if (($old_members = $this->members($gid, true, false)) != $members)
		{
			$this->backend->set_members($members, $gid);

			self::cache_invalidate(array_unique(array_merge(
				array($gid),
				array_diff($old_members, $members),
				array_diff($members, $old_members)
			)));
		}
	}

	/**
	 * splits users and groups from a array of id's or the accounts with run-rights for a given app-name
	 *
	 * @param array $app_users array of user-id's or app-name (if you use app-name the result gets cached!)
	 * @param string $use what should be returned only an array with id's of either 'accounts' or 'groups'.
	 *	Or an array with arrays for 'both' under the keys 'groups' and 'accounts' or 'merge' for accounts
	 *	and groups merged into one array
	 * @param boolean $active =false true: return only active (not expired or deactived) members, false: return all accounts
	 * @return array/boolean see $use, false on error (wront $use)
	 */
	function split_accounts($app_users,$use='both',$active=true)
	{
		if (!is_array($app_users))
		{
			self::setup_cache();
			$cache = &self::$cache['account_split'][$app_users];

			if (is_array($cache))
			{
				return $cache;
			}
			$app_users = $GLOBALS['egw']->acl->get_ids_for_location('run',1,$app_users);
		}
		$accounts = array(
			'accounts' => array(),
			'groups' => array(),
		);
		foreach($app_users as $id)
		{
			$type = $this->get_type($id);
			if($type == 'g')
			{
				$accounts['groups'][$id] = $id;
				if ($use != 'groups')
				{
					foreach((array)$this->members($id, true, $active) as $id)
					{
						$accounts['accounts'][$id] = $id;
					}
				}
			}
			else
			{
				$accounts['accounts'][$id] = $id;
			}
		}

		// not sure why they need to be sorted, but we need to remove the keys anyway
		sort($accounts['groups']);
		sort($accounts['accounts']);

		if (isset($cache))
		{
			$cache = $accounts;
		}

		switch($use)
		{
			case 'both':
				return $accounts;
			case 'groups':
				return $accounts['groups'];
			case 'accounts':
				return $accounts['accounts'];
			case 'merge':
				return array_merge($accounts['accounts'],$accounts['groups']);
		}
		return False;
	}

	/**
	 * Get a list of how many entries of each app the account has
	 *
	 * @param int $account_id
	 *
	 * @return array app => count
	 */
	public function get_account_entry_counts($account_id)
	{
		$owner_columns = static::get_owner_columns();

		$selects = $counts = [];
		foreach($owner_columns as $app => $column)
		{
			list($table, $column_name) = explode('.', $column['column']);
			$select = array(
				'table' => $table,
				'cols'  => array(
					"'$app' AS app",
					"'total' AS type",
					'count(' . $column['key'] . ') AS count'
				),
				'where' => array(
					$column['column'] => (int)$account_id
				),
				'app'   => $app
			);
			switch($app)
			{
				case 'infolog':
					$select['cols'][1] = 'info_type AS type';
					$select['append'] = ' GROUP BY info_type';
					break;
			}
			$selects[] = $select;
			$counts[$app] = ['total' => 0];
		}

		foreach($GLOBALS['egw']->db->union($selects, __LINE__ , __FILE__) as $row)
		{
			$counts[$row['app']][$row['type']] = $row['count'];
			if($row['type'] != 'total')
			{
				$counts[$row['app']]['total'] += $row['count'];
			}
		}

		return $counts;
	}

	protected function get_owner_columns()
	{
		$owner_columns = array();
		foreach($GLOBALS['egw_info']['apps'] as $appname => $app)
		{
			// Check hook
			$owner_column = Link::get_registry($appname, 'owner');

			// Try for automatically finding the modification
			if(!is_array($owner_column) && !in_array($appname, array('admin', 'api','etemplate','phpgwapi')))
			{
				$tables = $GLOBALS['egw']->db->get_table_definitions($appname);
				if(!is_array($tables))
				{
					continue;
				}
				foreach($tables as $table_name => $table)
				{
					foreach($table['fd'] as $column_name => $column)
					{
						if((strpos($column_name, 'owner') !== FALSE || strpos($column_name, 'creator') !== FALSE) &&
								($column['meta'] == 'account' || $column['meta'] == 'user')
						)
						{
							$owner_column = array(
								'key'    => $table_name . '.' . $table['pk'][0],
								'column' => $table_name . '.' . $column_name,
								'type' => $column['type']
							);
							break 2;
						}
					}
				}
			}
			if($owner_column)
			{
				$owner_columns[$appname] = $owner_column;
			}
		}

		return $owner_columns;
	}

	/**
	 * Add an account for an authenticated user
	 *
	 * Expiration date and primary group are read from the system configuration.
	 *
	 * @param string $account_lid
	 * @param string $passwd
	 * @param array $GLOBALS['auto_create_acct'] values for 'firstname', 'lastname', 'email' and 'primary_group'
	 * @return int|boolean account_id or false on error
	 */
	function auto_add($account_lid, $passwd)
	{
		$expires = !isset($this->config['auto_create_expire']) ||
			$this->config['auto_create_expire'] == 'never' ? -1 :
			time() + $this->config['auto_create_expire'] + 2;

		$memberships = array();
		$default_group_id = null;
		// check if we have a comma or semicolon delimited list of groups --> add first as primary and rest as memberships
		foreach(preg_split('/[,;] */',$this->config['default_group_lid']) as $group_lid)
		{
			if (($group_id = $this->name2id($group_lid,'account_lid','g')))
			{
				if (!$default_group_id) $default_group_id = $group_id;
				$memberships[] = $group_id;
			}
		}
		if (!$default_group_id && ($default_group_id = $this->name2id('Default','account_lid','g')))
		{
			$memberships[] = $default_group_id;
		}

		$primary_group = $GLOBALS['auto_create_acct']['primary_group'] &&
			$this->get_type((int)$GLOBALS['auto_create_acct']['primary_group']) === 'g' ?
			(int)$GLOBALS['auto_create_acct']['primary_group'] : $default_group_id;
		if ($primary_group && !in_array($primary_group, $memberships))
		{
			$memberships[] = $primary_group;
		}
		// add a requested addtional group, eg. Teachers for smallpart
		if (!empty($GLOBALS['auto_create_acct']['add_group']) &&
			$this->get_type((int)$GLOBALS['auto_create_acct']['add_group']) === 'g')
		{
			$memberships[] = (int)$GLOBALS['auto_create_acct']['add_group'];
		}
		$data = array(
			'account_lid'           => $account_lid,
			'account_type'          => 'u',
			'account_passwd'        => $passwd,
			'account_firstname'     => $GLOBALS['auto_create_acct']['firstname'] ? $GLOBALS['auto_create_acct']['firstname'] : 'New',
			'account_lastname'      => $GLOBALS['auto_create_acct']['lastname'] ? $GLOBALS['auto_create_acct']['lastname'] : 'User',
			'account_email'         => $GLOBALS['auto_create_acct']['email'],
			'account_status'        => 'A',
			'account_expires'       => $expires,
			'account_primary_group' => $primary_group,
		);
		// use given account_id, if it's not already used
		if (isset($GLOBALS['auto_create_acct']['account_id']) &&
			is_numeric($GLOBALS['auto_create_acct']['account_id']) &&
			!$this->id2name($GLOBALS['auto_create_acct']['account_id']))
		{
			$data['account_id'] = $GLOBALS['auto_create_acct']['account_id'];
		}
		if (!($data['account_id'] = $this->save($data)))
		{
			return false;
		}
		// set memberships if given
		if ($memberships)
		{
			$this->set_memberships($memberships,$data['account_id']);
		}
		// set the appropriate value for the can change password flag (assume users can, if the admin requires users to change their password)
		$data['changepassword'] = (bool)$GLOBALS['egw_info']['server']['change_pwd_every_x_days'];
		if(!$data['changepassword'])
		{
			$GLOBALS['egw']->acl->add_repository('preferences','nopasswordchange',$data['account_id'],1);
		}
		else
		{
			$GLOBALS['egw']->acl->delete_repository('preferences','nopasswordchange',$data['account_id']);
		}
		// call hook to notify interested apps about the new account
		$GLOBALS['hook_values'] = $data;
		Hooks::process($data+array(
			'location' => 'addaccount',
			// at login-time only the hooks from the following apps will be called
			'order' => array('felamimail','fudforum'),
		),False,True);  // called for every app now, not only enabled ones
		unset($data['changepassword']);

		return $data['account_id'];
	}

	/**
	 * Update the last login timestamps and the IP
	 *
	 * @param int $account_id
	 * @param string $ip
	 * @return int lastlogin time
	 */
	function update_lastlogin($account_id, $ip)
	{
		return $this->backend->update_lastlogin($account_id, $ip);
	}

	/**
	 * Query if backend allows to change username aka account_lid
	 *
	 * @return boolean false if backend does NOT allow it (AD), true otherwise (SQL, LDAP)
	 */
	function change_account_lid_allowed()
	{
		$change_account_lid = constant(get_class($this->backend).'::CHANGE_ACCOUNT_LID');
		if (!isset($change_account_lid)) $change_account_lid = true;
		return $change_account_lid;
	}

	/**
	 * Query if backend requires password to be set, before allowing to enable an account
	 *
	 * @return boolean true if backend requires a password (AD), false or null otherwise (SQL, LDAP)
	 */
	function require_password_for_enable()
	{
		return constant(get_class($this->backend).'::REQUIRE_PASSWORD_FOR_ENABLE');
	}

	/**
	 * Invalidate cache (or parts of it) after change in $account_ids
	 *
	 * We use now an instance-wide read-cache storing account-data and members(hips).
	 *
	 * @param int|array $account_ids user- or group-id(s) for which cache should be invalidated, default 0 = only search/name2id cache
	 */
	static function cache_invalidate($account_ids=0)
	{
		//error_log(__METHOD__.'('.array2string($account_ids).')');

		$instance = self::getInstance();

		// instance-wide cache
		$invalidate_groups = !$account_ids;
		if ($account_ids)
		{
			foreach((array)$account_ids as $account_id)
			{
				Cache::unsetCache($instance->config['install_id'], __CLASS__, 'account-'.$account_id);

				unset(self::$request_cache[$account_id]);

				if ($account_id < 0) $invalidate_groups = true;
			}
		}
		else
		{
			self::$request_cache = array();
		}
		// invalidate instance-wide all-groups cache
		if ($invalidate_groups)
		{
			Cache::unsetCache($instance->config['install_id'], __CLASS__, 'groups');
		}

		// session-cache
		if (self::$cache) self::$cache = array();
		Cache::unsetSession('accounts_cache','phpgwapi');

		if (method_exists($GLOBALS['egw'],'invalidate_session_cache'))	// egw object in setup is limited
		{
			Egw::invalidate_session_cache();	// invalidates whole egw-enviroment if stored in the session
		}
	}

	/**
	 * Timeout of instance wide cache for reading account-data and members(hips)
	 */
	const READ_CACHE_TIMEOUT = 43200;

	/**
	 * Local per request cache, to minimize calls to instance cache
	 *
	 * @var array
	 */
	static $request_cache = array();

	/**
	 * Read account incl. members/memberships from cache (or backend and cache it)
	 *
	 * @param int $account_id
	 * @param boolean $need_active =false true = 'members-active' required
	 * @param boolean $return_not_cached =false true: return null if nothing is cached
	 * @return array or null if nothing is cached
	 * @throws Exception\WrongParameter if no integer was passed as $account_id
	 */
	static function cache_read($account_id, bool $need_active=false, bool $return_not_cached=false)
	{
		if (!is_numeric($account_id)) throw new Exception\WrongParameter('Not an integer!');

		$account =& self::$request_cache[$account_id];

		if (!isset($account))	// not in request cache --> try instance cache
		{
			$instance = self::getInstance();

			$account = Cache::getCache($instance->config['install_id'], __CLASS__, 'account-'.$account_id);

			if (!isset($account))	// not in instance cache --> read from backend
			{
				if ($return_not_cached)
				{
					return null;
				}
				if (($account = $instance->backend->read($account_id)))
				{
					if ($instance->get_type($account_id) == 'u')
					{
						if (!isset($account['memberships'])) $account['memberships'] = $instance->backend->memberships($account_id);
					}
					else
					{
						if (!isset($account['members'])) $account['members'] = $instance->backend->members($account_id);
					}
					$instance->cache_data($account_id, $account);
				}
				// 						'account_lid' => 'Domain Users',
				elseif ($account_id == -513)
				{
					$instance->cache_data($account_id, $account = [
						'account_id'        => -513,
						'account_lid' => 'Domain Users',
						'account_type'      => 'g',
						'account_firstname' => 'Domain Users',
						'account_lastname'  => lang('Group'),
						'account_fullname'  => lang('Group').' Domain Users',
						'members-active' => [],
						'members' => [],
					]);
				}
				//error_log(__METHOD__."($account_id) read from backend ".array2string($account));
			}
			//else error_log(__METHOD__."($account_id) read from instance cache ".array2string($account));
		}
		// if required and not already set, query active members AND cache them too
		if ($need_active && $account_id < 0 && !isset($account['members-active']))
		{
			$instance = self::getInstance();
			$account['members-active'] = array();
			foreach((array)$account['members'] as $id => $lid)
			{
				if ($instance->is_active($id)) $account['members-active'][$id] = $lid;
			}
			Cache::setCache($instance->config['install_id'], __CLASS__, 'account-'.$account_id, $account, self::READ_CACHE_TIMEOUT);
		}
		//error_log(__METHOD__."($account_id, $need_active) returning ".array2string($account));
		return $account;
	}

	/**
	 * Cache account read by backend (incl. members or memberships!)
	 *
	 * Can be used by backends too, to inject accounts into the cache.
	 *
	 * @param int $account_id
	 * @param array $account
	 * @throws Exception\WrongParameter
	 */
	public function cache_data(int $account_id, array $account)
	{
		Cache::setCache($this->config['install_id'], __CLASS__, 'account-'.$account_id, $account, self::READ_CACHE_TIMEOUT);
	}

	/**
	 * Number of accounts to consider an installation huge
	 */
	const HUGE_LIMIT = 500;

	/**
	 * Check if instance is huge, has more than self::HUGE_LIMIT=500 users
	 *
	 * Can be used to disable features not working well for a huge installation.
	 *
	 * @param int|null $set=null to set call with total number of accounts
	 * @return bool
	 */
	public function isHuge(int $total=null)
	{
		if (isset($total))
		{
			$is_huge = $total > self::HUGE_LIMIT;
			Cache::setInstance(__CLASS__, 'is_huge', $is_huge);
			return $is_huge;
		}
		return Cache::getInstance(__CLASS__, 'is_huge', function()
		{
			$save_total = $this->total; // save and restore current total, to not have an unwanted side effect
			$this->search([
				'type'   => 'accounts',
				'start'  => 0,
				'offset' => 1,
				'active' => false,  // as this is set by admin_ui::get_users() and therefore we only return the cached result
			]);
			$total = $this->total;
			$this->total = $save_total;
			return $this->isHuge($total);
		});
	}

	/**
	 * Internal functions not meant to use outside this class!!!
	 */

	/**
	 * Sets up session cache, now only used for search and name2id list
	 *
	 * Other account-data is cached on instance-level
	 *
	 * The cache is shared between all instances of the account-class and it can be save in the session,
	 * if use_session_cache is set to True
	 *
	 * @internal
	 */
	private static function setup_cache()
	{
		if (is_array(self::$cache)) return;	// cache is already setup

		if (self::$use_session_cache && is_object($GLOBALS['egw']->session))
		{
			self::$cache =& Cache::getSession('accounts_cache','phpgwapi');
		}
		//error_log(__METHOD__."() use_session_cache=".array2string(self::$use_session_cache).", is_array(self::\$cache)=".array2string(is_array(self::$cache)));

		if (!is_array(self::$cache))
		{
			self::$cache = array();
		}
	}

	public function __destruct() {
		if (self::$_instance === $this)
		{
			self::$_instance = NULL;
		}
	}
}