<?php
/**
 * EGroupware API - LDAP connection handling
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <l.kneschke@metaways.de>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ldap
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * LDAP connection handling
 *
 * Please note for SSL or TLS connections hostname has to be:
 * - SSL: "ldaps://host[:port]/"
 * - TLS: "tls://host[:port]/"
 * Both require certificates installed on the webserver, otherwise the connection will fail!
 *
 * If multiple (space-separated) ldap hosts or urls are given, try them in order and
 * move first successful one to first place in session, to try not working ones
 * only once per session.
 *
 * Use Api\Ldap::factory($resource=true, $host='', $dn='', $passwd='') to open only a single connection.
 */
class Ldap
{
	/**
	* Holds the LDAP link identifier
	*
	* @var resource $ds
	*/
	var $ds;

	/**
	* Holds the detected information about the connected ldap server
	*
	* @var Ldap\ServerInfo $ldapserverinfo
	*/
	var $ldapserverinfo;

	/**
	 * Throw Exceptions in ldapConnect instead of echoing error and returning false
	 *
	 * @var boolean $exception_on_error
	 */
	var $exception_on_error=false;

	/**
	 * Constructor
	 *
	 * @param boolean $exception_on_error =false true: throw Exceptions in ldapConnect instead of echoing error and returning false
	 */
	function __construct($exception_on_error=false)
	{
		$this->exception_on_error = $exception_on_error;
		$this->restoreSessionData();
	}

	/**
	 * Connections created with factory method
	 *
	 * @var array
	 */
	protected static $connections = array();

	/**
	 * Connect to ldap server and return a handle or Api\Ldap object
	 *
	 * Use this factory method to open only a single connection to LDAP server!
	 *
	 * @param boolean $ressource =true true: return LDAP object/ressource for ldap_*-methods,
	 *	false: return connected instances of this Api\Ldap class
	 * @param string $host ='' ldap host, default $GLOBALS['egw_info']['server']['ldap_host']
	 * @param string $dn ='' ldap dn, default $GLOBALS['egw_info']['server']['ldap_root_dn']
	 * @param string $passwd ='' ldap pw, default $GLOBALS['egw_info']['server']['ldap_root_pw']
	 * @param bool $reconnect default false, true: reconnect, even if we have an existing connection
	 * @return object|resource|self|false resource/object from ldap_connect(), self or false on error
	 * @throws Exception\AssertionFailed 'LDAP support unavailable!' (no ldap extension)
	 * @throws Exception\NoPermission if bind fails
	 */
	public static function factory($ressource=true, $host='', $dn='', $passwd='', bool $reconnect=false)
	{
		$key = md5($host.':'.$dn.':'.$passwd);

		if (!isset(self::$connections[$key]) || $reconnect)
		{
			self::$connections[$key] = new Ldap(true);

			self::$connections[$key]->ldapConnect($host, $dn, $passwd);
		}
		return $ressource ? self::$connections[$key]->ds : self::$connections[$key];
	}

	/**
	 * Returns information about connected ldap server
	 *
	 * @return Ldap\ServerInfo|null
	 */
	function getLDAPServerInfo()
	{
		return $this->ldapserverinfo;
	}

	/**
	 * escapes a string for use in searchfilters meant for ldap_search.
	 *
	 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
	 * It's actually a PHP-Bug, that we have to escape space.
	 * For all other Characters, refer to RFC2254.
	 *
	 * @param string|array $string either a string to be escaped, or an array of values to be escaped
	 * @return string
	 */
	static function quote($string)
	{
		return str_replace(array('\\','*','(',')','\0',' '),array('\\\\','\*','\(','\)','\\0','\20'),$string);
	}

	/**
	 * Convert a single ldap result into a associative array
	 *
	 * @param array $ldap array with numerical and associative indexes and count's
	 * @param int $depth=0 0: single result / ldap_read, 1: multiple results / ldap_search
	 * @return boolean|array with only associative index and no count's or false on error (parm is no array)
	 */
	static function result2array($ldap, $depth=0)
	{
		if (!is_array($ldap)) return false;

		$arr = array();
		foreach($ldap as $var => $val)
		{
			if (is_int($var) && !$depth || $var === 'count') continue;

			if ($depth && is_array($val))
			{
				$arr[$var] = self::result2array($val, $depth-1);
			}
			elseif (is_array($val) && $val['count'] == 1)
			{
				$arr[$var] = $val[0];
			}
			else
			{
				if (is_array($val)) unset($val['count']);

				$arr[$var] = $val;
			}
		}
		return $arr;
	}

