<?php
/**
 * $Horde: framework/SyncML/SyncML/Sync.php,v 1.7 2004/09/14 04:27:05 chuck Exp $
 *
 * Copyright 2003-2004 Anthony Mills <amills@pyramid6.com>
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anthony Mills <amills@pyramid6.com>
 * @version $Revision$
 * @since   Horde 3.0
 * @package Horde_SyncML
 */
class Horde_SyncML_Sync {

    /**
     * Target, either contacts, notes, events,
     */
    var $_targetLocURI;

    var $_sourceLocURI;

    /**
     * Return if all commands success.
     */
    var $globalSuccess;

    /**
     * This is the content type to use to export data.
     */
    var $preferedContentType;

    /**
     * Do have the sync data loaded from the database already?
     */
    var $syncDataLoaded;

    function &factory($alert)
    {
    	Horde::logMessage('SyncML: new sync for alerttype ' . $alert, __FILE__, __LINE__, PEAR_LOG_DEBUG);
        switch ($alert) {
        case ALERT_TWO_WAY:
            include_once 'Horde/SyncML/Sync/TwoWaySync.php';
            return $sync = &new Horde_SyncML_Sync_TwoWaySync();

        case ALERT_SLOW_SYNC:
            include_once 'Horde/SyncML/Sync/SlowSync.php';
            return $sync = &new Horde_SyncML_Sync_SlowSync();

        case ALERT_ONE_WAY_FROM_CLIENT:
            include_once 'Horde/SyncML/Sync/OneWayFromClientSync.php';
            return $sync = &new Horde_SyncML_Sync_OneWayFromClientSync();

        case ALERT_REFRESH_FROM_CLIENT:
            include_once 'Horde/SyncML/Sync/RefreshFromClientSync.php';
            return $sync = &new Horde_SyncML_Sync_RefreshFromClientSync();

        case ALERT_ONE_WAY_FROM_SERVER:
            include_once 'Horde/SyncML/Sync/OneWayFromServerSync.php';
            return $sync = &new Horde_SyncML_Sync_OneWayFromServerSync();

        case ALERT_REFRESH_FROM_SERVER:
            include_once 'Horde/SyncML/Sync/RefreshFromServerSync.php';
            return $sync = &new Horde_SyncML_Sync_RefreshFromServerSync();
        }

        require_once 'PEAR.php';
        return PEAR::raiseError('Alert ' . $alert . ' not found.');
    }

    function nextSyncCommand($currentCmdID, &$syncCommand, &$output)
    {
        $result = $this->runSyncCommand($syncCommand);
        return $syncCommand->output($currentCmdID, $output);
    }

    function startSync($currentCmdID, &$output)
    {
        return $currentCmdID;
    }

    function endSync($currentCmdID, &$output)
    {
        return $currentCmdID;
    }

