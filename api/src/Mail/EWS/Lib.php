<?php
/**
 * fkar
 * Library to communicate with MS Exchange Public Folders via PHP EWS
 *
 */

namespace EGroupware\Api\Mail\EWS;
use EGroupware\Api\Mail;
use PhpEws\EwsConnection;
use PhpEws\DataType as DT;

class Lib
{
    static $info;

    static function login( $params ) {
        $ews = new EwsConnection( $params['host'], $params['username'], $params['password'], $params['version'] );
        try {
            $folders = self::getAllFolders( $ews );
        }
        catch (\SoapFault $e) {
            throw new \Exception('Authentication Failed');
        }
    }
    static function init( $profile ) {

        // Load credentials from db if needed
        if ( !isset( self::$info ) ) {
            $account = Mail\Account::read( $profile );

            $info = array(
                'exchange_user' => $account->params['acc_imap_username'],
                'exchange_host' => $account->params['acc_imap_host'],
                'exchange_password' => $account->params['acc_imap_password'],
                'exchange_version' => 'Exchange2007_SP1',
            );

            self::$info = $info;
        }

        extract( self::$info );

        return new EwsConnection(
            $exchange_host, 
            $exchange_user, 
            $exchange_password, 
            $exchange_version
        );
    }

    static function setRead( $profile, $mailID, $changeKey, $read = true ) {		
        $ews = self::init( $profile );

        $request = new DT\UpdateItemType();
        $request->ConflictResolution = "AutoResolve";
        $request->MessageDisposition = "SaveOnly";	    

        $request->ItemChanges = new DT\NonEmptyArrayOfItemChangesType();

        $itemChange = new DT\ItemChangeType();
        $itemChange->ItemId = new DT\ItemIdType();
        $itemChange->ItemId->Id = $mailID;
        $itemChange->ItemId->ChangeKey = $changeKey;
        $itemChange->Updates = new DT\NonEmptyArrayOfItemChangeDescriptionsType();

        $set = new DT\SetItemFieldType();
        $set->FieldURI = new DT\PathToUnindexedFieldType;
        $set->FieldURI->FieldURI = 'message:IsRead';
        $set->Message = new DT\MessageType();
        $set->Message->IsRead = $read;
        $itemChange->Updates->SetItemField = array( $set );

        $request->ItemChanges->ItemChange = array( $itemChange );

        $response = $ews->UpdateItem($request);

        return $response->ResponseMessages->UpdateItemResponseMessage->ResponseClass == 'Success';
    }

    static function getAttachment( $profile, $attachmentID ) {
        $ews = self::init( $profile );

        $request = new DT\GetAttachmentType();
        $request -> AttachmentIds = new DT\NonEmptyArrayOfRequestAttachmentIdsType();
        $request -> AttachmentIds -> AttachmentId = new DT\RequestAttachmentIdType();
        $request->AttachmentIds->AttachmentId->Id = $attachmentID;
        $response = $ews->GetAttachment($request); 

        $attachment = $response->ResponseMessages->GetAttachmentResponseMessage->Attachments->FileAttachment;

        return $attachment;
    }

    static function getMailBody( $profile, $emailID ) {
        $ews = self::init( $profile );

        $request = new DT\GetItemType();
        $request->ItemShape = new DT\ItemResponseShapeType();
        $request->ItemShape->BaseShape = DT\DefaultShapeNamesType::ALL_PROPERTIES;
        // $request->ItemShape->BodyType = BodyTypeResponseType::TEXT;

        $body_property = new DT\PathToUnindexedFieldType();
        $body_property->FieldURI = 'item:Body';
        $request->ItemShape->AdditionalProperties = new DT\NonEmptyArrayOfPathsToElementType();
        $request->ItemShape->AdditionalProperties->FieldURI = array($body_property);
        $request->ItemIds = new DT\NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = array();

        $message_item = new DT\ItemIdType();
        $message_item->Id = $emailID;
        $request->ItemIds->ItemId[] = $message_item;

        try {
            $response = $ews->GetItem($request);	
        }
        catch (Exception $e) {
            // Unknown error with some newsletters. Fall back to text
            if ( $e->getMessage() == 'looks like we got no XML document') {
                $request->ItemShape->BodyType = DT\BodyTypeResponseType::TEXT;	    		
                try {
                    $response = $ews->GetItem($request);	
                }
                catch (Exception $e) {
                    error_log( $e->getMessage() );
                    error_log( "Error caused by Mail Id: $emailID" );
                    return false;
                }
            }
        }


        $msg = $response->ResponseMessages->GetItemResponseMessage->Items->Message;

        return $msg;
    }

