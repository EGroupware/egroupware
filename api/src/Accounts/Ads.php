<?php
/**
 * API - accounts active directory backend
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 *
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts;

use EGroupware\Api;
use EGroupware\Api\Ldap\ServerInfo;

require_once EGW_INCLUDE_ROOT.'/vendor/adldap2/adldap2/src/adLDAP.php';
use adLDAPException;

/**
 * Active directory backend for accounts
 *
 * RID (relative id / last part of string-SID) is used as numeric account-id (negativ for groups).
 * SID for queries get reconstructed from account_id by prepending domain-SID.
 *
 * Easiest way to enable SSL on a win2008r2 DC is to install role "Active Director Certificate Services"
 * or in German "Active Directory-Zertifikatsdienste" AND reboot.
 *
 * Changing passwords require ldap_modify_batch method available in PHP 5.4 >= 5.4.26,
 * PHP 5.5 >= 5.5.10 or PHP 5.6+. In earlier PHP versions ads_admin user (configured in setup)
 * has to have "Reset Password" privileges!
 *
 * @access internal only use the interface provided by the accounts class
 * @link http://www.selfadsi.org/user-attributes-w2k8.htm
 * @link http://www.selfadsi.org/attributes-e2k7.htm
 * @link http://msdn.microsoft.com/en-us/library/ms675090(v=vs.85).aspx
 */
class Ads
{
	use LdapVlvSortRequestTrait;

	/**
	 * Timestamps ldap => egw used in several places
	 *
	 * @var string[]
	 */
	public $timestamps2egw = [
		'whencreated' => 'account_created',
		'whenchanged' => 'account_modified',
		'accountexpires'  => 'account_expires',
		'lastlogon' => 'account_lastlogin',
	];

	/**
	 * Other attributes sorted by their default matching rule
	 */
	public $other2egw = [
		'primarygroupid' => 'account_primary_group',
	];

	/**
	 * String attributes which can be sorted by caseIgnoreMatch ldap => egw
	 *
	 * @var string[]
	 */
	public $attributes2egw = [
		'samaccountname' => 'account_lid',
		'sn'             => 'account_lastname',
		'givenname'      => 'account_firstname',
		'displayname'    => 'account_fullname',
		'mail'           => 'account_email',
	];

	/**
	 * Instance of adLDAP class
	 *
	 * @var adLDAP
	 */
	private $adldap;
	/**
	 * total number of found entries from get_list method
	 *
	 * @var int
	 */
	public $total;

	/**
	 * Reference to our frontend
	 *
	 * @var Api\Accounts
	 */
	protected $frontend;

	/**
	 * Value of expires attribute for never
	 */
	const EXPIRES_NEVER = '9223372036854775807';

	/**
	 * AD does NOT allow to change sAMAccountName / account_lid
	 */
	const CHANGE_ACCOUNT_LID = false;

	/**
	 * Backend requires password to be set, before allowing to enable an account
	 */
	const REQUIRE_PASSWORD_FOR_ENABLE = true;

	/**
	 * Attributes to query to be able to generate account_id and account_lid
	 *
	 * @var array
	 */
	protected static $default_attributes = array(
		'objectsid', 'samaccounttype', 'samaccountname',
	);

	/**
	 * Attributes to query for a user (need to contain $default_attributes!)
	 *
	 * @var array
	 */
	protected static $user_attributes = array(
		'objectsid', 'samaccounttype', 'samaccountname',
		'primarygroupid', 'givenname', 'sn', 'mail', 'displayname', 'telephonenumber',
		'objectguid', 'useraccountcontrol', 'accountexpires', 'pwdlastset', 'whencreated', 'whenchanged', 'lastlogon',
		'jpegphoto',
	);

	/**
	 * Attributes to query for a group (need to contain $default_attributes!)
	 *
	 * @var array
	 */
	protected static $group_attributes = array(
		'objectsid', 'samaccounttype', 'samaccountname',
		'objectguid', 'mail', 'whencreated', 'whenchanged', 'description',
	);

	/**
	 * All users with an account_id below that get ignored, because they are system users (incl. 501="Administrator")
	 */
	const MIN_ACCOUNT_ID = 1000;

	/**
	 * Ignore group-membership of following groups, when compiling group-members
	 *
	 * We ignore "Domain Users" group with RID 513, as it contains all users!
	 *
	 * @var int[]
	 */
	public $ignore_membership = [ -513 ];

	/**
	 * Enable extra debug messages via error_log (error always get logged)
	 */
	public static $debug = false;

	/**
	 * Constructor
	 *
	 * @param Api\Accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 * @throws adLDAPException
	 */
	function __construct(Api\Accounts $frontend)
	{
		$this->frontend = $frontend;

		$this->adldap = self::get_adldap($this->frontend->config);

		$this->serverinfo = ServerInfo::get($this->ldap_connection(), $this->frontend->config['ads_host']);
	}

	/**
	 * Factory method and singleton to get adLDAP object for given configuration or default server config
	 *
	 * @param array $config=null values for keys 'ads_domain', 'ads_host' (required) and optional 'ads_admin_user', 'ads_admin_passwd', 'ads_connection'
	 * @return adLDAP
	 * @throws adLDAPException
	 */
	public static function get_adldap(array &$config=null)
	{
		static $adldap = array();
		if (!$config) $config =& $GLOBALS['egw_info']['server'];

		if (!isset($adldap[$config['ads_domain']]))
		{
			if (empty($config['ads_host'])) throw new Api\Exception("Required ADS host name(s) missing!");
			if (empty($config['ads_domain'])) throw new Api\Exception("Required ADS domain missing!");

			$base_dn_parts = array();
			foreach(explode('.', $config['ads_domain']) as $dc)
			{
				$base_dn_parts[] = 'DC='.$dc;
			}
			$base_dn = implode(',', $base_dn_parts);

			$options = array(
				// always return an uri (incl. port), but no use_ssl or ad_port
				'domain_controllers' => array_map(static function($uri_host_port) use ($config)
				{
					if (!preg_match('#^(ldaps?://)?([^:]+)(:\d+)?$#', $uri_host_port, $matches))
					{
						throw new \Exception("Invalid value for AD controller '$uri_host_port' in '$config[ads_host]'");
					}
					if ($matches[1] === 'ldaps://' || $config['ads_connection'] === 'ssl')
					{
						return 'ldaps://'.$matches[2].($matches[3] ?? '');
					}
					return 'ldap://'.$matches[2].($matches[3] ?? '');
				}, preg_split('/[ ,]+/', trim($config['ads_host']))),
				'base_dn' => $base_dn ?: null,
				'account_suffix' => '@'.$config['ads_domain'],
				'admin_username' => $config['ads_admin_user'],
				'admin_password' => $config['ads_admin_passwd'],
				'use_tls' => $config['ads_connection'] == 'tls',
				'charset' => Api\Translation::charset(),
			);

			$adldap[$config['ads_domain']] = new adLDAP($options);
			if (self::$debug) error_log(__METHOD__."() new adLDAP(".array2string($options).") returned ".array2string($adldap[$config['ads_domain']]).' '.function_backtrace());
		}
		//else error_log(__METHOD__."() returning cached adLDAP ".array2string($adldap[$config['ads_domain']]).' '.function_backtrace());
		return $adldap[$config['ads_domain']];
	}

	/**
	 * Well known SIDs / RIDs NOT using the local DOMAIN-SID as prefix
	 *
	 * @link https://learn.microsoft.com/en-us/windows/win32/secauthz/well-known-sids
	 * @var string[]
	 */
	static $well_known_sids = [
		544 => 'S-1-5-32-544',  // BUILDIN\Administrators
		545 => 'S-1-5-32-545',  // BUILDIN\Users
		546 => 'S-1-5-32-546',  // BUILDIN\Guests
		547 => 'S-1-5-32-547',
		548 => 'S-1-5-32-548',
		549 => 'S-1-5-32-549',
		550 => 'S-1-5-32-550',
		551 => 'S-1-5-32-551',
		552 => 'S-1-5-32-552',
		553 => 'S-1-5-32-553',
		554 => 'S-1-5-32-554',
		555 => 'S-1-5-32-555',
		556 => 'S-1-5-32-556',
		557 => 'S-1-5-32-557',
		558 => 'S-1-5-32-558',
		559 => 'S-1-5-32-559',
		560 => 'S-1-5-32-560',
		561 => 'S-1-5-32-561',
		562 => 'S-1-5-32-562',
		568 => 'S-1-5-32-568',
		569 => 'S-1-5-32-569',
		571 => 'S-1-5-32-571',
		572 => 'S-1-5-32-572',
		573 => 'S-1-5-32-573',
		574 => 'S-1-5-32-574',
		575 => 'S-1-5-32-575',
		576 => 'S-1-5-32-576',
		577 => 'S-1-5-32-577',
		578 => 'S-1-5-32-578',
		579 => 'S-1-5-32-579',
		580 => 'S-1-5-32-579',
		581 => 'S-1-5-32-581',
		582 => 'S-1-5-32-582',
		583 => 'S-1-5-32-583',
	];

