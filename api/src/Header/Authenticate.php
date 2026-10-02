<?php
/**
 * EGroupware API: Basic and Digest Auth
 *
 * For Apache FCGI you need the following rewrite rule:
 *
 * 	RewriteEngine on
 * 	RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 *
 * Otherwise authentication request will be send over and over again, as password is NOT available to PHP!
 * (This makes authentication details available in PHP as $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage header
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Header;

use EGroupware\Api;
use EGroupware\OpenID\Token;

/**
 * Class to authenticate via basic or digest auth
 *
 * The more secure digest auth requires:
 *	a) cleartext passwords in SQL table
 *	b) md5 hashes of username, realm, password stored somewhere (NOT yet implemented)
 * Otherwise digest auth is not possible and therefore not offered to the client.
 *
 * Usage example:
 *
 * $GLOBALS['egw_info']['flags'] = array(
 * 	'noheader'  => True,
 * 	'currentapp' => 'someapp',
 * 	'no_exception_handler' => 'basic_auth',	// we use a basic auth exception handler (sends exception message as basic auth realm)
 * 	'autocreate_session_callback' => 'EGroupware\\Api\\Header\\Authenticate::autocreate_session_callback',
 * 	'auth_realm' => 'EGroupware',
 * );
 * include(dirname(__FILE__).'/header.inc.php');
 *
 * @link http://www.php.net/manual/en/features.http-auth.php
 * @ToDo check if we have to check if returned nonce matches our challange (not done in above link, but why would it be there)
 * @link http://en.wikipedia.org/wiki/Digest_access_authentication
 * @link http://tools.ietf.org/html/rfc2617
 *
 * Commented out is accept-charset parameter from (seems not supported by any client I tested with)
 * @link https://tools.ietf.org/id/draft-reschke-basicauth-enc-06.html
 *
 * Implemented support for clients sending credentials in iso-8859-1 instead of our utf-8:
 * - Firefox 19.0
 * - Thunderbird 17.0.3 with Lightning 1.8
 * - IE 8
 * - Netdrive
 * (Chrome 24 or Safari 6 sends credentials in charset of webpage.)
 */
class Authenticate
{
	/**
	 * Log to error_log:
	 * 	0 = dont
	 *  1 = no cleartext passwords
	 *  2 = all
	 */
	const ERROR_LOG = 0;

