<?php
/**
 * API - LDAP connection handling
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
 */
class ldap
{
	/**
	* @var resource $ds holds the LDAP link identifier
	*/
	var $ds;

	/**
	* @var array $ldapServerInfo holds the detected information about the different ldap servers
	*/
	var $ldapServerInfo;

	/**
	 * the constructor for this class
	 */
	function __construct()
	{
		$this->restoreSessionData();
	}

	/**
	 * escapes a string for use in searchfilters meant for ldap_search.
	 *
	 * Escaped Characters are: '*', '(', ')', ' ', '\', NUL
	 * It's actually a PHP-Bug, that we have to escape space.
	 * For all other Characters, refer to RFC2254.
	 * @param $string either a string to be escaped, or an array of values to be escaped
	 * @return ldapserverinfo|boolean
	 */
	function getLDAPServerInfo($_host)
	{
		if($this->ldapServerInfo[$_host] instanceof ldapserverinfo)
		{
			return $this->ldapServerInfo[$_host];
		}
		return false;
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
	 * If multiple (space-separated) ldap servers are given, try them in order and
	 * move first successful one to first place in session, to try not working ones
	 * only once per session.
	 *
	 * @param $host ldap host
	 * @param $dn ldap dn
	 * @param $passwd ldap pw
	 * @return resource|boolean resource from ldap_connect() or false on error
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

			printf('<b>Error: LDAP support unavailable</b><br>',$host);
			return False;
		}
		if(!$host)
		{
			$host = $GLOBALS['egw_info']['server']['ldap_host'];
		}

		if(!$dn)
		{
			$dn = $GLOBALS['egw_info']['server']['ldap_root_dn'];
		}

		if(!$passwd)
		{
			$passwd = $GLOBALS['egw_info']['server']['ldap_root_pw'];
		}

		if (($use_tls = substr($host,0,6) == 'tls://'))
		{
			$port = parse_url($host,PHP_URL_PORT);
			$host = parse_url($host,PHP_URL_HOST);
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
					$this->ldapServerInfo[$host] =& $this->ldapServerInfo[$h];

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
		echo "<p><b>Error: Can't connect/bind to LDAP server '$host' and dn='$dn'!</b><br />".function_backtrace()."</p>\n";

		return false;
	}

	/**
	 * connect to the ldap server and return a handle
	 *
	 * @param $host ldap host
	 * @param $dn ldap dn
	 * @param $passwd ldap pw
	 * @return resource|boolean resource from ldap_connect() or false on error
	 */
	private function _connect($host, $dn, $passwd)
	{
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

		if(!isset($this->ldapServerInfo[$host]))
		{
			//error_log("no ldap server info found");
			$ldapbind = @ldap_bind($this->ds, $GLOBALS['egw_info']['server']['ldap_root_dn'], $GLOBALS['egw_info']['server']['ldap_root_pw']);

			$filter='(objectclass=*)';
			$justthese = array('structuralObjectClass','namingContexts','supportedLDAPVersion','subschemaSubentry');

			if(($sr = @ldap_read($this->ds, '', $filter, $justthese)))
			{
				if($info = ldap_get_entries($this->ds, $sr))
				{
					$ldapServerInfo = new ldapserverinfo();

					$ldapServerInfo->setVersion($supportedLDAPVersion);

					// check for naming contexts
					if($info[0]['namingcontexts'])
					{
						for($i=0; $i<$info[0]['namingcontexts']['count']; $i++)
						{
							$namingcontexts[] = $info[0]['namingcontexts'][$i];
						}
						$ldapServerInfo->setNamingContexts($namingcontexts);
					}

					// check for ldap server type
					if($info[0]['structuralobjectclass'])
					{
						switch($info[0]['structuralobjectclass'][0])
						{
							case 'OpenLDAProotDSE':
								$ldapServerType = OPENLDAP_LDAPSERVER;
								break;
							default:
								$ldapServerType = UNKNOWN_LDAPSERVER;
								break;
						}
						$ldapServerInfo->setServerType($ldapServerType);
					}

					// check for subschema entry dn
					if($info[0]['subschemasubentry'])
					{
						$subschemasubentry = $info[0]['subschemasubentry'][0];
						$ldapServerInfo->setSubSchemaEntry($subschemasubentry);
					}

					// create list of supported objetclasses
					if(!empty($subschemasubentry))
					{
						$filter='(objectclass=*)';
						$justthese = array('objectClasses');

						if($sr=ldap_read($this->ds, $subschemasubentry, $filter, $justthese))
						{
							if($info = ldap_get_entries($this->ds, $sr))
							{
								if($info[0]['objectclasses']) {
									for($i=0; $i<$info[0]['objectclasses']['count']; $i++)
									{
										$pattern = '/^\( (.*) NAME \'(\w*)\' /';
										if(preg_match($pattern, $info[0]['objectclasses'][$i], $matches))
										{
											#_debug_array($matches);
											if(count($matches) == 3)
											{
												$supportedObjectClasses[$matches[1]] = strtolower($matches[2]);
											}
										}
									}
									$ldapServerInfo->setSupportedObjectClasses($supportedObjectClasses);
								}
							}
						}
					}
					$this->ldapServerInfo[$host] = $ldapServerInfo;
				}
			}
			else
			{
				$this->ldapServerInfo[$host] = false;
			}
			$this->saveSessionData();
		}
		else
		{
			$ldapServerInfo = $this->ldapServerInfo[$host];
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