    static function getMailRaw( $profile, $emailID ) {
        $ews = self::init( $profile );

        $request = new DT\GetItemType();
        $request->ItemShape = new DT\ItemResponseShapeType();
        $request->ItemShape->BaseShape = DT\DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ItemShape->IncludeMimeContent = true;

        $request->ItemIds = new DT\NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = new DT\ItemIdType();
        $request->ItemIds->ItemId->Id = $emailID;

        try {
            $response = $ews->GetItem($request);	
        }
        catch (Exception $e) {
            // Unknown error with some newsletters. Fall back to text
            if ( $e->getMessage() == 'looks like we got no XML document') {
                $request->ItemShape->BodyType = DT\BodyTypeResponseType::TEXT;	    		
                $response = $ews->GetItem($request);	
            }
        }

        $response = $response->ResponseMessages->GetItemResponseMessage;
        if ($response->ResponseCode == 'NoError' &&
            $response->ResponseClass == 'Success') {
            return $response->Items->Message->MimeContent->_;
        }
        else
            return '';
    }

    static function getMails( $profile, $folderID, $start, $num_rows, $sort, $filter ) {
        $ews = self::init( $profile );		

        $request = new DT\FindItemType();
        $request->ItemShape = new DT\ItemResponseShapeType();
        $request->ItemShape->BaseShape = DT\DefaultShapeNamesType::ALL_PROPERTIES;
        $request->Traversal = DT\ItemQueryTraversalType::SHALLOW;

        /* $offset = $limit * ($page - 1); */
        $request->IndexedPageItemView = new DT\IndexedPageViewType();
        $request->IndexedPageItemView->BasePoint = "Beginning";
        $request->IndexedPageItemView->Offset = $start; // Item number you want to start at
        $request->IndexedPageItemView->MaxEntriesReturned = $num_rows; // Numer of items to return in total

        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->FolderId = new DT\FolderIdType();
        $request->ParentFolderIds->FolderId->Id = $folderID;

        $request->SortOrder = new DT\NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();

        $order = new DT\FieldOrderType();
        $order->FieldURI = '';
        @$order->FieldURI->FieldURI = $sort['order']; // @ symbol stops the creating default object from empty value error
        $order->Order = $sort['sort']; 
        $request->SortOrder->FieldOrder[] = $order;

        if ( $filter['string'] ) {
            $request->Restriction = self::setRestriction( $filter );
        }

        $response = $ews->FindItem($request);

        //Format
        $emails = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message;
        if ( $emails && is_array($emails) === FALSE ) {
            $emails = array( $emails );
        }

        return array( 'rows' => $emails, 'count' => $response->ResponseMessages->FindItemResponseMessage->RootFolder->TotalItemsInView ); 

    }