	/**
	 * Get SID of domain or an account
	 *
	 * @param int $account_id
	 * @return string|NULL
	 */
	public function get_sid($account_id=null)
	{
		if (isset($account_id) && $account_id < 0 && abs($account_id) < 1000 && isset(self::$well_known_sids[abs($account_id)]))
		{
			return self::$well_known_sids[abs($account_id)];
		}

		static $domain_sid = null;
		if (!isset($domain_sid))
		{
			$domain_sid = Api\Cache::getCache($this->frontend->config['install_id'], __CLASS__, 'ads_domain_sid');
			if ((!is_array($domain_sid) || !isset($domain_sid[$this->frontend->config['ads_domain']])) &&
				($adldap = self::get_adldap($this->frontend->config)) &&
				($sr = ldap_search($adldap->getLdapConnection(), $adldap->getBaseDn(), '(objectclass=domain)', array('objectsid'))) &&
				(($entries = ldap_get_entries($adldap->getLdapConnection(), $sr)) || true))
			{
				$domain_sid = array();
				$domain_sid[$this->frontend->config['ads_domain']] = $adldap->utilities()->getTextSID($entries[0]['objectsid'][0]);
				Api\Cache::setCache($this->frontend->config['install_id'], __CLASS__, 'ads_domain_sid', $domain_sid);
			}
		}
		$sid = $domain_sid[$this->frontend->config['ads_domain']];
		if ($sid && abs($account_id))
		{
			$sid .= '-'.abs($account_id);
		}
		return $sid;
	}

	const DOMAIN_USERS_GROUP = 513;
	const ADS_CONTEXT = 'ads_context';
	const ADS_GROUP_CONTEXT = 'ads_group_context';

	/**
	 * Get context for user and group objects
	 *
	 * Can be set via server-config "ads_context" and "ads_group_context", otherwise baseDN is used
	 *
	 * @param boolean $set_if_empty =false true set from DN of "Domain Users" group #
	 * @param bool|null $user true: user, false: group, null: both
	 * @return string
	 */
	public function ads_context($set_if_empty=false, bool $user=null)
	{
		if (empty($this->frontend->config[self::ADS_CONTEXT]))
		{
			if ($set_if_empty && ($dn = $this->id2name(-self::DOMAIN_USERS_GROUP, 'account_dn')))
			{
				$dn = preg_replace('/^CN=.*?,(CN|OU)=/i', '$1=', $dn);

				// Univention AD uses container OU=Groups for the stock groups, not like standard AD using OU=Users for both
				// save that as group context and generate OU=Users as user context from it
				if (preg_match('/^(CN|OU)=Groups,/i', $dn))
				{
					Api\Config::save_value(self::ADS_GROUP_CONTEXT, $this->frontend->config[self::ADS_GROUP_CONTEXT]=$dn, 'phpgwapi');
					$dn = preg_replace('/^(CN|OU)=(.*?),/i', '$1=Users,', $dn);
				}
				Api\Config::save_value(self::ADS_CONTEXT, $this->frontend->config[self::ADS_CONTEXT]=$dn, 'phpgwapi');
			}
			else
			{
				return $this->adldap->getBaseDn();
			}
		}
		// if we want and have a group context, use it
		if ($user === false && !empty($this->frontend->config[self::ADS_GROUP_CONTEXT]))
		{
			return $this->frontend->config[self::ADS_GROUP_CONTEXT];
		}
		// if we have a user-context and no group-context, use it
		if (empty($this->frontend->config[self::ADS_GROUP_CONTEXT]) && !empty($this->frontend->config[self::ADS_CONTEXT]))
		{
			return $this->frontend->config[self::ADS_CONTEXT];
		}
		// find shared base of both contexts and use it
		if (!empty($this->frontend->config[self::ADS_GROUP_CONTEXT]) && !empty($this->frontend->config[self::ADS_CONTEXT]))
		{
			$user_parts = explode(',', $this->frontend->config[self::ADS_CONTEXT]);
			$group_parts = explode(',', $this->frontend->config[self::ADS_GROUP_CONTEXT]);
			$shared_parts = [];
			while($user_parts && $group_parts && !strcasecmp($part=array_pop($user_parts), array_pop($group_parts)))
			{
				array_unshift($shared_parts, $part);
			}
			if ($shared_parts)
			{
				return implode(',', $shared_parts);
			}
		}
		// otherwise use base DN
		return $this->adldap->getBaseDn();
	}

	/**
	 * Get container for new user and group objects
	 *
	 * Can be set via server-config "ads_context" and "ads_group", otherwise parent of DN from "Domain Users" is used
	 *
	 * @param bool $user true: user, false: group, null: both
	 * @return string
	 */
	protected function _get_container(bool $user)
	{
		$context = $this->ads_context(true, $user);
		$base = $this->adldap->getBaseDn();
		$matches = null;
		if (!preg_match('/^(.*),'.preg_quote($base, '/').'$/i', $context, $matches))
		{
			throw new Api\Exception\WrongUserinput("Wrong or not configured ADS context '$context' (baseDN='$base')!");
		}
		$container = $matches[1];
		if (self::$debug) error_log(__METHOD__."() context='$context', base='$base' returning ".array2string($container));
		return $container;
	}

	/**
	 * Get connection to ldap server from adLDAP
	 *
	 * @param boolean $reconnect =false true: reconnect even if already connected
	 * @return resource
	 */
	public function ldap_connection($reconnect=false)
	{
		if (($reconnect || !($ds = $this->adldap->getLdapConnection())) &&
			// call connect, thought I dont know how it can be not connected ...
			!$this->adldap->connect() || !($ds = $this->adldap->getLdapConnection()))
		{
			error_log(__METHOD__."() !this->adldap->getLdapConnection() this->adldap=".array2string($this->adldap));
		}
		return $ds;
	}

	/**
	 * Get GUID from SID, as adLDAP only works on GUID not SID currently
	 *
	 * @param string $sid
	 * @return string|NULL
	 */
	/*protected function sid2guid($sid)
	{
		if (($sr = ldap_search($this->adldap->getLdapConnection(), $this->ads_context(), 'objectsid='.$sid, array('objectguid'))) &&
			($entries = ldap_get_entries($this->adldap->getLdapConnection(), $sr)))
		{
			return $this->adldap->utilities()->decodeGuid($entries[0]['objectguid'][0]);
		}
		return null;
	}*/

	/**
	 * Convert SID to account_id (RID = last part of SID)
	 *
	 * @param string $sid
	 * @return int
	 */
	public static function sid2account_id($sid)
	{
		$parts = explode('-', $sid);

		return (int)array_pop($parts);
	}

	/**
	 * Convert binary SID to account_id (RID = last part of SID)
	 *
	 * @param string $objectsid
	 * @return int
	 */
	public function objectsid2account_id($objectsid)
	{
		$sid = $this->adldap->utilities()->getTextSID(is_array($objectsid) ? $objectsid[0] : $objectsid);

		return self::sid2account_id($sid);
	}

	/**
	 * Convert binary GUID to string
	 *
	 * @param string $objectguid
	 * @return int
	 */
	public function objectguid2str($objectguid)
	{
		return $this->adldap->utilities()->decodeGuid(is_array($objectguid) ? $objectguid[0] : $objectguid);
	}

	/**
	 * Convert a string GUID to hex string used in filter
	 *
	 * @param string $strGUID
	 * @return int
	 */
	public function objectguid2hex($strGUID)
	{
		return $this->adldap->utilities()->strGuidToHex($strGUID);
	}

	/**
	 * Reads the data of one account
	 *
	 * @param int $account_id numeric account-id
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	public function read($account_id)
	{
		if (!(int)$account_id) return false;

		$ret = $account_id < 0 ? $this->_read_group($account_id) : $this->_read_user($account_id);
		if (self::$debug) error_log(__METHOD__."($account_id) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Saves / adds the data of one account
	 *
	 * If no account_id is set in data the account is added and the new id is set in $data.
	 *
	 * @param array $data array with account-data
	 * @return int|boolean the account_id or false on error
	 */
	function save(&$data)
	{
		$is_group = $data['account_id'] < 0 || $data['account_type'] === 'g';
		$data = Api\Translation::convert($data, Api\Translation::charset(), 'utf-8');

		if ($data['account_id'] && !($old = $this->read($data['account_id'])))
		{
			error_log(__METHOD__.'('.array2string($data).") account NOT found!");
			return false;
		}
		if ($old)
		{
			if (($old['account_type'] == 'g') != $is_group)
			{
				error_log(__METHOD__.'('.array2string($data).") changing account-type user <--> group forbidden!");
				return false;
			}
			$old = Api\Translation::convert($old, Api\Translation::charset(), 'utf-8');
		}
		$ret = $is_group ? $this->_save_group($data, $old) : $this->_save_user($data, $old);

		if (self::$debug) error_log(__METHOD__.'('.array2string($data).') returning '.array2string($ret));
		return $ret;
	}

