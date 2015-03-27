<?php
/**
 * API - accounts active directory backend
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @version $Id$
 */

require_once EGW_API_INC.'/adldap/adLDAP.php';

/**
 * Active directory backend for accounts
 *
 * RID (realtive id / last part of string-SID) is used as nummeric account-id (negativ for groups).
 * SID for queries get reconstructed from account_id by prepending domain-SID.
 *
 * Easiest way to enable SSL on a win2008r2 DC is to install role "Active Director Certificate Services"
 * or in German "Active Directory-Zertificatsdienste" AND reboot.
 *
 * Changing passwords currently requires ads_admin user (configured in setup) to have "Reset Password"
 * priveledges, as PHP can not delete unicodePwd attribute with old password and set it in same
 * operation with new password!
 *
 * @access internal only use the interface provided by the accounts class
 * @link http://www.selfadsi.org/user-attributes-w2k8.htm
 * @link http://www.selfadsi.org/attributes-e2k7.htm
 * @link http://msdn.microsoft.com/en-us/library/ms675090(v=vs.85).aspx
 */
class accounts_ads
{
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
	 * @var accounts
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
		'objectguid', 'useraccountcontrol', 'accountexpires', 'pwdlastset', 'whencreated', 'whenchanged',
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
	 * Enable extra debug messages via error_log (error always get logged)
	 */
	public static $debug = false;

	/**
	 * Constructor
	 *
	 * @param accounts $frontend reference to the frontend class, to be able to call it's methods if needed
	 * @throws adLDAPException
	 * @return accounts_ldap
	 */
	function __construct(accounts $frontend)
	{
		$this->frontend = $frontend;

		$this->adldap = self::get_adldap($this->frontend->config);
	}

