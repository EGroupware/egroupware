<?php
/**
 * EGroupware EMailAdmin: Support for Sieve scripts
 *
 * @link http://www.egroupware.org
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

include_once('Net/Sieve.php');

/**
 * Support for Sieve scripts
 *
 * Class can be switched to use exceptions by calling
 *
 * 	PEAR::setErrorHandling(PEAR_ERROR_EXCEPTION);
 *
 * In which case constructor and setters will throw exceptions for connection, login or other errors.
 *
 * retriveRules and getters will not throw an exception, if there's no script currently.
 *
 * Most methods incl. constructor accept a script-name, but by default current active script is used
 * and if theres no script emailadmin_sieve::DEFAULT_SCRIPT_NAME.
 */
class emailadmin_sieve extends Net_Sieve
{
	/**
	 * reference to emailadmin_imap object
	 *
	 * @var emailadmin_imap
	 */
	var $icServer;

	/**
	* @var string name of active script queried from Sieve server
	*/
	var $scriptName;

	/**
	* @var $rules containing the rules
	*/
	var $rules;

	/**
	* @var $vacation containing the vacation
	*/
	var $vacation;

	/**
	* @var $emailNotification containing the emailNotification
	*/
	var $emailNotification;

	/**
	* @var object $error the last PEAR error object
	*/
	var $error;

	/**
	 * The timeout for the connection to the SIEVE server.
	 * @var int
	 */
	var $_timeout = 10;

	/**
	 * Switch on some error_log debug messages
	 *
	 * @var boolean
	 */
	var $debug = false;

	/**
	 * Default script name used if no active script found on server
	 */
	const DEFAULT_SCRIPT_NAME = 'felamimail';

	/**
	 * Constructor
	 *
	 * @param emailadmin_imap $_icServer
	 * @param string $_euser effictive user, if given the Cyrus admin account is used to login on behalf of $euser
	 * @param string $_scriptName
	 */
	function __construct(emailadmin_imap $_icServer=null, $_euser='', $_scriptName=null)
	{
		parent::Net_Sieve();

		if ($_scriptName) $this->scriptName = $_scriptName;

		// TODO: since we seem to have major problems authenticating via DIGEST-MD5 and CRAM-MD5 in SIEVE, we skip MD5-METHODS for now
		if (!is_null($_icServer))
		{
			$_icServer->supportedAuthMethods = array('PLAIN' , 'LOGIN');
		}
		else
		{
			$this->supportedAuthMethods = array('PLAIN' , 'LOGIN');
		}

		$this->displayCharset	= translation::charset();

		if (!is_null($_icServer) && $this->_connect($_icServer, $_euser) === 'die') {
			die('Sieve not activated');
		}
	}

	/**
	 * Open connection to the sieve server
	 *
	 * @param emailadmin_imap $_icServer
	 * @param string $euser effictive user, if given the Cyrus admin account is used to login on behalf of $euser
	 * @return mixed 'die' = sieve not enabled, false=connect or login failure, true=success
	 */
	function _connect(emailadmin_imap $_icServer, $euser='')
	{
		static $isConError = null;
		static $sieveAuthMethods = null;
		$_icServerID = $_icServer->acc_id;
		if (is_null($isConError))
		{
			$isConError =  egw_cache::getCache(egw_cache::INSTANCE, 'email', 'icServerSIEVE_connectionError' . trim($GLOBALS['egw_info']['user']['account_id']));
		}
		if ( isset($isConError[$_icServerID]) )
		{
			$this->error = new PEAR_Error($isConError[$_icServerID]);
			return false;
		}

		if ($this->debug)
		{
			error_log(__METHOD__ . array2string($euser));
		}
		if($_icServer->acc_sieve_enabled)
		{
			if ($_icServer->acc_sieve_host)
			{
				$sieveHost = $_icServer->acc_sieve_host;
			}
			else
			{
				$sieveHost = $_icServer->acc_imap_host;
			}
			//error_log(__METHOD__.__LINE__.'->'.$sieveHost);
			$sievePort		= $_icServer->acc_sieve_port;

			$useTLS = false;

			switch($_icServer->acc_sieve_ssl)
			{
				case emailadmin_account::SSL_SSL:
					$sieveHost = 'ssl://'.$sieveHost;
					break;
				case emailadmin_account::SSL_TLS:
					$sieveHost = 'tls://'.$sieveHost;
					break;
				case emailadmin_account::SSL_STARTTLS:
					$useTLS = true;
			}
			if ($euser)
			{
				$username = $_icServer->acc_imap_admin_username;
				$password = $_icServer->acc_imap_admin_password;
			}
			else
			{
				$username = $_icServer->acc_imap_username;
				$password = $_icServer->acc_imap_password;
			}
			$this->icServer = $_icServer;
		}
		else
		{
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isConError,$expiration=60*15);
			return 'die';
		}
		$this->_timeout = 10; // socket::connect sets the/this timeout on connection
		$timeout = emailadmin_imap::getTimeOut('SIEVE');
		if ($timeout > $this->_timeout)
		{
			$this->_timeout = $timeout;
		}