	/**
	 * All non-empty values of the Authorization header, in order of preference
	 *
	 * Depending on web-server and PHP SAPI the header is available in $_SERVER['HTTP_AUTHORIZATION'], as
	 * $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] (rewrite rule, eg. Apache with FCGI/CGI), or only via getallheaders() /
	 * apache_request_headers() (Apache not writing it to $_SERVER).
	 *
	 * @param ?array $server default $_SERVER
	 * @param ?array $headers default getallheaders() (if available), pass [] to NOT use it
	 * @return string[] trimmed values
	 */
	static public function authorizationHeaders(?array $server=null, ?array $headers=null) : array
	{
		$server ??= $_SERVER;
		$values = [];
		foreach(['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $name)
		{
			if (isset($server[$name]) && is_string($server[$name]) && trim($server[$name]) !== '')
			{
				$values[] = trim($server[$name]);
			}
		}
		$headers ??= function_exists('getallheaders') ? (getallheaders() ?: []) : [];
		foreach($headers as $name => $value)
		{
			if (strcasecmp((string)$name, 'Authorization') === 0 && is_string($value) && trim($value) !== '')
			{
				$values[] = trim($value);
			}
		}
		return array_values(array_unique($values));
	}

	/**
	 * Check if a request carries anything autocreate_session_callback() could authenticate with
	 *
	 * Used by groupdav.php to directly reject requests without any credentials, so it has to look at the
	 * same places autocreate_session_callback() reads them from, otherwise it rejects requests the callback
	 * would have accepted: depending on web-server and PHP SAPI (Apache module, FPM/FCGI/CGI with or without
	 * rewrite rule or CGIPassAuth) credentials arrive as PHP_AUTH_USER/PW, PHP_AUTH_DIGEST or in an Authorization header
	 * (see authorizationHeaders()).
	 *
	 * @param ?array $server default $_SERVER
	 * @param ?array $cookie default $_COOKIE
	 * @param ?array $headers default getallheaders() (if available), pass [] to NOT use it
	 * @return bool true: credentials given, false: none (or just an empty "Basic")
	 */
	static public function hasCredentials(?array $server=null, ?array $cookie=null, ?array $headers=null) : bool
	{
		$server ??= $_SERVER;
		$cookie ??= $_COOKIE;

		if (!empty($server['PHP_AUTH_USER']) || !empty($server['PHP_AUTH_DIGEST']))
		{
			return true;
		}
		foreach(self::authorizationHeaders($server, $headers) as $authorization)
		{
			// an empty "Basic" (user and password missing, web-servers usually trim the trailing space) is no credentials,
			// this is a cheap way to reject floods of such requests before the DB is even opened
			if (!preg_match('/^basic$/i', $authorization))
			{
				return true;
			}
		}
		// bearer token from cookie is checked by autocreate_session_callback() too
		return !empty($cookie['oauth_id_token']);
	}

	/**
	 * Get basic auth credentials from an Authorization header
	 *
	 * @param ?array $server default $_SERVER
	 * @param ?array $headers default getallheaders() (if available), pass [] to NOT use it
	 * @return ?array null if there's no basic Authorization header, otherwise [$username, $password], both null if malformed
	 */
	static public function basicCredentials(?array $server=null, ?array $headers=null) : ?array
	{
		foreach(self::authorizationHeaders($server, $headers) as $authorization)
		{
			if (preg_match('/^Basic\s+(\S+)$/i', $authorization, $matches))
			{
				$hash = base64_decode($matches[1]);
				return $hash !== false && str_contains($hash, ':') ? explode(':', $hash, 2) : [null, null];
			}
		}
		return null;
	}

	/**
	 * Get a bearer token from the Authorization header or the oauth_id_token cookie
	 *
	 * @param ?array $server default $_SERVER
	 * @param ?array $cookie default $_COOKIE
	 * @param ?array $headers default getallheaders() (if available), pass [] to NOT use it
	 * @return ?string token or null if there is none
	 */
	static public function bearerToken(?array $server=null, ?array $cookie=null, ?array $headers=null) : ?string
	{
		$cookie ??= $_COOKIE;

		foreach(self::authorizationHeaders($server, $headers) as $authorization)
		{
			if (preg_match('/^Bearer\s+(\S.*)$/i', $authorization, $matches))
			{
				return $matches[1];
			}
		}
		return !empty($cookie['oauth_id_token']) ? $cookie['oauth_id_token'] : null;
	}

	/**
	 * Redact credentials in the output of phpinfo(), so it can be shared eg. to debug authentication problems
	 *
	 * phpinfo() shows PHP_AUTH_PW, the Authorization header (in $_SERVER, the environment and the request headers) and
	 * the cookies incl. the session-id in clear. We show what is needed to see which variables arrive in PHP, but
	 * not the secrets:
	 * - Authorization: Basic <username>:<as many * as the password has characters>
	 * - Authorization: other schemes: the scheme and the first 4 characters of the token plus ********, NOT its length
	 *   (digest: the username)
	 * - PHP_AUTH_PW: "** length=8 **"
	 * - cookies: only the names
	 *
	 * @param string $html output of phpinfo() (HTML)
	 * @return string
	 */
	static public function redactPhpinfo(string $html) : string
	{
		return preg_replace_callback('#<tr><td class="e">(.*?)</td><td class="v">(.*?)</td></tr>#s', static function(array $row)
		{
			$name = html_entity_decode(trim(strip_tags($row[1])), ENT_QUOTES | ENT_HTML5);
			$value = html_entity_decode(trim(strip_tags($row[2])), ENT_QUOTES | ENT_HTML5);
			$redacted = null;

			if (preg_match('/(^|[^A-Z0-9])PHP_AUTH_PW(?![A-Z0-9_])/i', $name))
			{
				$redacted = '** length='.strlen($value).' **';
			}
			elseif (preg_match('/(^|[^A-Z0-9])(PHP_AUTH_DIGEST|(REDIRECT_)*HTTP_AUTHORIZATION|Authorization)(?![A-Z0-9_])/i', $name))
			{
				$redacted = self::redactAuthorization($value);
			}
			elseif (preg_match('/\$_COOKIE\[/i', $name))
			{
				$redacted = '***';
			}
			elseif (preg_match('/(^|[^A-Z0-9])(\w+_)?HTTP_COOKIE(?![A-Z0-9_])|(^|[^A-Z0-9])Cookie(?![A-Z0-9_])/i', $name))
			{
				$redacted = implode('; ', array_map(static function($cookie)
				{
					return trim(explode('=', $cookie, 2)[0]).'=***';
				}, array_filter(explode(';', $value), static fn($cookie) => trim($cookie) !== '')));
			}
			return $redacted === null ? $row[0] :
				'<tr><td class="e">'.$row[1].'</td><td class="v">'.htmlspecialchars($redacted, ENT_QUOTES | ENT_HTML5).' </td></tr>';
		}, $html);
	}

	/**
	 * Which text format does the client prefer over HTML (Accept header, a generic "*&#47;*" does NOT count)
	 *
	 * @param ?string $accept default $_SERVER['HTTP_ACCEPT']
	 * @return ?string 'text/markdown' or 'text/plain' (both get the same markdown), null for HTML
	 */
	static public function preferredTextFormat(?string $accept=null) : ?string
	{
		$quality = ['text/markdown' => 0.0, 'text/plain' => 0.0, 'text/html' => 0.0];
		foreach(explode(',', $accept ?? $_SERVER['HTTP_ACCEPT'] ?? '') as $type)
		{
			$parts = array_map('trim', explode(';', $type));
			$mime = strtolower(array_shift($parts));
			if (!isset($quality[$mime])) continue;
			$q = 1.0;
			foreach($parts as $param)
			{
				if (preg_match('/^q=([0-9.]+)$/i', $param, $m)) $q = (float)$m[1];
			}
			$quality[$mime] = max($quality[$mime], $q);
		}
		// the better one of markdown and plain (markdown on a tie), but only if not less preferred than HTML
		$mime = $quality['text/plain'] > $quality['text/markdown'] ? 'text/plain' : 'text/markdown';
		return $quality[$mime] > 0 && $quality[$mime] >= $quality['text/html'] ? $mime : null;
	}

	/**
	 * Convert the HTML output of phpinfo() to Markdown: headings and tables (GitHub flavoured), nothing else
	 *
	 * Tables get the header row of the HTML table, or "Name | Value" if it has none.
	 *
	 * @param string $html output of phpinfo() (HTML)
	 * @return string
	 */
	static public function phpinfoToMarkdown(string $html) : string
	{
		$decode = static fn($html) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(
			preg_replace('#<br\s*/?>#i', ' ', $html)), ENT_QUOTES | ENT_HTML5)));
		// pipes have to be escaped, asterisks (eg. redacted passwords) are shown as code instead of being escaped
		$cell = static function($html) use ($decode)
		{
			$text = str_replace('|', '\|', $decode($html));
			if (str_contains($text, '*'))
			{
				$text = str_contains($text, '`') ? str_replace('*', '\*', $text) : '`'.$text.'`';
			}
			return $text;
		};
		$heading = static function($tag, $html) use ($decode)
		{
			return str_repeat('#', (int)$tag[1]).' '.$decode($html);
		};

