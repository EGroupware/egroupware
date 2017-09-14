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
use EGroupware\Api\Mail\EWS\Lib;

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
	var $ImapServerId;
    var $params;

	function __construct(array $params, $_timeout=null)
	{
		$this->ImapServerId = $params['acc_id'];
        $this->params['username'] = $params['acc_imap_username'];
        $this->params['password'] = $params['acc_imap_password'];
        $this->params['host'] = $params['acc_imap_host'];
        $this->params['version'] = 'Exchange2007_SP1'; 
	}
	function getCurrentMailbox()
	{
        return Lib::getDefaultFolder( $this->ImapServerId );
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
                break;
			case 'subscribeMailbox':
			case 'search':
                return;
                break;
		}
		throw new Api\Exception\WrongParameter("No method '$name' implemented!");
	}
	function hasCapability($capability)
	{
        return true;
        //TEMPORARY
		if ($capability=='SUPPORTS_KEYWORDS')
		{
			// if pseudo-flag is not set, call examineMailbox now to set it (no STATUS_ALL = counters necessary)
			if (!isset(self::$supports_keywords[$this->ImapServerId]))
			{
				try
				{
					$this->examineMailbox('INBOX', Horde_Imap_Client::STATUS_FLAGS|Horde_Imap_Client::STATUS_PERMFLAGS);
				}
				catch (\Exception $e)
				{
					error_log(__METHOD__.__LINE__.' (examineServer for detection) '.$capability.'->'.array2string(self::$supports_keywords).' failed '.function_backtrace());
					self::$supports_keywords[$this->ImapServerId]=false;
				}
			}
			//error_log(__METHOD__.__LINE__.' '.$capability.'->'.array2string(self::$supports_keywords).' '.function_backtrace());
			return self::$supports_keywords[$this->ImapServerId];
		}
		try
		{
			$cap = $this->capability();
		}
		catch (\Exception $e)
		{
			if ($this->debug) error_log(__METHOD__.__LINE__.' error querying for capability:'.$capability.' ->'.$e->getMessage());
			return false;
		}
		if (!is_array($cap))
		{
			error_log(__METHOD__.__LINE__.' error querying for capability:'.$capability.' Expected array but got->'.array2string($cap));
			return false;
		}
		foreach ($cap as $c => $v)
		{
			if (is_array($v))
			{
				foreach ($v as $v)
				{
					$cap[$c.'='.$v] = true;
				}
			}
		}
		//error_log(__METHOD__.__LINE__.$capability.'->'.array2string($cap));
		if (isset($cap[$capability]) && $cap[$capability])
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	function mailboxExist($mailbox)
	{
        //TODO
        return true;
		try
		{
			//error_log(__METHOD__.__LINE__.':'.$mailbox);
			$currentMailbox = $this->currentMailbox();
		}
		catch(\Exception $e)
		{
			//error_log(__METHOD__.__LINE__.' failed detecting currentMailbox:'.$currentMailbox.':'.$e->getMessage());
			$currentMailbox=null;
			unset($e);
		}
		try
		{
			//error_log(__METHOD__.__LINE__.':'.$mailbox);
			$this->openMailbox($mailbox);
			$returnvalue=true;
		}
		catch(\Exception $e)
		{
			//error_log(__METHOD__.__LINE__.' failed opening:'.$mailbox.':'.$e->getMessage().' Called by:'.function_backtrace());
			unset($e);
			$returnvalue=false;
		}
		if (!empty($currentMailbox) && $currentMailbox['mailbox'] != $mailbox)
		{
			try
			{
				//error_log(__METHOD__.__LINE__.':'.$currentMailbox .'<->'.$mailbox);
				$this->openMailbox($currentMailbox['mailbox']);
			}
			catch(\Exception $e)
			{
				//error_log(__METHOD__.__LINE__.' failed reopening:'.$currentMailbox.':'.$e->getMessage());
				unset($e);
			}
		}
		return $returnvalue;
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
	function examineMailbox($mailbox, $flags=null)
	{
		return false;
	}
	function getDelimiter($_type=1)
	{
		return "/";
	}
	function getNameSpaceArray()
	{
        return array();
	}
	function getStorageQuotaRoot($mailboxName)
	{
		return false;
	}

}
