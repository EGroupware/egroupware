<?php
/**
 * EGroupware API - Json Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Json;

use EGroupware\Api;

/**
 * A method or function was called with a wrong or missing parameter
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 */
class Exception extends Api\Exception\WrongParameter { }
