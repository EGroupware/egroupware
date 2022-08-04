<?php
/**
 * EGroupware API - Authentication via SAML, Shibboleth or everything supported by SimpleSAMLphp
 *
 * @link https://www.egroupware.org
 * @link https://simplesamlphp.org/docs/stable/
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 */

namespace EGroupware\Api\Auth;

use EGroupware\Api;
use SimpleSAML;
use EGroupware\Api\Exception;

/**
 * Authentication based on SAML, Shibboleth or everything supported by SimpleSAMLphp
 *
 * SimpleSAMLphp is installed together with EGroupware and a default configuration is created in EGroupware
 * files subdirectory "saml" eg. when you first store it's configuration in Setup > Configuration > SAML/Shibboleth
 *
 * Storing setup configuration modifies the following files:
 * a) $files_dir/saml/config.php
 * b) $files_dir/saml/authsources.php (only "default-sp" is used currently)
 * c) $files_dir/saml/metadata/*
 * d) $files_dir/saml/cert/*
 * Modification is only on certain values, everything else can be edited to suit your needs.
 *
 * Initially also a key-pair is generated as $files_dir/saml/cert/saml.{pem,crt}.
 * If you want or have to use a different certificate, best replace these with your files (they are referenced multiple times!).
 * They must stay in the files directory and can NOT be symlinks to eg. /etc, as only files dir is mounted into the container!
 *
 * Authentication / configuration can be tested independent of EGroupware by using https://example.org/egroupware/saml/
 * with the "admin" user and password stored in cleartext in $files_dir/saml/config.php under 'auth.adminpassword'.
 *
 * There are basically three possible scenarios currently supported:
 * a) a single IdP and SAML configured as authentication method
 * --> gives full SSO (login page is never displayed, it directly redirects to the IdP)
 * b) one or multiple IdP, a discovery label and another authentication type eg. SQL configured
 * --> uses the login page for local accounts plus a button or selectbox (depending on number of IdPs) to start SAML login
 * c) multiple IdP and SAML configured as authentication method
 * --> SimpleSAML discovery/selection page with a checkbox to remember the selection (SSO after first selection)
 *
 * EGroupware understands assertions / attributes send after authentication in the following ways:
 * - as "urn:uuid:<oid>" uri as used eg. in the DFN federation
 * - as LDAP attribute name send eg. by Univention IdP (either lower cased or in matching camelCase)
 *
 * SAML support in EGroupware automatically downgrades session cookies to SameSite=Lax, as our default SameSite=Strict
 * does NOT work with SAML redirects from an IdP in a different domain (browser simply ignores the session cookies)!
 *
 * Please note: the following SimpleSAMLphp WARNING can be safely ignored (as EGroupware shares the session with it):
 * There is already a PHP session with the same name as SimpleSAMLphp's session, or the
 * 'session.phpsession.cookiename' configuration option is not set. Make sure to set SimpleSAMLphp's cookie name
 * with a value not used by any other applications.
 */
class Saml implements BackendSSO
{
	/**
	 * Which entry in authsources.php to use.
	 * 
	 * Setup > configuration always modifies "default-sp"
	 * 
	 * A different SP can be configured via header.inc.php by adding at the end:
	 *
	 * EGroupware\Api\Auth\Saml::$auth_source = "other-sp";
	 */
	static public $auth_source = 'default-sp';

	/**
	 * Constructor
	 */
	function __construct()
	{
		// ensure we have (at least) a default configuration
		self::checkDefaultConfig();
	}

