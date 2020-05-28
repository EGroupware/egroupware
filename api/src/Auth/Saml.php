<?php
/**
 * EGroupware API - Authentication via SAML or everything supported by SimpleSAMLphp
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
 * Authentication based on SAML or everything supported by SimpleSAMLphp
 *
 * SimpleSAMLphp is installed together with EGroupware and a default configuration is created in EGroupware
 * files subdirectory "saml", once "Saml" is set as authentication method in setup and eg. the login page is loaded.
 *
 * It will NOT work, before you configure at least one IdP (Identity Provider) for the default-sp (Service Provider) in saml/authsourcres.php:
 *
 *	// An authentication source which can authenticate against both SAML 2.0
 *	// and Shibboleth 1.3 IdPs.
 *	'default-sp' => [
 *		'saml:SP',
 *
 *		// The entity ID of this SP.
 *		// Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
 *		'entityID' => null,
 *
 *		// The entity ID of the IdP this SP should contact.
 *		// Can be NULL/unset, in which case the user will be shown a list of available IdPs.
 *		'idp' => 'https://samltest.id/saml/idp',
 *
 * And the IdP's metadata in saml/metadata/saml20-idp-remote.php
 *
 *		$metadata['https://samltest.id/saml/idp'] = [
 *			'SingleSignOnService'  => 'https://samltest.id/idp/profile/SAML2/Redirect/SSO',
 *			'SingleLogoutService'  => 'https://samltest.id/idp/profile/Logout',
 *			'certificate'          => 'samltest.id.pem',
 *		];
 *
 * https://samltest.id/ is just a SAML / Shibboleth test side allowing AFTER uploading your metadata to test with a couple of static test-accounts.
 *
 * The metadata can be downloaded by via https://example.org/egroupware/saml/ under Federation, it also allows to test the authentication.
 * The required (random) Admin password can be found in /var/lib/egrouwpare/default/saml/config.php searching for auth.adminpassword.
 *
 * Alternativly you can also modify the following metadata example by replacing https://example.org/ with your domain:
 *
 * <?xml version="1.0"?>
 * <md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" entityID="https://example.org/egroupware/saml/module.php/saml/sp/metadata.php/default-sp">
 * <md:SPSSODescriptor protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol urn:oasis:names:tc:SAML:1.1:protocol">
 * <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" Location="https://example.org/egroupware/saml/module.php/saml/sp/saml2-logout.php/default-sp"/>
 * <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST" Location="https://example.org/egroupware/saml/module.php/saml/sp/saml2-acs.php/default-sp" index="0"/>
 * <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:1.0:profiles:browser-post" Location="https://example.org/egroupware/saml/module.php/saml/sp/saml1-acs.php/default-sp" index="1"/>
 * <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact" Location="https://example.org/egroupware/saml/module.php/saml/sp/saml2-acs.php/default-sp" index="2"/>
 * <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:1.0:profiles:artifact-01" Location="https://example.org/egroupware/saml/module.php/saml/sp/saml1-acs.php/default-sp/artifact" index="3"/>
 * </md:SPSSODescriptor>
 * <md:ContactPerson contactType="technical">
 * <md:GivenName>Admin</md:GivenName>
 * <md:SurName>Name</md:SurName>
 * <md:EmailAddress>mailto:admin@example.org</md:EmailAddress>
 * </md:ContactPerson>
 * </md:EntityDescriptor>
 */
