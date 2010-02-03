<?php
/**
 * eGroupWare - SyncML based on Horde 3
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
include_once 'Horde/SyncML/Sync.php';

class Horde_SyncML_Sync_RefreshFromServerSync extends Horde_SyncML_Sync_TwoWaySync {
	function handleSync($currentCmdID, $hordeType, $syncType, &$output, $refts) {
		global $registry;

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

		if (isset($state->curSyncItem)) {
			// Finish the pending sync item
			$cmd = &$state->curSyncItem;
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

		$adds = &$state->getAddedItems($syncType);
		Horde::logMessage("SyncML: ".count($adds).
			' added items found for '.$syncType  ,
			__FILE__, __LINE__, PEAR_LOG_DEBUG);

		if(is_array($adds)) {
			while($guid = array_shift($adds)) {
				$currentSize = $output->getOutputSize();
				// return if we have to much data
				if (($maxEntries && ($state->getNumberOfElements() >= $maxEntries)
					&& isset($contentType['mayFragment'])
					&& $contentType['mayFragment'])
					|| ($maxMsgSize
						&& (($currentSize + MIN_MSG_LEFT * 2) > $maxMsgSize))) {
					// put the item back in the queue
					$adds[] = $guid;
					$state->maxNumberOfElements();
					$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
					return $currentCmdID;
				}

				if ($locID = $state->getLocID($syncType, $guid)) {
					Horde::logMessage("SyncML: RefreshFromServerSync add to client: $guid ignored, already at client($locID)",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
				}

				$guid_ts = $state->getSyncTSforAction($guid, 'add');
                if ($guid_ts > $serverAnchorNext) {
					// Change was made after we started this sync.
					// Don't sent this now to the client.
					Horde::logMessage("SyncML: RefreshFromServerSync add $guid is in our future",
						__FILE__, __LINE__, PEAR_LOG_DEBUG);
					continue;
                }

				$contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI, $this->_targetLocURI);
				$c = $registry->call($hordeType . '/export', array('guid' => $guid, 'contentType' => $contentType));

				if ($c === false) continue; // no content to export

				if (is_a($c, 'PEAR_Error')) {
					Horde::logMessage("SyncML: refresh failed to export guid $guid:\n" . print_r($c, true),
						__FILE__, __LINE__, PEAR_LOG_WARNING);
					$state->log("Server-ExportFailed");
					continue;
				}

                $size = strlen($c);
                // return if we have to much data
                if ($maxMsgSize && !$deviceInfo['supportLargeObjs']) {
                    if (($size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
                        Horde::logMessage("SyncML: refresh failed to export guid $guid due to size $size",
                        	__FILE__, __LINE__, PEAR_LOG_ERROR);
                        $state->log("Server-ExportFailed");
                        continue;
                    }
                    if (($currentSize + $size + MIN_MSG_LEFT * 2) > $maxMsgSize) {
                        // put the item back in the queue
						$adds[] = $guid;
                        $state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
                        return $currentCmdID;
					}
                }

				Horde::logMessage("SyncML: refresh add $guid to client\n$c",
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

				// moreData split; put the guid back in the list and return
				if ($cmd->hasMoreData()) {
					$state->curSyncItem = &$cmd;
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

	function loadData() {
		global $registry;

		$state = &$_SESSION['SyncML.state'];
		$syncType = $this->_targetLocURI;
		$hordeType = $state->getHordeType($syncType);
		$state->setTargetURI($syncType);
		$future = $state->getServerAnchorNext($syncType);
		$delta_add = 0;

		Horde::logMessage("SyncML: reading added items from database for $hordeType",
			__FILE__, __LINE__, PEAR_LOG_DEBUG);
		/* The items, which now match the filter criteria are show here, too
		$state->setAddedItems($syncType, $registry->call($hordeType. '/listBy',
			array('action' => 'add',
					'timestamp' => $future,
					'type' => $syncType,
					'filter' => $this->_filterExpression)));
		$delta_add = count($state->getAddedItems($hordeType));
		*/
		$state->mergeAddedItems($syncType, $registry->call($hordeType. '/list',array('filter' => $this->_filterExpression)));

		$this->_syncDataLoaded = TRUE;

		return count($state->getAddedItems($syncType)) - $delta_add;
	}
}
