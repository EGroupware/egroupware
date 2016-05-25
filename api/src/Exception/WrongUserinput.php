<?php
/**
 * EGroupware API - Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Exception;

/**
 * Wrong or missing required user input: message should be translated so it can be shown directly to the user
 *
 */
class WrongUserinput extends AssertionFailed { }
