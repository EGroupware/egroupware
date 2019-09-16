<?php
/**
 * EGroupware support for Univention UDM REST Api
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 *
 * @link https://www.univention.com/blog-en/2019/07/udm-rest-api-beta-version-released/
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts\Univention;

use EGroupware\Api;

/**
 * Univention UDM REST Api
 *
 * @todo Use just UDM instead of still calling ldap/parent
 */
class Udm
{
	/**
	 * Config to use
	 *
	 * @var array $config
	 */
	protected $config;

	/**
	 * Hostname of master, derived from ldap_host
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * Username, derived from ldap_root_dn
	 *
	 * @var string
	 */
	protected $user;

	/**
	 * Udm url prefix, prepend to relative path like 'users/user'
	 */
	const PREFIX = '/univention/udm/';

	/**
	 * Log webservice-calls to error_log
	 */
	const DEBUG = true;

	/**
	 * Constructor
	 *
	 * @param array $config =null config to use, default $GLOBALS['egw_info']['server']
	 * @throws Api\Exception\WrongParameter for missing LDAP config
	 */
	public function __construct(array $config=null)
	{
		$this->config = isset($config) ? $config : $GLOBALS['egw_info']['server'];

		$this->host = parse_url($this->config['ldap_host'], PHP_URL_HOST);
		if (empty($this->host))
		{
			throw new Api\Exception\WrongParameter ("Univention needs 'ldap_host' configured!");
		}
		$matches = null;
		if (!preg_match('/^(cn|uid)=([^,]+),/i', $this->config['ldap_root_dn'], $matches))
		{
			throw new Api\Exception\WrongParameter ("Univention needs 'ldap_rood_dn' configured!");
		}
		$this->user = $matches[2];
	}

	/**
	 * Call UDM REST Api
	 *
	 * @param string $_path path to call, if relative PREFIX is prepended eg. 'users/user'
	 * @param string $_method ='GET'
	 * @param array $_payload =[] payload to send
	 * @param array& $headers =[] on return response headers
	 * @param string $if_match =null etag for If-Match header
	 * @param boolean $return_dn =false return DN of Location header
	 * @param int $retry =1 >0 retry on connection-error only
	 * @return array|string decoded JSON or DN for $return_DN === true
	 * @throws UdmCantConnect for connection errors or JSON decoding errors
	 * @throws UdmError for returned JSON error object
	 * @throws UdmMissingLocation for missing Location header with DN ($return_dn === true)
	 */
	protected function call($_path, $_method='GET', array $_payload=[], &$headers=[], $if_match=null, $return_dn=false, $retry=1)
	{
		$curl = curl_init();

		// fix error: Request argument "policies" is not a "dict" (PHP encodes empty arrays as array, not object)
		if (array_key_exists('policies', $_payload) && empty($_payload['policies']))
		{
			$_payload['policies'] = new \stdClass();	// force "policies": {}
		}

		$headers = [];
		$curlOpts = [
			CURLOPT_URL => 'https://'.$this->host.($_path[0] !== '/' ? self::PREFIX : '').$_path,
			CURLOPT_USERPWD => $this->user.':'.$this->config['ldap_root_pw'],
			//CURLOPT_SSL_VERIFYHOST => 2,	// 0: to disable certificate check
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
			],
			CURLOPT_CUSTOMREQUEST => $_method,
			CURLOPT_RETURNTRANSFER => 1,
			//CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT => 30,	// setting a timeout of 30 seconds, as recommended by Univention
			CURLOPT_VERBOSE => 1,
			CURLOPT_HEADERFUNCTION =>
				function($curl, $header) use (&$headers)
				{
					$len = strlen($header);
					$header = explode(':', $header, 2);
					if (count($header) < 2)
					{
						$headers[] = $header[0];	// http status
						return $len;
					}
					$name = strtolower(trim($header[0]));
					if (!array_key_exists($name, $headers))
					{
						$headers[$name] = trim($header[1]);
					}
					else
					{
						$headers[$name] = [$headers[$name]];
						$headers[$name][] = trim($header[1]);
					}
					unset($curl);	// not used, but required by function signature
					return $len;
				},
		];
		if (isset($if_match))
		{
			$curlOpts[CURLOPT_HTTPHEADER][] = 'If-Match: '.$if_match;
		}
		switch($_method)
		{
			case 'PUT':
			case 'POST':
				$curlOpts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
				$curlOpts[CURLOPT_POSTFIELDS] = json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
				break;

			case 'GET':
			default:
				if ($_payload)
				{
					$curlOpts[CURLOPT_URL] .= '?'. http_build_query($_payload);
				}
				break;
		}
		curl_setopt_array($curl, $curlOpts);
		$response = curl_exec($curl);

		$path = urldecode($_path);	// for nicer error-messages
		if (!$response || !($json = json_decode($response, true)) && json_last_error())
		{
			$info = curl_getinfo($curl);
			curl_close($curl);
			if ($retry > 0)
			{
				error_log(__METHOD__."($path, $_method, ...) failed, retrying in 100ms, returned $response, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).", curl_getinfo()=".json_encode($info, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
				usleep(100000);
				return $this->call($_path, $_method, $_payload, $headers, $if_match, $return_dn, --$retry);
			}
			error_log(__METHOD__."($path, $_method, ...) returned $response, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).", curl_getinfo()=".json_encode($info, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
			error_log(__METHOD__."($path, $_method, ".json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).")");
			throw new UdmCantConnect("Error contacting Univention UDM REST Api ($_path)".($response ? ': '.json_last_error() : ''));
		}
		curl_close($curl);
		if (!empty($json['error']))
		{
			error_log(__METHOD__."($path, $_method, ...) returned $response, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
			error_log(__METHOD__."($path, $_method, ".json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).")");
			throw new UdmError("UDM REST Api (".urldecode($_path)."): ".(empty($json['error']['message']) ? $response : $json['error']['message']), $json['error']['code']);
		}
		if (self::DEBUG)
		{
			error_log(__METHOD__."($path, $_method, ...) returned $response, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
			error_log(__METHOD__."($path, $_method, ".json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).")");
		}

		if ($return_dn)
		{
			$matches = null;
			if (!isset($headers['location']) || !preg_match('|/([^/]+)$|', $headers['location'], $matches))
			{
				throw new UdmMissingLocation("UDM REST Api ($_path) did not return Location header!");
			}
			return urldecode($matches[1]);
		}
		return $json;
	}