	/**
	 * Delete one account, deletes also all acl-entries for that account
	 *
	 * @param int $account_id numeric account_id
	 * @return boolean true on success, false otherwise
	 */
	function delete($account_id)
	{
		if (!(int)$account_id || !($account_lid = $this->id2name($account_id)))
		{
			error_log(__METHOD__."($account_id) NOT found!");
			return false;
		}

		// for some reason deleting fails with "ldap_search(): supplied argument is not a valid ldap link resource"
		// forcing a reconnect fixes it ;-)
		$this->ldap_connection(true);

		if ($account_id < 0)
		{
			$ret = $this->adldap->group()->delete($account_lid);
		}
		else
		{
			$ret = $this->adldap->user()->delete($account_lid);
		}
		if (self::$debug) error_log(__METHOD__."($account_id) account_lid='$account_lid' returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Convert ldap data of a group
	 *
	 * @param array $_data
	 * @return array
	 */
	protected function _ldap2group($_data)
	{
		$data = Api\Translation::convert($_data, 'utf-8');

		// no need to calculate sid, if already calculated
		$sid = is_string($data['objectsid']) ? $data['objectsid'] :
			$this->adldap->utilities()->getTextSID($data['objectsid'][0]);
		$account_id = -self::sid2account_id($sid);

		$group = array(
			'account_dn'        => $data['dn'],
			'account_id'        => $account_id,
			'account_sid'       => $sid,
			'account_guid'      => $this->adldap->utilities()->decodeGuid($data['objectguid'][0]),
			'account_lid'       => $data['samaccountname'][0],
			'account_type'      => 'g',
			'account_firstname' => $data['samaccountname'][0],
			'account_lastname'  => lang('Group'),
			'account_fullname'  => lang('Group').' '.$data['samaccountname'][0],
			'account_email'     => $data['mail'][0],
			'account_created'   => !isset($data['whencreated'][0]) ? null :
				self::_when2ts($data['whencreated'][0]),
			'account_modified'  => !isset($data['whenchanged'][0]) ? null :
				self::_when2ts($data['whenchanged'][0]),
			'account_description' => $data['description'][0],
			'mailAllowed'       => true,
		);
		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($group));
		return $group;
	}

	/**
	 * Reads the data of one group
	 *
	 * @internal
	 * @todo take recursive group memberships into account
	 * @param int $account_id numeric account-id (< 0 as it's for a group)
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	protected function _read_group($account_id)
	{
		if (!($data = $this->filter(array('objectsid' => $this->get_sid($account_id)), 'g', self::$group_attributes)))
		{
			return false;    // group not found
		}
		$group = $this->_ldap2group(array_shift($data));

		$group['members'] = $this->getMembers($group);

		return $group;
	}

	/**
	 * Query members of group
	 *
	 * @param array $group with values for keys account_id and account_dn
	 * @return array
	 */
	public function getMembers(array $group)
	{
		if (empty($group['account_dn']) || empty($group['account_id']))
		{
			throw new \InvalidArgumentException(__METHOD__.'('.json_encode($group).') missing account_id and/or account_dn attribute');
		}
		// for memberships, we have to query primaryGroupId and memberOf of users
		$members = $this->filter(array('memberOf' => $group['account_dn']), 'u');
		// primary group is not stored in memberOf attribute, need to add them too
		$members = $this->filter(array('primaryGroupId' => abs($group['account_id'])), 'u', null, $members);

		return $members;
	}

	/**
	 * Convert ldap data of a user
	 *
	 * @param array $_data
	 * @return array
	 */
	protected function _ldap2user(array $_data)
	{
		$data = Api\Translation::convert($_data, 'utf-8');

		// no need to calculate sid, if already calculated
		$sid = is_string($data['objectsid']) ? $data['objectsid'] :
			$this->adldap->utilities()->getTextSID($data['objectsid'][0]);
		$account_id = self::sid2account_id($sid);

		$user = array(
			'account_dn'        => $data['dn'],
			'account_id'        => $account_id,
			'account_sid'       => $sid,
			'account_guid'      => $this->adldap->utilities()->decodeGuid($data['objectguid'][0]),
			'account_lid'       => $data['samaccountname'][0],
			'account_type'      => 'u',
			'account_primary_group' => (string)-$data['primarygroupid'][0],
			'account_firstname' => $data['givenname'][0],
			'account_lastname'  => $data['sn'][0],
			'account_email'     => $data['mail'][0],
			'account_fullname'  => $data['displayname'][0],
			'account_phone'     => $data['telephonenumber'][0],
			'account_status'    => $data['useraccountcontrol'][0] & 2 ? false : 'A',
			'account_expires'   => !isset($data['accountexpires']) || !$data['accountexpires'][0] ||
				$data['accountexpires'][0] == self::EXPIRES_NEVER ? -1 :
				$this->adldap->utilities()->convertWindowsTimeToUnixTime($data['accountexpires'][0]),
			'account_lastpwd_change' => !isset($data['pwdlastset']) ? null : (!$data['pwdlastset'][0] ? 0 :
				$this->adldap->utilities()->convertWindowsTimeToUnixTime($data['pwdlastset'][0])),
			'account_lastlogin' => empty($data['lastlogon'][0]) ? null :
				$this->adldap->utilities()->convertWindowsTimeToUnixTime($data['lastlogon'][0]),
			'account_created' => !isset($data['whencreated'][0]) ? null :
				self::_when2ts($data['whencreated'][0]),
			'account_modified' => !isset($data['whenchanged'][0]) ? null :
				self::_when2ts($data['whenchanged'][0]),
			'account_has_photo' => !empty($data['jpegphoto'][0])
		);
		// expired accounts are NOT active
		if ($user['account_expires'] !== -1 && $user['account_expires'] < time())
		{
			$user['account_status'] = false;
		}
		$user['person_id'] = $user['account_guid'];	// id of contact
		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($user));
		return $user;
	}

	/**
	 * Check if user is active
	 *
	 * @param array $data values for attributes 'useraccountcontrol' and 'accountexpires'
	 * @return boolean true if user is active, false otherwise
	 */
	public function user_active(array $data)
	{
		$user = $this->_ldap2user($data);
		$active = Api\Accounts::is_active($user);
		//error_log(__METHOD__."(cn={$data['cn'][0]}, useraccountcontrol={$data['useraccountcontrol'][0]}, accountexpires={$data['accountexpires'][0]}) user=".array2string($user)." returning ".array2string($active));
		return $active;
	}

	/**
	 * Reads the data of one user
	 *
	 * @internal
	 * @param int $account_id numeric account-id
	 * @return array|boolean array with account data (keys: account_id, account_lid, ...) or false if account not found
	 */
	protected function _read_user($account_id)
	{
		if (!($data = $this->filter(array('objectsid' => $this->get_sid($account_id)), 'u', self::$user_attributes)))
		{
			return false;	// user not found
		}
		$user = $this->_ldap2user(array_shift($data));

		// query memberships direct, as accounts class will query it anyway and we still have dn and primary group available
		$user['memberships'] = $this->filter(array('member' => $user['account_dn']), 'g');
		if (!isset($user['memberships'][$user['account_primary_group']]))
		{
			$user['memberships'][$user['account_primary_group']] = $this->id2name($user['account_primary_group']);
		}
		return $user;
	}

	const WHEN_FORMAT = 'YmdHis';

	/**
	 * Convert when(Created|Changed) attribute to unix timestamp
	 *
	 * @param string $_when eg. "20130520200000.0Z"
	 * @return int
	 */
	protected static function _when2ts($_when)
	{
		static $utc=null;
		if (!isset($utc)) $utc = new \DateTimeZone('UTC');

		list($when) = explode('.', $_when);	// remove .0Z not understood by createFromFormat
		$datetime = Api\DateTime::createFromFormat(self::WHEN_FORMAT, $when, $utc);
		if (Api\DateTime::$server_timezone) $datetime->setTimezone(Api\DateTime::$server_timezone);

		return $datetime->getTimestamp();
	}

