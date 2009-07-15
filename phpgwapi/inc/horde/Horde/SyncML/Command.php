<?php
/**
 * eGroupWare - SyncML based on Horde 3
 *
 * The SyncML_Command class provides a base class for handling all <SyncBody>
 * commands.
 *
 * A SyncML command is a protocol primitive. Each SyncML command specifies to
 * a recipient an individual operation that is to be performed.
 *
 * The SyncML_Command objects are hooked into the XML parser of the
 * SyncML_ContentHandler class and are reponsible for parsing a single command
 * inside the SyncBody section of a SyncML message. All actions that must be
 * executed for a single SyncML command are handled by these objects, by means
 * of the handleCommand() method.
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Anthony Mills <amills@pyramid6.com>
 * @author Jan Schneider <jan@horde.org>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id$
 */
include_once 'Horde/SyncML/State_egw.php';

class Horde_SyncML_Command {

    /**
     * Name of the command, like 'Put'.
     *
     * Must be overwritten by a sub class.
     *
     * @var string
     */
    var $_cmdName;

    /**
     * The command ID (<CmdID>).
     *
     * @var integer
     */
    var $_cmdID;

    /**
     * Stack for holding the XML elements during creation of the object from
     * the XML event flow.
     *
     * @var array
     */
    var $_stack = array();

    /**
     * Buffer for the parsed character data.
     *
     * @var string
     */
    var $_chars = '';

    /**
     * Start element handler for the XML parser, delegated from
     * SyncML_ContentHandler::startElement().
     *
     * @param string $uri      The namespace URI of the element.
     * @param string $element  The element tag name.
     * @param array $attrs     A hash with the element's attributes.
     */
    function startElement($uri, $element, $attrs)
    {
        $this->_stack[] = $element;
    }

    /**
     * End element handler for the XML parser, delegated from
     * SyncML_ContentHandler::endElement().
     *
     * @param string $uri      The namespace URI of the element.
     * @param string $element  The element tag name.
     */
    function endElement($uri, $element)
    {
        if (count($this->_stack) == 2 &&
            $element == 'CmdID') {
            $this->_cmdID = intval(trim($this->_chars));
        }

        if (strlen($this->_chars)) {
            $this->_chars = '';
        }

        array_pop($this->_stack);
    }

    /**
     * Character data handler for the XML parser, delegated from
     * SyncML_ContentHandler::characters().
     *
     * @param string $str  The data string.
     */
    function characters($str)
    {
        if (isset($this->_chars)) {
            $this->_chars .= $str;
        } else {
            $this->_chars = $str;
        }
    }

    /**
     * Returns the command name this instance is reponsible for.
     *
     * @return string  The command name this object is handling.
     */
    function getCommandName()
    {
        return $this->_cmdName;
    }

    /**
     * This method is supposed to implement the actual business logic of the
     * command once the XML parsing is complete.
     *
     * @abstract
     */
    function output($currentCmdID, &$output)
    {
    }

    /**
     * Attempts to return a concrete Horde_SyncML_Command instance based on
     * $command.
     *
     * @param string $command                  The type of the concrete
     *                                         SyncML_Comment subclass to
     *                                         return.
     * @param  $params         Optional Parameter.
     *
     * @return SyncML_Command  The newly created concrete SyncML_Command
     *                         instance, or false on error.
     */
    function &factory($command, $params = null)
    {
        $command = basename($command);
        $class = 'Horde_SyncML_Command_' . $command;

        if (!class_exists($class)) {
            include_once 'Horde/SyncML/Command/' . $command . '.php';
        }
        if (class_exists($class)) {
            $cmd = new $class($params);
        } else {
            $msg = 'SyncML: Class definition of ' . $class . ' not found.';
            Horde::logMessage($msg, __FILE__, __LINE__, PEAR_LOG_ERR);
            require_once 'PEAR.php';
            $cmd = PEAR::raiseError($msg);
        }

        return $cmd;
    }

}
