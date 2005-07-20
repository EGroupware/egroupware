<?php

include_once 'Horde/SyncML/Command.php';

/**
 * $Horde: framework/SyncML/SyncML/Command/Sync/SyncElement.php,v 1.11 2004/07/02 19:24:44 chuck Exp $
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
class Horde_SyncML_Command_Sync_SyncElement extends Horde_SyncML_Command {

    var $_luid;
    var $_guid;
    var $_isSource;
    var $_content;
    var $_contentType;
    var $_status = RESPONSE_OK;

    function &factory($command, $params = null)
    {
        @include_once 'Horde/SyncML/Command/Sync/' . $command . '.php';
        $class = 'Horde_SyncML_Command_Sync_' . $command;
        if (class_exists($class)) {
            #Horde::logMessage('SyncML: Class definition of ' . $class . ' found in SyncElement::factory.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            return $element = &new $class($params);
        } else {
            Horde::logMessage('SyncML: Class definition of ' . $class . ' not found in SyncElement::factory.', __FILE__, __LINE__, PEAR_LOG_DEBUG);
            require_once 'PEAR.php';
            return PEAR::raiseError('Class definition of ' . $class . ' not found.');
        }
    }

    function startElement($uri, $element, $attrs)
    {
        parent::startElement($uri, $element, $attrs);

        switch ($this->_xmlStack) {
        case 3:
            if ($element == 'Source') {
                $this->_isSource = true;
            }
            break;
        }
    }

    function endElement($uri, $element)
    {
    	$search = array('/ *\n/','/ *$/m');
    	$replace = array('','');
        switch ($this->_xmlStack) {
        case 1:
            // Need to add sync elements to the Sync method?
            break;

        case 3:
            if ($element == 'Source') {
                $this->_isSource = false;
            } elseif ($element == 'Data') {
                $this->_content = $this->_chars;
            } elseif ($element == 'MoreData') {
                $this->_moreData = TRUE;
            } elseif ($element == 'Type') {
            	if(!isset($this->_contentType))
                	$this->_contentType = trim($this->_chars);
            }
            break;

        case 4:
            if ($element == 'LocURI' && $this->_isSource) {
                $this->_luid = trim($this->_chars);
            } elseif ($element == 'Type') {
                $this->_contentType = trim($this->_chars);
            } elseif ($element == 'Size') {
                $this->_contentSize = trim($this->_chars);
            }
            break;
        }

        parent::endElement($uri, $element);
    }

    function getLocURI()
    {
        return $this->_luid;
    }

    function getGUID()
    {
        return $this->_guid;
    }

    function setLocURI($luid)
    {
        $this->_luid = $luid;
    }

    function setGUID($guid)
    {
        $this->_guid = $guid;
    }

    function setContentType($c)
    {
        $this->_contentType = $c;
    }

    function getContentType()
    {
        return $this->_contentType;
    }

    function getContent()
    {
        return $this->_content;
    }

    function setContent($content)
    {
        $this->_content = $content;
    }
    
    function setStatus($_status)
    {
    	$this->_status = $_status;
    }
}