		if(PEAR::isError($this->error = $this->connect($sieveHost , $sievePort, $options=null, $useTLS) ) )
		{
			if ($this->debug)
			{
				error_log(__METHOD__ . ": error in connect($sieveHost,$sievePort, " . array2string($options) . ", $useTLS): " . $this->error->getMessage());
			}
			$isConError[$_icServerID] = $this->error->getMessage();
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isConError,$expiration=60*15);
			return false;
		}
		// we cache the supported AuthMethods during session, to be able to speed up login.
		if (is_null($sieveAuthMethods))
		{
			$sieveAuthMethods = & egw_cache::getSession('email', 'sieve_supportedAuthMethods');
		}
		if (isset($sieveAuthMethods[$_icServerID]))
		{
			$this->supportedAuthMethods = $sieveAuthMethods[$_icServerID];
		}

		if(PEAR::isError($this->error = $this->login($username, $password, null, $euser) ) )
		{
			if ($this->debug)
			{
				error_log(__METHOD__ . ": error in login($username,$password,null,$euser): " . $this->error->getMessage());
			}
			$isConError[$_icServerID] = $this->error->getMessage();
			egw_cache::setCache(egw_cache::INSTANCE,'email','icServerSIEVE_connectionError'.trim($GLOBALS['egw_info']['user']['account_id']),$isConError,$expiration=60*15);
			return false;
		}

		// query active script from Sieve server
		if (empty($this->scriptName))
		{
			try {
				$this->scriptName = $this->getActive();
			}
			catch(Exception $e) {
				unset($e);	// ignore NOTEXISTS exception
			}
			if (empty($this->scriptName))
			{
				$this->scriptName = self::DEFAULT_SCRIPT_NAME;
			}
		}

		//error_log(__METHOD__.__LINE__.array2string($this->_capability));
		return true;
	}

    /**
     * Handles connecting to the server and checks the response validity.
     * overwritten function from Net_Sieve to respect timeout
     *
     * @param string  $host    Hostname of server.
     * @param string  $port    Port of server.
     * @param array   $options List of options to pass to
     *                         stream_context_create().
     * @param boolean $useTLS  Use TLS if available.
     *
     * @return boolean  True on success, PEAR_Error otherwise.
     */
    function connect($host, $port, $options = null, $useTLS = true)
    {
        if ($this->debug)
		{
			error_log(__METHOD__ . __LINE__ . "$host, $port, " . array2string($options) . ", $useTLS");
		}
		$this->_data['host'] = $host;
        $this->_data['port'] = $port;
        $this->_useTLS       = $useTLS;
        if (is_array($options)) {
            $this->_options = array_merge((array)$this->_options, $options);
        }

        if (NET_SIEVE_STATE_DISCONNECTED != $this->_state) {
            return PEAR::raiseError('Not currently in DISCONNECTED state', 1);
        }

		if (PEAR::isError($res = $this->_sock->connect($host, $port, false, ($this->_timeout?$this->_timeout:10), $options))) {
            return $res;
        }

        if ($this->_bypassAuth) {
            $this->_state = NET_SIEVE_STATE_TRANSACTION;
        } else {
            $this->_state = NET_SIEVE_STATE_AUTHORISATION;
            if (PEAR::isError($res = $this->_doCmd())) {
                return $res;
            }
        }

        // Explicitly ask for the capabilities in case the connection is
        // picked up from an existing connection.
        if (PEAR::isError($res = $this->_cmdCapability())) {
            return PEAR::raiseError(
                'Failed to connect, server said: ' . $res->getMessage(), 2
            );
        }

        // Check if we can enable TLS via STARTTLS.
        if ($useTLS && !empty($this->_capability['starttls'])
            && function_exists('stream_socket_enable_crypto')
        ) {
            if (PEAR::isError($res = $this->_startTLS())) {
                return $res;
            }
        }

        return true;
    }

    /**
     * Handles the authentication using any known method
     * overwritten function from Net_Sieve to support fallback
     *
     * @param string $uid The userid to authenticate as.
     * @param string $pwd The password to authenticate with.
     * @param string $userMethod The method to use ( if $userMethod == '' then the class chooses the best method (the stronger is the best ) )
     * @param string $euser The effective uid to authenticate as.
     *
     * @return mixed  string or PEAR_Error
     *
     * @access private
     * @since  1.0
     */
    function _cmdAuthenticate($uid , $pwd , $userMethod = null , $euser = '' )
    {
        if ( PEAR::isError( $method = $this->_getBestAuthMethod($userMethod) ) ) {
            return $method;
        }
        //error_log(__METHOD__.__LINE__.' using AuthMethod: '.$method);
        switch ($method) {
            case 'DIGEST-MD5':
                $result = $this->_authDigest_MD5( $uid , $pwd , $euser );
                if (!PEAR::isError($result))
				{
					break;
				}
				$res = $this->_doCmd();
                unset($this->_error);
                $this->supportedAuthMethods = array_diff($this->supportedAuthMethods,array($method,'CRAM-MD5'));
                return $this->_cmdAuthenticate($uid , $pwd, null, $euser);
            case 'CRAM-MD5':
                $result = $this->_authCRAM_MD5( $uid , $pwd, $euser);
                if (!PEAR::isError($result))
				{
					break;
				}
				$res = $this->_doCmd();
                unset($this->_error);
                $this->supportedAuthMethods = array_diff($this->supportedAuthMethods,array($method,'DIGEST-MD5'));
                return $this->_cmdAuthenticate($uid , $pwd, null, $euser);
            case 'LOGIN':
                $result = $this->_authLOGIN( $uid , $pwd , $euser );
                if (!PEAR::isError($result))
				{
					break;
				}
				$res = $this->_doCmd();
                unset($this->_error);
                $this->supportedAuthMethods = array_diff($this->supportedAuthMethods,array($method));
                return $this->_cmdAuthenticate($uid , $pwd, null, $euser);
            case 'PLAIN':
                $result = $this->_authPLAIN( $uid , $pwd , $euser );
                break;
            default :
                $result = new PEAR_Error( "$method is not a supported authentication method" );
                break;
        }
        if (PEAR::isError($result))
		{
			return $result;
		}
		if (PEAR::isError($res = $this->_doCmd())) {
            return $res;
        }

        // Query the server capabilities again now that we are authenticated.
        if (PEAR::isError($res = $this->_cmdCapability())) {
            return PEAR::raiseError(
                'Failed to connect, server said: ' . $res->getMessage(), 2
            );
        }

        return $result;
    }

	function getRules()
	{
		if (!isset($this->rules)) $this->retrieveRules();

		return $this->rules;
	}

	function getVacation()
	{
		if (!isset($this->rules)) $this->retrieveRules();

		return $this->vacation;
	}

	function getEmailNotification()
	{
		if (!isset($this->rules)) $this->retrieveRules();

		return $this->emailNotification;
	}

	/**
	 * Set email notifications
	 *
	 * @param array $_rules
	 * @param string $_scriptName
	 */
	function setRules(array $_rules, $_scriptName=null)
	{
		$script = $this->retrieveRules($_scriptName);
		$script->debug = $this->debug;
		$script->rules = $_rules;
		$ret = $script->updateScript($this);
		$this->error = $script->errstr;
		return $ret;
	}

	/**
	 * Set email notifications
	 *
	 * @param array $_vacation
	 * @param string $_scriptName
	 */
	function setVacation(array $_vacation, $_scriptName=null)
	{
		if ($this->debug)
		{
			error_log(__METHOD__ . "($_scriptName," . print_r($_vacation, true) . ')');
		}
		$script = $this->retrieveRules($_scriptName);
		$script->debug = $this->debug;
		$script->vacation = $_vacation;
		$ret = $script->updateScript($this);
		$this->error = $script->errstr;
		return $ret;
	}

	/**
	 * Set email notifications
	 *
	 * @param array $_emailNotification
	 * @param string $_scriptName
	 * @return emailadmin_script
	 */
	function setEmailNotification(array $_emailNotification, $_scriptName=null)
	{
		if ($_emailNotification['externalEmail'] == '' || !preg_match("/\@/",$_emailNotification['externalEmail'])) {
    		$_emailNotification['status'] = 'off';
    		$_emailNotification['externalEmail'] = '';
    	}

    	$script = $this->retrieveRules($_scriptName);
   		$script->emailNotification = $_emailNotification;
		$ret = $script->updateScript($this);
		$this->error = $script->errstr;
		return $ret;
	}

	/**
	 * Retrive rules, vacation, notifications and return emailadmin_script object to update them
	 *
	 * @param string $_scriptName
	 * @return emailadmin_script
	 */
	function retrieveRules($_scriptName=null)
	{
		if (!$_scriptName)
		{
			$_scriptName = $this->scriptName;
		}
		$script = new emailadmin_script($_scriptName);

		try {
			$script->retrieveRules($this);
		}
		catch (Exception $e) {
			unset($e);	// ignore not found script exception
		}
		$this->rules =& $script->rules;
		$this->vacation =& $script->vacation;
		$this->emailNotification =& $script->emailNotification; // Added email notifications

		return $script;
	}
}
