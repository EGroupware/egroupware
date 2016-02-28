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

use EGroupware\Api;

/**
 * An necessary assumption the developer made failed, regular execution can not continue
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 */
class AssertionFailed extends Api\Exception { }