	/**
	 * Factory method and singelton to get adLDAP object for given configuration or default server config
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
			if (empty($config['ads_host'])) throw new Exception("Required ADS host name(s) missing!");
			if (empty($config['ads_domain'])) throw new Exception("Required ADS domain missing!");

			$base_dn_parts = array();
			foreach(explode('.', $config['ads_domain']) as $dc)
			{
				$base_dn_parts[] = 'DC='.$dc;
			}
			$base_dn = implode(',', $base_dn_parts);
			$options = array(
				'domain_controllers' => preg_split('/[ ,]+/', $config['ads_host']),
				'base_dn' => $base_dn ? $base_dn : null,
				'account_suffix' => '@'.$config['ads_domain'],
				'admin_username' => $config['ads_admin_user'],
				'admin_password' => $config['ads_admin_passwd'],
				'use_tls' => $config['ads_connection'] == 'tls',
				'use_ssl' => $config['ads_connection'] == 'ssl',
				'charset' => translation::charset(),
			);
			$adldap[$config['ads_domain']] = new adLDAP_egw($options);
			if (self::$debug) error_log(__METHOD__."() new adLDAP(".array2string($options).") returned ".array2string($adldap[$config['ads_domain']]).' '.function_backtrace());
		}
		//else error_log(__METHOD__."() returning cached adLDAP ".array2string($adldap[$config['ads_domain']]).' '.function_backtrace());
		return $adldap[$config['ads_domain']];
	}

	/**
	 * Get SID of domain or an account
	 *
	 * @param int $account_id
	 * @return string|NULL
	 */
	protected function get_sid($account_id=null)
	{
		static $domain_sid = null;
		if (!isset($domain_sid))
		{
			$domain_sid = egw_cache::getCache($this->frontend->config['install_id'], __CLASS__, 'ads_domain_sid');
			if ((!is_array($domain_sid) || !isset($domain_sid[$this->frontend->config['ads_domain']])) &&
				($adldap = self::get_adldap($this->frontend->config)) &&
				($sr = ldap_search($adldap->getLdapConnection(), $adldap->getBaseDn(), '(objectclass=domain)', array('objectsid'))) &&
				(($entries = ldap_get_entries($adldap->getLdapConnection(), $sr)) || true))
			{
				$domain_sid = array();
				$domain_sid[$this->frontend->config['ads_domain']] = $adldap->utilities()->getTextSID($entries[0]['objectsid'][0]);
				egw_cache::setCache($this->frontend->config['install_id'], __CLASS__, 'ads_domain_sid', $domain_sid);
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

	/**
	 * Get context for user and group objects
	 *
	 * Can be set via server-config "ads_context", otherwise baseDN is used
	 *
	 * @param boolean $set_if_empty =false true set from DN of "Domain Users" group #
	 * @return string
	 */
	public function ads_context($set_if_empty=false)
	{
		if (empty($this->frontend->config[self::ADS_CONTEXT]))
		{
			if ($set_if_empty && ($dn = $this->id2name(-self::DOMAIN_USERS_GROUP, 'account_dn')))
			{
				$dn = preg_replace('/^CN=.*?,(CN|OU)=/i', '$1=', $dn);
				config::save_value(self::ADS_CONTEXT, $this->frontend->config[self::ADS_CONTEXT]=$dn, 'phpgwapi');
			}
			else
			{
				return $this->adldap->getBaseDn();
			}
		}
		return $this->frontend->config[self::ADS_CONTEXT];
	}

	/**
	 * Get container for new user and group objects
	 *
	 * Can be set via server-config "ads_context", otherwise parent of DN from "Domain Users" is used
	 *
	 * @return string
	 */
	protected function _get_container()
	{
		$context = $this->ads_context(true);
		$base = $this->adldap->getBaseDn();
		$matches = null;
		if (!preg_match('/^(.*),'.preg_quote($base, '/').'$/i', $context, $matches))
		{
			throw new egw_exception_wrong_userinput("Wrong or not configured ADS context '$context' (baseDN='$base')!");
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
		$data = translation::convert($data, translation::charset(), 'utf-8');

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
			$old = translation::convert($old, translation::charset(), 'utf-8');
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
		$data = translation::convert($_data, 'utf-8');

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
			return false;	// group not found
		}
		$group = $this->_ldap2group(array_shift($data));

		// for memberships we have to query primaryGroupId and memberOf of users
		$group['members'] = $this->filter(array('memberOf' => $group['account_dn']), 'u');
		// primary group is not stored in memberOf attribute, need to add them too
		$group['members'] = $this->filter(array('primaryGroupId' => abs($account_id)), 'u', null, $group['members']);

		return $group;
	}

	/**
	 * Convert ldap data of a user
	 *
	 * @param array $_data
	 * @return array
	 */
	protected function _ldap2user(array $_data)
	{
		$data = translation::convert($_data, 'utf-8');

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
			'account_expires'   => !isset($data['accountexpires']) || $data['accountexpires'][0] == self::EXPIRES_NEVER ? -1 :
				$this->adldap->utilities()->convertWindowsTimeToUnixTime($data['accountexpires'][0]),
			'account_lastpwd_change' => !isset($data['pwdlastset']) ? null : (!$data['pwdlastset'][0] ? 0 :
				$this->adldap->utilities()->convertWindowsTimeToUnixTime($data['pwdlastset'][0])),
			'account_created' => !isset($data['whencreated'][0]) ? null :
				self::_when2ts($data['whencreated'][0]),
			'account_modified' => !isset($data['whenchanged'][0]) ? null :
				self::_when2ts($data['whenchanged'][0]),
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
		$active = accounts::is_active($user);
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
		if (!isset($utc)) $utc = new DateTimeZone('UTC');

		list($when) = explode('.', $_when);	// remove .0Z not understood by createFromFormat
		$datetime = egw_time::createFromFormat(self::WHEN_FORMAT, $when, $utc);
		if (egw_time::$server_timezone) $datetime->setTimezone(egw_time::$server_timezone);

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
				if (isset($data[$egw])) $attributes[$adldap] = $data[$egw];
			}
			$attributes['container'] = $this->_get_container();

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
					default:
						$ldap[$adldap] = $data[$egw];
						break;
				}
			}
		}
		// attributes not (yet) suppored by adldap
		if ($ldap && !($ret = @ldap_modify($ds=$this->ldap_connection(), $old['account_dn'], $ldap)))
		{
			error_log(__METHOD__."(".array2string($data).") ldap_modify($ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret));
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
			$attributes['container'] = $this->_get_container();

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
							$this->adldap->group()->addUser($group=$this->id2name($data[$egw]), $data['account_id']))
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
						if ($new_entry && empty($data['account_passwd'])) continue;	// cant active new account without passwd!
						$attributes[$adldap] = $data[$egw] == 'A';
						break;
					case 'account_lastpwd_change':
						// Samba4 does not understand -1 for current time, but Win2008r2 only allows to set -1 (beside 0)
						// call auth_ads::setLastPwdChange with true to get correct modification for both
						$ldap = array_merge($ldap, auth_ads::setLastPwdChange($data['account_lid'], null, $data[$egw], true));
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
			error_log(__METHOD__."(".array2string($data).") ldap_modify($ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret).' ('.ldap_error($ds).') '.function_backtrace());
			return false;
		}
		//elseif ($ldap) error_log(__METHOD__."(".array2string($data).") ldap_modify($ds, '$old[account_dn]', ".array2string($ldap).') returned '.array2string($ret).' '.function_backtrace());

		//error_log(__METHOD__."(".array2string($data).") returning ".array2string($old['account_id']));
		return $old['account_id'];
	}