	/**
	* Here's where the actual processing of a client-sent Sync
	* Command takes place. Entries are added, deleted or replaced
	* from the server database by using Horde API (Registry) calls.
	*/
	function runSyncCommand(&$command) {
		#Horde::logMessage('SyncML: content type is ' . $command->getContentType() .' moreData '. $command->_moreData, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		global $registry;
		
		$history = $GLOBALS['egw']->contenthistory;
		
		$state = &$_SESSION['SyncML.state'];
		
		if(isset($state->_moreData['luid'])) {
			if(($command->_luid == $state->_moreData['luid'])) {
                                Horde::logMessage('SyncML: got next moreData chunk '.$command->getContent(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$lastChunks = implode('',$state->_moreData['chunks']);
				$command->_content = $lastChunks.$command->_content;
				$stringlen1 =  strlen($lastChunks);
				$stringlen2 =  strlen($command->_content);
				
				if(!$command->_moreData && strlen($command->_content) != $state->_moreData['contentSize']) {
					$command->_status = RESPONSE_SIZE_MISMATCH;
					$state->_moreData = array();
					
					return;
				} elseif(!$command->_moreData && strlen($command->_content) == $state->_moreData['contentSize']) {
					$state->_moreData = array();
					Horde::logMessage('SyncML: chunk ended successful type is ' . $command->getContentType() .' content is '. $command->getContent(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				}
			} else {
				// alert 223 needed too
				#$command->_status = ALERT_NO_END_OF_DATA;
				
				$state->_moreData = array();
				
				return;
			}
		}
		
		// don't add/replace the data currently, they are not yet complete
		if($command->_moreData == TRUE) {
			$state->_moreData['chunks'][]	= $command->_content;
			$state->_moreData['luid']	= $command->_luid;
			
			// gets only set with the first chunk of data
			if(isset($command->_contentSize))
				$state->_moreData['contentSize'] = $command->_contentSize;
				
			$command->_status = RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED;
                        Horde::logMessage('SyncML: added moreData chunk '.$command->getContent(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			
			return;
		}
		
		$hordeType = $type = $this->_targetLocURI;
		$hordeType = $state->getHordeType($hordeType);
		if(!$contentType = $command->getContentType()) {
			$contentType = $state->getPreferedContentType($type);
		}
		
		if (($contentType == 'text/x-vcalendar' || $contentType == 'text/calendar')
			&& strpos($command->getContent(), 'BEGIN:VTODO') !== false)
		{
			$hordeType = 'tasks';
		}

		$syncElementItems = $command->getSyncElementItems();
		
		foreach($syncElementItems as $syncItem) {
		
			$guid = false;
		
			if (is_a($command, 'Horde_SyncML_Command_Sync_Add')) {
				$guid = $registry->call($hordeType . '/import',
					array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
				
				if (!is_a($guid, 'PEAR_Error')) {
					$ts = $state->getSyncTSforAction($guid, 'add');
					$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
					$state->log("Client-Add");
					Horde::logMessage('SyncML: added client entry as ' . $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
				} else {
					$state->log("Client-AddFailure");
					Horde::logMessage('SyncML: Error in adding client entry:' . $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
				}
			} elseif (is_a($command, 'Horde_SyncML_Command_Sync_Delete')) {
				// We can't remove the mapping entry as we need to keep
				// the timestamp information.
				$guid = $state->removeUID($type, $syncItem->getLocURI());
				#$guid = $state->getGlobalUID($type, $syncItem->getLocURI());
				Horde::logMessage('SyncML: about to delete entry ' . $type .' / '. $guid . ' due to client request '.$syncItem->getLocURI(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
			
				if (!is_a($guid, 'PEAR_Error') && $guid != false) {
					$registry->call($hordeType . '/delete', array($guid));
					#$ts = $state->getSyncTSforAction($guid, 'delete');
					#$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
					$state->log("Client-Delete");
					Horde::logMessage('SyncML: deleted entry ' . $guid . ' due to client request', __FILE__, __LINE__, PEAR_LOG_DEBUG);
				} else {
					$state->log("Client-DeleteFailure");
					Horde::logMessage('SyncML: Failure deleting client entry, maybe gone already on server. msg:'. $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
				}
			} elseif (is_a($command, 'Horde_SyncML_Command_Sync_Replace')) {
				$guid = $state->getGlobalUID($type, $syncItem->getLocURI());
				$ok = false;
				if ($guid) {
					Horde::logMessage('SyncML: locuri'. $syncItem->getLocURI() . ' guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_ERR);
					// Entry exists: replace current one.
				  $ok = $registry->call($hordeType . '/replace',
					array($guid, $state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
					if (!is_a($ok, 'PEAR_Error')) {
						$ts = $state->getSyncTSforAction($guid, 'modify');
						$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
						Horde::logMessage('SyncML: replaced entry due to client request guid: ' .$guid. ' ts: ' .$ts, __FILE__, __LINE__, PEAR_LOG_DEBUG);
						$state->log("Client-Replace");
						error_log("done_replace");
						$ok = true;
					} else {
						// Entry may have been deleted; try adding it.
						$ok = false;
					}
				}
				
				if (!$ok) {
					// Entry does not exist in map or database: add a new one.
					Horde::logMessage('SyncML: try to add contentype ' . $contentType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					$guid = $registry->call($hordeType . '/import',
						array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
					if (!is_a($guid, 'PEAR_Error')) {
						$ts = $state->getSyncTSforAction($guid, 'add');
						$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
						$state->log("Client-AddReplace");
						Horde::logMessage('SyncML: r/ added client entry as ' . $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					} else {
						Horde::logMessage('SyncML: Error in replacing/add client entry:' . $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
						$state->log("Client-AddFailure");
					}
				}
			}
		}
		
		return $guid;
	}
}
