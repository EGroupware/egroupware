<?php

include_once 'Horde/SyncML/Sync/TwoWaySync.php';

/**
 * Slow sync may just work; I think most of the work is going to be
 * done by the API.
 *
 * $Horde: framework/SyncML/SyncML/Sync/SlowSync.php,v 1.7 2004/05/26 17:32:50 chuck Exp $
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
class Horde_SyncML_Sync_SlowSync extends Horde_SyncML_Sync_TwoWaySync {

    function handleSync($currentCmdID, $hordeType, $syncType,&$output, $refts)
    {
        global $registry;
        
        $history = $GLOBALS['phpgw']->contenthistory;
        $state = &$_SESSION['SyncML.state'];
        
        $adds = &$state->getAddedItems($hordeType);
        
	#if($adds === FALSE)
	#{
	#	Horde::logMessage("SyncML: reading added items from database", __FILE__, __LINE__, PEAR_LOG_DEBUG);
	#	$state->setAddedItems($hordeType, $registry->call($hordeType. '/list', array()));
	#	$adds = &$state->getAddedItems($hordeType);
        #}

	Horde::logMessage("SyncML: ".count($adds).   ' added items found for '.$hordeType  , __FILE__, __LINE__, PEAR_LOG_DEBUG);
        $serverAnchorNext = $state->getServerAnchorNext($syncType);
	$counter = 0;	

	while($guid = array_shift($adds))
	{
		#$guid_ts = max($history->getTSforAction($guid, 'add'),$history->getTSforAction($guid, 'modify'));
		$sync_ts = $state->getChangeTS($syncType, $guid);
		#Horde::logMessage("SyncML: slowsync timestamp add: $guid sync_ts: $sync_ts anchorNext: ". $serverAnchorNext.' / '.time(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
		// $sync_ts                       it got synced from client to server someone
		// $sync_ts >= $serverAnchorNext  it got synced from client to server in this sync package already
		if ($sync_ts && $sync_ts >= $serverAnchorNext) {
			// Change was done by us upon request of client.
			// Don't mirror that back to the client.
			//Horde::logMessage("SyncML: slowsync add: $guid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
			continue;
		}

		#$locid = $state->getLocID($syncType, $guid);

		// Create an Add request for client.
# LK            $contentType = $state->getPreferedContentTypeClient($syncType);

		$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI);
		if(is_a($contentType, 'PEAR_Error')) {
			// Client did not sent devinfo
			$contentType = array('ContentType' => $state->getPreferedContentType($this->_targetLocURI));
		}

		$cmd = &new Horde_SyncML_Command_Sync_ContentSyncElement();
		$c = $registry->call($hordeType . '/export', array('guid' => $guid, 'contentType' => $contentType));
		#Horde::logMessage("SyncML: slowsync add to server $c", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		if (!is_a($c, 'PEAR_Error')) {
			// Item in history but not in database. Strange, but
			// can happen.
			#LK		$cmd->setContent($state->convertServer2Client($c, $contentType));
			$cmd->setContent($c);
			if($hordeType == 'sifcalendar' || $hordeType == 'sifcontacts' || $hordeType == 'siftasks') {
				$cmd->setContentFormat('b64');
			}
			$cmd->setContentType($contentType['ContentType']);
			$cmd->setSourceURI($guid);
			$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
			$state->log('Server-Add');
			
			// return if we have to much data
			#Horde::logMessage("SyncML: ".' checking hordetype '.$hordeType  , __FILE__, __LINE__, PEAR_LOG_DEBUG);
			if(++$counter >= MAX_ENTRIES && $hordeType != 'sifcalendar' && $hordeType != 'sifcontacts' && $hordeType != 'siftasks') {
				$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
				return $currentCmdID;
			}
		}
	}
	#Horde::logMessage("SyncML: handling sync ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);
	
       	$state->clearSync($syncType);

        return $currentCmdID;
    }
    
	/**
	* Here's where the actual processing of a client-sent Sync
	* Command takes place. Entries are added or replaced
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
		// remove the './' from the beginning
		$hordeType = str_replace('./','',$hordeType);
		
		$syncElementItems = $command->getSyncElementItems();
		
		foreach($syncElementItems as $syncItem) {
			if(!$contentType = $syncItem->getContentType()) {
				$contentType = $state->getPreferedContentType($type);
			}
			
			if ($this->_targetLocURI == 'calendar' && strpos($syncItem->getContent(), 'BEGIN:VTODO') !== false) {
				$hordeType = 'tasks';
			}
		
			$guid = false;
		#	if (is_a($command, 'Horde_SyncML_Command_Sync_Add')) {
		#		$guid = $registry->call($hordeType . '/import',
		#			array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
		#		if (!is_a($guid, 'PEAR_Error')) {
		#			$ts = $history->getTSforAction($guid, 'add');
		#			$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
		#			$state->log("Client-Add");
		#			#Horde::logMessage('SyncML: added client entry as ' . $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		#		} else {
		#			$state->log("Client-AddFailure");
		#			Horde::logMessage('SyncML: Error in adding client entry:' . $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
		#		}
		#	} elseif (is_a($command, 'Horde_SyncML_Command_Sync_Replace')) {
				#$guid = $state->getGlobalUID($type, $syncItem->getLocURI());
				$guid = $registry->call($hordeType . '/search',
					array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
				Horde::logMessage('SyncML: found guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$ok = false;
				if ($guid) {
					#Horde::logMessage('SyncML: locuri'. $syncItem->getLocURI() . ' guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_ERR);
					// Entry exists: replace current one.
					$ok = $registry->call($hordeType . '/replace',
						array($guid, $state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
					if (!is_a($ok, 'PEAR_Error')) {
						$ts = $history->getTSforAction($guid, 'modify');
						$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
						#Horde::logMessage('SyncML: replaced entry due to client request guid: '. $guid  .' LocURI: '. $syncItem->getLocURI() .' ts: '. $ts, __FILE__, __LINE__, PEAR_LOG_DEBUG);
						$state->log("Client-Replace");
						$ok = true;
					} else {
						// Entry may have been deleted; try adding it.
						$ok = false;
					}
				}
			
				if (!$ok) {
					// Entry does not exist in map or database: add a new
					// one.
					Horde::logMessage('SyncML: try to add contentype ' . $contentType .' to '. $hordeType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					$guid = $registry->call($hordeType . '/import',
						array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
					if (!is_a($guid, 'PEAR_Error')) {
						$ts = $history->getTSforAction($guid, 'add');
						$state->setUID($type, $syncItem->getLocURI(), $guid, $ts);
						$state->log("Client-AddReplace");
						Horde::logMessage('SyncML: r/ added client entry as ' . $guid, __FILE__, __LINE__, PEAR_LOG_DEBUG);
					} else {
						Horde::logMessage('SyncML: Error in replacing/add client entry:' . $guid->message, __FILE__, __LINE__, PEAR_LOG_ERR);
						$state->log("Client-AddFailure");
					}
				}
		#	}
		}

		return true;
	}
    

    function loadData()
    {
        global $registry;

	$state = &$_SESSION['SyncML.state'];
        $syncType = $this->_targetLocURI;
        $hordeType = str_replace('./','',$syncType);

	Horde::logMessage("SyncML: reading added items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
	$state->setAddedItems($hordeType, $registry->call($hordeType. '/list', array()));
	$adds = &$state->getAddedItems($hordeType);
	$this->_syncDataLoaded = TRUE;

	return count($state->getAddedItems($hordeType));
    }

}