	/**
	* Add seconds between 1601-01-01 and 1970-01-01 and multiply by 10000000
	*
	* @param long $unixTime
	* @return long windowsTime
	*/
	public static function convertUnixTimeToWindowsTime($unixTime)
	{
		return ($unixTime + 11644477200) * 10000000;
	}

	/**
	 * Searches / lists accounts: users and/or groups
	 *
	 * @todo sort and limit query on AD, PHP5.4 and AD support it
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
	 * @return array with account_id => data pairs, data is an array with account_id, account_lid, account_firstname,
	 *	account_lastname, person_id (id of the linked addressbook entry), account_status, account_expires, account_primary_group
	 */
	function search($param)
	{
		//error_log(__METHOD__.'('.array2string($param).')');
		$account_search = &$this->cache['account_search'];

		// check if the query is cached
		$serial = serialize($param);
		if (isset($account_search[$serial]))
		{
			$this->total = $account_search[$serial]['total'];
			return $account_search[$serial]['data'];
		}
		// if it's a limited query, check if the unlimited query is cached
		$start = $param['start'];
		if (!($maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'])) $maxmatchs = 15;
		if (!($offset = $param['offset'])) $offset = $maxmatchs;
		unset($param['start']);
		unset($param['offset']);
		$unl_serial = serialize($param);
		if (isset($account_search[$unl_serial]))
		{
			$this->total = $account_search[$unl_serial]['total'];
			$sortedAccounts = $account_search[$unl_serial]['data'];
		}
		else	// we need to run the unlimited query
		{
			$query = ldap::quote(strtolower($param['query']));

			$accounts = array();
			if($param['type'] != 'groups')
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
								'lid'       => 'uid',
								'email'     => 'mail',
							);
							$filter = '('.$to_ldap[$param['query_type']].'=*'.$query.'*)';
							break;
					}
				}
				foreach($this->filter($filter, 'u', self::$user_attributes) as $account_id => $data)
				{
					$account = $this->_ldap2user($data);
					if ($param['active'] && !$this->frontend->is_active($account))
					{
						continue;
					}
					$account['account_fullname'] = common::display_fullname($account['account_lid'],$account['account_firstname'],$account['account_lastname'],$account['account_id']);
					$accounts[$account_id] = $account;
				}
			}
			if ($param['type'] == 'groups' || $param['type'] == 'both')
			{
				$query = ldap::quote(strtolower($param['query']));

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
				foreach($this->filter($filter, 'g', self::$group_attributes) as $account_id => $data)
				{
					$accounts[$account_id] = $this->_ldap2group($data);
				}
			}
			// sort the array
			$this->_callback_sort = strtoupper($param['sort']);
			$this->_callback_order = empty($param['order']) ? array('account_lid') : explode(',',$param['order']);
			$sortedAccounts = $accounts;
			uasort($sortedAccounts,array($this,'_sort_callback'));
			$account_search[$unl_serial]['data'] = $sortedAccounts;