	/**
	 * Connect to ldap server and return a handle
	 *
	 * If multiple (space-separated) ldap hosts or urls are given, try them in order and
	 * move first successful one to first place in session, to try not working ones
	 * only once per session.
	 *
	 * @param string $host ='' ldap host, default $GLOBALS['egw_info']['server']['ldap_host']
	 * @param string $dn ='' ldap dn, default $GLOBALS['egw_info']['server']['ldap_root_dn']
	 * @param string $passwd ='' ldap pw, default $GLOBALS['egw_info']['server']['ldap_root_pw']
	 * @return resource|boolean resource from ldap_connect() or false on error
	 * @throws Exception\AssertionFailed 'LDAP support unavailable!' (no ldap extension)
	 * @throws Exception\NoPermission if bind fails
	 */
	function ldapConnect($host='', $dn='', $passwd='')
	{
		if(!function_exists('ldap_connect'))
		{
			if ($this->exception_on_error) throw new Exception\AssertionFailed('LDAP support unavailable!');

			printf('<b>Error: LDAP support unavailable</b><br>');
			return False;
		}
		if (empty($host))
		{
			$host = $GLOBALS['egw_info']['server']['ldap_host'];
		}
		if (empty($dn))
		{
			$dn = $GLOBALS['egw_info']['server']['ldap_root_dn'];
			$passwd = $GLOBALS['egw_info']['server']['ldap_root_pw'];
		}

		// if multiple hosts given, try them all, but only once per session!
		if (isset($_SESSION) && isset($_SESSION['ldapConnect']) && isset($_SESSION['ldapConnect'][$host]))
		{
			$host = $_SESSION['ldapConnect'][$host];
		}
		foreach($hosts=preg_split('/[ ,;]+/', $host) as $h)
		{
			if ($this->_connect($h, $dn, $passwd))
			{
				if ($h !== $host)
				{
					if (isset($_SESSION))	// store working host as first choice in session
					{
						$_SESSION['ldapConnect'][$host] = implode(' ',array_unique(array_merge(array($h),$hosts)));
					}
				}
				return $this->ds;
			}
			error_log(__METHOD__."('$h', '$dn', \$passwd) Can't connect/bind to ldap server!".
				($this->ds ? ' '.ldap_error($this->ds).' ('.ldap_errno($this->ds).')' : '').
				' '.function_backtrace());
		}
		// give visible error, only if we cant connect to any ldap server
		if ($this->exception_on_error) throw new Exception\NoPermission("Can't connect/bind to LDAP server '$host' and dn='$dn'!");

		return false;
	}

	/**
	 * connect to the ldap server and return a handle
	 *
	 * @param string $host ldap host
	 * @param string $dn ldap dn
	 * @param string $passwd ldap pw
	 * @return resource|boolean resource from ldap_connect() or false on error
	 */
	private function _connect($host, $dn, $passwd)
	{
		if (($use_tls = substr($host,0,6) == 'tls://'))
		{
			$port = parse_url($host,PHP_URL_PORT);
			$host = parse_url($host,PHP_URL_HOST);
		}
		// connect to ldap server (never fails, as connection happens in bind!)
		if(!$host || !($this->ds = !empty($port) ? ldap_connect($host, $port) : ldap_connect($host)))
		{
			return False;
		}
		// set network timeout to not block for minutes
		ldap_set_option($this->ds, LDAP_OPT_NETWORK_TIMEOUT, 5);

		if(ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3))
		{
			$supportedLDAPVersion = 3;
		}
		else
		{
			$supportedLDAPVersion = 2;
		}
		if ($use_tls) ldap_start_tls($this->ds);

		if (!isset($this->ldapserverinfo) ||
			!is_a($this->ldapserverinfo,'EGroupware\Ldap\ServerInfo') ||
			$this->ldapserverinfo->host != $host)
		{
			//error_log("no ldap server info found");
			@ldap_bind($this->ds, $GLOBALS['egw_info']['server']['ldap_root_dn'], $GLOBALS['egw_info']['server']['ldap_root_pw']);

			$this->ldapserverinfo = Ldap\ServerInfo::get($this->ds, $host, $supportedLDAPVersion);
			$this->saveSessionData();
		}

		if(!@ldap_bind($this->ds, $dn, $passwd))
		{
			return False;
		}

		return $this->ds;
	}

	/**
	 * disconnect from the ldap server
	 */
	function ldapDisconnect()
	{
		if ($this->ds)
		{
			ldap_unbind($this->ds);
			unset($this->ds);
			unset($this->ldapserverinfo);
		}
	}

	/**
	 * restore the session data
	 */
	function restoreSessionData()
	{
		if (isset($GLOBALS['egw']->session))	// no availible in setup
		{
			$this->ldapserverinfo = Cache::getSession(__CLASS__, 'ldapServerInfo');
		}
	}

	/**
	 * save the session data
	 */
	function saveSessionData()
	{
		if (isset($GLOBALS['egw']->session))	// no availible in setup
		{
			Cache::setSession(__CLASS__, 'ldapServerInfo', $this->ldapserverinfo);
		}
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
		unset($vars['ds']);
		return array_keys($vars);
	}

	/**
	 * __wakeup function gets called by php while unserializing the object to reconnect with the ldap server
	 */
	function __wakeup()
	{

	}
}