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
 * Environment variable UDM_REST_INSECURE=<not-empty> can be set to (temporary) disable certificate validation for UDM REST calls.
 * Used by EGroupware UCS appliance, which does not yet have a final certificate during EGroupware installation.
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
	const DEBUG = false;

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

		// fix error like: Request argument "policies" is not a "dict" (PHP encodes empty arrays as array, not object)
		if (array_key_exists('policies', $_payload) && empty($_payload['policies']))
		{
			$_payload['policies'] = new \stdClass();	// force "policies": {}
		}
		if (is_array($_payload['properties']) && array_key_exists('umcProperty', $_payload['properties']) && empty($_payload['properties']['umcProperty']))
		{
			$_payload['properties']['umcProperty'] = new \stdClass();	// force "umcProperty": {}
		}

		$curlOpts = [
			CURLOPT_URL => 'https://'.$this->host.($_path[0] !== '/' ? self::PREFIX : '').$_path,
			CURLOPT_USERPWD => $this->user.':'.$this->config['ldap_root_pw'],
			CURLOPT_SSL_VERIFYHOST => empty($_SERVER['UDM_REST_INSECURE']) ? 2 : 0,	// 0: to disable certificate check
			CURLOPT_SSL_VERIFYPEER => empty($_SERVER['UDM_REST_INSECURE']),
			CURLOPT_HTTPHEADER => [
				'Accept: application/json',
			],
			CURLOPT_CUSTOMREQUEST => $_method,
			CURLOPT_RETURNTRANSFER => 1,
			//CURLOPT_FOLLOWLOCATION => 1,
			CURLOPT_TIMEOUT => 30,	// setting a timeout of 30 seconds, as recommended by Univention
			CURLOPT_VERBOSE => 1,
			CURLOPT_HEADER => 1,
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

		$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$headers = self::getHeaders(substr($response, 0, $header_size));
		$body = substr($response, $header_size);

		$path = urldecode($_path);	// for nicer error-messages
		if ($response === false || $body !== '' && !($json = json_decode($body, true)) && json_last_error())
		{
			$info = curl_getinfo($curl);
			curl_close($curl);
			if ($retry > 0)
			{
				error_log(__METHOD__."($path, $_method, ...) failed, retrying in 100ms, returned $body, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).", curl_getinfo()=".json_encode($info, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
				usleep(100000);
				return $this->call($_path, $_method, $_payload, $headers, $if_match, $return_dn, --$retry);
			}
			error_log(__METHOD__."($path, $_method, ...) returned $body, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).", curl_getinfo()=".json_encode($info, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
			error_log(__METHOD__."($path, $_method, ".json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).")");
			throw new UdmCantConnect("Error contacting Univention UDM REST Api ($path)".($response !== false ? ': '.json_last_error() : ''));
		}
		curl_close($curl);
		// error in json or non 20x http status
		if (!empty($json['error']) || !preg_match('|^HTTP/[0-9.]+ 20|', $headers[0]))
		{
			error_log(__METHOD__."($path, $_method, ...) returned $response, headers=".json_encode($headers, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
			error_log(__METHOD__."($path, $_method, ".json_encode($_payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).")");
			throw new UdmError("UDM REST Api ($path): ".(empty($json['error']['message']) ? $headers[0] : $json['error']['message']), $json['error']['code']);
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
				throw new UdmMissingLocation("UDM REST Api ($path) did not return Location header!");
			}
			return urldecode($matches[1]);
		}
		return $json;
	}

	/**
	 * Convert header string in array of headers
	 *
	 * A "HTTP/1.1 100 Continue" is NOT returned!
	 *
	 * @param string $head
	 * @return array with name => value pairs, 0: http-status, value can be an array for multiple headers with same name
	 */
	protected static function getHeaders($head)
	{
		$headers = [];
		foreach(explode("\r\n", $head) as $header)
		{
			if (empty($header)) continue;

			$parts = explode(':', $header, 2);
			if (count($parts) < 2)
			{
				$headers[0] = $header;	// http-status
			}
			else
			{
				$name = strtolower($parts[0]);
				if (!isset($headers[$name]))
				{
					$headers[$name] = trim($parts[1]);
				}
				else
				{
					if (!is_array($headers[$name]))
					{
						$headers[$name] = [$headers[$name]];
					}
					$headers[$name][] = trim($parts[1]);
				}
			}
		}
		if (self::DEBUG) error_log(__METHOD__."(\$head) returning ".json_encode($headers));
		return $headers;
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
		$payload = $this->user2udm($data, $this->call('users/user/add'));

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
		return $this->call('users/user/'.urlencode($dn), 'PUT', $payload, $headers, $get_headers['etag'], true);
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
		$payload = $this->group2udm($data, $this->call('groups/group/add'));

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
		$payload = $this->group2udm($data, $this->call('groups/group/'.urlencode($dn), 'GET', [], $get_headers));

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
			'account_id' => 'gidNumber',
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
					// our account_id is negative for groups!
					$payload['properties'][$name] = $egw === 'account_id' ? abs($data[$egw]) : $data[$egw];
				}
			}
		}

		return $payload;
	}
}