			$account_search[$unl_serial]['total'] = $this->total = count($accounts);
		}
		//echo "<p>accounts_ldap::search() found $this->total: ".microtime()."</p>\n";
		// return only the wanted accounts
		reset($sortedAccounts);
		if(is_numeric($start) && is_numeric($offset))
		{
			$account_search[$serial]['data'] = array_slice($sortedAccounts, $start, $offset);
			$account_search[$serial]['total'] = $this->total;
			//error_log(__METHOD__.'('.array2string($param).") returning $offset/$this->total entries from $start ".array2string($account_search[$serial]['data']));
			return $account_search[$serial]['data'];
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
	 * Query ADS by (optional) filter and (optional) account-type filter
	 *
	 * All reading ADS queries are done throught this methods.
	 *
	 * @param string|array $attr_filter array with attribute => value pairs or filter string or empty
	 * @param string $account_type u = user, g = group, default null = try both
	 * @param array $attrs =null default return account_lid, else return raw values from ldap-query
	 * @param array $accounts =array() array to add filtered accounts too, default empty array
	 * @return array account_id => account_lid or values for $attrs pairs
	 */
	protected function filter($attr_filter, $account_type=null, array $attrs=null, array $accounts=array())
	{
		switch($account_type)
		{
			case 'u':
				$type_filter = '(samaccounttype='.adLDAP::ADLDAP_NORMAL_ACCOUNT.')';
				break;
			case 'g':
				$type_filter = '(samaccounttype='.adLDAP::ADLDAP_SECURITY_GLOBAL_GROUP.')';
				break;
			default:
				$type_filter = '(|(samaccounttype='.adLDAP::ADLDAP_NORMAL_ACCOUNT.')(samaccounttype='.adLDAP::ADLDAP_SECURITY_GLOBAL_GROUP.'))';
				break;
		}
		if (!$attr_filter)
		{
			$filter = $type_filter;
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
			$filter .= $type_filter.')';
		}
		$sri = ldap_search($ds=$this->ldap_connection(), $context=$this->ads_context(), $filter,
			$attrs ? $attrs : self::$default_attributes);
		if (!$sri)
		{
			if (self::$debug) error_log(__METHOD__.'('.array2string($attr_filter).", '$account_type') ldap_search($ds, '$context', '$filter') returned ".array2string($sri).' trying to reconnect ...');
			$sri = ldap_search($ds=$this->ldap_connection(true), $context=$this->ads_context(), $filter,
				$attrs ? $attrs : self::$default_attributes);
		}

		if ($sri && ($allValues = ldap_get_entries($ds, $sri)))
		{
			foreach($allValues as $key => $data)
			{
				if ($key === 'count') continue;

				if ($account_type && !($account_type == 'u' && $data['samaccounttype'][0] == adLDAP::ADLDAP_NORMAL_ACCOUNT ||
					$account_type == 'g' && $data['samaccounttype'][0] == adLDAP::ADLDAP_SECURITY_GLOBAL_GROUP))
				{
					continue;
				}
				$sid = $data['objectsid'] = $this->adldap->utilities()->getTextSID($data['objectsid'][0]);
				$rid = self::sid2account_id($sid);

				if ($data['samaccounttype'][0] == adLDAP::ADLDAP_NORMAL_ACCOUNT && $rid < self::MIN_ACCOUNT_ID)
				{
					continue;	// ignore system accounts incl. "Administrator"
				}
				$accounts[($data['samaccounttype'][0] == adLDAP::ADLDAP_SECURITY_GLOBAL_GROUP ? '-' : '').$rid] =
					$attrs ? $data : translation::convert($data['samaccountname'][0], 'utf-8');
			}
		}
		else if (self::$debug) error_log(__METHOD__.'('.array2string($attr_filter).", '$account_type') ldap_search($ds, '$context', '$filter')=$sri allValues=".array2string($allValues));

		//error_log(__METHOD__.'('.array2string($attr_filter).", '$account_type') ldap_search($ds, '$context', '$filter') returning ".array2string($accounts).' '.function_backtrace());
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
	 * @param int $account_id numerica account_id
	 * @param string $which ='account_lid' type to convert to: account_lid (default), account_email, ...
	 * @return string/false converted value or false on error ($account_id not found)
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
 * Fixes an enhancements for adLDAP required by EGroupware
 *
 * - allow to use utf-8 charset internally, not just an 8-bit iso-charset
 * - support for Windows2008r2 (maybe earlier too) and Samba4 "CN=Users" DN as container to create users or groups
 */
class adLDAP_egw extends adLDAP
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

	/**
    * Convert 8bit characters e.g. accented characters to UTF8 encoded characters
    *
    * Extended to use mbstring to convert from arbitrary charset to utf-8
	*/
	protected function encode8Bit(&$item, $key)
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
			$this->userClass = new adLDAPUsers_egw($this);
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
            $this->groupClass = new adLDAPGroups_egw($this);
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
            $this->utilClass = new adLDAPUtils_egw($this);
        }
        return $this->utilClass;
    }
}

