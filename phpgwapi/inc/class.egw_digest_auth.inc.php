<?php
/**
 * eGroupWare API: Basic and Digest Auth
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage auth
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

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
 * 	'autocreate_session_callback' => array('egw_digest_auth','autocreate_session_callback'),
 * 	'auth_realm' => 'EGroupware',
 * );
 * include(dirname(__FILE__).'/header.inc.php');
 *
 * @link http://www.php.net/manual/en/features.http-auth.php
 * @ToDo check if we have to check if returned nonce matches our challange (not done in above link, but why would it be there)
 * @link http://en.wikipedia.org/wiki/Digest_access_authentication
 * @link http://tools.ietf.org/html/rfc2617
 */
class egw_digest_auth
{
	/**
	 * Log to error_log:
	 * 	0 = dont
	 *  1 = no cleartext passwords
	 *  2 = all
	 */
	const ERROR_LOG = 0;

	/**
	 * Callback to be used to create session via header include authenticated via basic or digest auth
	 *
	 * @param array $account NOT used!
	 * @return string valid session-id or does NOT return at all!
	 */
	static public function autocreate_session_callback(&$account)
	{
		if (self::ERROR_LOG) error_log(__METHOD__.'() PHP_AUTH_USER='.array2string($_SERVER['PHP_AUTH_USER']).', PHP_AUTH_PW='.array2string($_SERVER['PHP_AUTH_PW']).', PHP_AUTH_DIGEST='.array2string($_SERVER['PHP_AUTH_DIGEST']));
		$realm = $GLOBALS['egw_info']['flags']['auth_realm'];
		if (empty($realm)) $realm = 'EGroupware';

		if (!isset($_SERVER['PHP_AUTH_USER']) && !isset($_SERVER['PHP_AUTH_DIGEST']) ||
			isset($_SERVER['PHP_AUTH_DIGEST']) && (!self::is_valid($realm,$_SERVER['PHP_AUTH_DIGEST'],$username,$password) ||
				!($sessionid = $GLOBALS['egw']->session->create($username,$password,'text'))) ||
			isset($_SERVER['PHP_AUTH_USER']) && !($sessionid = $GLOBALS['egw']->session->create($_SERVER['PHP_AUTH_USER'],$_SERVER['PHP_AUTH_PW'],'text')))
		{
			// if the session class gives a reason why the login failed --> append it to the REALM
			if ($GLOBALS['egw']->session->reason) $realm .= ': '.$GLOBALS['egw']->session->reason;

			header('WWW-Authenticate: Basic realm="'.$realm.'"');
			self::digest_header($realm);
			header('HTTP/1.1 401 Unauthorized');
			header('X-WebDAV-Status: 401 Unauthorized', true);
			echo "<html>\n<head>\n<title>401 Unauthorized</title>\n<body>\nAuthorization failed.\n</body>\n</html>\n";
			exit;
		}
		return $sessionid;
	}

	/**
	 * Check if digest auth is available for a given realm (and user): do we use cleartext passwords
	 *
	 * If no user is given, check is NOT authoretive, as we can only check if cleartext passwords are generally used
	 *
	 * @param string $realm
	 * @param string $username=null username or null to only check if we auth agains sql and use plaintext passwords
	 * @param string &$user_pw=null stored cleartext password, if $username given AND function returns true
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
			if (self::ERROR_LOG > 1)
			{
				error_log(__METHOD__."('$realm','$username','$user_pw') return true");
			}
			else
			{
				error_log(__METHOD__."('$realm','$username',\$user_pw) return true");
			}
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
			$nonce = uniqid();
   			header('WWW-Authenticate: Digest realm="'.$realm.'",qop="auth",nonce="'.$nonce.'",opaque="'.md5($realm).'"');
			if (self::ERROR_LOG) error_log(__METHOD__."() offering digest auth for realm '$realm' using nonce='$nonce'");
		}
	}

	/**
	 * Check digest
	 *
	 * @param string $realm
	 * @param string $auth_digest=null default to $_SERVER['PHP_AUTH_DIGEST']
	 * @param string &$username on return username
	 * @param string &$password on return cleartext password
	 * @return boolean true if digest is correct, false otherwise
	 */
	static public function is_valid($realm,$auth_digest=null,&$username=null,&$password=null)
	{
		if (is_null($auth_digest)) $auth_digest = $_SERVER['PHP_AUTH_DIGEST'];

		$data = self::parse_digest($auth_digest);

		if (!$data || !($A1 = self::get_digest_A1($realm,$username=$data['username'],$password=null)))
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
