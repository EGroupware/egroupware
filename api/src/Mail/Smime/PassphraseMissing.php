<?php
/**
 * EGroupware API - Exceptions
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package Mail
 * @subpackage Smime
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Mail\Smime;

use EGroupware\Api;

/**
 * Smime passphrase missing exception
 *
 * As you get this only by an error in the code or during development, the message does not need to be translated
 */
class PassphraseMissing extends Api\Exception\AssertionFailed { }