    // Get all mail ids inside a specific folder
    static function getMailIds( $profile, $folderID, $page, $limit) {
        $ews = self::init( $profile );		

        $request = new DT\FindItemType();
        $request->ItemShape = new DT\ItemResponseShapeType();
        $request->ItemShape->BaseShape = DT\DefaultShapeNamesType::ID_ONLY;
        $request->Traversal = DT\ItemQueryTraversalType::SHALLOW;

        $offset = $limit * ($page - 1);
        $request->IndexedPageItemView = new DT\IndexedPageViewType();
        $request->IndexedPageItemView->BasePoint = "Beginning";
        $request->IndexedPageItemView->Offset = $offset; // Item number you want to start at
        $request->IndexedPageItemView->MaxEntriesReturned = $limit; // Numer of items to return in total

        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->FolderId = new DT\FolderIdType();
        $request->ParentFolderIds->FolderId->Id = $folderID;

        $request->SortOrder = new DT\NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();
        $order = new DT\FieldOrderType();

        $order->FieldURI = '';
        @$order->FieldURI->FieldURI = 'item:DateTimeSent'; // @ symbol stops the creating default object from empty value error
        $order->Order = 'Descending'; 
        $request->SortOrder->FieldOrder[] = $order;

        $response = $ews->FindItem($request);

        //Format
        $emails = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message;
        if ( is_array($emails) === FALSE ) {
            $array = array( $emails );
        }
        else {
            $array = $emails;
        }
        $ids = array();
        foreach ( $array as $email ) {
            $ids[] = $email->ItemId->Id;
        }

        return $ids;

    }

    static function getMailIdsAfterDate( $profile, $folderID, $date ) {
        $ews = self::init( $profile );		

        $request = new DT\FindItemType();
        $request->ItemShape = new DT\ItemResponseShapeType();
        $request->ItemShape->BaseShape = DT\DefaultShapeNamesType::ID_ONLY;
        $request->Traversal = DT\ItemQueryTraversalType::SHALLOW;

        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->FolderId = new DT\FolderIdType();
        $request->ParentFolderIds->FolderId->Id = $folderID;

        $request->SortOrder = new DT\NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();
        $order = new DT\FieldOrderType();

        $request->Restriction = new DT\RestrictionType();
        $request->Restriction->IsGreaterThan = new DT\IsGreaterThanType();
        $request->Restriction->IsGreaterThan->FieldURI = new DT\PathToUnindexedFieldType();
        $request->Restriction->IsGreaterThan->FieldURI->FieldURI = 'item:DateTimeReceived';
        $request->Restriction->IsGreaterThan->FieldURIOrConstant = new DT\FieldURIOrConstantType();
        $request->Restriction->IsGreaterThan->FieldURIOrConstant->Constant = new DT\ConstantValueType();
        $request->Restriction->IsGreaterThan->FieldURIOrConstant->Constant->Value = $date;

        $order->FieldURI = '';
        @$order->FieldURI->FieldURI = 'item:DateTimeReceived';
        $order->Order = 'Ascending'; 
        $request->SortOrder->FieldOrder[] = $order;

        $response = $ews->FindItem($request);

        if ( $emails = $response->ResponseMessages->FindItemResponseMessage->RootFolder->Items->Message ) {
            //Format
            if ( is_array($emails) === FALSE ) {
                $array = array( $emails );
            }
            else {
                $array = $emails;
            }
            $ids = array();
            foreach ( $array as $email ) {
                $ids[] = $email->ItemId->Id;
            }
        }
        else
            $ids = array();

        return $ids;

    }

    static function getFolder( $profile, $folderID ) {
        $ews = self::init( $profile );

        $request = new DT\GetFolderType();
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->FolderId = new DT\FolderIdType();
        $request->FolderIds->FolderId->Id = $folderID;

        $response = $ews->GetFolder($request);

        $folder = $response->ResponseMessages->GetFolderResponseMessage->Folders->Folder;

        return $folder;
    }

    static function getTreeFolders( $profile ) {		

        $folders = array();

        /* $info = self::get_inbox_info( $profile ); */
        $account = Mail\Account::read( $profile );

        // INBOX
        if ( $account->params['acc_ews_type'] == 'inbox' ) {
            // Get Inbox Folders

            $username = $account->params['acc_imap_username'];

            $array = self::getInboxFolders( $profile, $username );
            foreach ( $array as $folder ) {	            
                $folders[] = array(
                    'id' => $folder->FolderId->Id,
                    'name' => $folder->DisplayName,
                    'delete' => 1,
                );
            }
        }
        else {
            // For public folders, No need to call server, arrays already in DB
            /* $db = self::get_folders_info( $profile ); */
            /* while( $row = $db->row(true) ) { */
            /*     $folders[] = array( */
            /*         'id' => $row['folder_id'], */
            /*         'name' => $row['folder_name'], */
            /*         'delete' => $row['delete_permission'] */
            /*     ); */
            /* } */
            $ews = self::init( $profile );
            $array = self::getAllFolders( $ews );
            foreach ( $array as $id => $folder ) {	            
                $folders[] = array(
                    'id' => $id,
                    'name' => $folder,
                    'delete' => 1,
                );
            }

        }

        return $folders;
    }