	/**
	 * Authentication against SAML
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		// login (redirects to IdP)
		$as = new SimpleSAML\Auth\Simple(self::$auth_source);
		$as->requireAuth();

		return true;
	}

	/**
	 * changes password in SAML
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id =0 account id of user whose passwd should be changed
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0)
	{
		/* Not allowed */
		return false;
	}

	/**
	 * Some urn:oid constants for common attributes
	 */
	const eduPersonPricipalName = 'urn:oid:1.3.6.1.4.1.5923.1.1.1.6';
	const eduPersonUniqueId = 'urn:oid:1.3.6.1.4.1.5923.1.1.1.13';
	const emailAddress = 'urn:oid:0.9.2342.19200300.100.1.3';
	const uid = 'urn:oid:0.9.2342.19200300.100.1.1';
	const firstName = 'urn:oid:2.5.4.42';
	const lastName = 'urn:oid:2.5.4.4';

	/**
	 * Attempt SSO login
	 *
	 * @return string sessionid on successful login, null otherwise
	 */
	function login()
	{
		// login (redirects to IdP)
		$as = new SimpleSAML\Auth\Simple(self::$auth_source);
		$as->requireAuth(preg_match('|^https://|', $_REQUEST['idp']) ?
			['saml:idp' => $_REQUEST['idp']] : []);

		/* cleanup session for EGroupware: currently NOT used as we share the session with SimpleSAMLphp
		$session = SimpleSAML\Session::getSessionFromRequest();
		$session->cleanup();*/

		// get attributes for (automatic) account creation
		$attrs = $as->getAttributes();
		if (!$attrs || empty($username = self::samlAttr(null, $attrs)))
		{
			throw new \Exception('Got NO '.(!$attrs ? 'attributes' : 'username attribute').' from SAML: '.json_encode($attrs));
		}

		// check if user already exists
		if (!$GLOBALS['egw']->accounts->name2id($username, 'account_lid', 'u'))
		{
			if (($existing = $this->checkJoin($_GET['login'], $_GET['passwd'], $username)) ||
				($existing = $this->checkReplaceUsername($username)))
			{
				$username = $this->updateJoinedAccount($existing, $attrs);
			}
			else
			{
				// fail if auto-creation of authenticated users is NOT configured
				if (empty($GLOBALS['egw_info']['server']['auto_create_acct']))
				{
					return null;
				}
				$GLOBALS['auto_create_acct'] = [
					'firstname' => self::samlAttr(self::firstName, $attrs),
					'lastname' => self::samlAttr(self::lastName, $attrs),
					'email' => self::samlAttr(self::emailAddress, $attrs),
				];
			}
		}

		// check affiliation / group to add or remove
		self::checkAffiliation($username, $attrs, $GLOBALS['auto_create_acct']);

		// Set SameSite attribute for cookies, as SAML redirect does NOT work with samesite=Strict,
		// it requires as least Lax, otherwise the browser will ignore the session cookies set in Session::create()!
		if ($GLOBALS['egw_info']['server']['cookie_samesite_attribute'] === 'Strict')
		{
			$GLOBALS['egw_info']['server']['cookie_samesite_attribute'] = 'Lax';
		}
		// return user session
		return $GLOBALS['egw']->session->create($username, null, null, false, false);
	}

	/**
	 * Check if joining a SAML account with an existing accounts is enabled and user specified correct credentials
	 *
	 * @param string $login login-name entered by user
	 * @param string $password password entered by user
	 * @param string $username SAML username
	 * @return string|null|false existing user-name to join or
	 *  null if no joining configured or missing credentials or user does not exist or
	 * 	false if authentication with given credentials failed
	 */
	private function checkJoin($login, $password, $username)
	{
		// check SAML username is stored in account_description and we have a matching account
		if ($GLOBALS['egw_info']['server']['saml_join'] === 'description' &&
			($account_id = $GLOBALS['egw']->accounts->name2id($username, 'account_description', 'u')))
		{
			return Api\Accounts::id2name($account_id);
		}

		// check join configuration and if user specified credentials
		if (empty($GLOBALS['egw_info']['server']['saml_join']) || empty($login) || empty($password))
		{
			return null;
		}

		$backend = Api\Auth::backend($GLOBALS['egw_info']['server']['auth_type'] ?: 'sql', false);
		if (!$backend->authenticate($login, $password))
		{
			return false;
		}
		return $login;
	}

	/**
	 * Update joined account, if configured
	 *
	 * @param string $account_lid existing account_lid
	 * @param array $attrs saml attributes incl. SAML username
	 * @return string username to use
	 */
	private function updateJoinedAccount($account_lid, array $attrs)
	{
		if (empty($GLOBALS['egw_info']['server']['saml_join']))
		{
			return $account_lid;
		}
		$account = $update = $GLOBALS['egw']->accounts->read($account_lid);

		switch($GLOBALS['egw_info']['server']['saml_join'])
		{
			case 'usernameemail':
				if (!empty(self::samlAttr(self::emailAddress, $attrs)))
				{
					unset($update['account_email']);	// force email update
				}
			// fall through
			case 'username':
				$update['account_lid'] = self::samlAttr(null, $attrs);
				break;

			case 'description':
				$update['account_description'] = self::samlAttr(null, $attrs);
				break;
		}
		// update other attributes
		foreach([
			'account_email' => self::emailAddress,
			'account_firstname' => self::firstName,
			'account_lastname' => self::lastName,
		] as $name => $oid)
		{
			if (!empty($value = self::samlAttr($oid, $attrs)) && ($name !== 'account_email' || empty($update['account_email'])))
			{
				$update[$name] = $value;
			}
		}
		// update account if necessary
		if ($account != $update)
		{
			// notify user about successful update of existing account and evtl. updated account-name
			if ($GLOBALS['egw']->accounts->save($update))
			{
				$msg = lang('Your account has been updated with new data from your identity provider.');
				if ($account['account_lid'] !== $update['account_lid'])
				{
					$msg .= "\n".lang("Please remember to use '%1' as username for local login's from now on!", $update['account_lid']);
					// rename home directory
					Api\Vfs::$is_root = true;
					Api\Vfs::rename('/home/'.$account['account_lid'], '/home/'.$update['account_lid']);
					Api\Vfs::$is_root = false;
				}
				Api\Framework::message($msg, 'notice');
			}
			else
			{
				Api\Framework::message(lang('Updating your account with new data from your identity provider failed!'), 'error');
			}
		}
		return $update['account_lid'];
	}

	/**
	 * Check if some replacement is configured to match SAML usernames to existing ones
	 *
	 * @param string $username SAML username
	 * @return string|null existing username or null if not found
	 */
	private function checkReplaceUsername($username)
	{
		if (empty($GLOBALS['egw_info']['server']['saml_replace']))
		{
			return null;
		}
		$replace = $GLOBALS['egw_info']['server']['saml_replace'];
		$with = $GLOBALS['egw_info']['server']['saml_replace_with'] ?? '';
		$replaced = $replace[0] === '/' ? preg_replace($replace, $with, $username) : str_replace($replace, $with, $username);

		if (empty($replaced) || !$GLOBALS['egw']->accounts->name2id($replaced, 'account_lid', 'u'))
		{
			return null;
		}
		return $replaced;
	}

	/**
	 * Logout SSO system
	 */
	function logout()
	{
		$as = new SimpleSAML\Auth\Simple(self::$auth_source);
		if ($as->isAuthenticated()) $as->logout();
	}

	/**
	 * Return (which) parts of session needed by current auth backend
	 *
	 * If this returns any key(s), the session is NOT destroyed by Api\Session::destroy,
	 * just everything but the keys is removed.
	 *
	 * @return array of needed keys in session
	 */
	function needSession()
	{
		return ['SimpleSAMLphp_SESSION', Api\Session::EGW_APPSESSION_VAR];	// Auth stores backend via Cache::setSession()
	}

	const IDP_DISPLAY_NAME = 'OrganizationDisplayName';

	/**
	 * Display a IdP selection / discovery
	 *
	 * Will be displayed if IdP(s) are added in setup and a discovery label is specified.
	 *
	 * @return string|null html to display in login page or null to disable the selection
	 */
	static public function discovery()
	{
		if (empty($GLOBALS['egw_info']['server']['saml_discovery']) ||
			!($metadata = self::metadata()))
		{
			return null;
		}
		//error_log(__METHOD__."() metadata=".json_encode($metadata));
		$lang = Api\Translation::$userlang;
		$select = ['' => $GLOBALS['egw_info']['server']['saml_discovery']];
		foreach($metadata as $idp => $data)
		{
			$select[$idp] = $data[self::IDP_DISPLAY_NAME][$lang] ?: $data[self::IDP_DISPLAY_NAME]['en'];
		}
		return count($metadata) > 1 ?
			Api\Html::select('auth=saml', '', $select, true, 'class="onChangeSubmit"') :
			Api\Html::input('auth=saml', $GLOBALS['egw_info']['server']['saml_discovery'], 'submit', 'formmethod="get"');
	}

	/**
	 * @return array IdP => metadata pairs
	 */
	static public function metadata($files_dir=null)
	{
		$metadata = [];
		if (file_exists($file = ($files_dir ?: $GLOBALS['egw_info']['server']['files_dir']).'/saml/metadata/saml20-idp-remote.php'))
		{
			include $file;
		}
		return $metadata;
	}

	const ASYNC_JOB_ID = 'saml_metadata_refresh';

	/**
	 * Hook called when setup configuration is being stored:
	 * - updating SimpleSAMLphp config files
	 * - creating/removing cron job to refresh metadata
	 *
	 * @param array $location key "newsettings" with reference to changed settings from setup > configuration
	 * @throws \Exception for errors
	 */
	public static function setupConfig(array $location)
	{
		$config =& $location['newsettings'];

		if (empty($config['saml_idp'])) return;	// nothing to do, if no idp defined

		if (file_exists($config['files_dir'].'/saml/config.php'))
		{
			self::updateConfig($config);
		}
		self::checkDefaultConfig($config);
		// config files are PHP files and EGroupware contaier does not check timestamps
		if (function_exists('opcache_reset')) opcache_reset();

		// install or remove async job to refresh metadata
		static $freq2times = [
			'daily'  => ['min' => 4, 'hour' => 4],	// daily at 4:04am
			'weekly' => ['min' => 4, 'hour' => 4, 'dow' => 5],	// Saturdays as 4:04am
		];
		$async = new Api\Asyncservice();
		if (isset($freq2times[$config['saml_metadata_refresh']]) &&
			preg_match('|^https://|', $config['saml_metadata']))
		{
			$async->set_timer($freq2times[$config['saml_metadata_refresh']], self::ASYNC_JOB_ID, self::class.'::refreshMetadata');
		}
		else
		{
			$async->cancel_timer(self::ASYNC_JOB_ID);
		}

		// only refresh metadata if we have to, or request by user
		if ($config['saml_metadata_refresh'] !== 'no')
		{
			$metadata = self::metadata($config['files_dir']);
			$idps = self::splitIdP($config['saml_idp']);
			foreach($idps as $idp)
			{
				if (!isset($metadata[$idp]))
				{
					$metadata = [];
					break;
				}
			}
			if (count($metadata) !== count($idps) || $config['saml_metadata_refresh'] === 'now')
			{
				self::refreshMetadata($config);
			}
		}
	}

	/**
	 * Split multiple IdP
	 *
	 * @param string $config
	 * @return string[]
	 */
	private static function splitIdP($config)
	{
		return preg_split('/[\n\r ]+/', trim($config)) ?: [];
	}

	/**
	 * Refresh metadata
	 *
	 * @param array|null $config defaults to $GLOBALS['egw_info']['server']
	 * @throws \Exception
	 */
	public static function refreshMetadata(array $config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server'];
		$old_config = Api\Config::read('phpgwapi');

		$saml_config = $config['files_dir'].'/saml';
		SimpleSAML\Configuration::setConfigDir($saml_config);

		$source = [
			'src' => $config['saml_metadata'],
			// only read/configure our idp(s), the whole thing can be huge
			'whitelist' => self::splitIdP($config['saml_idp']),
		];
		if (!empty($config['saml_certificate']))
		{
			$cert = $saml_config.'/cert/'.basename(parse_url($config['saml_certificate'], PHP_URL_PATH));
			if ((!file_exists($cert) || $config['saml_certificate'] !== $old_config['saml_certificate']) &&
				(!($content = file_get_contents($config['saml_certificate'])) ||
				 !file_put_contents($cert, $content)))
			{
				throw new \Exception("Could not load certificate from $config[saml_certificate]!");
			}
			$source['certificate'] = $cert;
		}
		$metaloader = new SimpleSAML\Module\metarefresh\MetaLoader();
		$metaloader->loadSource($source);
		$metaloader->writeMetadataFiles($saml_config.'/metadata');

		// metadata files are PHP files and EGroupware contaier does not check timestamps
		if (function_exists('opcache_reset')) opcache_reset();
	}

	/**
	 * Update config files
	 *
	 * @param array $config
	 */
	public static function updateConfig(array $config)
	{
		// some Api classes require the config in $GLOBALS['egw_info']['server']
		$GLOBALS['egw_info']['server']['webserver_url'] = $config['webserver_url'];
		$GLOBALS['egw_info']['server']['usecookies'] = true;
		$config['baseurlpath'] = Api\Framework::getUrl(Api\Egw::link('/saml/'));
		$config['username_oid'] = [self::usernameOid($config)];
		$config['attribute_oids'] = [
			'eduPersonPricipalName' => self::eduPersonPricipalName,
			'eduPersonUniqueId' => self::eduPersonUniqueId,
			'emailAddress' => self::emailAddress,
			'uid' => self::uid,
			'firstName' => self::firstName,
			'lastName' => self::lastName,
		];
		if (!in_array(self::usernameOid($config), $config['attribute_oids']))
		{
			$config['attribute_oids']['customOid'] = self::usernameOid($config);
		}
		// if multiple IdP's are configured, do NOT specify one to let user select
		if (count(self::splitIdP($config['saml_idp'])) > 1)
		{
			unset($config['saml_idp']);
		}
		else
		{
			$config['saml_idp'] = trim($config['saml_idp']);
		}
		// update config.php and default-sp in authsources.php
		foreach([
			'authsources.php' => [
				'saml_idp' => "/('default-sp' => *\\[.*?'idp' => *).*?$/ms",
				'saml_sp'  => "/('default-sp' => *\\[.*?'name' => *\\[.*?'en' => *).*?$/ms",
				'attribute_oids' => "/('default-sp' => *\\[.*?'attributes' => *)\\[.*?\\],$/ms",
				'username_oid' => "/('default-sp' => *\\[.*?'attributes.required' => *)\\[.*?\\],$/ms",
			],
			'config.php' => [
				'baseurlpath' => "/('baseurlpath' => *).*?$/ms",
				'saml_contact_name' => "/('technicalcontact_name' => *).*?$/ms",
				'saml_contact_email' => "/('technicalcontact_email' => *).*?$/ms",
			]
		] as $file => $replacements)
		{
			if (file_exists($path = $config['files_dir'] . '/saml/'.$file) &&
				($content = file_get_contents($path)))
			{
				foreach($replacements as $conf => $reg_exp)
				{
					$content = preg_replace($reg_exp, '$1' . (is_array($config[$conf]) ?
						self::quoteArray($config[$conf]) : self::quote($config[$conf])) . ',', $content);
				}
				if (!file_put_contents($path, $content))
				{
					throw new \Exception("Failed to update '$path'!");
				}
			}
		}
	}

	/**
	 * @param string|null $str
	 * @param string $empty=null default value, if $str is empty
	 * @return string
	 */
	private static function quote($str, $empty=null)
	{
		return $str || isset($empty) ? "'".addslashes($str ?: $empty)."'" : 'null';
	}

	/**
	 * @param array $arr
	 * @param null $empty
	 * @return string
	 */
	private static function quoteArray($arr, $empty=null)
	{
		$str = "[\n";
		foreach($arr as $key => $val)
		{
			$str .= "\t\t";
			if (!is_int($key)) $str .= self::quote($key).'=>';
			$str .= self::quote($val, $empty).",\n";
		}
		$str .= "\t]";
		return $str;
	}

	/**
	 * Get the urn:oid of the username
	 *
	 * @param array|null $config
	 * @return string
	 */
	private static function usernameOid(array $config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server'];

		switch($config['saml_username'])
		{
			case 'eduPersonPrincipalName':
				return self::eduPersonPricipalName;
			case 'eduPersonUniqueId':
				return self::eduPersonUniqueId;
			case 'emailAddress':
				return self::emailAddress;
			case 'uid':
				return self::uid;
			case 'customOid':
				return !empty($config['saml_username_oid']) ? 'urn:oid:'.$config['saml_username_oid'] : self::emailAddress;
		}
		return self::emailAddress;
	}

	/**
	 * Get SAML attribute by name, taking into account that some IdP, like eg. Univention, use not the oid but the LDAP name
	 *
	 * @param string $name attribute name or null for the configured username
	 * @param array $attrs as returned by SimpleSAML\Auth\Simple::getAttributes()
	 * @param ?array $config default use config from $GLOBALS['egw_info']['server']
	 * @return string[]|string|null
	 */
	private static function samlAttr($name, array $attrs, array $config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server'];

		switch($name ?: $config['saml_username'] ?? 'emailAddress')
		{
			case 'emailAddress':
			case 'mailPrimaryAddress':
			case self::emailAddress:
				$keys = [self::emailAddress, 'emailAddress', 'mailPrimaryAddress'];
				break;
			case 'eduPersonPrincipalName':
			case self::eduPersonPricipalName:
				$keys = [self::eduPersonPricipalName, 'eduPersonPrincipalName'];
				break;
			case 'eduPersonUniqueId':
			case self::eduPersonUniqueId:
				$keys = [self::eduPersonUniqueId, 'eduPersonUniqueId'];
				break;
			case 'uid':
			case self::uid:
				$keys = [self::uid, 'uid'];
				break;
			case 'sn':
			case 'surname':
			case self::lastName:
				$keys = [self::lastName, 'sn', 'surname'];
				break;
			case 'givenName':
			case self::firstName:
				$keys = [self::firstName, 'givenName'];
				break;
			case 'customOid':
				$keys = ['urn:oid:'.$config['saml_username_oid'], $config['saml_username_oid']];
				break;
		}
		foreach($keys as $key)
		{
			if (isset($attrs[$key]) || isset($attrs[$key = strtolower($key)]))
			{
				return count($attrs[$key]) === 1 ? current($attrs[$key]) : $attrs[$key];
			}
		}
		return null;
	}

	/**
	 * eduPersonAffiliation attribute
	 */
	const eduPersonAffiliation = 'urn:oid:1.3.6.1.4.1.5923.1.1.1.1';

	/**
	 * Check if a group is specified depending on an affiliation attribute
	 *
	 * @param string $username
	 * @param array $attrs
	 * @param ?array& $auto_create_acct reference to $GLOBALS['auto_create_acct'] for not existing accounts
	 * @param array|null $config
	 */
	private function checkAffiliation($username, array $attrs, array &$auto_create_acct=null, array $config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server'];

		// check if affiliation is configured and attribute returned by IdP
		$attr = $config['saml_affiliation'] === 'eduPersonAffiliation' ? self::eduPersonAffiliation : $config['saml_affiliation_oid'];
		if (!empty($attr) && !empty($affiliation = self::samlAttr($attr, $attrs)) && !empty($config['saml_affiliation_group']) && !empty($config['saml_affiliation_values']) &&
			($gid = $GLOBALS['egw']->accounts->name2id($config['saml_affiliation_group'], 'account_lid', 'g')))
		{
			if (!isset($auto_create_acct) && ($accout_id = $GLOBALS['egw']->accounts->name2id($username, 'account_lid', 'u')))
			{
				$memberships = $GLOBALS['egw']->accounts->memberships($accout_id, true);
			}
			// check if attribute matches given values to add the extra membership
			if (array_intersect($affiliation, preg_split('/, */', $config['saml_affiliation_values'])))
			{
				if (isset($auto_create_acct))
				{
					$auto_create_acct['add_group'] = $gid;
				}
				elseif ($accout_id && !in_array($gid, $memberships))
				{
					$memberships[] = $gid;
					$GLOBALS['egw']->accounts->set_memberships($memberships, $accout_id);
				}
			}
			// remove membership, if it's set
			elseif ($accout_id && ($key = array_search($gid, $memberships, false)) !== false)
			{
				unset($memberships[$key]);
				$GLOBALS['egw']->accounts->set_memberships($memberships, $accout_id);
			}
		}
		error_log(__METHOD__."('$username', ".json_encode($attrs).", ".json_encode($auto_create_acct).") attr=$attr, gid=$gid --> account_id=$accout_id, memberships=".json_encode($memberships));
	}

	/**
	 * Create simpleSAMLphp default configuration
	 *
	 * @param array $config=null default $GLOBALS['egw_info']['server']
	 * @throws Exception
	 */
	public static function checkDefaultConfig(array $config=null)
	{
		if (!isset($config)) $config = $GLOBALS['egw_info']['server'];

		// some Api classes require the config in $GLOBALS['egw_info']['server']
		$GLOBALS['egw_info']['server']['webserver_url'] = $config['webserver_url'];
		$GLOBALS['egw_info']['server']['usecookies'] = true;

		// use "saml" subdirectory of EGroupware files directory as simpleSAMLphp config-directory
		$config_dir = $config['files_dir'].'/saml';
		if (!file_exists($config_dir) && !mkdir($config_dir))
		{
			throw new Exception("Can't create SAML config directory '$config_dir'!");
		}
		SimpleSAML\Configuration::setConfigDir($config_dir);

		// check if all necessary directories exist, if not create them
		foreach(['cert', 'log', 'data', 'metadata', 'tmp'] as $dir)
		{
			if (!file_exists($config_dir.'/'.$dir) && !mkdir($config_dir.'/'.$dir, 0700, true))
			{
				throw new Exception("Can't create $dir-directory '$config_dir/$dir'!");
			}
		}

		// create a key-pair, if not existing
		$cert_dir = $config_dir.'/cert';
		$private_key_path = $cert_dir.'/saml.pem';
		$public_key_path = $cert_dir.'/saml.crt';

		if (!file_exists($private_key_path) || !file_exists($public_key_path) ||
			!preg_match('/^-----BEGIN CERTIFICATE-----$/m', file_get_contents($public_key_path)))
		{
			$config = [
				"digest_alg" => "sha512",
				"private_key_bits" => 2048,
				"private_key_type" => OPENSSL_KEYTYPE_RSA,
			];
			// Read or generate the private key
			if ((!file_exists($private_key_path) ||
				($pkey = openssl_pkey_get_private(file_get_contents($private_key_path))) === false) &&
				($pkey = openssl_pkey_new($config)) === false)
			{
				throw new Exception('Error generating key-pair!');
			}

			// generate CSR and self-sign it
			if (($csr = openssl_csr_new([
					'commonName' => Api\Header\Http::host(),
				], $pkey, $config)) === false ||
				($cert = openssl_csr_sign($csr, null, $pkey, 3650, $config)) === false)
			{
				throw new Exception('Error self-signing cert!');
			}

			// Extract the public key from $res to $pubKey
			if (openssl_x509_export_to_file($cert, $public_key_path) === false ||
				// Extract the private key
				openssl_pkey_export_to_file($pkey, $private_key_path) === false)	// ToDo: db-password as passphrase
			{
				throw new Exception('Error storing key-pair!');
			}

			// fix permisions to only allow webserver access
			chmod($public_key_path, 0600);
			chmod($private_key_path, 0600);
		}

		// create a default configuration
		if (!file_exists($config_dir.'/config.php') || filesize($config_dir.'/config.php') < 1000)
		{
			$simplesaml_dir = EGW_SERVER_ROOT.'/vendor/simplesamlphp/simplesamlphp';

			foreach(glob($simplesaml_dir.'/config-templates/*.php') as $path)
			{
				switch($file=basename($path))
				{
					case 'config.php':
						$cookie_domain = Api\Session::getCookieDomain($cookie_path, $cookie_secure);
						$replacements = [
							'$config = [' => <<<EOF
// SimpleSAMLphp does NOT honor X-Forwarded-* headers
// and solution mentioned in docs to just set baseurlpath to correct https-URL does NOT work in all cases
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    \$_SERVER['HTTPS'] = 'on';
    \$_SERVER['SERVER_PORT'] = '443';
}

\$config = [
EOF
							,
							"'baseurlpath' => 'simplesaml/'," => "'baseurlpath' => '".Api\Framework::getUrl(Api\Egw::link('/saml/'))."',",
							"'timezone' => null," => "'timezone' => 'Europe/Berlin',",	// ToDo: use default prefs
							"'secretsalt' => 'defaultsecretsalt'," => "'secretsalt' => '".Api\Auth::randomstring(32)."',",
							"'auth.adminpassword' => '123'," => "'auth.adminpassword' => '".Api\Auth::randomstring(12)."',",
							"'admin.protectindexpage' => false," => "'admin.protectindexpage' => true,",
							"'certdir' => 'cert/'," => "'certdir' => __DIR__.'/cert/',",
							"'loggingdir' => 'log/'," => "'loggingdir' => __DIR__.'/log/',",
							"'datadir' => 'data/'," => "'datadir' => __DIR__.'/data/',",
							"'tempdir' => '/tmp/simplesaml'," => "'tempdir' => __DIR__.'/tmp',",
							"'metadatadir' => 'metadata'," => "'metadatadir' => __DIR__.'/metadata',",
							"'logging.handler' => 'syslog'," => "'logging.handler' => 'errorlog',",
							"'technicalcontact_name' => 'Administrator'" =>
								"'technicalcontact_name' => ".self::quote($config['saml_contact_name'], 'Administrator'),
							"'technicalcontact_email' => 'na@example.org'" =>
								"'technicalcontact_email' => ".self::quote($config['saml_contact_email'], 'na@example.org'),
							"'metadata.sign.privatekey' => null," => "'metadata.sign.privatekey' => 'saml.pem',",
							//"'metadata.sign.privatekey_pass' => null," => "",
							"'metadata.sign.certificate' => null," =>  "'metadata.sign.certificate' => 'saml.crt',",
							//"'metadata.sign.algorithm' => null," => "",
							// we have to use EGroupware session/cookie parameters
							"'session.cookie.name' => 'SimpleSAMLSessionID'," => "'session.cookie.name' => 'sessionid',",
							"'session.cookie.path' => '/'," => "'session.cookie.path' => '$cookie_path',",
							"'session.cookie.domain' => null," => "'session.cookie.domain' => '.$cookie_domain',",
							"'session.cookie.secure' => false," => "'session.cookie.secure' => ".($cookie_secure ? 'true' : 'false').',',
							"'session.phpsession.cookiename' => 'SimpleSAML'," => "'session.phpsession.cookiename' => 'sessionid',",
						];
						break;

					case 'authsources.php':
						$replacements = [
							"'idp' => null," => "'idp' => ".self::quote(
								count(self::splitIdP($config['saml_idp'])) <= 1 ? trim($config['saml_idp']) : null).',',
							"'discoURL' => null," => "'discoURL' => null,\n\n".
								// add our private and public keys
								"\t'privatekey' => 'saml.pem',\n\n".
								"\t// to include certificate in metadata\n".
								"\t'certificate' => 'saml.crt',\n\n".
								"\t// new certificates for rotation: add new, wait for IdP sync, swap old and new, wait, comment again\n".
								"\t//'new_privatekey' => 'new-saml.pem',\n".
								"\t//'new_certificate' => 'new-saml.crt',\n\n".
								"\t// logout is NOT signed by default, but signature is required from the uni-kl.de IdP for logout\n".
								"\t'sign.logout' => true,\n\n".
								"\t'name' => [\n".
								"\t\t'en' => ".self::quote($config['saml_sp'] ?: 'EGroupware').",\n".
								"\t],\n\n".
								"\t'attributes' => [\n".
								"\t\t'eduPersonPricipalName' => '".self::eduPersonPricipalName."',\n".
								"\t\t'eduPersonUniqueId' => '".self::eduPersonUniqueId."',\n".
								"\t\t'emailAddress' => '".self::emailAddress."',\n".
								"\t\t'firstName' => '".self::firstName."',\n".
								"\t\t'lastName' => '".self::lastName."',\n".
								"\t],\n".
								"\t'attributes.required' => [".self::quote(self::usernameOid($config))."],",
						];
						break;

					default:
						unset($replacements);
						if (!copy($path, $config_dir.'/'.$file))
						{
							throw new Exception("Can't copy SAML config file '$config_dir/$file'!");
						}
						break;
				}
				if (isset($replacements) &&
					!file_put_contents($config_dir.'/'.$file,
						$c=strtr($t=file_get_contents($path), $replacements)))
				{
					header('Content-Type: text/plain');
					echo "<pre>template:\n$t\n\nconfig:\n$c\n</pre>\n";
					throw new Exception("Can't write SAML config file '$config_dir/config.php'!");
				}
			}
			foreach(glob($simplesaml_dir.'/metadata-templates/*.php') as $path)
			{
				$dest = $config_dir . '/metadata/' . basename($path);
				if (!copy($path, $dest))
				{
					throw new Exception("Can't copy SAML metadata file '$dest'!");
				}
			}
		}
	}
}