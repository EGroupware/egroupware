<?php
/**
 * EGroupware - Mail - worker class
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage amil
 * @author Stylite AG [info@stylite.de]
 * @copyright (c) 2013-2016 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api;

use Horde_Imap_Client;
use Horde_Imap_Client_Ids;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Data_Fetch;
use Horde_Mime_Part;
use Horde_Imap_Client_Search_Query;
use Horde_Idna;
use Horde_Imap_Client_DateTime;
use Horde_Mime_Headers;
use Horde_Compress;
use Horde_Mime_Magic;
use Horde_Mail_Rfc822;
use Horde_Mail_Rfc822_List;
use Horde_Mime_Mdn;
use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\EWS\Lib;

use tidy;

/**
 * Mail worker class
 *  -provides backend functionality for all classes in Mail
 *  -provides classes that may be used by other apps too
 *
 * @link https://github.com/horde/horde/blob/master/imp/lib/Contents.php
 */
class Mail_EWS extends Mail
{
	protected function __construct($_displayCharset='utf-8',$_restoreSession=true, $_profileID=0, $_oldImapServerObject=false, $_reuseCache=null)
	{
		if (is_null($_reuseCache)) $_reuseCache = $_restoreSession;
		if (!empty($_displayCharset)) self::$displayCharset = $_displayCharset;
		// not nummeric, we assume we only want an empty class object
		if (!is_numeric($_profileID)) return true;
		if ($_restoreSession)
		{
			//error_log(__METHOD__." Session restore ".function_backtrace());
			$this->restoreSessionData();
			$lv_mailbox = $this->sessionData['mailbox'];
			$firstMessage = $this->sessionData['previewMessage'];
		}
		else
		{
			$this->restoreSessionData();
			$lv_mailbox = $this->sessionData['mailbox'];
			$firstMessage = $this->sessionData['previewMessage'];
			$this->sessionData = array();
		}
		if (!$_reuseCache) $this->forcePrefReload($_profileID,!$_reuseCache);
		try
		{
			$this->profileID = self::validateProfileID($_profileID);
			$this->accountid	= $GLOBALS['egw_info']['user']['account_id'];

			//error_log(__METHOD__.' ('.__LINE__.') '." ProfileID ".$this->profileID.' called from:'.function_backtrace());
			$acc = Mail\Account::read($this->profileID);
		}
		catch (\Exception $e)
		{
			throw new Exception(__METHOD__." failed to instanciate Mail for $_profileID / ".$this->profileID." with error:".$e->getMessage());
		}
		//error_log(__METHOD__.' ('.__LINE__.') '.array2string($acc->imapServer()));
		$this->icServer = ($_oldImapServerObject?$acc->oldImapServer():$acc->imapServer());
		$this->ogServer = $acc->smtpServer();
		// TODO: merge mailprefs into userprefs, for easy treatment
		$this->mailPreferences = $GLOBALS['egw_info']['user']['preferences']['mail'];
		$this->htmlOptions  = $this->mailPreferences['htmlOptions'];
		if (isset($this->icServer->ImapServerId) && !empty($this->icServer->ImapServerId))
		{
			$_profileID = $this->profileID = $GLOBALS['egw_info']['user']['preferences']['mail']['ActiveProfileID'] = $this->icServer->ImapServerId;
		}

		if (is_null(self::$mailConfig)) self::$mailConfig = Config::read('mail');
	}
	function getFolderArrays ($_nodePath = null, $_onlyTopLevel = false, $_search= 2, $_subscribedOnly = false, $_getCounter = false) {
		$efolders = Lib::getTreeFolders( $this->profileID );
		$foldersList = array();
		foreach ( $efolders as $folder ) {
			$foldersList[ $folder['name'] ] = array(
				'MAILBOX'	=>	$folder['name'] ,
				'ATTRIBUTES'	=>	array(
					'\\hasChildren', '\\subscribed', 
				),
				'ID' => $folder['id'],
				'CAN_DELETE' => $folder['delete'],
				'delimiter'	=> '/',
				'SUBSCRIBED' =>	1,
				'counter' => array(
					'MESSAGES' => '1',
                    'RECENT' => '0',
                    'UIDNEXT' => '2',
                    'UIDVALIDITY' => '1465840832',
                    'UNSEEN' => '0',
                )
            );				
		}
		//error_log(__METHOD__."(".print_r($foldersList, true).")");
		return $foldersList;
	}	
	function getJunkFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getDraftFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getTemplateFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getTrashFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getSentFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getOutboxFolder($_checkexistance=TRUE)
	{
        return false;
	}
	function getArchiveFolder($_checkexistance=TRUE)
	{
        return false;
	}
}