    static function getInboxFolders( $profile, $account ) {
        $ews = self::init( $profile );

        $request = new DT\FindFolderType();
        $request->Traversal = DT\FolderQueryTraversalType::SHALLOW; 
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::ALL_PROPERTIES;

        $request->IndexedPageFolderView = new DT\IndexedPageViewType();
        $request->IndexedPageFolderView->BasePoint = 'Beginning';
        $request->IndexedPageFolderView->Offset = 0;

        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();

        $request->ParentFolderIds->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = DT\DistinguishedFolderIdNameType::MESSAGE_FOLDER_ROOT;    		
        $request->ParentFolderIds->DistinguishedFolderId->Mailbox = new DT\EmailAddressType();
        $request->ParentFolderIds->DistinguishedFolderId->Mailbox->EmailAddress = "$account@sigalas.eu";


        $response = $ews->FindFolder($request);
        $array = $response->ResponseMessages->FindFolderResponseMessage->RootFolder->Folders->Folder;

        return $array;
    }

    static function getAllFolders( $ews) {

        $request = new DT\FindFolderType();
        $request->Traversal = DT\FolderQueryTraversalType::SHALLOW; 
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->IndexedPageFolderView = new DT\IndexedPageViewType();
        $request->IndexedPageFolderView->BasePoint = 'Beginning';
        $request->IndexedPageFolderView->Offset = 0;
        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();

        $request->ParentFolderIds->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
        $request->ParentFolderIds->DistinguishedFolderId->Id = DT\DistinguishedFolderIdNameType::PUBLIC_FOLDERS_ROOT; 			

        $response = $ews->FindFolder($request);

        $folders = array();
        $array = $response->ResponseMessages->FindFolderResponseMessage->RootFolder->Folders->Folder;
        foreach ( $array as $folder ) {
            $folders[ $folder->FolderId->Id ] = $folder->DisplayName;
        }

        return $folders;
    }

    static function getWriteFolders( $profile, $folder_id ){

        $sql = "SELECT profile_name,exchange_user,is_inbox,inbox_account FROM ac_exchange_profiles WHERE profile_id=$profile";
        $db = clone($GLOBALS['egw']->db);
        $db->query($sql);
        $row = $db->row(true);
        $folders = array();
        if ( $row['is_inbox'] ) {

            $account = $row['exchange_user'];
            if ( $row['inbox_account'] ) {
                if ( $row['inbox_account'] == 'dynamic' ) 
                    $account = $GLOBALS['egw_info']['user']['account_lid'];        		
                else 
                    $account = $row['inbox_account'];        		
            }
            $array = self::getInboxFolders( $profile, $account );
            foreach ( $array as $item ) {
                $folders[] = array(
                    'value' => "exchange$profile::".$item->FolderId->Id,
                    'label' => $item->DisplayName,
                );
            }
        }
        else {
            // Get all write folders
            $sql = "SELECT folder_id, folder_name FROM ac_exchange_permissions WHERE profile_id=$profile and write_permission=1 ORDER BY folder_order";
            $db->query($sql);
            $all = array();
            while ( $row = $db->row(true) ) {
                if ( $row['folder_id'] == $folder_id ) continue;
                $all[] = array( 
                    'value' => "exchange$profile::".$row['folder_id'],
                    'label' => $row['folder_name'],
                );
            }        	
            // Get allowed write folders
            $sql = "SELECT folders_allowed FROM ac_exchange_permissions WHERE profile_id=$profile and folder_id='$folder_id'";
            $db->query($sql);
            $row = $db->row(true);
            $allowed = unserialize( $row['folders_allowed'] );
            foreach ( $all as $item ) {
                list(,$item_id) = explode('::',$item['value']);
                if ( in_array( $item_id, $allowed ) )
                    $folders[] = $item;
            }
        }

        return $folders;
    }