/**
 * Fixes an enhancements for adLDAPUser required by EGroupware
 */
class adLDAPUsers_egw extends adLDAPUsers
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
			error_log(__METHOD__."(".array2string($attributes).") ldap_add($ds, '$dn', ".array2string($add).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
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
				if (!$result) error_log(__METHOD__."(".array2string($attributes).") ldap_modify($ds, '$dn', ".array2string($mod).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
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
        if (function_exists('mb_convert_encoding'))
        {
            return mb_convert_encoding($password, 'UTF-16LE', $this->adldap->charset);
        }
        $encoded="";
        for ($i=0; $i <strlen($password); $i++){ $encoded.="{$password{$i}}\000"; }
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
    	if (!$result) error_log(__METHOD__."('$dn', '$password') ldap_mod_replace($ds, '$dn', \$password) returned FALSE: ".ldap_error($ds));
    	return $result;
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
				error_log(__METHOD__."(".array2string($attributes).") ldap_modify($ds, '$userDn', ".array2string($mod).") returned ".array2string($result)." ldap_error()=".ldap_error($ds));
	        	return false;
	        }
		}
        if (array_key_exists("password",$attributes) && !$this->setPassword($userDn, $attributes['password']))
		{
			return false;
		}
		return true;
	}
}

/**
 * Fixes an enhancements for adLDAPGroups required by EGroupware
 */
class adLDAPGroups_egw extends adLDAPGroups
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
		if (!array_key_exists("description", $attributes)){ return "Missing compulsory field [description]"; }
		if (empty($attributes["container"])){ return "Container attribute must be an array or string."; }

		//$member_array = array();
		//$member_array[0] = "cn=user1,cn=Users,dc=yourdomain,dc=com";
		//$member_array[1] = "cn=administrator,cn=Users,dc=yourdomain,dc=com";

		$add = array();
		$add["cn"] = $attributes["group_name"];
		$add["samaccountname"] = $attributes["group_name"];
		$add["objectClass"] = "Group";
		$add["description"] = $attributes["description"];
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
class adLDAPUtils_egw extends adLDAPUtils
{
	/**
	 * Convert 8bit characters e.g. accented characters to UTF8 encoded characters
	 */
	public function encode8Bit(&$item, $key)
	{
		return $this->adldap->encode8bit($item, $key);
	}
}
