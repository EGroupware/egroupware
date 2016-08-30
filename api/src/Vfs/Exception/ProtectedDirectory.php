<?php
/**
 * EGroupware API - Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package vfs
 * @subpackage exception
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Vfs\Exception;

/**
 * User or code tried to delete or rename a protected directory, see Vfs::isProtectedDir
 *
 * This exception extends \Exception to not catch it accidently.
 */
class ProtectedDirectory extends \Exception { }
