<?php
/**
 * EGroupware Api: Interface for IMAP support
 *
 * @link http://www.stylite.de
 * @package api
 * @subpackage mail
 * @author Ralf Becker <rb@stylite.de>
 * @author Stylite AG <info@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Mail;
use EGroupware\Api\Mail;

/**
 * This class holds all information about the imap connection.
 * This is the base class for all other imap classes.
 *
 * Also proxies Sieve calls to Mail\Sieve (eg. it behaves like the former felamimail bosieve),
 * to allow IMAP plugins to also manage Sieve connection.
 */
class EWS 
{
	const DESCRIPTION = 'Microsoft Exchange (EWS)';
    var $params;

	function __construct(array $params, $_timeout=null)
	{
        $this->params['username'] = $params['acc_imap_username'];
        $this->params['password'] = $params['acc_imap_password'];
        $this->params['host'] = $params['acc_imap_host'];
        $this->params['version'] = 'Exchange2007_SP1'; 
	}

    static function description() {
        return self::DESCRIPTION;
    }

    function isSecureConnection() {
        return false;
    }

    function login() {
        EWS\Lib::login( $this->params );
        return true;
    }
	function setUserData($_username, $_quota)
	{
		unset($_username, $_quota);	// not used
		return true;
	}
    static function getUIreadonlys() {
        return array();
    }
	public function runOnLogin($func, array $params=array())
	{
		$this->run_on_login[] = array($func, $params);
	}
	public function __call($name,array $params=null)
	{
		if ($this->debug) error_log(__METHOD__.'->'.$name.' with params:'.array2string($params));
		switch($name)
		{
			case 'installScript':
			case 'getScript':
			case 'setActive':
			case 'setEmailNotification':
			case 'getEmailNotification':
			case 'setRules':
			case 'getRules':
			case 'retrieveRules':
			case 'getVacation':
			case 'setVacation':
				if (is_null($this->sieve))
				{
					$this->sieve = new Sieve($this);
					$this->error =& $this->sieve->error;
				}
				$ret = call_user_func_array(array($this->sieve,$name),$params);
				//error_log(__CLASS__.'->'.$name.'('.array2string($params).') returns '.array2string($ret));
				return $ret;
		}
		throw new Api\Exception\WrongParameter("No method '$name' implemented!");
	}

}
