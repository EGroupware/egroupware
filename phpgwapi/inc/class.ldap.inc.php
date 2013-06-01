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

/**
 * LDAP connection handling
 *
 * Please note for SSL or TLS connections hostname has to be:
 * - SSL: "ldaps://host[:port]/"
 * - TLS: "tls://host[:port]/"
 * Both require certificats installed on the webserver, otherwise the connection will fail!
 *
 * If multiple (space-separated) ldap hosts or urls are given, try them in order and
 * move first successful one to first place in session, to try not working ones
 * only once per session.
 */
class ldap
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
	* @var ldapserverinfo $ldapserverinfo
	*/
	var $ldapServerInfo;

	/**
	 * Throw Exceptions in ldapConnect instead of echoing error and returning false
	 *
	 * @var boolean $exception_on_error
	 */
	var $exception_on_error=false;

	/**
	 * Constructor
	 *
	 * @param boolean $exception_on_error=false true: throw Exceptions in ldapConnect instead of echoing error and returning false
	 */
	function __construct($exception_on_error=false)
	{
		$this->exception_on_error = $exception_on_error;
		$this->restoreSessionData();
	}

	/**
	 * Returns information about connected ldap server
	 *
	 * @return ldapserverinfo|null
	 */
	function getLDAPServerInfo()
	{
		return $this->ldapServerInfo;
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
	 * Connect to ldap server and return a handle
	 *
	 * If multiple (space-separated) ldap hosts or urls are given, try them in order and
	 * move first successful one to first place in session, to try not working ones
	 * only once per session.
	 *
	 * @param $host='' ldap host, default $GLOBALS['egw_info']['server']['ldap_host']
	 * @param $dn='' ldap dn, default $GLOBALS['egw_info']['server']['ldap_root_dn']
	 * @param $passwd='' ldap pw, default $GLOBALS['egw_info']['server']['ldap_root_pw']
	 * @return resource|boolean resource from ldap_connect() or false on error
	 * @throws egw_exception_assertion_failed 'LDAP support unavailable!' (no ldap extension)
	 */
	function ldapConnect($host='', $dn='', $passwd='')
	{
		if(!function_exists('ldap_connect'))
		{
			/* log does not exist in setup(, yet) */
			if(isset($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message('F-Abort, LDAP support unavailable');
				$GLOBALS['egw']->log->commit();
			}
			if ($this->exception_on_error) throw new egw_exception_assertion_failed('LDAP support unavailable!');

			printf('<b>Error: LDAP support unavailable</b><br>',$host);
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
		if ($this->exception_on_error) throw new egw_exception_no_permission("Can't connect/bind to LDAP server '$host' and dn='$dn'!");

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
		if(!$this->ds = ldap_connect($host, $port))
		{
			/* log does not exist in setup(, yet) */
			if(isset($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message('F-Abort, Failed connecting to LDAP server');
				$GLOBALS['egw']->log->commit();
			}
			return False;
		}

		if(ldap_set_option($this->ds, LDAP_OPT_PROTOCOL_VERSION, 3))
		{
			$supportedLDAPVersion = 3;
		}
		else
		{
			$supportedLDAPVersion = 2;
		}
		if ($use_tls) ldap_start_tls($this->ds);

		if (!isset($this->ldapServerInfo) ||
			!is_a($this->ldapServerInfo,'ldapserverinfo') ||
			$this->ldapServerInfo->host != $host)
		{
			//error_log("no ldap server info found");
			$ldapbind = @ldap_bind($this->ds, $GLOBALS['egw_info']['server']['ldap_root_dn'], $GLOBALS['egw_info']['server']['ldap_root_pw']);

			$this->ldapServerInfo = ldapserverinfo::get($this->ds, $host, $supportedLDAPVersion);
			$this->saveSessionData();
		}

		if(!@ldap_bind($this->ds, $dn, $passwd))
		{
			if(isset($GLOBALS['egw']->log))
			{
				$GLOBALS['egw']->log->message('F-Abort, Failed binding to LDAP server');
				$GLOBALS['egw']->log->commit();
			}

			return False;
		}

		return $this->ds;
	}

	/**
	 * disconnect from the ldap server
	 */
	function ldapDisconnect()
	{
		if(is_resource($this->ds))
		{
			ldap_unbind($this->ds);
			unset($this->ds);
			unset($this->ldapServerInfo);
		}
	}

	/**
	 * restore the session data
	 */
	function restoreSessionData()
	{
		if (isset($GLOBALS['egw']->session))	// no availible in setup
		{
			$this->ldapServerInfo = (array) unserialize($GLOBALS['egw']->session->appsession('ldapServerInfo'));
		}
	}

	/**
	 * save the session data
	 */
	function saveSessionData()
	{
		if (isset($GLOBALS['egw']->session))	// no availible in setup
		{
			$GLOBALS['egw']->session->appsession('ldapServerInfo','',serialize($this->ldapServerInfo));
		}
	}
}