	/**
	 * Create a user
	 *
	 * @param array $data
	 * @throws Exception on error-message
	 * @return string with DN of new user
	 */
	public function createUser(array $data)
	{
		// set default values
		$payload = $this->user2udm($data, $this->call('users/user/add')['entry']);

		$payload['superordinate'] = null;
		$payload['position'] = $this->config['ldap_context'];

		$headers = [];
		return $this->call('users/user/', 'POST', $payload, $headers, null, true);
	}

	/**
	 * Update a user
	 *
	 * @param string $dn dn of user to update
	 * @param array $data
	 * @return string with dn
	 * @throws Exception on error-message
	 */
	public function updateUser($dn, array $data)
	{
		// set existing values
		$get_headers = [];
		$payload = $this->user2udm($data, $this->call('users/user/'.urlencode($dn), 'GET', [], $get_headers));

		$headers = [];
		$ret = $this->call('users/user/'.urlencode($dn), 'PUT', $payload, $headers, $get_headers['etag'], true);

		// you can not set the password and force a password change for next login in the same call
		// the forced password change will be lost --> call again without password to force the change on next login
		if (!empty($data['account_passwd']) && !empty($data['mustchangepassword']))
		{
			unset($data['account_passwd']);
			$ret = $this->updateUser($ret, $data);
		}
		return $ret;
	}

	/**
	 * Copy EGroupware user-values to UDM ones
	 *
	 * @param array $data
	 * @param array $payload
	 * @return array with updated payload
	 */
	protected function user2udm(array $data, array $payload)
	{
		// gives error: The property passwordexpiry has an invalid value: Value may not change.
		unset($payload['properties']['passwordexpiry']);

		foreach([
			'account_lid' => 'username',
			'account_passwd' => 'password',
			'account_lastname' => 'lastname',
			'account_firstname' => 'firstname',
			'account_id' => ['uidNumber', 'sambaRID'],
			'account_email' => 'mailPrimaryAddress',
			'mustchangepassword' => 'pwdChangeNextLogin',
		] as $egw => $names)
		{
			if (!empty($data[$egw]))
			{
				foreach((array)$names as $name)
				{
					if (!array_key_exists($name, $payload['properties']))
					{
						throw new \Exception ("No '$name' in properties: ".json_encode($payload['properties']));
					}
					$payload['properties'][$name] = $data[$egw];
				}
			}
		}

		if (!empty($data['account_email']))
		{
			// we need to set mailHomeServer, so mailbox gets created for Dovecot
			// get_default() does not work for Adminstrator, try acc_id=1 instead
			// if everything fails try ldap host / master ...
			try {
				if (!($account = Api\Mail\Account::get_default(false, false, false)))
				{
					$account = Api\Mail\Account::read(1);
				}
				$hostname = $account->acc_imap_host;
			}
			catch(\Exception $e) {
				unset($e);
			}
			if (empty($hostname)) $hostname = $this->host;
			$payload['properties']['mailHomeServer'] = $hostname;
		}

		return $payload;
	}

	/**
	 * Create a group
	 *
	 * @param array $data
	 * @throws Exception on error-message
	 * @return string with DN of new user
	 */
	public function createGroup(array $data)
	{
		// set default values
		$payload = $this->group2udm($data, $this->call('groups/group/add')['entry']);

		$payload['superordinate'] = null;
		$payload['position'] = empty($this->config['ldap_group_context']) ? $this->config['ldap_context'] : $this->config['ldap_group_context'];

		$headers = [];
		return $this->call('groups/group/', 'POST', $payload, $headers, null, true);
	}

	/**
	 * Update a group
	 *
	 * @param string $dn dn of group to update
	 * @param array $data
	 * @throws Exception on error-message
	 * @return string with DN of new user
	 */
	public function updateGroup($dn, array $data)
	{
		// set existing values
		$get_headers = [];
		$payload = $this->user2udm($data, $this->call('groups/group/'.urlencode($dn), 'GET', [], $get_headers));

		$headers = [];
		return $this->call('groups/group/'.urlencode($dn), 'PUT', $payload, $headers, $get_headers['etag'], true);
	}

	/**
	 * Copy EGroupware group values to UDM ones
	 *
	 * @param array $data
	 * @param array $payload
	 * @return array with updated payload
	 */
	protected function group2udm(array $data, array $payload)
	{
		foreach([
			'account_lid' => 'name',
			'account_id' => ['gidNumber', 'sambaRID'],
		] as $egw => $names)
		{
			if (!empty($data[$egw]))
			{
				foreach((array)$names as $name)
				{
					if (!array_key_exists($name, $payload['properties']))
					{
						throw new \Exception ("No '$name' in properties: ".json_encode($payload['properties']));
					}
					$payload['properties'][$name] = $data[$egw];
				}
			}
		}

		return $payload;
	}
}