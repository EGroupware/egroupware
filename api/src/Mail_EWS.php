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
use EGroupware\Api\Mail\EWS;
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

            $acc = Mail\Account::read($this->profileID);
        }
        catch (\Exception $e)
        {
            throw new Exception(__METHOD__." failed to instanciate Mail for $_profileID / ".$this->profileID." with error:".$e->getMessage());
        }
        $this->icServer = ($_oldImapServerObject?$acc->oldImapServer():$acc->imapServer());
        $this->ogServer = $acc->smtpServer();
        // TODO: merge mailprefs into userprefs, for easy treatment
        $this->mailPreferences = $GLOBALS['egw_info']['user']['preferences']['mail'];
        $this->htmlOptions  = $this->mailPreferences['htmlOptions'];
        if (isset($this->icServer->ImapServerId) && !empty($this->icServer->ImapServerId))
        {
            $_profileID = $this->profileID = $this->icServer->ImapServerId;
        }

        if (is_null(self::$mailConfig)) self::$mailConfig = Config::read('mail');
    }
	function appendMessage($_folderId, $_header, $_body, $_flags='\\Recent')
	{
        $folderId = $this->getFolderId( $_folderId );
        // After Message is Sent, store mail (contained as stream in _header) to 'Sent Folder' (from config) _folderId
        $raw = stream_get_contents( $_header );
        $mime = base64_encode( $raw );

        return Lib::createMail( $this->profileID, $folderId, $mime );
	}
    function deleteMessages($_messageUID, $_folder=NULL, $_forceDeleteMethod='no') {
        // Delete messages after checking for specitic folder permissions
        if ( !$_folder || !$_messageUID ) return;
        

        $folderID = $this->getFolderId( $_folder );
        $methodMap = array(
            'no' => 'MoveToDeletedItems',
            'mark_as_deleted' => 'SoftDelete',
            'remove_immediately' => 'HardDelete',
            'move_to_trash' => 'MoveToDeletedItems',
        );

        if ( $_messageUID == 'all' ) 
            $_messageUID = Lib::getMailIds( $this->profileID, $folderID );

        $messages = '';
        foreach( $_messageUID as $message ) {
            list($mailID, $changeKey) = explode( '||', $message );
            $allowed = Lib::can_delete( $this->profileID, $folderID );
            if ( !$allowed )
                $messages .= "No Permissions";


            // Check for external actions before deleting email
            $validations = Api\Hooks::process(array(
                'location' => 'mail_before_delete',
                'profile' => $this->profileID,
                'folder' => $folderID,
                'message' => $mailID,
            ));
            if ( is_array( $validations ) ) {
                foreach ( $validations as $app => $validation) {
                    $allowed = $allowed && $validation['allowed'];
                    if ( !$allowed )
                        $messages .= $validation['messages'];
                }
            }
            $method = $methodMap[ $_forceDeleteMethod ];

            // If in Trash folder, hard Delete
            if ( $_folder == $this->getTrashFolder() )
                $method = 'HardDelete'; 

            if ( !$allowed )
				throw new Exception("Deleting Mail failed! $messages");

            try {
                if ( $method == 'MoveToDeletedItems' && $this->getTrashFolder() )
                    Lib::moveMail( $this->profileID, $mailID, $changeKey, $this->getTrashFolder() );
                else
                    Lib::DeleteMail( $this->profileID, $mailID, $method );
            }
            catch (\Exception $e ) {
				throw new Exception("Deleting Mail failed! Error:".$e->getMessage());
            }

        }

        return true;

    }
    function flagMessages($_flag, $_messageUID,$_folder=NULL) {
        // Flag messages as read/unread. Other flags are not implemented
        $messages = '';
        if ( !is_array( $_messageUID ) ) $_messageUID = array( $_messageUID );
        foreach( $_messageUID as $message ) {
            list($mailID, $changeKey) = explode( '||', $message );
            if ( $_flag == 'read' ) 
                $messages .= Lib::setRead( $this->profileID, $mailID, $changeKey, true );
            else if ( $_flag == 'unread' ) 
                $messages .= Lib::setRead( $this->profileID, $mailID, $changeKey, false );
            /* else */
            /*     throw new Exception("Operation '$_flag' not supported for EWS"); */
        }

        return $messages;
    }
	function getAttachments($mailID )
	{
        // Get attachments for mailID excluding Embedded images
        $attachments = $this->getAllAttachments( $mailID );
        $only = array();
        foreach ( $attachments as $attachment )
            if ( !$attachment['cid'] ) $only[] = $attachment;

        return $only;
	}
    function getAllAttachments( $mailID ) {
        // Get All attachments for mailID, including Embedded images
        $mailID  = str_replace(' ','+', $mailID );
        $email = Lib::getMailBody( $this->profileID, $mailID );

        // Format data (one or many)
        $files = array();
        $items = array();
        if ( $email->Attachments->FileAttachment )
            $files = ( is_array( $email->Attachments->FileAttachment ) ? $email->Attachments->FileAttachment : array( $email->Attachments->FileAttachment ) );
        if ( $email->Attachments->ItemAttachment )
            $items = ( is_array( $email->Attachments->ItemAttachment ) ? $email->Attachments->ItemAttachment : array( $email->Attachments->ItemAttachment ) );
        $data = array_merge( $files, $items );

        // Format for use in header
        $attachments = array();
        foreach ( $data as $attachment ) {
            // Double check: search email for CID in case ContentId is set, but doesn't exist in body
            $cid = '';
            if ( $attachment->ContentId && strpos($email->Body->_, $attachment->ContentId) !== FALSE ) 
                $cid = $attachment->ContentId;

            $attachments[] = array(
                'size' => ( $attachment->Size ? $attachment->Size : 0 ),
                'filename' => $attachment->Name,
                'type' => $attachment->ContentType,
                'mimeType' => $attachment->ContentType,
                'uid' => $attachment->AttachmentId->Id,
                'cid' => $cid,
                'partID' => $attachment->AttachmentId->Id,
                'name' => $attachment->Name,
                'folder' => 'foo',
            );
        }
        return $attachments;
    }
	function getAttachment($_uid, $_partID, $_winmail_nr=0, $_returnPart=true, $_stream=false, $_folder=null)
	{
        // Get attachment and its contents, return as data or stream
        $_partID = str_replace(' ','+', $_partID );
		$attachment = Lib::getAttachment( $this->profileID, $_partID );

        if ( $_stream ) {
            $tmp = fopen('php://temp', 'w+');

            if (!is_null($attachment->Content)) {
                fwrite($tmp, $attachment->Content);
                rewind($tmp);
            }
            $content = $tmp;
        }
        else {
            $content = $attachment->Content;
        }

		return array(
            'type' => $attachment->ContentType,
            'charset' => '',
            'filename' => $attachment->Name,
            'attachment' => $content,
        );		
	}
    function getAttachmentByCID($_uid, $_cid, $_part, $_stream=null) {
        // Get embedded
        list($mailID,$changeKey) = explode( '||', $_uid );
        $mailID  = str_replace(' ','+', $mailID );
        $attachments = self::getAllAttachments( $mailID );

        $final = new EWS\Attachment();
        foreach( $attachments as $attachment ) {
            if ( $attachment['cid'] == $_cid ) {
                $full_attach = self::getAttachment( $_uid, $attachment['partID'], 0, true, $_stream );
                $final->loadAttachment( $full_attach );
            }
        }

        return $final;
    }
    function getFolderArrays ($_nodePath = null, $_onlyTopLevel = false, $_search= 2, $_subscribedOnly = false, $_getCounter = false) {
        // Get Folder Tree to display. Used in mail_tree    
        $efolders = Lib::getTreeFolders( $this->profileID );
        $foldersList = array();
        $ids = array();
        foreach ( $efolders as $folder ) {
            $ids[ $folder['name'] ] = $folder['id'];
            $foldersList[ $folder['name'] ] = array(
                'MAILBOX'	=>	$folder['name'] ,
                'ATTRIBUTES'	=>	array(
                    '\\hasChildren', '\\subscribed', 
                ),
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
        Api\Cache::setSession('mail', 'ews_folder_ids', $ids );
        return $foldersList;
    }	
    function getFolderStatus($_folderName,$ignoreStatusCache=false,$basicInfoOnly=false,$fetchSubscribedInfo=true) {
        // Get Folder status (read/unread)
        $folderId = $this->getFolderId( $_folderName );
        $folder = Lib::getFolder( $this->profileID, $folderId );

        return array(
            'displayName' => $folder->DisplayName,
            'shortName' => $folder->DisplayName,
            'shortDisplayName' => $folder->DisplayName,
            'messages' => $folder->TotalCount,
            'unseen' => $folder->UnreadCount,
            /* 'uidnext' => 129, */
            /* 'uidvalidity' => 1, */
            /* 'recent' => 0, */
            /* 'subscribed' => '', */
            /* 'delimiter' => '/', */
        );
    }
    function getHeaders($_folderName, $_startMessage, $_numberOfMessages, $_sort, $_reverse, $_filter, $_thisUIDOnly=null, $_cacheResult=true, $_fetchPreviews=false) 
    {
        // Get All mails in specific folder

        // Get default folder if none
        if ( !$_folderName ) 
        	$_folderName = Lib::getDefaultFolder( $this->profileID );

        $folderID = $this->getFolderId( $_folderName );

        if ( !$_sort )
            $_sort = 'date';

        $sort_map = array(
            'subject'		=> 'item:Subject',
            'size'			=> 'item:Size',
            'date'			=> 'item:DateTimeSent',
            'arrival'	    => 'item:DateTimeCreated',
            'uid'			=> 'item:ItemId',
            'attachments'	=> 'item:HasAttachments',
            'seen'			=> 'item:IsRead',
            'toaddress'	    => 'item:DisplayTo',
            'fromaddress'	=> 'message:From',
            'address'	    => 'message:From',
        );
        $sort = array(
            'order' => $sort_map[ $_sort ],
            'sort' => 'Descending',
        );
        $_startMessage--;

        $array = Lib::getMails( $this->profileID, $folderID, $_startMessage, $_numberOfMessages, $sort, $_filter );
        if ( empty( $array['rows'] ) )
            return array();

        $emails = array();
        foreach ( $array['rows'] as $index => &$email ) {
            if ( !$email->HasAttachments ) {
                $attachments = array();
            }
            else {
                // For emails with attachment, do extra request to get attachment info
                $attachments = $this->getAttachments( $email->ItemId->Id );
            }
            // Set fields
            // Appended ChangeKey to ID with default delimiter. This means that uid is still recognized since array is exploded,
            // and in specific cases ChangeKey is available for use (eg. set read/unread )

            // Convert datetimes
            $properties = array( 'DateTimeSent', 'DateTimeCreated');
            $zone = new \DateTimeZone( $GLOBALS['egw_info']['server']['server_timezone'] );
            foreach ( $properties as $property ) {
                $date = new \DateTime($email->$property);    		
                $date->setTimezone($zone);
                $email->$property = $date->format('c');	
            }			

            $emails[] = array(
                'subject' => $email->Subject,
                'size' => $email->Size,
                'date' => $email->DateTimeSent,
                'internaldate' => $email->DateTimeCreated,
                'mimetype' => 'multipart/alternative',
                'uid' => $email->ItemId->Id .'||'. $email->ItemId->ChangeKey,
                'attachments' => $attachments,
                // 'uid' => "e$index",
                'priority' => 3,
                'recent' => '',
                'flagged' => '',
                'answered' => '',
                'forwarded' => '',
                'deleted' => '',
                'seen' => $email->IsRead,
                'draft' => '',
                'mdnsent' => '',
                'mdnnotsent' => '',
                'label1' => '',
                'label2' => '',
                'label3' => '',
                'label4' => '',
                'label5' => '',
                'sender_address' => $email->From->Mailbox->Name,
                'to_address' => $email->DisplayTo .' <@>',
                'cc_addresses' => array( $email->DisplayCc ),
            );
            // We add '@', since without it the Name is not displayed
            // We don't have the actual email address because EWS fetches that only when it fetches the actual email
            // EGW fetches the email headers altogether in get_rows, even though in preview you just see the current mail
        }

        return array( 'header' => $emails, 'info' => array( 'total' => $array['count'] ) );
    }
	function getMessageAttachments($_uid, $_partID=null, Horde_Mime_Part $_structure=null, $fetchEmbeddedImages=true, $fetchTextCalendar=false, $resolveTNEF=true, $_folder='')
	{
        // Get all attachments for message 

        $_uid = str_replace(' ','+', $_uid );
        list($mailID,) = explode('||', $_uid);
        $attachments = $this->getAllAttachments( $mailID );
        $only = array();
        foreach ( $attachments as $attachment )
            if ( !$attachment['cid'] ) $only[] = $attachment;

        return $only;
	}
    function getMessageBody($_uid, $_htmlOptions='', $_partID=null, Horde_Mime_Part $_structure=null, $_preserveSeen = false, $_folder = '', &$calendar_part=null)
    {
        // Get Message Body

        $_uid = str_replace(' ','+', $_uid );
        list($mailID,) = explode('||', $_uid);

        $email = Lib::getMailBody( $this->profileID, $mailID );
        return array( array(
            'body'		=> $email->Body->_,
            // 'mimeType'		=> 'text/html',
            'mimeType'	=> ($email->Body->BodyType == 'HTML' ? 'text/html' : 'text/plain'),
            'charSet'	=> 'utf-8',
        ));
    }
	function getMessageRawBody($_uid, $_partID = '', $_folder='', $_stream=false)
	{
        // Get Raw message (eml)
        $_uid = str_replace(' ','+', $_uid );
        list($mailID,) = explode('||', $_uid);

        $raw = Lib::getMailRaw( $this->profileID, $mailID );

        if ( $_stream ) {
            $tmp = fopen('php://temp', 'w+');

            if (!is_null($raw)) {
                fwrite($tmp, $raw);
                rewind($tmp);
            }
            $message = $tmp;
        }
        else
            $message = $raw;

		return $message;
	}
    function getMessageRawHeader($_uid, $_partID = '', $_folder = '')
    {
        // Show only First part of raw message, before line break
        $body = $this->getMessageRawBody( $_uid, $_partID, $_folder );

        $br = strpos( $body, "\r\n\r\n" );
        if ( $br )
            $body = substr( $body, 0, $br );

        return $body;
    }
    function getMessageEnvelope($_uid, $_partID = '',$decode=false, $_folder='', $_useHeaderInsteadOfEnvelope=false)
    {
        // Get Mail and Headers

        $_uid = str_replace(' ','+', $_uid );
        list($mailID,) = explode('||', $_uid);

        $email = Lib::getMailBody( $this->profileID, $mailID );
        $arrays = array( 'From', 'ToRecipients', 'CcRecipients', 'Sender' );
        $addresses = array();
        foreach ( $arrays as $property ) {
            $mailboxes = ( is_array( $email->$property->Mailbox ) ? $email->$property->Mailbox : array( $email->$property->Mailbox ) );

            $tmp = array();
            foreach ( $mailboxes as $mailbox ) {
                $tmp[] = $mailbox->Name .' <'. $mailbox->EmailAddress .'>';
            }
            $addresses[ $property ] = $tmp;
        }

        // Convert datetimes
        $properties = array( 'DateTimeReceived' );
        $zone = new \DateTimeZone( $GLOBALS['egw_info']['server']['server_timezone'] );
        foreach ( $properties as $property ) {
            $date = new \DateTime($email->$property);    		
            $date->setTimezone($zone);
            $email->$property = $date->format('c');	
        }

        return array(
            'SENDER' => $addresses['Sender'],
            'TO' => $addresses['ToRecipients'],
            'FROM' => $addresses['From'],
            'CC' => $addresses['CcRecipients'],
            'BCC' => Array(),
            'REPLY-TO' => Array(),
            'DATE' => $email->DateTimeReceived,
            'MESSAGE-ID' => $email->InternetMessageId,
            'IN-REPLY-TO' => '',
            'REFERENCES' => '',
            'SUBJECT' => $email->Subject,
            'CONTENT-MD5' => '',
            'MIME-VERSION' => '',
            'CONTENT-TYPE' => '',
            'CONTENT-TRANSFER-ENCODING' => '',
            'CONTENT-ID' => '',
            'CONTENT-DESCRIPTION' => '',
            'CONTENT-BASE' => '',
            'CONTENT-DISPOSITION' => '',
            'CONTENT-DURATION' => '',
            'CONTENT-LOCATION' => '',
            'CONTENT-FEATURES' => '',
            'CONTENT-LANGUAGE' => '',
            'CONTENT-ALTERNATIVE' => '',
            'IMPORTANCE' => $email->Importance,
            'X-PRIORITY' => '',
            'LIST-HELP' => '',
            'LIST-UNSUBSCRIBE' => '',
            'LIST-SUBSCRIBE' => '',
            'LIST-OWNER' => '',
            'LIST-POST' => '',
            'LIST-ARCHIVE' => '',
            'LIST-ID' => '',
            'BODY' => $email->Body->_,
        );
    }
    function getSortedList($_folderName, $_sort, &$_reverse, $_filter, &$resultByUid=true, $setSession=true)
    {
        // Get All mails in specific folder
        $folderID = $this->getFolderId( $_folderName );

        $sort_map = array(
            'subject'		=> 'item:Subject',
            'size'			=> 'item:Size',
            'date'			=> 'item:DateTimeSent',
            'arrival'	    => 'item:DateTimeCreated',
            'uid'			=> 'item:ItemId',
            'attachments'	=> 'item:HasAttachments',
            'seen'			=> 'item:IsRead',
            'toaddress'	    => 'item:DisplayTo',
            'fromaddress'	=> 'message:From',
            'address'	    => 'message:From',
        );
        $sort = array(
            'order' => $sort_map[ $_sort ],
            'sort' => 'Descending',
        );
        if ( !$sort['order'] )
            $sort['order'] = 'item:DateTimeCreated';

        $_startMessage--;

        $array = Lib::getMails( $this->profileID, $folderID, 0, 999999, $sort, $_filter, true );

        if ( empty( $array['rows'] ) )
            return array();

        $emails = array();
        foreach ( $array['rows'] as $email ) 
            $emails[] = $email->ItemId->Id;

        return array( 'match' => $emails, 'header' => $emails, 'info' => array( 'total' => $array['count'] ) );
    }
    function getDefaultFolder() {
        return $this->profileID.self::DELIMITER.Lib::getDefaultFolder( $this->profileID );
    }
    function moveMessages($_foldername, $_messageUID, $deleteAfterMove=true, $currentFolder = Null, $returnUIDs = false, $_sourceProfileID = Null, $_targetProfileID = Null)
    {
        $folderID = $this->getFolderId( $_foldername );
        $curfolderID = $this->getFolderId( $currentFolder );

        // Check permissions 
        if ( !Lib::can_move( $this->profileID, $curfolderID, $folderID ) )
            throw new Exception("Operation not allowed, not enough permissions.");

        foreach( $_messageUID as $message ) {
            list($mailID, $changeKey) = explode( '||', $message );
            try {
                Lib::moveMail( $this->profileID, $mailID, $changeKey, $folderID, $deleteAfterMove );
            }
            catch (\Exception $e ) {
                $operation = ( $deleteAfterMove ? 'Moving' : 'Copying' );
                throw new Exception("$operation to Folder $_foldername failed! Error:".$e->getMessage());
            }
        }

        return true;
    }
	function createFolder($_parent, $_folderName, &$_error)
	{
        $parentID = $this->getFolderId( $_parent );

        if ( $parentID && !Lib::can_manage_folder( $this->profileID, $parentID ) )
            throw new Exception("Operation not allowed, not enough permissions.");

        try{
            Lib::createFolder( $this->profileID, $parentID, $_folderName );
        }
        catch (\Exception $e ) {
            throw new Exception( $e->getMessage() );
        }

		return $_folderName;
	}
	function deleteFolder($_folderName)
	{
        $folderID = $this->getFolderId( $_folderName );
        if ( $folderID && !Lib::can_manage_folder( $this->profileID, $folderID ) )
            throw new Exception("Operation not allowed, not enough permissions.");

        if ( $folderID ) {
            try{
                Lib::deleteFolder( $this->profileID, $folderID );
            }
            catch (\Exception $e ) {
				throw new Exception( $e->getMessage() );
            }
        }

		return true;
	}
	function renameFolder($_oldFolderName, $_parent, $_folderName)
	{
        // renameFolder gets called both for moving and renaming 

        // Check which operation we need to perform
        $oldParent = null;
        $oldName = $_oldFolderName;
        if ( strpos( $_oldFolderName, '/' ) !== FALSE ) {
            $path = explode('/', $_oldFolderName);
            $oldName = array_pop( $path );
            $oldParent = implode('/', $path );
        }

        if ( $oldParent != $_parent ) {
            // Move
            try {
                $folderID = $this->getFolderId( $_oldFolderName );
                $parentID = $this->getFolderId( $_parent );

                if ( !Lib::can_manage_folder( $this->profileID, $folderID ) || !Lib::can_manage_folder( $this->profileID, $parentID ) )
                    throw new Exception("Operation not allowed, not enough permissions.");

                Lib::moveFolder( $this->profileID, $folderID, $parentID );
            }
            catch (\Exception $e ) {
				throw new Exception( $e->getMessage() );
            }
        }
        else if ( $oldName != $_folderName ) {
            // Rename

            // Get Folder to find its changeKey
            $folderID = $this->getFolderId( $_oldFolderName );
            $folder = Lib::getFolder( $this->profileID, $folderID, true );
            $changeKey = $folder->FolderId->ChangeKey;

                if ( !Lib::can_manage_folder( $this->profileID, $folderID ) )
                    throw new Exception("Operation not allowed, not enough permissions.");

            // Rename Folder
            try {
                Lib::renameFolder( $this->profileID, $folderID, $changeKey, $_folderName );
            }
            catch (\Exception $e ) {
				throw new Exception( $e->getMessage() );
            }
        }
        
        $newFolderName = ( $_parent ? "$_parent/$_folderName" : $_folderName );

        // Update Session
        $ids = Api\Cache::getSession('mail', 'ews_folder_ids' );
        unset( $ids[ $_oldFolderName ] );
        $ids[ $newFolderName ] = $folderID;
        Api\Cache::setSession('mail', 'ews_folder_ids', $ids );

        // Update DB
        Lib::renameFolderDB( $this->profileID, $folderID, $newFolderName );

		return $newFolderName;
	}
    static function getFolderPermissions( $profile_id ) {
        // From Lib
        if ( !$profile_id ) return array();
        $folders = Lib::getSettingsFolders( $profile_id );

        // From Db
        $rows = Lib::getDBFolders( $profile_id );
        $final = array('');
        $used = array();
        foreach( $rows as $row) {
            if ( $row['ews_permissions'] ) {
                $permissions = unserialize( $row['ews_permissions'] );
                foreach( $permissions as $permission => $value )
                    $row[ $permission ] = $value;
            }
            $row['ews_is_default'] = (int) $row['ews_is_default'];
            $row['ews_apply_permissions'] = (int) $row['ews_apply_permissions'];
            $row['ews_move_anywhere'] = (int) $row['ews_move_anywhere'];
            $row['ews_move_to'] = explode(',', $row['ews_move_to'] );
            $final[] = $row;
            $used[] = $row['ews_folder'];
        }

        // Consolidate
        foreach( $folders as $folder ) {
            if ( !in_array( $folder['id'], $used ) ) 
                $final[] = array(
                    'ews_folder' => $folder['id'],
                    'ews_name' => $folder['name'],
                );
        }
        
        return $final;
    }
    static function getFolderPermissionsSelOptions( $profile_id ) {
        // From Lib
        if ( !$profile_id ) return array();
        $folders = Lib::getSettingsFolders( $profile_id );

        $normalize = array();
        foreach( $folders as $folder )
            $normalize[ $folder['id'] ] = $folder['name'];

        $sel_options = array('');
        foreach( $folders as $folder )
            $sel_options[]['ews_move_to'] = $normalize;

        return $sel_options;
    }
    static function storeFolderPermissions( $content, $profile_id ) {
        if ( !$profile_id ) return ;
        $db = clone( $GLOBALS['egw']->db );
        $sql = "DELETE FROM egw_ea_ews WHERE ews_profile=$profile_id";
        $db->query($sql);    

        $fields = array( 'read', 'write', 'delete', 'manage_folder' );
        foreach ( $content as $folder ) {
            if (!$folder || !$folder['ews_folder']) continue;

            $permissions = array();
            foreach( $fields as $field ) 
                $permissions[ $field ] = $folder[ $field ];


            $obj = new Api\Storage\Base('api','egw_ea_ews',null);
            $folder['ews_profile'] = $profile_id;
            $folder['ews_permissions'] = serialize( $permissions );
            if ( $folder['ews_move_to'] )
                $folder['ews_move_to'] = implode(',', $folder['ews_move_to'] );
            $db->insert( 'egw_ea_ews', $folder, false, __LINE__, __FILE__ );
        }
        return true;
    }
    
    function getFolderId( $_folderName ) {
        $ids = Api\Cache::getSession('mail', 'ews_folder_ids' );
        $folderID = ( $ids[ $_folderName ] ? $ids[ $_folderName ] : $_folderName );
        $folderID  = str_replace(' ','+', $folderID );

        return $folderID;
    }
    function getJunkFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_junk');
    }
    function getDraftFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_draft');
    }
    function getTemplateFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_template');
    }
    function getTrashFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_trash');
    }
    function getSentFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_sent');
    }
    function getOutboxFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_sent');
    }
    function getArchiveFolder($_checkexistance=TRUE)
    {
        return $this->getSpecialFolder('acc_folder_archive');
    }
    function getSpecialFolder( $folder ) {
        $acc = Mail\Account::read($this->profileID);
        return $acc->params[ $folder ];
    }

    // Empty functions to comply with Api\Mail
    function reopen($_foldername)
    {
        return true;
    }
    function closeConnection()
    {
        return true;
    }
    function getStructure($_uid, $_partID=null, $_folder=null, $_preserveSeen=false)
    {
        return new Horde_Mime_Part();
    }
    function getMessageHeader($_uid, $_partID = '',$decode=false, $preserveUnSeen=false, $_folder='')
    {
        return array();
    }
    function getFlags ($_messageUID) 
    {
        return null;
    }
}