    static function getAllWriteFolders( $folder_id ) {

        // Get User info
        $user = $GLOBALS['egw_info']['user']['account_id'];
        $db = clone($GLOBALS['egw']->db);
        $sql = "SELECT ac_exchange_profiles FROM ac_user_setting WHERE user_id=$user";
        $db->query($sql);
        $row = $db->row(true);
        $profiles = explode( ',', $row['ac_exchange_profiles'] );
        $folders = array();

        foreach ($profiles as $profile) {
            if ( empty($profile) ) continue;
            $sql = "SELECT profile_name,exchange_user,is_inbox,inbox_account FROM ac_exchange_profiles WHERE profile_id=$profile";
            $db->query($sql);
            $row = $db->row(true);
            if ( $row['is_inbox'] ) {

                $account = $row['exchange_user'];
                if ( $row['inbox_account'] ) {
                    if ( $row['inbox_account'] == 'dynamic' ) 
                        $account = $GLOBALS['egw_info']['user']['account_lid'];        		
                    else 
                        $account = $row['inbox_account'];        		
                }
                $array = self::getInboxFolders( $profile, $account );
                foreach ( $array as $item ) {
                    $folders[] = array(
                        'value' => "exchange$profile::".$item->FolderId->Id,
                        'label' => $row['profile_name'].' - '.$item->DisplayName,
                    );
                }
            }
            else {
                $sql = "SELECT folder_id, folder_name FROM ac_exchange_permissions WHERE profile_id=$profile and write_permission=1 ORDER BY folder_order";
                $db->query($sql);
                while ( $crow = $db->row(true) ) {
                    if ( $crow['folder_id'] == $folder_id ) continue;
                    $folders[] = array( 
                        'value' => "exchange$profile::".$crow['folder_id'],
                        'label' => $row['profile_name'].' - '.$crow['folder_name'],
                    );
                }        	
            }
        }

        return $folders;
    }

    static function CopyMail( $profile, $Id, $toFolder ) {
        $ews = self::init( $profile );

        $request = new DT\BaseMoveCopyItemType();
        $request->ToFolderId = new DT\TargetFolderIdType();
        $request->ToFolderId->FolderId = new DT\FolderIdType();
        $request->ToFolderId->FolderId->Id =$toFolder;
        $request->ItemIds = new DT\NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = array();
        $message_item = new DT\ItemIdType();
        $message_item->Id = $Id;
        $request->ItemIds->ItemId[] = $message_item;
        $response = $ews->CopyItem($request);

        $msg = '';
        $status = ( $response->ResponseMessages->CopyItemResponseMessage->ResponseClass == 'Success' );
        if ( !$status )
            $msg = 'Exchange:'.$response->ResponseMessages->CopyItemResponseMessage->MessageText; 
        return array( 'status' => $status, 'msg' => $msg );
    }

    static function MoveMail( $profile, $Id, $ChangeKey, $toFolder ) {
        $ews = self::init( $profile );

        $request = new DT\BaseMoveCopyItemType();
        $request->ToFolderId = new DT\TargetFolderIdType();
        $request->ToFolderId->FolderId = new DT\FolderIdType();
        $request->ToFolderId->FolderId->Id =$toFolder;
        $request->ItemIds = new DT\NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = array();
        $message_item = new DT\ItemIdType();
        $message_item->Id = $Id;
        $message_item->ChangeKey = $ChangeKey;
        $request->ItemIds->ItemId[] = $message_item;
        $response = $ews->MoveItem($request);

        $msg = '';
        $status = ( $response->ResponseMessages->MoveItemResponseMessage->ResponseClass == 'Success' );
        if ( !$status )
            $msg = 'Exchange:'.$response->ResponseMessages->MoveItemResponseMessage->MessageText; 
        return array( 'status' => $status, 'msg' => $msg );
    }