		if (($pos = stripos($html, '<body')) !== false)
		{
			$html = substr($html, $pos);
		}
		// tables: headings inside them (PHP Version) are headings, all other rows form a table
		$html = preg_replace_callback('#<table[^>]*>(.*?)</table>#is', static function($table) use ($cell, $heading)
		{
			$out = $rows = [];
			$header = null;
			preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $table[1], $trs);
			foreach($trs[1] as $tr)
			{
				if (preg_match('#<(h[1-3])[^>]*>(.*?)</h[1-3]>#is', $tr, $h))
				{
					$out[] = $heading($h[1], $h[2]);
					continue;
				}
				preg_match_all('#<t([dh])[^>]*>(.*?)</t[dh]>#is', $tr, $cells);
				$row = array_map($cell, $cells[2]);
				if (!$rows && $header === null && $cells[1] && !in_array('d', $cells[1]))
				{
					$header = $row;	// all cells are <th>
				}
				else
				{
					$rows[] = $row;
				}
			}
			if ($rows)
			{
				$cols = max(count($header ?? []), ...array_map('count', $rows));
				$header ??= array_slice(['Name', 'Value', 'Master'], 0, $cols) + array_fill(0, $cols, '');
				$line = static fn(array $cells) => '| '.implode(' | ', array_pad($cells, $cols, '')).' |';
				$out[] = $line($header)."\n".'|'.str_repeat(' --- |', $cols)."\n".implode("\n", array_map($line, $rows));
			}
			// decoded text is escaped again, so the final strip_tags() does not remove "<...>" contained in values
			return "\n\n".htmlspecialchars(implode("\n\n", $out))."\n\n";
		}, $html);
		// headings outside of tables, eg. one per module
		$html = preg_replace_callback('#<(h[1-3])[^>]*>(.*?)</h[1-3]>#is', static fn($m) => "\n\n".htmlspecialchars($heading($m[1], $m[2]))."\n\n", $html);
		$text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

		return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+\n/', "\n", $text)))."\n";
	}

	/**
	 * Redact the value of an Authorization header, see redactPhpinfo()
	 *
	 * @param string $authorization eg. "Basic dXNlcjpwdw=="
	 * @return string eg. "Basic user:**"
	 */
	static public function redactAuthorization(string $authorization) : string
	{
		$authorization = trim($authorization);
		[$scheme, $rest] = preg_split('/\s+/', $authorization, 2) + [1 => ''];
		switch(strtolower($scheme))
		{
			case 'basic':
				$credentials = base64_decode($rest, true);
				return $credentials !== false && str_contains($credentials, ':') ?
					$scheme.' '.explode(':', $credentials, 2)[0].':'.str_repeat('*', strlen(explode(':', $credentials, 2)[1])) :
					$scheme.($rest !== '' ? ' ********' : '');
			case 'digest':
				return $scheme.(preg_match('/username="([^"]*)"/', $rest, $m) ? ' username="'.$m[1].'", ********' : ' ********');
			default:
				return $scheme.($rest !== '' ? ' '.self::maskSecret($rest) : '');
		}
	}

	/**
	 * Show only the start of a secret (token): first 4 characters (less for short secrets) plus fixed number of *
	 *
	 * The length of the secret is NOT shown.
	 *
	 * @param string $secret
	 * @return string eg. "abcd********"
	 */
	static protected function maskSecret(string $secret) : string
	{
		return mb_substr($secret, 0, min(4, intdiv(mb_strlen($secret), 3))).'********';
	}

	/**
	 * Callback to be used to create session via header include authenticated via basic or digest auth
	 *
	 * @param array $account NOT used!
	 * @return string valid session-id or does NOT return at all!
	 */
	static public function autocreate_session_callback(&$account)
	{
		unset($account);	// not used, but required by function signature
		if (self::ERROR_LOG)
		{
			$pw = self::ERROR_LOG > 1 ? $_SERVER['PHP_AUTH_PW'] : '**********';
			error_log(__METHOD__.'() PHP_AUTH_USER='.array2string($_SERVER['PHP_AUTH_USER']).', PHP_AUTH_PW='.array2string($pw).', PHP_AUTH_DIGEST='.array2string($_SERVER['PHP_AUTH_DIGEST']));
		}
		$realm = $GLOBALS['egw_info']['flags']['auth_realm'];
		if (empty($realm)) $realm = 'EGroupware';

		/** @var Api\Session $session */
		$session = $GLOBALS['egw']->session;

		$username = $_SERVER['PHP_AUTH_USER']; $password = $_SERVER['PHP_AUTH_PW'];
		// Support for basic auth when PHP did not parse the header into PHP_AUTH_USER/PW (eg. PHP CGI, Apache with rewrite rule)
		if (!isset($username) && ($basic = self::basicCredentials()) !== null)
		{
			[$username, $password] = $basic;
		}
		elseif (isset($_SERVER['PHP_AUTH_DIGEST']) && !self::is_valid($realm,$_SERVER['PHP_AUTH_DIGEST'],$username,$password))
		{
			unset($password);
		}
		elseif (($bearer = self::bearerToken()) !== null &&
			class_exists('EGroupware\OpenID\Token') && ($token = (new Token())->validate($bearer, "PT5M", $client)))
		{
			$username = $token->claims()->get('sub');
			unset($password);
			$auth_check = false;    // we just checked the authentication
			// set session->limits from app-* scopes, thought we need to check the client itself, as the token contains only requested scopes
			// we always set "api", as setting nothing give full access like for the user itself
			$session->limits = ['api' => true];
			foreach($client->getScopes() ?? [] as $scope)
			{
				if (str_starts_with($scope, 'app-'))
				{
					$session->limits[substr($scope, 4)] = true;
				}
			}
		}
		// if given password contains non-ascii chars AND we can not authenticate with it
		if (isset($username) && isset($password) &&
			(preg_match('/[^\x20-\x7F]/', $password) || strpos($password, '\\x') !== false) &&
			!$GLOBALS['egw']->auth->authenticate($username, $password, 'text'))
		{
			self::decode_password($password);
		}
		// create session without session cookie (session->create(..., true), as we use pseudo sessionid from credentials
		if (!isset($username) || !($sessionid = $session->create($username, $password, 'text', true, $auth_check ?? true)))
		{
			// if the session class gives a reason why the login failed --> append it to the REALM
			if ($GLOBALS['egw']->session->reason &&
				// not for bad-login-or-password as it stalls storing the credentials!
				$GLOBALS['egw']->session->cd_reason != Api\Session::CD_BAD_LOGIN_OR_PASSWORD)
			{
				$realm .= ': '.$GLOBALS['egw']->session->reason;
			}
			header('WWW-Authenticate: Basic realm="'.$realm.'"');// draft-reschke-basicauth-enc-06 adds, accept-charset="'.Api\Translation::charset().'"');
			self::digest_header($realm);
			header('HTTP/1.1 401 Unauthorized');
			header('X-WebDAV-Status: 401 Unauthorized', true);
			echo "<html>\n<head>\n<title>401 Unauthorized</title>\n<body>\nAuthorization failed.\n</body>\n</html>\n";
			exit;
		}
		return $sessionid;
	}

	/**
	 * Decode password containing non-ascii chars
	 *
	 * @param string &$password
	 * @return boolean true if conversation happend, false if there was no need for a conversation
	 */
	public static function decode_password(&$password)
	{
		// if given password contains non-ascii chars AND we can not authenticate with it
		if (preg_match('/[^\x20-\x7F]/', $password) || strpos($password, '\\x') !== false)
		{
			// replace \x encoded non-ascii chars in password, as they are used eg. by Thunderbird for German umlauts
			if (strpos($password, '\\x') !== false)
			{
				$password = preg_replace_callback('/\\\\x([0-9A-F]{2})/i', function($matches){
					return chr(hexdec($matches[1]));
				}, $password);
			}
			// try translating the password from iso-8859-1 to utf-8
			$password = Api\Translation::convert($password, 'iso-8859-1');
			//error_log(__METHOD__."() Fixed non-ascii password of user '$username' from '$_SERVER[PHP_AUTH_PW]' to '$password'");
			return true;
		}
		return false;
	}

	/**
	 * Check if digest auth is available for a given realm (and user): do we use cleartext passwords
	 *
	 * If no user is given, check is NOT authoritative, as we can only check if cleartext passwords are generally used
	 *
	 * @param string $realm
	 * @param string $username =null username or null to only check if we auth agains sql and use plaintext passwords
	 * @param string &$user_pw =null stored cleartext password, if $username given AND function returns true
	 * @return boolean true if digest auth is available, false otherwise
	 */
	static public function digest_auth_available($realm,$username=null,&$user_pw=null)
	{
		// we currently require plaintext passwords!
		if (!($GLOBALS['egw_info']['server']['auth_type'] == 'sql' && $GLOBALS['egw_info']['server']['sql_encryption_type'] == 'plain') ||
			  $GLOBALS['egw_info']['server']['auth_type'] == 'ldap' && $GLOBALS['egw_info']['server']['ldap_encryption_type'] == 'plain')
		{
			if (self::ERROR_LOG) error_log(__METHOD__."('$username') return false (no plaintext passwords used)");
			return false;	// no plain-text passwords used
		}
		// check for specific user, if given
		if (!is_null($username) && !(($user_pw = $GLOBALS['egw']->accounts->id2name($username,'account_pwd','u')) ||
			$GLOBALS['egw_info']['server']['auth_type'] == 'sql' && substr($user_pw,0,7) != '{PLAIN}'))
		{
			unset($user_pw);
			if (self::ERROR_LOG) error_log(__METHOD__."('$realm','$username') return false (unknown user or NO plaintext password for user)");
			return false;	// user does NOT exist, or has no plaintext passwords (ldap server requires real root_dn or special ACL!)
		}
		if (substr($user_pw,0,7) == '{PLAIN}') $user_pw = substr($user_pw,7);

		if (self::ERROR_LOG)
		{
			$pw = self::ERROR_LOG > 1 ? $user_pw : '**********';
			error_log(__METHOD__."('$realm','$username','$pw') return true");
		}
		return true;
	}

	/**
	 * Send header offering digest auth, if it's generally available
	 *
	 * @param string $realm
	 * @param string &$nonce=null on return
	 */
	static public function digest_header($realm,&$nonce=null)
	{
		if (self::digest_auth_available($realm))
		{
			$nonce = Api\Auth::randomstring();
   			header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.$nonce.'",opaque="'.md5($realm).'"');
			if (self::ERROR_LOG) error_log(__METHOD__."() offering digest auth for realm '$realm' using nonce='$nonce'");
		}
	}

	/**
	 * Check digest
	 *
	 * @param string $realm
	 * @param string $auth_digest =null default to $_SERVER['PHP_AUTH_DIGEST']
	 * @param string &$username on return username
	 * @param string &$password on return cleartext password
	 * @return boolean true if digest is correct, false otherwise
	 */
	static public function is_valid($realm,$auth_digest=null,&$username=null,&$password=null)
	{
		if (is_null($auth_digest)) $auth_digest = $_SERVER['PHP_AUTH_DIGEST'];

		$data = self::parse_digest($auth_digest);

		$password = null;
		if (!$data || !($A1 = self::get_digest_A1($realm,$username=$data['username'],$password)))
		{
			error_log(__METHOD__."('$realm','$auth_digest','$username') returning FALSE");
			return false;
		}
		$A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);

		$valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

		if (self::ERROR_LOG) error_log(__METHOD__."('$realm','$auth_digest','$username') response='$data[response]', valid_response='$valid_response' returning ".array2string($data['response'] === $valid_response));
		return $data['response'] === $valid_response;
	}

	/**
	 * Calculate the A1 digest hash
	 *
	 * @param string $realm
	 * @param string $username
	 * @param string &$password=null password to use or if null, on return stored password
	 * @return string|boolean false if $password not given and can NOT be read
	 */
	static private function get_digest_A1($realm,$username,&$password=null)
	{
		$user_pw = null;
		if (empty($username) || empty($realm) || !self::digest_auth_available($realm,$username,$user_pw))
		{
			return false;
		}
		if (is_null($password)) $password = $user_pw;

		$A1 = md5($username . ':' . $realm . ':' . $password);
		if (self::ERROR_LOG > 1) error_log(__METHOD__."('$realm','$username','$password') returning ".array2string($A1));
		return $A1;
	}

	/**
	 * Parse the http auth header
	 */
	static public function parse_digest($txt)
	{
	    // protect against missing data
	    $needed_parts = array('nonce'=>1, 'nc'=>1, 'cnonce'=>1, 'qop'=>1, 'username'=>1, 'uri'=>1, 'response'=>1);
	    $data = array();
	    $keys = implode('|', array_keys($needed_parts));

		$matches = null;
	    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

	    foreach ($matches as $m)
	    {
	        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
	        unset($needed_parts[$m[1]]);
	    }
	    //error_log(__METHOD__."('$txt') returning ".array2string($needed_parts ? false : $data));
	    return $needed_parts ? false : $data;
	}
}