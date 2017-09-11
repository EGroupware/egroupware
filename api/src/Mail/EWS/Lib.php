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
            $folders = self::getInbox( $ews );
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

    static function createMail( $profile, $folderID, $mime ) {
        $ews = self::init( $profile );

        $request = new DT\CreateItemType();
        $request->MessageDisposition = 'SaveOnly';

        $request->SavedItemFolderId = new DT\TargetFolderIdType();
        $request->SavedItemFolderId->FolderId = new DT\FolderIdType();
        $request->SavedItemFolderId->FolderId->Id = $folderID;

        $message = new DT\MessageType();
        $message->MimeContent = $mime;

        $request->Items = new DT\NonEmptyArrayOfAllItemsType();
        $request->Items->Message[] = $message;

        $response = $ews->CreateItem($request);

        return $response->ResponseMessages->CreateItemResponseMessage->ResponseClass == 'Success';
    }

    static function getAttachment( $profile, $attachmentID ) {
        $ews = self::init( $profile );

        $request = new DT\GetAttachmentType();
        $request->AttachmentIds = new DT\NonEmptyArrayOfRequestAttachmentIdsType();
        $request->AttachmentIds->AttachmentId = new DT\RequestAttachmentIdType();
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
            return base64_decode( $response->Items->Message->MimeContent->_ );
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

    static function getFolder( $profile, $folderID, $id_only = false ) {
        $ews = self::init( $profile );

        $request = new DT\GetFolderType();
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;
        if ( $id_only )
            $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::ID_ONLY;

        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->FolderId = new DT\FolderIdType();
        $request->FolderIds->FolderId->Id = $folderID;

        $response = $ews->GetFolder($request);

        $folder = $response->ResponseMessages->GetFolderResponseMessage->Folders->Folder;

        return $folder;
    }

    static function getFoldersSelOptions( $profile ) {		
        if ( !$profile ) return array();

        $folders = self::getSettingsFolders( $profile );
        $final = array();
        foreach ( $folders as $folder ) 
            $final[ $folder['id'] ] = $folder['name'];

        return $final;
    }
    static function getTreeFolders( $profile ) {		

        // From Db
        $used = array();
        $forbidden = array();
        $final = array();
        $rows = self::getDBFolders( $profile );
        foreach( $rows as $row ) {
            if ( !self::is_allowed( $profile, $row['ews_folder'], 'read' ) ) {
                $forbidden[] = $row['ews_folder'];
                continue;
            }

            $final[] = array(
                'id' => $row['ews_folder'],
                'name' => $row['ews_name'],
            );
            $used[] = $row['ews_folder'];
        }

        // Fill in the rest
        $folders = self::getSettingsFolders( $profile );

        foreach ( $folders as $idx => $folder ) {
            if  ( !in_array( $folder['id'], $used ) && !in_array( $folder['id'], $forbidden ) )
                $final[] = $folder;
        }

        return $final;
    }

    static function getFolders( $profile, $node = null, $node_name = null) {
        $ews = self::init( $profile );

        $request = new DT\FindFolderType();
        $request->Traversal = DT\FolderQueryTraversalType::SHALLOW; 
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->IndexedPageFolderView = new DT\IndexedPageViewType();
        $request->IndexedPageFolderView->BasePoint = 'Beginning';
        $request->IndexedPageFolderView->Offset = 0;
        $request->ParentFolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();

        if ( $node ) {
            $request->ParentFolderIds->FolderId = new DT\FolderIdType();
            $request->ParentFolderIds->FolderId->Id = $node;
        }
        else {
            $request->ParentFolderIds->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
            $request->ParentFolderIds->DistinguishedFolderId->Id = self::getRootFolder( $profile );
        }

        $response = $ews->FindFolder($request);

        $folders = array();
        $array = $response->ResponseMessages->FindFolderResponseMessage->RootFolder->Folders->Folder;
        if ( !is_array( $array ) ) $array = array( $array );
        foreach ( $array as $folder ) {
            $name = $folder->DisplayName;
            if ( $node_name )
                $name = "$node_name/$name";
            $folders[ $folder->FolderId->Id ] = $name;

            if ( $folder->ChildFolderCount ) 
                $folders += self::getFolders( $profile, $folder->FolderId->Id, $name );
        }

        return $folders;
    }

    static function getSettingsFolders( $profile ) {
        $folders = array();

        $array = self::getFolders( $profile );

        foreach ( $array as $id => $folder ) {	            
            $folders[] = array(
                'id' => $id,
                'name' => $folder,
            );
        }

        return $folders;
    }

    static function moveMail( $profile, $Id, $ChangeKey, $toFolder, $move = true ) {
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
        if ( $move )
            $response = $ews->MoveItem($request);
        else
            $response = $ews->CopyItem($request);

        $status = ( $response->ResponseMessages->MoveItemResponseMessage->ResponseClass == 'Success' );
        $msg = 'Exchange:'.$response->ResponseMessages->MoveItemResponseMessage->MessageText; 
        if ( !$status )
            throw new \Exception( $msg );


        return $msg;
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

        $msg = 'Exchange:'.$response->ResponseMessages->DeleteItemResponseMessage->MessageText; 
        $status = ( $response->ResponseMessages->DeleteItemResponseMessage->ResponseClass == 'Success' );
        if ( !$status )
            throw new \Exception( $msg );


        return $msg;
    }

    static function getInbox( $ews ) {

        $request = new DT\GetFolderType();
        $request->FolderShape = new DT\FolderResponseShapeType();
        $request->FolderShape->BaseShape = DT\DefaultShapeNamesType::DEFAULT_PROPERTIES;

        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
        $request->FolderIds->DistinguishedFolderId->Id = DT\DistinguishedFolderIdNameType::MESSAGE_FOLDER_ROOT; 			

        $response = $ews->GetFolder($request);

        if ( $response->ResponseMessages->GetFolderResponseMessage->ResponseClass == 'Error' )
            throw new \Exception( $response->ResponseMessages->GetFolderResponseMessage->MessageText  );

        return true;

    }

    static function getDefaultFolder( $profile ) {
        // Get From Db
        $db = clone($GLOBALS['egw']->db);
        $sql = "SELECT * FROM egw_ea_ews WHERE ews_profile= $profile and ews_is_default=1";
        $db->query($sql);
        $row = $db->row( true );
        if ( $row )
            return $row['ews_name'];

        $folders = self::getTreeFolders( $profile );
        return $folders[0]['name'];
    }

    static function createFolder( $profile, $parentID, $name ) {
        $ews = self::init( $profile );

        // Build the request object.
        $request = new DT\CreateFolderType();
        $request->Folders = new DT\ArrayOfFoldersType();

        $parent = new DT\TargetFolderIdType();
        if ( !$parentID ) {
            $parent->DistinguishedFolderId = new DT\DistinguishedFolderIdType();
            $parent->DistinguishedFolderId->Id = self::getRootFolder( $profile );
        }
        else {
            $parent->FolderId = new DT\FolderIdType();
            $parent->FolderId->Id = $parentID;
        }

        $request->ParentFolderId = $parent;

        $folder = new DT\FolderType();
        $folder->DisplayName = $name;
        $request->Folders->Folder = array( $folder );
        $response = $ews->CreateFolder($request);

        if ( $response->ResponseMessages->CreateFolderResponseMessage->ResponseClass == 'Error' ) 
            throw new \Exception( $response->ResponseMessages->CreateFolderResponseMessage->MessageText  );

        return true;
    }
    static function deleteFolder( $profile, $folderID ) {
        $ews = self::init( $profile );

        // Build the request object.
        $request = new DT\DeleteFolderType();
        $request->DeleteType = 'HardDelete';
        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();

        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->FolderId = new DT\FolderIdType();
        $request->FolderIds->FolderId->Id = $folderID;

        $response = $ews->DeleteFolder($request);

        if ( $response->ResponseMessages->DeleteFolderResponseMessage->ResponseClass == 'Error' ) 
            throw new \Exception( $response->ResponseMessages->DeleteFolderResponseMessage->MessageText  );

        return true;
    }
    static function moveFolder( $profile, $folder, $parent ) {
        $ews = self::init( $profile );

        // Build the request object.
        $request = new DT\BaseMoveCopyFolderType();

        $request->ToFolderId = new DT\TargetFolderIdType();
        $request->ToFolderId->FolderId = new DT\FolderIdType();
        $request->ToFolderId->FolderId->Id = $parent;

        // Set the parent folder for the newly DT\created folder.
        $request->FolderIds = new DT\NonEmptyArrayOfBaseFolderIdsType();
        $request->FolderIds->FolderId = new DT\FolderIdType();
        $request->FolderIds->FolderId->Id = $folder;

        $response = $ews->MoveFolder($request);
        if ( $response->ResponseMessages->MoveFolderResponseMessage->ResponseClass == 'Error' ) 
            throw new \Exception( $response->ResponseMessages->MoveFolderResponseMessage->MessageText  );

        return true;
    }

    static function renameFolder( $profile, $folder, $changeKey, $name ) {
        $ews = self::init( $profile );

        // Build the request object.
        $request = new DT\UpdateFolderType();

        $set = new DT\SetFolderFieldType();
        $set->FieldURI = new DT\PathToUnindexedFieldType;
        $set->FieldURI->FieldURI = 'folder:DisplayName';
        $set->Folder = new DT\FolderType();
        $set->Folder->DisplayName = $name;

        $change = new DT\FolderChangeType();
        $change->FolderId = new DT\FolderIdType();
        $change->FolderId->Id = $folder;
        $change->FolderId->ChangeKey = $changeKey;
        $change->Updates = new DT\NonEmptyArrayOfFolderChangeDescriptionsType();
        $change->Updates->SetFolderField = array( $set );

        $request->FolderChanges = new DT\NonEmptyArrayOfFolderChangesType();
        $request->FolderChanges->FolderChange = array( $change );

        $response = $ews->UpdateFolder($request);

        if ( $response->ResponseMessages->UpdateFolderResponseMessage->ResponseClass == 'Error' ) 
            throw new \Exception( $response->ResponseMessages->UpdateFolderResponseMessage->MessageText  );

        return true;
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

    static function getDBFolders( $profile ) {
        $db = clone( $GLOBALS['egw']->db );
        $db->query("SELECT * FROM egw_ea_ews WHERE ews_profile=$profile ORDER BY ifnull(ews_order,99)");
        $rows = array();
        while( $row = $db->row( true ) )
            $rows[] = $row;

        return $rows;
    }
    static function is_allowed( $profile, $folder, $action ) {
        $allowed = false;

        $db = clone($GLOBALS['egw']->db);
        $sql = "SELECT ews_apply_permissions, ews_permissions FROM egw_ea_ews WHERE ews_profile=$profile AND ews_folder= BINARY '$folder' ORDER BY ews_order";
        $db->query($sql);
        $row = $db->row( true );

        $acc = Mail\Account::read( $profile );

        if ( $row['ews_apply_permissions'] || $acc['acc_ews_apply_permissions']) {
            $permissions = unserialize( $row['ews_permissions'] );
            $allow = ( $permissions[ $action ] );
        }
        else
            $allow = true;

        return $allow;
    }
    static function can_delete( $profile, $folder ) {
        return self::is_allowed( $profile, $folder, 'delete' );
    }
    static function can_move( $profile, $from, $to ) {
        $db = clone($GLOBALS['egw']->db);

        // Can move FROM->TO folder
        $sql = "SELECT ifnull(ews_move_to,0) as ews_move_to, ews_move_anywhere FROM egw_ea_ews WHERE ews_profile= $profile and ews_folder= BINARY '$from'";
        $db->query($sql);
        $row = $db->row( true );
        $acc = Mail\Account::read( $profile );
        if ( $row['ews_apply_permissions'] || $acc['acc_ews_apply_permissions']) {
            $allow_from = ( $row['ews_move_anywhere'] || in_array( $to, explode(',', $row['ews_move_to'] )));
        }
        else 
            $allow_from = true;

        // Can write in TO folder
        $allowed_to = self::is_allowed( $profile, $to, 'write' );

        return $allow_from && $allow_to;
    }
    static function getRootFolder( $profile ) {
        if ( $profile )
            $account = Mail\Account::read( $profile );

        if ( !$profile || $account->params['acc_ews_type'] == 'inbox' ) 
            return DT\DistinguishedFolderIdNameType::MESSAGE_FOLDER_ROOT;    		
        else 
            return DT\DistinguishedFolderIdNameType::PUBLIC_FOLDERS_ROOT; 			
    }

    static function renameFolderDB( $profile, $folderID, $name ) {
        $db = clone($GLOBALS['egw']->db);
        $sql = "UPDATE egw_ea_ews set ews_name='$name' WHERE ews_profile= $profile and ews_folder= BINARY '$folderID'";
        $db->query($sql);
        return true;
    }
}
