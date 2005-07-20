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
	    Horde::logMessage("SyncML: slowsync timestamp add: $guid sync_ts: $sync_ts anchorNext: ". $serverAnchorNext.' / '.time(), __FILE__, __LINE__, PEAR_LOG_DEBUG);
            // $sync_ts                       it got synced from client to server someone
            // $sync_ts >= $serverAnchorNext  it got synced from client to server in this sync package already
            if ($sync_ts && $sync_ts >= $serverAnchorNext) {
                // Change was done by us upon request of client.
                // Don't mirror that back to the client.
                //Horde::logMessage("SyncML: slowsync add: $guid ignored, came from client", __FILE__, __LINE__, PEAR_LOG_DEBUG);
                continue;
            }

#            $locid = $state->getLocID($syncType, $guid);
#

            // Create an Add request for client.
# LK            $contentType = $state->getPreferedContentTypeClient($syncType);
            $contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI);

            $cmd = &new Horde_SyncML_Command_Sync_ContentSyncElement();
            $c = $registry->call($hordeType . '/export',
                                 array('guid' => $guid,
                                       'contentType' => $contentType));
	    Horde::logMessage("SyncML: slowsync add to server $c", __FILE__, __LINE__, PEAR_LOG_DEBUG);
            if (!is_a($c, 'PEAR_Error')) {
                // Item in history but not in database. Strange, but
                // can happen.
#LK		$cmd->setContent($state->convertServer2Client($c, $contentType));
                $cmd->setContent($c);
                $cmd->setContentType($contentType['ContentType']);
                $cmd->setSourceURI($guid);
               	$currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
                $state->log('Server-Add');

                // return if we have to much data
                if(++$counter >= MAX_ENTRIES)
                {
	               	$state->setSyncStatus(SERVER_SYNC_DATA_PENDING);
                	return $currentCmdID;
                }
            }
        }
	Horde::logMessage("SyncML: handling sync ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);
	
       	$state->clearSync($syncType);

        return $currentCmdID;
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