class Saml implements BackendSSO
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		// ensure we have (at least) a default configuration
		self::checkDefaultConfig();
	}

	/**
	 * authentication against SAML
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @param string $passwd_type ='text' 'text' for cleartext passwords (default)
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		// login (redirects to IdP)
		$as = new SimpleSAML\Auth\Simple('default-sp');
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
	const emailAddress = 'urn:oid:0.9.2342.19200300.100.1.1';
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
		$as = new SimpleSAML\Auth\Simple('default-sp');
		$as->requireAuth();

		// cleanup session for EGroupware
		$session = SimpleSAML\Session::getSessionFromRequest();
		$session->cleanup();

		// get attributes for (automatic) account creation
		$attrs = $as->getAttributes();
		$username = $attrs[self::usernameOid()][0];

		// check if user already exists
		if (!$GLOBALS['egw']->accounts->name2id($username, 'account_lid', 'u'))
		{
			// fail if auto-creation of authenticated users is NOT configured
			if (empty($GLOBALS['egw_info']['server']['auto_create_acct']))
			{
				return null;
			}
			$GLOBALS['auto_create_acct'] = [
				'firstname' => $attrs[self::firstName][0],
				'lastname' => $attrs[self::lastName][0],
				'email' => $attrs[self::emailAddress][0],
			];
		}
		// return user session
		return $GLOBALS['egw']->session->create($username, null, null, false, false);
	}

	/**
	 * Logout SSO system
	 */
	function logout()
	{
		$as = new SimpleSAML\Auth\Simple('default-sp');
		$as->logout();
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
		return ['SimpleSAMLphp_SESSION'];
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

		/*error_log(__METHOD__."() ".json_encode(array_filter($config, function($value, $key) {
			return substr($key, 0, 5) === 'saml_' || $key === 'auth_type';
		}, ARRAY_FILTER_USE_BOTH), JSON_UNESCAPED_SLASHES));*/

		if ($newsettings['auth_type'] !== 'saml') return;	// nothing to do

		if (file_exists($config['files_dir'].'/saml/config.php'))
		{
			self::updateConfig($config);
		}
		self::checkDefaultConfig($config);

		// install or remove async job to refresh metadata
		static $freq2times = [
			'hourly' => ['min' => 4],	// hourly at minute 4
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

		if ($config['saml_metadata_refresh'] !== 'no')
		{
			self::refreshMetadata($config);
		}
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
			'whitelist' => [$config['saml_idp']],	// only ready our idp, the whole thing can be huge
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

		// update config.php and default-sp in authsources.php
		foreach([
			'authsources.php' => [
				'saml_idp' => "/('default-sp' => *\\[.*?'idp' => *).*?$/ms",
				'saml_sp'  => "/('default-sp' => *\\[.*?'name' => *\\[.*?'en' => *).*?$/ms",
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
						"[".implode(',', array_map(self::class.'::quote', $config[$conf]))."]" :
						self::quote($config[$conf])) . ',', $content);
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
			case 'emailAddress':
				return self::emailAddress;
			case 'customOid':
				return $config['saml_username_oid'] ?: self::emailAddress;
		}
		return self::emailAddress;
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
			if (!file_exists($config_dir.'/'.$dir) && !mkdir($config_dir.'/'.$dir, 700, true))
			{
				throw new Exception("Can't create $dir-directory '$config_dir/$dir'!");
			}
		}

		// create a default configuration
		if (!file_exists($config_dir.'/config.php') || filesize($config_dir.'/config.php') < 1000)
		{
			// create a key-pair
			$cert_dir = $config_dir.'/cert';
			$private_key_path = $cert_dir.'/saml.pem';
			$public_key_path = $cert_dir.'/saml.crt';

			if (!file_exists($private_key_path) || !file_exists($public_key_path))
			{
				// Create the private and public key
				$res = openssl_pkey_new([
					"digest_alg" => "sha512",
					"private_key_bits" => 2048,
					"private_key_type" => OPENSSL_KEYTYPE_RSA,
				]);

				if ($res === false)
				{
					throw new Exception('Error generating key-pair!');
				}

				// Extract the public key from $res to $pubKey
				$details = openssl_pkey_get_details($res);

				// Extract the private key from $res
				$public_key = null;
				openssl_pkey_export($res, $public_key);	// ToDo: db-password as passphrase

				if (!file_put_contents($public_key_path, $details["key"]) ||
					!file_put_contents($private_key_path, $public_key.$details["key"]))
				{
					throw new Exception('Error storing key-pair!');
				}

				// fix permisions to only allow webserver access
				chmod($public_key_path, 0600);
				chmod($private_key_path, 0600);
			}

			$simplesaml_dir = EGW_SERVER_ROOT.'/vendor/simplesamlphp/simplesamlphp';

			foreach(glob($simplesaml_dir.'/config-templates/*.php') as $path)
			{
				switch($file=basename($path))
				{
					case 'config.php':
						$cookie_domain = Api\Session::getCookieDomain($cookie_path, $cookie_secure);
						$replacements = [
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
							"'idp' => null," => "'idp' => ".self::quote($config['saml_idp']).',',
							"'discoURL' => null," => "'discoURL' => null,\n\n".
								// add our private and public keys
								"\t'privatekey' => 'saml.pem',\n\n".
								"\t// to include certificate in metadata\n".
								"\t'certificate' => 'saml.crt',\n\n".
								"\t'name' => [\n".
								"\t\t'en' => ".self::quote($config['saml_sp'] ?: 'EGroupware').",\n".
								"\t],\n\n".
								"\t'attributes' => [\n".
								"\t\t'eduPersonPricipalName' => '".self::eduPersonPricipalName."',\n".
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