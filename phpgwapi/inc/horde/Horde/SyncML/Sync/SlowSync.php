<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * Slow sync may just work; I think most of the work is going to be
 * done by the API.
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/Sync/TwoWaySync.php';

class Horde_SyncML_Sync_SlowSync extends Horde_SyncML_Sync_TwoWaySync {

	function handleSync($currentCmdID, $hordeType, $syncType, &$output, $refts) {
		global $registry;

		$history = $GLOBALS['egw']->contenthistory;
		$state = &$_SESSION['SyncML.state'];
		$maxMsgSize = $state->getMaxMsgSizeClient();
		$deviceInfo = $state->getClientDeviceInfo();

		if (isset($deviceInfo['maxEntries'])) {
			$maxEntries = $deviceInfo['maxEntries'];
			if (!$maxMsgSize && !$maxEntries) {
				// fallback to default
				$maxEntries = MAX_ENTRIES;
			}
		} else {
			$maxEntries = MAX_ENTRIES;
		}

		$serverAnchorNext = $state->getServerAnchorNext($syncType);

		// now we remove all UID from contentmap that have not been verified in this slowsync
		$state->removeOldUID($syncType, $serverAnchorNext);

		if (isset($state->curSyncItem)) {
			// Finish the pending sync item
			$cmd = &$state->curSyncItem;
			if (!is_a($cmd, 'Horde_SyncML_Command_Sync_ContentSyncElement')) {
				// Conflict with other datastore
				Horde :: logMessage("SyncML: handleSync($currentCmdID, $hordeType, $syncType) moreData conflict found",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
				$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
				return $currentCmdID;
			}
			unset($state->curSyncItem);
			$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Sync');

			// moreData split; save in session state and end current message
			if ($cmd->hasMoreData()) {
				$state->curSyncItem = &$cmd;
				$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
				return $currentCmdID;
			}
			$state->incNumberOfElements();
		}

		$adds =& $state->getAddedItems($syncType);
		$conflicts =& $state->getConflictItems($syncType);
		Horde::logMessage('SyncML: ' .count($adds). ' added items found for ' .$syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);
		Horde::logMessage('SyncML: ' . count($conflicts) . ' items to delete on client found for ' . $syncType, __FILE__, __LINE__, PEAR_LOG_DEBUG);

		if (is_array($adds)) {
			while ($guid = array_shift($adds)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset($contentType['mayFragment'])
					&& $contentType['mayFragment']) ||
					($maxMsgSize && (($currentSize + MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$adds[] = $guid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}

				if (($locID = $state->getLocID($syncType, $guid))) {
					Horde::logMessage("SyncML: slowsync add to client: $guid ignored, already at client($locID)",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$guid_ts = $state->getSyncTSforAction($guid, 'add');
				if ($guid_ts > $serverAnchorNext) {
					// Change was made after we started this sync.
					// Don't sent this now to the client.
					Horde::logMessage("SyncML: slowsync add $guid is in our future",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$c = $registry->call($hordeType . '/export', array('guid' => $guid, 'contentType' => $contentType));

				if ($c === false) continue; // no content to export

				if (is_a($c, 'PEAR_Error')) {
					Horde::logMessage("SyncML: slowsync failed to export guid $guid:\n" . print_r($c, true),
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					continue;
				}

				$size = strlen($c);
				// return if we have to much data
				if ($maxMsgSize && !$deviceInfo['supportLargeObjs']) {
					if (($size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
						Horde::logMessage("SyncML: slowsync failed to export guid $guid due to size $size",
							__FILE__, __LINE__, PEAR_LOG_ERROR);
						continue;
					}
					if (($currentSize + $size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
						// put the item back in the queue
						$adds[] = $guid;
						$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
						return $currentCmdID;
					}
				}

				Horde::logMessage("SyncML: slowsync add guid $guid to client\n$c",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setContent($c);
				$cmd->setContentType($contentType['ContentType']);
				if (isset($contentType['ContentFormat'])) {
					$cmd->setContentFormat($contentType['ContentFormat']);
				}
				$cmd->setGUID($guid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
				$state->log('Server-Add');

				// moreData split; save in session state and end current message
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = &$cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}
		// handle remote deletes due to conflicts
		if (count($conflicts) > 0) {
			while ($locid = array_shift($conflicts)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset ($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize +MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$conflicts[] = $locid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				Horde :: logMessage("SyncML: delete client locid: $locid",
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
				// Create a Delete request for client.
				$cmd = new Horde_SyncML_Command_Sync_ContentSyncElement();
				$cmd->setLocURI($locid);
				$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Delete');
				$state->log('Server-DeletedConflicts');
				$state->removeUID($syncType, $locid);

				// moreData split; save in session state and end current message
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = & $cmd;
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}
				$state->incNumberOfElements();
			}
		}
		Horde::logMessage("SyncML: All items handled for sync $syncType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		$state->removeExpiredUID($syncType, $serverAnchorNext);
		$state->clearSync($syncType);

		return $currentCmdID;
	}

	/**
	* Here's where the actual processing of a client-sent Sync
	* Command takes place. Entries are added or replaced
	* from the server database by using Horde API (Registry) calls.
	*/
	function runSyncCommand(&$command) {
		global $registry;
		$history = $GLOBALS['egw']->contenthistory;
		$state = &$_SESSION['SyncML.state'];

		if ($command->hasMoreData()) {
			Horde::logMessage('SyncML: moreData: TRUE', __FILE__, __LINE__, PEAR_LOG_DEBUG);
			$command->setStatus(RESPONSE_CHUNKED_ITEM_ACCEPTED_AND_BUFFERED);
			return true;
		}

		$type = $this->_targetLocURI;

		$syncml_prefs = $GLOBALS['egw_info']['user']['preferences']['syncml'];
		if (isset($syncml_prefs[$type])) {
			$sync_conflicts = $syncml_prefs[$type];
		} else {
			$sync_conflicts = CONFLICT_SERVER_WINNING;
		}

		$hordeType = $state->getHordeType($type);

		$syncElementItems = $command->getSyncElementItems();

		foreach($syncElementItems as $syncItem) {

			$contentSize = strlen($syncItem->_content);
			if ((($size = $syncItem->getContentSize()) !== false) &&
				($contentSize != $size) &&
				($contentSize + 1 != $size)) {
				Horde::logMessage('SyncML: content size missmatch for LocURI ' . $syncItem->_luid .
					": $contentSize ($size)", __FILE__, __LINE__, PEAR_LOG_ERROR);
				$command->setStatus(RESPONSE_SIZE_MISMATCH);
				return;
			}

			if(!$contentType = $syncItem->getContentType()) {
				$contentType = $state->getPreferedContentType($type);
			}

			if (($contentType == 'text/x-vcalendar' || $contentType == 'text/calendar')
				&& strpos($syncItem->getContent(), 'BEGIN:VTODO') !== false) {
				$hordeType = 'tasks';
			}

			$guid = false;

			$oguid = $state->getGlobalUID($type, $syncItem->getLocURI());

			$guid = $registry->call($hordeType . '/search',
				array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType, $oguid, $type));

			if ($guid) {
				// Check if the found entry came from the client
				$guid_ts = $state->getSyncTSforAction($guid, 'add');
				$sync_ts = $state->getChangeTS($type, $guid);
				if ($oguid != $guid && $sync_ts && $sync_ts == $guid_ts) {
					// Entry came from the client, so we get a duplicate here
					Horde::logMessage('SyncML: CONFLICT for locuri ' . $syncItem->getLocURI()
						. ' guid ' . $guid , __FILE__, __LINE__, PEAR_LOG_WARNING);
					if 	($sync_conflicts != CONFLICT_RESOLVED_WITH_DUPLICATE) {
						$state->log("Client-AddReplaceIgnored");
						continue;
					}
				} else {
					# Entry exists in database already. Just update the mapping
					Horde::logMessage('SyncML: adding mapping for locuri:'
						. $syncItem->getLocURI() . ' and guid:' . $guid,
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
						$state->setUID($type, $syncItem->getLocURI(), $guid);
						$state->log("Client-Map");
						continue;
				}
			}
			if ($sync_conflicts > CONFLICT_RESOLVED_WITH_DUPLICATE) {
				// We enforce the client not to change anything
				if ($sync_conflicts > CONFLICT_CLIENT_CHANGES_IGNORED) {
					// delete this item from client
					Horde::logMessage('SyncML: Server RO! REMOVE ' . $syncItem->getLocURI()
					. ' from client', __FILE__, __LINE__, PEAR_LOG_WARNING);
					$state->addConflictItem($type, $syncItem->getLocURI());
				} else {
					Horde::logMessage('SyncML: Server RO! REJECT all client changes',
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					$state->log("Client-AddReplaceIgnored");
				}
				$command->setStatus(RESPONSE_NO_EXECUTED);
				continue;
			}

			// Add entry to the database.
			$state->removeUID($type, $syncItem->getLocURI());
			Horde::logMessage('SyncML: try to add contentype ' . $contentType .' to '. $hordeType,
				__FILE__, __LINE__, PEAR_LOG_DEBUG);
			$guid = $registry->call($hordeType . '/import',
				array($state->convertClient2Server($syncItem->getContent(), $contentType), $contentType));
			if (!is_a($guid, 'PEAR_Error') && $guid != false) {
				$state->setUID($type, $syncItem->getLocURI(), $guid);
				$state->log("Client-AddReplace");
				Horde::logMessage('SyncML: replaced/added client entry as ' . $guid,
					__FILE__, __LINE__, PEAR_LOG_DEBUG);
			} else {
				Horde::logMessage('SyncML: Error in replacing/add client entry:' . $guid->message,
					__FILE__, __LINE__, PEAR_LOG_ERR);
				$state->log("Client-AddFailure");
			}
		}

		return true;
	}

	function loadData() {
		global $registry;

		$state = &$_SESSION['SyncML.state'];
		$syncType = $this->_targetLocURI;
		$hordeType = $state->getHordeType($syncType);
		$state->setTargetURI($syncType);
		$future = $state->getServerAnchorNext($syncType);

		$state->mergeAddedItems($syncType, $registry->call($hordeType. '/list', array('filter' => $this->_filterExpression)));

		$this->_syncDataLoaded = TRUE;

		return count($state->getAddedItems($syncType)) + count($state->getConflictItems($syncType));
	}
}
