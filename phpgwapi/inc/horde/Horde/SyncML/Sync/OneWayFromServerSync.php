<?php

include_once 'Horde/SyncML/Sync.php';

/**
 * Will run normal $end from TwoWaySync, the client should not send
 * any changes.
 *
 * $Horde: framework/SyncML/SyncML/Sync/OneWayFromServerSync.php,v 1.6 2004/05/26 17:32:50 chuck Exp $
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
class Horde_SyncML_Sync_OneWayFromServerSync extends Horde_SyncML_Sync {

    function endSync($currentCmdID, &$output)
    {
        global $registry;
        $state = &$_SESSION['SyncML.state'];
        
        // counter for synced items
        $syncItems = 0;

        $syncType = $this->_targetLocURI;
        $hordeType = str_replace('./','',$syncType);

	if(!$adds = &$state->getAddedItems($hordeType))
	{
		Horde::logMessage("SyncML: reading list of added items", __FILE__, __LINE__, PEAR_LOG_DEBUG);
		$adds = $registry->call($hordeType, '/list', array());
		$adds = &$state->getAddedItems($hordeType);
	}
	Horde::logMessage("SyncML: ....... ".count($adds).' items to send ..............', __FILE__, __LINE__, PEAR_LOG_DEBUG);
        
        Horde::logMessage("SyncML: starting OneWayFromServerSync ($hordeType)", __FILE__, __LINE__, PEAR_LOG_DEBUG);

        #foreach ($adds as $guid) {
        while($guid = array_shift($adds))
        {
            $contentType = $state->getPreferedContentTypeClient($this->_sourceLocURI);

            $cmd = &new Horde_SyncML_Command_Sync_ContentSyncElement();
            $c = $registry->call($hordeType . '/export',
                                 array('guid' => $guid,
                                       'contentType' => $contentType
                                 )
            );
            if (!is_a($c, 'PEAR_Error')) {
                // Item in history but not in database. Strange, but
                // can happen.
#LK		$cmd->setContent($state->convertServer2Client($c, $contentType));
                $cmd->setContent($c);
                $cmd->setContentType($contentType['ContentType']);
                $cmd->setSourceURI($guid);
                $currentCmdID = $cmd->outputCommand($currentCmdID, $output, 'Add');
                $state->log('Server-Add');

		$syncItems++;
		// return if we have to much data
		if($syncItems >= MAX_ENTRIES)
		{
			$state->setMoreDataPending();
			return $currentCmdID;
		}
            }
        }
	Horde::logMessage("SyncML: handling OneWayFromServerSync done ".$currentCmdID, __FILE__, __LINE__, PEAR_LOG_DEBUG);

        return $currentCmdID;
    }

}