	/**
	 * Saves a group
	 *
	 * @internal
	 * @param array $data array with account-data in utf-8
	 * @param array $old =null current data
	 * @return int|false account_id or false on error
	 */
	protected function _save_group(array &$data, array $old=null)
	{
		//error_log(__METHOD__.'('.array2string($data).', old='.array2string($old).')');

		if (!$old)	// new entry
		{
			static $new2adldap = array(
				'account_lid'       => 'group_name',
				'account_description' => 'description',
			);
			$attributes = array();
			foreach($new2adldap as $egw => $adldap)
			{
				$attributes[$adldap] = (string)$data[$egw];
			}
			$attributes['container'] = $this->_get_container(false);

			$ret = $this->adldap->group()->create($attributes);
			if ($ret !== true)
			{
				error_log(__METHOD__."(".array2string($data).") adldap->group()->create(".array2string($attributes).') returned '.array2string($ret));
				return false;
			}
			if (!($ret = $this->name2id($data['account_lid'])) || !($old = $this->read($ret)))
			{
				error_log(__METHOD__."(".array2string($data).") newly created group NOT found!");
				return false;
			}
		}

		// Samba4 does NOT allow to change samaccountname, but CN or DN of a group!
		// therefore we do NOT allow to change group-name for now (adLDAP also has no method for it)
		/* check if DN/account_lid changed (not yet supported by adLDAP)
		if ($old['account_lid'] !== $data['account_lid'])
		{
			if (!($ret = ldap_rename($ds=$this->ldap_connection(), $old['account_dn'],
				'CN='.$this->adldap->utilities()->ldapSlashes($data['account_lid']), null, true)))
			{
				error_log(__METHOD__."(".array2string($data).") rename to new CN failed!");
				return false;
			}
		}*/
		static $egw2adldap = array(
			//'account_lid'       => 'samaccountname',	// need to be changed too
			'account_email'       => 'mail',
			'account_description' => 'description',
		);
		$ldap = array();
		foreach($egw2adldap as $egw => $adldap)
		{
			if (isset($data[$egw]) && (string)$data[$egw] != (string)$old[$egw])
			{
				switch($egw)
				{
					case 'account_description':
						$ldap[$adldap] = !empty($data[$egw]) ? $data[$egw] : array();
						break;

					default:
						$ldap[$adldap] = $data[$egw];
						break;
				}
			}
		}
		// attributes not (yet) supported by adldap
		if ($ldap && !($ret = @ldap_modify($ds=$this->ldap_connection(), $old['account_dn'], $ldap)))
		{
			error_log(__METHOD__."(".array2string($data).") ldap_modify(\$ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret));
			return false;
		}
		return $old['account_id'];
	}

	/**
	 * Saves a user account
	 *
	 * @internal
	 * @param array $data array with account-data in utf-8
	 * @param array $old =null current data
	 * @return int|false account_id or false on error
	 */
	protected function _save_user(array &$data, array $old=null)
	{
		//error_log(__METHOD__.'('.array2string($data).', old='.array2string($old).')');
		if (!isset($data['account_fullname']) && !empty($data['account_firstname']) && !empty($data['account_lastname']))
		{
			$data['account_fullname'] = $data['account_firstname'].' '.$data['account_lastname'];
		}

		if (($new_entry = !$old))	// new entry
		{
			static $new2adldap = array(
				'account_lid'       => 'username',
				'account_firstname' => 'firstname',
				'account_lastname'  => 'surname',
				'account_email'     => 'email',
				'account_fullname'  => 'display_name',
				'account_passwd'    => 'password',
				'account_status'    => 'enabled',
			);
			$attributes = array();
			foreach($new2adldap as $egw => $adldap)
			{
				if ($egw == 'account_passwd' && (empty($data[$egw]) ||
					!$this->adldap->getUseSSL() && !$this->adldap->getUseTLS()))
				{
					continue;	// do not try to set password, if no SSL or TLS, whole user creation will fail
				}
				if (isset($data[$egw])) $attributes[$adldap] = $data[$egw];
			}
			$attributes['enabled'] = !isset($data['account_status']) || $data['account_status'] === 'A';
			$attributes['container'] = $this->_get_container(true);

			$ret = $this->adldap->user()->create($attributes);
			if ($ret !== true)
			{
				error_log(__METHOD__."(".array2string($data).") adldap->user()->create(".array2string($attributes).') returned '.array2string($ret));
				return false;
			}
			if (!($ret = $this->name2id($data['account_lid'])) || !($old = $this->read($ret)))
			{
				error_log(__METHOD__."(".array2string($data).") newly created user NOT found!");
				return false;
			}
			$data['account_id'] = $old['account_id'];
		}
		// check if DN/account_lid changed (not yet supported by adLDAP)
		/* disabled as AD does NOT allow to change user-name (account_lid), which is used for DN
		if (isset($data['account_lid']) && $old['account_lid'] !== $data['account_lid'] ||
			(stripos($old['account_dn'], 'CN='.$data['account_lid'].',') !== 0))
		{
			if (!($ret = ldap_rename($ds=$this->ldap_connection(), $old['account_dn'],
				'CN='.$this->adldap->utilities()->ldapSlashes($data['account_lid']), null, true)))
			{
				error_log(__METHOD__."(".array2string($data).") rename to new CN failed!");
				return false;
			}
		}*/
		static $egw2adldap = array(
			'account_lid'       => 'samaccountname',
			'account_firstname' => 'firstname',
			'account_lastname'  => 'surname',
			'account_email'     => 'email',
			'account_fullname'  => 'display_name',	// handeled currently in rename above, as not supported by adLDAP
			'account_passwd'    => 'password',
			'account_status'    => 'enabled',
			'account_primary_group' => 'primarygroupid',
			'account_expires'   => 'expires',
			//'mustchangepassword'=> 'change_password',	// can only set it, but not reset it, therefore we set pwdlastset direct
			'account_lastpwd_change' => 'pwdlastset',
			//'account_phone'   => 'telephone',	not updated by accounts, only read so far
		);
		$attributes = $ldap = array();
		// for a new entry set certain values (eg. profilePath) to in setup configured value
		if ($new_entry)
		{
			foreach($this->frontend->config as $name => $value)
			{
				if (substr($name, 0, 8) == 'ads_new_')
				{
					$ldap[substr($name, 8)] = str_replace('%u', $data['account_lid'], $value);
				}
			}
		}
		foreach($egw2adldap as $egw => $adldap)
		{
			if (isset($data[$egw]) && (string)$data[$egw] != (string)$old[$egw])
			{
				switch($egw)
				{
					case 'account_passwd':
						if (!empty($data[$egw]) && ($this->adldap->getUseSSL() || $this->adldap->getUseTLS()))
						{
							$attributes[$adldap] = $data[$egw];	// only try to set password, if no SSL or TLS
						}
						break;
					case 'account_primary_group':
						// setting a primary group seems to fail, if user is no member of that group
						if (isset($old['memberships'][$data[$egw]]) ||
							($group=$this->id2name($data[$egw])) && $this->adldap->group()->addUser($group, $data['account_id']))
						{
							$old['memberships'][$data[$egw]] = $group;
							$ldap[$adldap] = abs($data[$egw]);
						}
						break;
					case 'account_lid':
						$ldap[$adldap] = $data[$egw];
						$ldap['userPrincipalName'] = $data[$egw].'@'.$this->frontend->config['ads_domain'];
						break;
					case 'account_expires':
						$attributes[$adldap] = $data[$egw] == -1 ? self::EXPIRES_NEVER :
							self::convertUnixTimeToWindowsTime($data[$egw]);
						break;
					case 'account_status':
						if ($new_entry && empty($data['account_passwd'])) continue 2;	// cant active new account without passwd!
						$attributes[$adldap] = $data[$egw] == 'A';
						break;
					case 'account_lastpwd_change':
						// Samba4 does not understand -1 for current time, but Win2008r2 only allows to set -1 (beside 0)
						// call Api\Auth\Ads::setLastPwdChange with true to get correct modification for both
						$ldap = array_merge($ldap, Api\Auth\Ads::setLastPwdChange($data['account_lid'], null, $data[$egw], true));
						break;
					default:
						$attributes[$adldap] = $data[$egw];
						break;
				}
			}
		}
		// check if we need to update something
		if ($attributes && !($ret = $this->adldap->user()->modify($data['account_lid'], $attributes)))
		{
			error_log(__METHOD__."(".array2string($data).") adldap->user()->modify('$data[account_lid]', ".array2string($attributes).') returned '.array2string($ret).' '.function_backtrace());
			return false;
		}
		//elseif ($attributes) error_log(__METHOD__."(".array2string($data).") adldap->user()->modify('$data[account_lid]', ".array2string($attributes).') returned '.array2string($ret).' '.function_backtrace());
		// attributes not (yet) suppored by adldap
		if ($ldap && !($ret = @ldap_modify($ds=$this->ldap_connection(), $old['account_dn'], $ldap)))
		{
			error_log(__METHOD__."(".array2string($data).") ldap_modify(\$ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret).' ('.ldap_error($ds).') '.function_backtrace());
			return false;
		}
		//elseif ($ldap) error_log(__METHOD__."(".array2string($data).") ldap_modify($ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret).' '.function_backtrace());

		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($old['account_id']));
		return $old['account_id'];
	}

	/**
	* Add seconds between 1601-01-01 and 1970-01-01 and multiply by 10000000
	*
	* @param int $unixTime
	* @return int windowsTime
	*/
	public static function convertUnixTimeToWindowsTime($unixTime)
	{
		return ($unixTime + 11644477200) * 10000000;
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @param array with the following keys:
	 * @param $param['type'] string/int 'accounts', 'groups', 'owngroups' (groups the user is a member of), 'both'
	 *	or integer group-id for a list of members of that group
	 * @param $param['start'] int first account to return (returns offset or max_matches entries) or all if not set
	 * @param $param['order'] string column to sort after, default account_lid if unset
	 * @param $param['sort'] string 'ASC' or 'DESC', default 'ASC' if not set
	 * @param $param['query'] string to search for, no search if unset or empty
	 * @param $param['query_type'] string:
	 *	'all'   - query all fields for containing $param[query]
	 *	'start' - query all fields starting with $param[query]
	 *	'exact' - query all fields for exact $param[query]
	 *	'lid','firstname','lastname','email' - query only the given field for containing $param[query]
	 * @param $param['offset'] int - number of matches to return if start given, default use the value in the prefs
	 * @param $param['objectclass'] boolean return objectclass(es) under key 'objectclass' in each account
	 * @param $param['active'] boolean true: only return active / not expired accounts
	 * @param $param['modified'] int if given minimum modification time
	 * @param $param['account_id'] int[] return only given account_id's
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//error_log(__METHOD__.'('.json_encode($param).') '.function_backtrace());
		$start = $param['start'];
		if (!($maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'])) $maxmatchs = 15;
		if (!($offset = $param['offset'])) $offset = $maxmatchs;

		$this->total = null;
		$query = Api\Ldap::quote(strtolower($param['query']));

		$accounts = array();
		if($param['type'] !== 'groups')
		{
			if (!empty($query) && $query != '*')
			{
				switch($param['query_type'])
				{
					case 'all':
					default:
						$query = '*'.$query;
						// fall-through
					case 'start':
						$query .= '*';
						// fall-through
					case 'exact':
						$filter = "(|(samaccountname=$query)(sn=$query)(cn=$query)(givenname=$query)(mail=$query))";
						break;
					case 'firstname':
					case 'lastname':
					case 'lid':
					case 'email':
						static $to_ldap = array(
							'firstname' => 'givenname',
							'lastname'  => 'sn',
							'lid'       => 'samaccountname',
							'email'     => 'mail',
						);
						$filter = '('.$to_ldap[$param['query_type']].'=*'.$query.'*)';
						break;
				}
			}
			if (is_numeric($param['type']))
			{
				$membership_filter = '(|(memberOf='.$this->id2name((int)$param['type'], 'account_dn').')(PrimaryGroupId='.abs($param['type']).'))';
				$filter = $filter ? "(&$membership_filter$filter)" : $membership_filter;
			}
			if (!empty($param['account_id']))
			{
				$account_ids_filter = '(|(objectsid='.implode(')(objectsid=', array_map([$this, 'get_sid'], (array)$param['account_id'])).')';
				$filter = $filter ? "(&$filter$account_ids_filter)" : $account_ids_filter;
			}
			if (!empty($param['modified']))
			{
				$filter = "(&(whenChanged>=".gmdate('YmdHis', $param['modified']).".0Z)$filter)";
			}
			foreach($this->filter($filter, 'u', self::$user_attributes, [], $param['active'], $param['order'].' '.$param['sort'], $start, $offset, $this->total) as $account_id => $data)
			{
				$account = $this->_ldap2user($data);
				$account['account_fullname'] = Api\Accounts::format_username($account['account_lid'],$account['account_firstname'],$account['account_lastname'],$account['account_id']);
				$accounts[$account_id] = $account;
			}
		}
		if ($param['type'] === 'groups' || $param['type'] === 'both')
		{
			$query = Api\Ldap::quote(strtolower($param['query']));

			$filter = null;
			if(!empty($query) && $query != '*')
			{
				switch($param['query_type'])
				{
					case 'all':
					default:
						$query = '*'.$query;
						// fall-through
					case 'start':
						$query .= '*';
						// fall-through
					case 'exact':
						break;
				}
				$filter = "(|(cn=$query)(description=$query))";
			}
			if (!empty($param['modified']))
			{
				$filter = "(&(whenChanged>=".gmdate('YmdHis', $param['modified']).".0Z)$filter)";
			}
			foreach($this->filter($filter, 'g', self::$group_attributes) as $account_id => $data)
			{
				$accounts[$account_id] = $this->_ldap2group($data);
			}
		}
		// sort the array
		$this->_callback_sort = strtoupper($param['sort']);
		$this->_callback_order = empty($param['order']) ? array('account_lid') : explode(',',$param['order']);
		foreach($this->_callback_order as &$col)
		{
			if (substr($col, 0, 8) !== 'account_') $col = 'account_'.$col;
		}
		$sortedAccounts = $accounts;
		uasort($sortedAccounts,array($this,'_sort_callback'));

		$this->total = $this->total ?? count($accounts);

		// return only the wanted accounts
		reset($sortedAccounts);
		if(is_numeric($start) && is_numeric($offset))
		{
			return array_slice($sortedAccounts, $start, $offset, true);
		}
		//error_log(__METHOD__.'('.array2string($param).') returning all '.array2string($sortedAccounts));
		return $sortedAccounts;
	}

	/**
	 * DESC or ASC
	 *
	 * @var string
	 */
	private $_callback_sort = 'ASC';
	/**
	 * column_names to sort by
	 *
	 * @var array
	 */
	private $_callback_order = array('account_lid');

	/**
	 * Sort callback for uasort
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function _sort_callback($a,$b)
	{
		foreach($this->_callback_order as $col )
		{
			if($this->_callback_sort != 'DESC')
			{
				$cmp = strcasecmp( $a[$col], $b[$col] );
			}
			else
			{
				$cmp = strcasecmp( $b[$col], $a[$col] );
			}
			if ( $cmp != 0 )
			{
				return $cmp;
			}
		}
		return 0;
	}

	/**
	 * Get LDAP filter for user, groups or both
	 *
	 * @param string|null $account_type u = user, g = group, default null = try both
	 * @param bool $filter_expired =false true: filter out expired users
	 * @return string string with LDAP filter
	 */
	public function type_filter($account_type=null, $filter_expired=false)
	{
		switch ($account_type)
		{
			default:    // user or groups
			case 'u':
				$type_filter = '(&(samaccounttype=' . adLDAP::ADLDAP_NORMAL_ACCOUNT . ')';
				$type_filter .= '(!(isCriticalSystemObject=*))';	// exclude stock users (eg. Administrator) and groups
				if ($filter_expired)
				{
					$type_filter .= '(|(!(accountExpires=*))(accountExpires=0)(accountExpires>='.self::convertUnixTimeToWindowsTime(time()).'))';
				}
				if (!empty($this->frontend->config['ads_user_filter']))
				{
					$type_filter .= $this->frontend->config['ads_user_filter'];
				}
				// for non-admins and account_selection "groupmembers" we have to filter by memberOf attribute
				if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] === 'groupmembers' &&
					isset($GLOBALS['egw_info']['user']['account_id']) &&	// only use groupmembers, if we have a user context (stalls login otherwise!)
					(!isset($GLOBALS['egw_info']['user']['apps']['admin'])))
				{
					// generate groupmembers filter once per session without any external calls (but groups) to NOT generate a deep recursion!
					$type_filter .= Api\Cache::getSession(__CLASS__, 'groupmembers_filter', function()
					{
						$account_id = $GLOBALS['egw_info']['user']['account_id'];
						$account = $this->filter(['objectSid' => $this->get_sid($account_id)],
							false, ['memberOf','primaryGroupID','objectSid','samAccountType'])[$account_id];
						$filter = '(|';
						$primary_ignored = in_array(-$account['primarygroupid'][0], $this->ignore_membership);
						foreach($account['memberof'] as $key => $dn)
						{
							if ($key === 'count') continue;
							// if the primary group of the user is ignored (=Domain Users), we assume it's the case for everyone
							if ($primary_ignored)
							{
								$filter .= '(memberOf='.$dn.')';
								continue;
							}
							if (!isset($groups))
							{
								$groups = $this->frontend->search(['type' => 'groups']);
							}
							foreach($groups as $gid => $group)
							{
								if (($gid == -$account['primarygroupid'][0] || $dn === $group['account_dn']) &&
									!in_array($gid, $this->ignore_membership))
								{
									$filter .= '(memberOf='.$group['account_dn'].')(primaryGroupID='.(-$gid).')';
									break;
								}
							}
						}
						return $filter.')';
					});
				}
				$type_filter .= ')';
				if ($account_type === 'u') break;
				$user_filter = $type_filter;
			// fall through
			case 'g':
				/** @noinspection SuspiciousAssignmentsInspection */
				$type_filter = '(|(samaccounttype=' . adLDAP::ADLDAP_SECURITY_GLOBAL_GROUP .
					')(samaccounttype=' . adLDAP::ADLDAP_SECURITY_LOCAL_GROUP;
				// should we also consider distribution-lists
				if (!empty($this->frontend->config['ads_group_extra_types']) && $this->frontend->config['ads_group_extra_types'] === 'distributionlists')
				{
					$type_filter .= ')(samaccounttype=' . adLDAP::ADLDAP_DISTRIBUTION_GROUP.
						')(samaccounttype=' . adLDAP::ADLDAP_DISTRIBUTION_LOCAL_GROUP;
				}
				$type_filter .=	'))';
				if (!empty($this->frontend->config['ads_group_filter']))
				{
					$type_filter = '(&' . $type_filter . $this->frontend->config['ads_group_filter'] . ')';
				}
				if ($account_type === 'g') break;
				// user or groups
				$type_filter = '(|' . $user_filter . $type_filter . ')';
				break;
		}
		return $type_filter;
	}

	/**
	 * Query ADS by (optional) filter and (optional) account-type filter
	 *
	 * All reading ADS queries are done through this method.
	 *
	 * @param string|array $attr_filter array with attribute => value pairs or filter string or empty
	 * @param string|false $account_type u = user, g = group, default null = try both, false: no type_filter!
	 * @param ?array $attrs =null default return account_lid, else return raw values from ldap-query
	 * @param array $accounts =array() array to add filtered accounts too, default empty array
	 * @param bool $filter_expired =false true: filter out expired users
	 * @param string $order_by sql order string eg. "contact_email ASC"
	 * @param ?int $start on return null, if result sorted and limited by server
	 * @param int $num_rows number of rows to return if isset($start)
	 * @param ?int $total on return total number of rows
	 * @return array account_id => account_lid or values for $attrs pairs
	 */
	protected function filter($attr_filter, $account_type=null, array $attrs=null, array $accounts=array(), $filter_expired=false, $order_by=null, &$start=null, $num_rows=null, &$total=null)
	{
		if (!$attr_filter)
		{
			$filter = $this->type_filter($account_type, $filter_expired);
		}
		else
		{
			$filter = '(&';
			if (is_string($attr_filter))
			{
				$filter .= $attr_filter;
			}
			else
			{
				foreach($attr_filter as $attr => $value)
				{
					$filter .= '('.$attr.'='.$this->adldap->utilities()->ldapSlashes($value).')';
				}
			}
			if ($account_type !== false)
			{
				$filter .= $this->type_filter($account_type);
			}
			$filter .= ')';
		}

		if (($allValues = $this->vlvSortQuery($this->ads_context(), $filter, $attrs ?? self::$default_attributes, $order_by, $start, $num_rows, $total)))
		{
			foreach($allValues as $data)
			{
				$sid = $data['objectsid'] = $this->adldap->utilities()->getTextSID($data['objectsid'][0]);
				$rid = self::sid2account_id($sid);

				$accounts[($data['samaccounttype'][0] == adLDAP::ADLDAP_NORMAL_ACCOUNT ? '' : '-').$rid] =
					$attrs ? $data : Api\Translation::convert($data['samaccountname'][0], 'utf-8');
			}
		}
		return $accounts;
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
	 * @param string $account_type u = user, g = group, default null = try both
	 * @return int|false numeric account_id or false on error ($name not found)
	 */
	public function name2id($name, $which='account_lid', $account_type=null)
	{
		static $to_ldap = array(
			'account_lid'   => 'samaccountname',
			'account_email' => 'mail',
			'account_fullname' => 'cn',
			'account_sid'   => 'objectsid',
			'account_guid'  => 'objectguid',
		);
		$ret = false;
		if (isset($to_ldap[$which]))
		{
			foreach($this->filter(array($to_ldap[$which] => $name), $account_type) as $account_id => $account_lid)
			{
				unset($account_lid);
				$ret = $account_id;
				break;
			}
		}
		if (self::$debug) error_log(__METHOD__."('$name', '$which', '$account_type') returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Convert an numeric account_id to any other value of that account (account_lid, account_email, ...)
	 *
	 * Calls frontend which uses (cached) read method to fetch all data by account_id.
	 *
	 * @param int $account_id numeric account_id
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string|false converted value or false on error ($account_id not found)
	 */
	public function id2name($account_id, $which='account_lid')
	{
		return $this->frontend->id2name($account_id,$which);
	}

	/**
	 * Update the last login timestamps and the IP
	 *
	 * @param int $_account_id
	 * @param string $ip
	 * @return int lastlogin time
	 */
	function update_lastlogin($_account_id, $ip)
	{
		unset($_account_id, $ip);	// not used, but required by function signature

		return false;	// not longer supported
	}

	/**
	 * Query memberships of a given account
	 *
	 * Calls frontend which uses (cached) read method to fetch all data by account_id.
	 *
	 * @param int $account_id
	 * @return array|boolean array with account_id => account_lid pairs or false if account not found
	 */
	function memberships($account_id)
	{
		if (!($data = $this->frontend->read($account_id)) || $data['account_id'] <= 0) return false;

		return $data['memberships'];
	}

	/**
	 * Query the members of a group
	 *
	 * Calls frontend which uses (cached) read method to fetch all data by account_id.
	 *
	 * @param int $gid
	 * @return array with uidnumber => uid pairs
	 */
	function members($gid)
	{
		if (!($data = $this->frontend->read($gid)) || $data['account_id'] >= 0) return false;

		return $data['members'];
	}

	/**
	 * Sets the memberships of the given account
	 *
	 * @param array $groups array with gidnumbers
	 * @param int $account_id uidnumber
	 * @return int number of added or removed memberships
	 */
	function set_memberships($groups,$account_id)
	{
		if (!($account = $this->id2name($account_id))) return;
		$current = array_keys($this->memberships($account_id));

		$changed = 0;
		foreach(array(
			'add' => array_diff($groups, $current),		// add account to all groups he is currently not in
			'remove' => array_diff($current, $groups),	// remove account from all groups he is only currently in
		) as $op => $memberships)
		{
			$func = $op.($account_id > 0 ? 'User' : 'Group');
			foreach($memberships as $gid)
			{
				$ok = $this->adldap->group()->$func($group=$this->id2name($gid), $account);
				//error_log(__METHOD__.'('.array2string($groups).", $account_id) $func('$group', '$account') returned ".array2string($ok));
				$changed += (int)$ok;
			}
		}
		if (self::$debug) error_log(__METHOD__.'('.array2string($groups).", $account_id) current=".array2string($current)." returning $changed");
		return $changed;
	}

	/**
	 * Set the members of a group
	 *
	 * @param array $users array with uidnumber or uid's
	 * @param int $gid gidnumber of group to set
	 * @return int number of added or removed members
	 */
	function set_members($users, $gid)
	{
		if (!($group = $this->id2name($gid))) return;
		$current = array_keys($this->members($gid));

		$changed = 0;
		foreach(array(
			'add' => array_diff($users, $current),	// add members currently not in
			'remove' => array_diff($current, $users),	// remove members only currently in
		) as $op => $members)
		{
			foreach($members as $account_id)
			{
				$func = $op.($account_id > 0 ? 'User' : 'Group');
				$ok = $this->adldap->group()->$func($group, $account=$this->id2name($account_id));
				//error_log(__METHOD__.'('.array2string($users).", $account_id) $func('$group', '$account') returned ".array2string($ok));
				$changed += (int)$ok;
			}
		}
		if (self::$debug) error_log(__METHOD__.'('.array2string($users).", $gid) current=".array2string($current)." returning $changed");
		return $changed;
	}
}

/**
 * Fixes and enhancements for adLDAP required by EGroupware
 *
 * - allow to use utf-8 charset internally, not just an 8-bit iso-charset
 * - support for Windows2008r2 (maybe earlier too) and Samba4 "CN=Users" DN as container to create users or groups
 */
class adLDAP extends \adLDAP
{
	/**
	 * Charset used for internal encoding
	 *
	 * @var string
	 */
	public $charset = 'iso-8859-1';

	function __construct(array $options=array())
	{
		if (isset($options['charset']))
		{
			$this->charset = strtolower($options['charset']);
		}
		parent::__construct($options);
	}

	private string $_controller;

	/**
	 * Reimplemented to try all given AD controllers, before finally failing
	 *
	 * @return bool
	 * @throws adLDAPException
	 */
	function connect()
	{
		// if no more working (not failed) controllers, try again with all of them
		if (!($controllers = array_diff($this->domainControllers, $failed=(array)Api\Cache::getInstance(__CLASS__, 'failed'))))
		{
			$controllers = $this->domainControllers;
			$failed = [];
			Api\Cache::unsetInstance(__CLASS__, 'failed');
		}
		if ((float)PHP_VERSION < 8.2)
		{
			$shuffled = [];
			while($controllers)
			{
				$shuffled[] = $controllers[$key = array_rand($controllers)];
				unset($controllers[$key]);
			}
			$controllers = $shuffled;
		}
		else
		{
			$r = new \Random\Randomizer();
			$controllers = $r->shuffleArray($controllers);
		}
		foreach($controllers as $this->_controller)
		{
			try {
				return parent::connect();
			}
			catch (adLDAPException $e) {
				$failed[] = $this->_controller;
				Api\Cache::setInstance(__CLASS__, 'failed', $failed, 300);
			}
		}
		// if none of the controllers worked, throw the exception
		throw $e;
	}

	/**
	 * Not so random anymore ;)
	 *
	 * @return string
	 */
	function randomController()
	{
		return $this->_controller ?? parent::randomController();
	}

	/**
	 * Reimplemented to check ldaps uri instead of the no longer used attribute $this->useSSL
	 *
	 * @return bool
	 */
	function getUseSSL()
	{
		return substr($this->_controller, 0, 8) === 'ldaps://';
	}

	/**
	 * Magic method called when object gets serialized
	 *
	 * We do NOT store ldapConnection, as we need to reconnect anyway.
	 * PHP 8.1 gives an error when trying to serialize LDAP\Connection object!
	 *
	 * @return array
	 */
	function __sleep()
	{
		$vars = get_object_vars($this);
		unset($vars['ldapConnection']);
		unset($this->ldapConnection);
		return array_keys($vars);
	}

	/**
    * Convert 8bit characters e.g. accented characters to UTF8 encoded characters
    *
    * Extended to use mbstring to convert from arbitrary charset to utf-8
	*/
	public function encode8Bit(&$item, $key)
	{
		if ($this->charset != 'utf-8' && $key != 'password')
		{
			if (function_exists('mb_convert_encoding'))
			{
				$item = mb_convert_encoding($item, 'utf-8', $this->charset);
			}
			else
			{
				parent::encode8Bit($item, $key);
			}
		}
	}

	/**
	 * Get the userclass interface
	 *
	 * @return adLDAPUsers
	 */
	public function user() {
		if (!$this->userClass) {
			$this->userClass = new adLDAPUsers($this);
		}
		return $this->userClass;
	}

    /**
    * Get the group class interface
    *
    * @return adLDAPGroups
    */
    public function group() {
        if (!$this->groupClass) {
            $this->groupClass = new adLDAPGroups($this);
        }
        return $this->groupClass;
    }

    /**
    * Get the utils class interface
    *
    * @return adLDAPUtils
    */
    public function utilities() {
        if (!$this->utilClass) {
            $this->utilClass = new adLDAPUtils($this);
        }
        return $this->utilClass;
    }

	/**
	 * Get last error from Active Directory.
	 *
	 * Reimplemented to return which AD we're connecting.
	 *
	 * return string
	 */
	public function getLastError()
	{
		$url = $this->useSSL ? 'ldaps://' : 'ldap://';
		if (!empty($this->adminUsername)) $url .= $this->adminUsername.$this->accountSuffix.'@';
		$url .= implode(',', $this->domainControllers);
		if (!empty($this->adPort)) $url .= ':'.$this->adPort;

		return $url.': '.parent::getLastError();
	}
}

/**
 * Fixes an enhancements for adLDAPUser required by EGroupware
 */
class adLDAPUsers extends \adLDAPUsers
{
	/**
	 * Create a user
	 *
	 * Extended to allow to specify $attribute["container"] as string, because array hardcodes "OU=", while Samba4 and win2008r2 uses "CN=Users"
	 *
	 * Extended to ensure following creating order required by at least win2008r2:
	 * - new user without password and deactivated
	 * - add password, see new method setPassword
	 * - activate user
	 *
	 * @param array $attributes The attributes to set to the user account
	 * @return bool
	 */
	public function create($attributes)
	{
		// Check for compulsory fields
		if (!array_key_exists("username", $attributes)){ return "Missing compulsory field [username]"; }
		if (!array_key_exists("firstname", $attributes)){ return "Missing compulsory field [firstname]"; }
		if (!array_key_exists("surname", $attributes)){ return "Missing compulsory field [surname]"; }
		if (!array_key_exists("email", $attributes)){ return "Missing compulsory field [email]"; }
		if (!array_key_exists("container", $attributes)){ return "Missing compulsory field [container]"; }
		if (empty($attributes["container"])){ return "Container attribute must be an array or string."; }

		if (array_key_exists("password",$attributes) && (!$this->adldap->getUseSSL() && !$this->adldap->getUseTLS())){
			throw new adLDAPException('SSL must be configured on your webserver and enabled in the class to set passwords.');
		}

		if (!array_key_exists("display_name", $attributes)) {
			$attributes["display_name"] = $attributes["firstname"] . " " . $attributes["surname"];
		}

		// Translate the schema
		$add = $this->adldap->adldap_schema($attributes);

		// Additional stuff only used for adding accounts
		$add["cn"][0] = $attributes["username"];
		$add["samaccountname"][0] = $attributes["username"];
		$add["userPrincipalName"][0] = $attributes["username"].$this->adldap->getAccountSuffix();
		$add["objectclass"][0] = "top";
		$add["objectclass"][1] = "person";
		$add["objectclass"][2] = "organizationalPerson";
		$add["objectclass"][3] = "user"; //person?
		//$add["name"][0]=$attributes["firstname"]." ".$attributes["surname"];

		// Set the account control attribute
		$control_options = array("NORMAL_ACCOUNT", "ACCOUNTDISABLE");
		$add["userAccountControl"][0] = $this->accountControl($control_options);

		// Determine the container
		if (is_array($attributes['container'])) {
			$attributes["container"] = array_reverse($attributes["container"]);
			$attributes["container"] = "OU=" . implode(",OU=",$attributes["container"]);
		}
		// we can NOT set password with ldap_add or ldap_modify, it needs ldap_mod_replace, at least under Win2008r2
		unset($add['unicodePwd']);

		// Add the entry
		$result = ldap_add($ds=$this->adldap->getLdapConnection(), $dn="CN=" . $add["cn"][0] . "," . $attributes["container"] . "," . $this->adldap->getBaseDn(), $add);
		if ($result != true) {
			error_log(__METHOD__."(".array2string($attributes).") ldap_add(\$ds, '$dn', ".array2string($add).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
			return false;
		}

		// now password can be added to still disabled account
		if (array_key_exists("password",$attributes))
		{
			if (!$this->setPassword($dn, $attributes['password'])) return false;

			// now account can be enabled
			if ($attributes["enabled"])
			{
				$control_options = array("NORMAL_ACCOUNT");
				$mod = array("userAccountControl" => $this->accountControl($control_options));
				$result = ldap_modify($ds, $dn, $mod);
				if (!$result) error_log(__METHOD__."(".array2string($attributes).") ldap_modify(\$ds, '$dn', ".array2string($mod).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
			}
		}

		return true;
	}

    /**
    * Encode a password for transmission over LDAP
    *
    * Extended to use mbstring to convert from arbitrary charset to UTF-16LE
    *
    * @param string $password The password to encode
    * @return string
    */
    public function encodePassword($password)
    {
        $password="\"".$password."\"";
        if (function_exists('mb_convert_encoding') && !empty($this->adldap->charset))
        {
            return mb_convert_encoding($password, 'UTF-16LE', $this->adldap->charset);
        }
        $encoded="";
        for ($i=0, $len=strlen($password); $i < $len; $i++)
        {
        	$encoded .= $password[$i]."\000";
        }
        return $encoded;
    }

    /**
     * Set a password
     *
     * Requires "Reset password" priviledges from bind user!
     *
	 * We can NOT set password with ldap_add or ldap_modify, it needs ldap_mod_replace, at least under Win2008r2!
	 *
     * @param string $dn
     * @param string $password
     * @return boolean
     */
    public function setPassword($dn, $password)
    {
    	$result = ldap_mod_replace($ds=$this->adldap->getLdapConnection(), $dn, array(
    		'unicodePwd' => $this->encodePassword($password),
    	));
    	if (!$result) error_log(__METHOD__."('$dn', '$password') ldap_mod_replace(\$ds, '$dn', \$password) returned FALSE: ".ldap_error($ds));
    	return $result;
    }

	/**
	 * Check if we can to a real password change, not just a password reset
	 *
	 * Requires PHP 5.4 >= 5.4.26, PHP 5.5 >= 5.5.10 or PHP 5.6 >= 5.6.0
	 *
	 * @return boolean
	 */
	public static function changePasswordSupported()
	{
		return function_exists('ldap_modify_batch');
	}

    /**
    * Set the password of a user - This must be performed over SSL
    *
    * @param string $username The username to modify
    * @param string $password The new password
    * @param bool $isGUID Is the username passed a GUID or a samAccountName
	* @param string $old_password old password for password change, if supported
    * @return bool
    */
    public function password($username, $password, $isGUID = false, $old_password=null)
    {
        if ($username === NULL) { return false; }
        if ($password === NULL) { return false; }
        if (!$this->adldap->getLdapBind()) { return false; }
        if (!$this->adldap->getUseSSL() && !$this->adldap->getUseTLS()) {
            throw new adLDAPException('SSL must be configured on your webserver and enabled in the class to set passwords.');
        }

        $userDn = $this->dn($username, $isGUID);
        if ($userDn === false) {
            return false;
        }

        $add=array();

		if (empty($old_password) || !function_exists('ldap_modify_batch')) {
			$add["unicodePwd"][0] = $this->encodePassword($password);

			$result = @ldap_mod_replace($this->adldap->getLdapConnection(), $userDn, $add);
		}
		else {
			$mods = array(
				array(
					"attrib"  => "unicodePwd",
					"modtype" => LDAP_MODIFY_BATCH_REMOVE,
					"values"  => array($this->encodePassword($old_password)),
				),
				array(
					"attrib"  => "unicodePwd",
					"modtype" => LDAP_MODIFY_BATCH_ADD,
					"values"  => array($this->encodePassword($password)),
				),
			);
			$result = ldap_modify_batch($this->adldap->getLdapConnection(), $userDn, $mods);
		}
        if ($result === false){
            $err = ldap_errno($this->adldap->getLdapConnection());
            if ($err) {
                $msg = 'Error ' . $err . ': ' . ldap_err2str($err) . '.';
                if($err == 53) {
                    $msg .= ' Your password might not match the password policy.';
                }
                throw new adLDAPException($msg);
            }
            else {
                return false;
            }
        }

        return true;
    }

    /**
    * Modify a user
    *
    * @param string $username The username to query
    * @param array $attributes The attributes to modify.  Note if you set the enabled attribute you must not specify any other attributes
    * @param bool $isGUID Is the username passed a GUID or a samAccountName
    * @return bool
    */
    public function modify($username, $attributes, $isGUID = false)
    {
        if ($username === NULL) { return "Missing compulsory field [username]"; }
        if (array_key_exists("password", $attributes) && !$this->adldap->getUseSSL() && !$this->adldap->getUseTLS()) {
            throw new adLDAPException('SSL/TLS must be configured on your webserver and enabled in the class to set passwords.');
        }

        // Find the dn of the user
        $userDn = $this->dn($username, $isGUID);
        if ($userDn === false) {
            return false;
        }

        // Translate the update to the LDAP schema
        $mod = $this->adldap->adldap_schema($attributes);

        // Check to see if this is an enabled status update
        if (!$mod && !array_key_exists("enabled", $attributes)){
            return false;
        }

        // Set the account control attribute (only if specified)
        if (array_key_exists("enabled", $attributes)){
            if ($attributes["enabled"]){
                $controlOptions = array("NORMAL_ACCOUNT");
            }
            else {
                $controlOptions = array("NORMAL_ACCOUNT", "ACCOUNTDISABLE");
            }
            $mod["userAccountControl"][0] = $this->accountControl($controlOptions);
        }
		// we can NOT set password with ldap_add or ldap_modify, it needs ldap_mod_replace, at least under Win2008r2
		unset($mod['unicodePwd']);

		if ($mod)
		{
	        // Do the update
	        $result = @ldap_modify($ds=$this->adldap->getLdapConnection(), $userDn, $mod);
	        if ($result == false) {
				if (isset($mod['unicodePwd'])) $mod['unicodePwd'] = '***';
				error_log(__METHOD__."(".array2string($attributes).") ldap_modify(\$ds, '$userDn', ".array2string($mod).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
	        	return false;
	        }
		}
        if (array_key_exists("password",$attributes) && !$this->setPassword($userDn, $attributes['password']))
		{
			return false;
		}
		return true;
	}

	/**
	 * Find information about the users. Returned in a raw array format from AD.
	 *
	 * Reimplemented to deal with InvalidArgumentException caused by calling
	 * ldap_search or ldap_get_entries under PHP 8.x.
	 *
	 * @param string $username The username to query
	 * @param array  $fields   Array of parameters to query
	 * @param bool   $isGUID   Is the username passed a GUID or a samAccountName
	 *
	 * @return array|false false on error
	 */
	public function info($username, $fields = null, $isGUID = false)
	{
		try {
			return parent::info($username, $fields, $isGUID);
		}
		catch(\InvalidArgumentException $e) {
			// ignore the exceptions caused by calling ldap_get_entries or ldap_get_entries under PHP 8.x
		}
		return false;
	}
}

/**
 * Fixes an enhancements for adLDAPGroups required by EGroupware
 */
class adLDAPGroups extends \adLDAPGroups
{
	/**
	 * Create a group
	 *
	 * Extended to allow to specify $attribute["container"] as string, because array hardcodes "OU=", while Samba4 and win2008r2 uses "CN=Users"
	 *
	 * @param array $attributes Default attributes of the group
	 * @return bool
	 */
	public function create($attributes)
	{
		if (!is_array($attributes)){ return "Attributes must be an array"; }
		if (!array_key_exists("group_name", $attributes)){ return "Missing compulsory field [group_name]"; }
		if (!array_key_exists("container", $attributes)){ return "Missing compulsory field [container]"; }
		if (empty($attributes["container"])){ return "Container attribute must be an array or string."; }

		//$member_array = array();
		//$member_array[0] = "cn=user1,cn=Users,dc=yourdomain,dc=com";
		//$member_array[1] = "cn=administrator,cn=Users,dc=yourdomain,dc=com";

		$add = array();
		$add["cn"] = $attributes["group_name"];
		$add["samaccountname"] = $attributes["group_name"];
		$add["objectClass"] = "Group";
		if (!empty($attributes["description"])) $add["description"] = $attributes["description"];
		//$add["member"] = $member_array; UNTESTED

		// Determine the container
		if (is_array($attributes['container'])) {
			$attributes["container"] = array_reverse($attributes["container"]);
			$attributes["container"] = "OU=" . implode(",OU=",$attributes["container"]);
		}
		$result = ldap_add($this->adldap->getLdapConnection(), "CN=" . $add["cn"] . "," . $attributes["container"] . "," . $this->adldap->getBaseDn(), $add);
		if ($result != true) {
			return false;
		}
		return true;
	}
}

/**
 * Fixes an enhancements for adLDAPUtils required by EGroupware
 */
class adLDAPUtils extends \adLDAPUtils
{
	/**
	 * Convert 8bit characters e.g. accented characters to UTF8 encoded characters
	 */
	public function encode8Bit(&$item, $key)
	{
		$encode = false;
		if (is_string($item)) {
			for ($i = 0, $len=strlen($item); $i < $len; $i++) {
				if (ord($item[$i]) >> 7) {
					$encode = true;
				}
			}
		}
		if ($encode === true && $key != 'password') {
			$item = utf8_encode($item);
		}
	}

    /**
    * Escape strings for the use in LDAP filters
    *
    * DEVELOPERS SHOULD BE DOING PROPER FILTERING IF THEY'RE ACCEPTING USER INPUT
    * Ported from Perl's Net::LDAP::Util escape_filter_value
    *
    * @param string $str The string the parse
    * @author Port by Andreas Gohr <andi@splitbrain.org>
    * @return string
    */
    public function ldapSlashes($str){
        return preg_replace_callback(
      		'/([\x00-\x1F\*\(\)\\\\])/',
        	function ($matches) {
            	return "\\".join("", unpack("H2", $matches[1]));
        	},
        	$str
    	);
    }
}