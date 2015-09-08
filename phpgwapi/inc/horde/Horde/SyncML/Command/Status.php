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
include_once 'Horde/SyncML/State.php';
include_once 'Horde/SyncML/Command.php';

class Horde_SyncML_Command_Status extends Horde_SyncML_Command {

   /**
     * Name of the command.
     *
     * @var string
     */
    var $_cmdName = 'Status';

    /**
     * The Response code of the command sent to the client, that this
     * Status response refers to.
     *
     * @var integer
     */
    var $_response;

    /**
     * The command ID (CmdID) of the command sent to the client, that this
     * Status response refers to.
     *
     * @var integer
     */
    var $_cmdRef;

    /**
     * The command (Add, Replace, etc) sent to the client, that this Status
     * response refers to.
     *
     * @var string
     */
    var $_cmd;

    /**
     * The server ID of the sent object, that this Status response refers to.
     *
     * This element is optional. If specified, Status response refers to a
     * single Item in the command sent to the client. It refers to all Items in
     * the sent command otherwise.
     *
     * @var string
     */
    var $_sourceRef;


    /**
     * The client ID of the sent object, that this Status response refers to.
     *
     * This element is optional. If specified, Status response refers to a
     * single Item in the command sent to the client. It refers to all Items in
     * the sent command otherwise.
     *
     * @var string
     */
    var $_targetRef;

    var $_chalMetaFormat;

    var $_chalMetaType;

    var $_chalMetaNextNonce;

    var $_itemDataAnchorNext;

    var $_itemDataAnchorLast;

    var $_itemTargetLocURI;

    var $_itemSourceLocURI;

    var $_syncItems;

   /**
     * Constructor.
     *
     * @param integer $response   The response code.
     * @param string  $cmd        The command sent to the client,
     *                             that this Status response refers to.
     */
    function Horde_SyncML_Command_Status($response = null, $cmd = null)
    {
        if ($response != null) {
            $this->_response = $response;
        }

        if ($cmd != null) {
            $this->_cmd = $cmd;
        }
    }

    function output($currentCmdID, &$output)
    {
        $state = &$_SESSION['SyncML.state'];

        $attrs = array();

        if ($this->_cmd != null) {
            $output->startElement($state->getURI(), 'Status', $attrs);

            $output->startElement($state->getURI(), 'CmdID', $attrs);
            $chars = $currentCmdID;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdID');

            $output->startElement($state->getURI(), 'MsgRef', $attrs);
            $chars = $state->getMsgID();
            $output->characters($chars);
            $output->endElement($state->getURI(), 'MsgRef');

            $output->startElement($state->getURI(), 'CmdRef', $attrs);
            $chars = $this->_cmdRef;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'CmdRef');

            $output->startElement($state->getURI(), 'Cmd', $attrs);
            $chars = $this->_cmd;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Cmd');

            if (isset($this->_targetRef)) {
                $output->startElement($state->getURI(), 'TargetRef', $attrs);
                $chars = $this->_targetRef;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'TargetRef');
            }

            if (isset($this->_sourceRef)) {
                $output->startElement($state->getURI(), 'SourceRef', $attrs);
                $chars = $this->_sourceRef;
                $output->characters($chars);
                $output->endElement($state->getURI(), 'SourceRef');
            }

            // If we are responding to the SyncHdr and we are not
            // authorized then request basic authorization.
            //
            // FIXME: Right now we always send this, ignoring the
            // isAuthorized() test. Is that correct?
            if ($this->_cmd == 'SyncHdr' && !$state->isAuthorized()) {
                $this->_chalMetaFormat = 'b64';
                $this->_chalMetaType = 'syncml:auth-basic';
            }

            if (isset($this->_chalMetaFormat) && isset($this->_chalMetaType)) {
                $output->startElement($state->getURI(), 'Chal', $attrs);
                $output->startElement($state->getURI(), 'Meta', $attrs);

                $metainfuri = $state->getURIMeta();

                $output->startElement($metainfuri, 'Format', $attrs);
                $chars = $this->_chalMetaFormat;
                $output->characters($chars);
                $output->endElement($metainfuri, 'Format');

                $output->startElement($metainfuri, 'Type', $attrs);
                $chars = $this->_chalMetaType;
                $output->characters($chars);
                $output->endElement($metainfuri, 'Type');

                // $output->startElement($metainfuri, 'NextNonce', $attrs);
                // $chars = $this->_chalMetaNextNonce;
                // $output->characters($chars);
                // $output->endElement($metainfuri, 'NextNonce');

                $output->endElement($state->getURI(), 'Meta');
                $output->endElement($state->getURI(), 'Chal');
            }

            $output->startElement($state->getURI(), 'Data', $attrs);
            $chars = $this->_response;
            $output->characters($chars);
            $output->endElement($state->getURI(), 'Data');

