<?php
/**
 * EGroupware API - Authentication SSO (single-sign-on) backend interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 */

namespace EGroupware\Api\Auth;

/**
 * Interface for SSO authentication backends
 */
interface BackendSSO extends Backend
{
	/**
	 * Attempt SSO login
	 *
	 * @return string sessionid on successful login, null otherwise
	 */
	function login();

	/**
	 * Logout SSO system
	 */
	function logout();
}