    static function DeleteMail( $profile, $Id, $deleteType ) {
        $ews = self::init( $profile );

        $request = new DT\DeleteItemType();
        $request->DeleteType = $deleteType;
        $request->ItemIds = new DT\NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = array();
        $message_item = new DT\ItemIdType();
        $message_item->Id = $Id;
        $request->ItemIds->ItemId[] = $message_item;
        $response = $ews->DeleteItem($request);

        $msg = '';
        $status = ( $response->ResponseMessages->DeleteItemResponseMessage->ResponseClass == 'Success' );
        if ( !$status )
            $msg = 'Exchange:'.$response->ResponseMessages->DeleteItemResponseMessage->MessageText; 
        return array( 'status' => $status, 'msg' => $msg );
    }

    static function getInboxId( $profile ) {
        $ews = self::init( $profile );

        $request = new DT\GetFolderType();
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
        $request->FolderIds->DistinguishedFolderId->Id = DT\DistinguishedFolderIdNameType::INBOX; 			

        $response = $ews->GetFolder($request);

        $folder = $response->ResponseMessages->GetFolderResponseMessage->Folders->Folder;
        return $folder->FolderId->Id;

    }

    static function get_inbox_info( $profile ) {		

        $table = new achelper_base( 'acadmin', 'ac_exchange_profiles' );
        $columns = array( 'is_inbox', 'inbox_account', 'exchange_user', 'inbox_backup_folder' );
        $result = $table->read( $profile, $columns );
        return $result;
    }

    static function get_folders_info( $profile ) {
        if ( strpos($profile, 'exchange') !== FALSE )
            $profile = substr($profile, 8);

        $db = clone($GLOBALS['egw']->db);
        $sql = "SELECT * FROM ac_exchange_permissions WHERE profile_id = $profile and read_permission=1 ORDER BY folder_order";
        $db->query($sql);
        return $db;
    }

    static function setRestriction( $filter ) {

        $fields = array(
            'cc' => 'message:CcRecipients',
            'to' => 'message:ToRecipients',
            'from' => 'message:From',
            'subject' => 'item:Subject',
            'body' => 'item:Body',
        );

        // Prepare restrictions
        $restr = array();
        foreach( $fields as $field => $value ) {
            $tmp = new DT\ContainsExpressionType();
            $tmp->ContainmentMode = 'Substring';
            $tmp->ContainmentComparison = 'IgnoreCase';
            $tmp->FieldURI = new DT\PathToUnindexedFieldType();
            $tmp->FieldURI->FieldURI = $value;
            $tmp->Constant = new DT\ConstantValueType();
            $tmp->Constant->Value = $filter['string'];
            $restr[ $field ] = $tmp;
        }

        // Apply appropriate restrictions to each scenario
        $ors = array();
        switch( $filter['type'] ) {
        case 'quickwithcc':
            $ors[] = $restr['cc'];
        case 'bydate':
        case 'quick':
            $ors[] = $restr['to'];
            $ors[] = $restr['from'];
            $ors[] = $restr['subject'];
            break;
        case 'subject':
        case 'body':
        case 'from':
        case 'to':
        case 'cc':
            $ors[] = $restr[ $filter['type'] ];
            break;
        case 'text':
            foreach( $fields as $field => $value ) 
                $ors[] = $restr[ $field ];
            break;
        }

        // Build the restriction.
        $restriction = new DT\RestrictionType();

        if ( count( $ors ) > 1 ) {
            // Concatenate ORs
            $or = new DT\OrType();
            $one = array_pop( $ors );
            $two = array_pop( $ors );
            $or->Contains = array( $one, $two );
            while( $curr = array_pop( $ors ) ) {
                $new = new DT\OrType();
                $new->Contains = $curr;
                $new->Or = $or;
                $or = $new;
            }

            $restriction->Or = $or;
        }
        else 
            $restriction->Contains = $ors[0];

        return $restriction;
    }
}
