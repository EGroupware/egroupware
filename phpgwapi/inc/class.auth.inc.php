<?php
/**
 * EGroupware API - Authentication
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @author Miles Lott <milos@groupwhere.org>
 * @copyright 2004 by Miles Lott <milos@groupwhere.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Authentication
 *
 * @deprecated use Api\Auth
 */
class auth extends Api\Auth
{
	/**
	 * Retired password check method called all over the place
	 *
	 * @deprecated use check_password_change
	 */
	static function check_password_age()
	{
		return true;	// no change
	}
}

/**
 * @deprecated use Api\Auth\Backend
 */
interface auth_backend extends Api\Auth\Backend {}

/**
 * @deprecated use Api\Auth\Fallback
 */
class auth_fallback extends Api\Auth\Fallback {}