            if (isset($this->_itemDataAnchorNext) || isset($this->_itemDataAnchorLast)) {
                $output->startElement($state->getURI(), 'Item', $attrs);
                $output->startElement($state->getURI(), 'Data', $attrs);

                $metainfuri = $state->getURIMeta();
                // $metainfuri = $state->getURI(); // debug by FOU

                $output->startElement($metainfuri, 'Anchor', $attrs);

                if (isset($this->_itemDataAnchorNext)) {

                  $output->startElement($metainfuri, 'Next', $attrs);
                  $chars = $this->_itemDataAnchorNext;
                  $output->characters($chars);
                  $output->endElement($metainfuri, 'Next');
                }

                if (isset($this->_itemDataAnchorLast)) {

                  $output->startElement($metainfuri, 'Last', $attrs);
                  $chars = $this->_itemDataAnchorLast;
                  $output->characters($chars);
                  $output->endElement($metainfuri, 'Last');
                }

                $output->endElement($metainfuri, 'Anchor');

                $output->endElement($state->getURI(), 'Data');
                $output->endElement($state->getURI(), 'Item');
            }

			if (isset($this->_syncItems)) {
				// Support multible items per command
				foreach ($this->_syncItems as $locURI => &$syncItem) {
					$output->startElement($state->getURI(), 'Item', $attrs);
					$output->startElement($state->getURI(), 'Source', $attrs);
					$output->startElement($state->getURI(), 'LocURI', $attrs);
					$output->characters($locURI);
					$output->endElement($state->getURI(), 'LocURI');
					$output->endElement($state->getURI(), 'Source');
					$output->endElement($state->getURI(), 'Item');
				}
			} elseif (isset($this->_itemTargetLocURI) || isset($this->_itemSourceLocURI)) {
				$output->startElement($state->getURI(), 'Item', $attrs);

				if (isset($this->_itemTargetLocURI)) {
					$output->startElement($state->getURI(), 'Target', $attrs);
					$output->startElement($state->getURI(), 'LocURI', $attrs);
					$output->characters($this->_itemTargetLocURI);
					$output->endElement($state->getURI(), 'LocURI');
					$output->endElement($state->getURI(), 'Target');
				}
				if (isset($this->_itemSourceLocURI)) {
					$output->startElement($state->getURI(), 'Source', $attrs);
					$output->startElement($state->getURI(), 'LocURI', $attrs);
					$output->characters($this->_itemSourceLocURI);
					$output->endElement($state->getURI(), 'LocURI');
					$output->endElement($state->getURI(), 'Source');
				}
				$output->endElement($state->getURI(), 'Item');
			}

            $output->endElement($state->getURI(), 'Status');

            $currentCmdID++;

        }

        return $currentCmdID;
    }

    /**
     * Setter for property response.
     *
     * @param string $response  New value of property response.
     */
    function setResponse($response)
    {
        $this->_response = $response;
    }

    /**
     * Setter for property cmd.
     *
     * @param string $cmd  New value of property cmd.
     */
    function setCmd($cmd)
    {
        $this->_cmd = $cmd;
    }

    /**
     * Setter for property cmdRef.
     *
     * @param string $cmdRef  New value of property cmdRef.
     */
    function setCmdRef($cmdRef)
    {
        $this->_cmdRef = $cmdRef;
    }

    /**
     * Setter for property sourceRef.
     *
     * @param string $sourceRef  New value of property sourceRef.
     */
    function setSourceRef($sourceRef)
    {
        $this->_sourceRef = $sourceRef;
    }

    /**
     * Setter for property targetRef.
     *
     * @param string $targetRef  New value of property targetRef.
     */
    function setTargetRef($targetRef)
    {
        $this->_targetRef = $targetRef;
    }

    /**
     * Setter for property itemDataAnchorNext.
     *
     * @param string $itemDataAnchorNext  New value of property itemDataAnchorNext.
     */
    function setItemDataAnchorNext($itemDataAnchorNext)
    {
        $this->_itemDataAnchorNext = $itemDataAnchorNext;
    }

    /**
     * Setter for property itemDataAnchorLast.
     *
     * @param string $itemDataAnchorLast  New value of property itemDataAnchorLast.
     */
    function setItemDataAnchorLast($itemDataAnchorLast)
    {
        $this->_itemDataAnchorLast = $itemDataAnchorLast;
    }

    /**
     * Setter for property itemSourceLocURI.
     *
     * @param string $itemSourceLocURI  New value of property itemSourceLocURI.
     */
    function setItemSourceLocURI($itemSourceLocURI)
    {
        $this->_itemSourceLocURI = $itemSourceLocURI;
    }

    /**
     * Setter for property itemTargetLocURI.
     *
     * @param string $itemTargetLocURI  New value of property itemTargetLocURI.
     */
    function setItemTargetLocURI($itemTargetLocURI)
    {
        $this->_itemTargetLocURI = $itemTargetLocURI;
    }

    /**
     * Setter for the the list of handled SyncItems
     *
     * @param array $syncItems  The Items of the command
     */
    function setSyncItems(&$syncItems)
    {
        $this->_syncItems = $syncItems;
    }
}
