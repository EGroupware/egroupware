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
	function handleSync($currentCmdID, $hordeType, $syncType, &$output, $refts) {
		global $registry;
		
		$history = $GLOBALS['egw']->contenthistory;
		$state = &$_SESSION['SyncML.state'];
		
		$serverAnchorNext = $state->getServerAnchorNext($syncType);
		
		// now we remove all UID from contentmap that have not been verified in this slowsync
		$state->removeOldUID($syncType, $serverAnchorNext);
		$adds = &$state->getAddedItems($hordeType);
		Horde::logMessage("SyncML: ".count($adds).   ' added items found for '.$hordeType  , __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$counter = 0;
		
		if(is_array($adds)) {
			while($guid = array_shift($adds)) {
				if ($locID = $state->getLocID($syncType, $guid)) {
					Horde::logMessage("SyncML: slowsync add to client: $guid ignored, already at client($locID)", __FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}
				
				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$cmd = &new Horde_SyncML_Command_Sync_ContentSyncElement();
				$c = $registry->call($hordeType . '/export', array('guid' => $guid, 'contentType' => $contentType));
				#Horde::logMessage("SyncML: slowsync add guid $guid to client ". print_r($c, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
				if (!is_a($c, 'PEAR_Error')) {
					$cmd->setContent($c);
					$cmd->setContentType($contentType['ContentType']);
					if (isset($contentType['ContentFormat']))
					{
						$cmd->setContentFormat($contentType['ContentFormat']);
					}
					
					$cmd->setSourceURI($guid);
					$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
					$state->log('Server-Add');
					
					// return if we have to much data
					if(++$counter >= MAX_ENTRIES
						&& isset($contentType['mayFragment'])
						&& $contentType['mayFragment'])
					{
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
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
		
		$type = $this->_targetLocURI;
		$hordeType = $state->getHordeType($type);
		
		$syncElementItems = $command->getSyncElementItems();
		
		foreach($syncElementItems as $syncItem) {
			if(!$contentType = $syncItem->getContentType()) {
				$contentType = $state->getPreferedContentType($type);
			}
			
			if (($contentType == 'text/x-vcalendar' || $contentType == 'text/calendar')
				&& strpos($syncItem->getContent(), 'BEGIN:VTODO') !== false)
			{
				$hordeType = 'tasks';
			}
		
			$guid = false;
			
			$guid = $registry->call($hordeType . '/search',
				array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType, $state->getGlobalUID($type, $syncItem->getLocURI()) ));
			
			if ($guid) {
				# entry exists in database already. Just update the mapping
				Horde::logMessage('SyncML: adding mapping for locuri:'. $syncItem->getLocURI() . ' and guid:' . $guid , __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$state->setUID($type, $syncItem->getLocURI(), $guid, mktime());
				$state->log("Client-Replace");
			} else {
				# Entry does not exist in database: add a new one.
				$state->removeUID($type, $syncItem->getLocURI());
				Horde::logMessage('SyncML: try to add contentype ' . $contentType .' to '. $hordeType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
				$guid = $registry->call($hordeType . '/import',
					array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
				if (!is_a($guid, 'PEAR_Error') && $guid != false) {
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

		return true;
	}
	
	function loadData() {
		global $registry;
		
		$state = &$_SESSION['SyncML.state'];
		$syncType = $this->_targetLocURI;
		$hordeType = $state->getHordeType($syncType);
		
		Horde::logMessage("SyncML: reading added items from database for $hordeType", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$state->setAddedItems($hordeType, $registry->call($hordeType. '/list', array()));
		$adds = &$state->getAddedItems($hordeType);
		$this->_syncDataLoaded = TRUE;
		
		return count($state->getAddedItems($hordeType));
	}
}
