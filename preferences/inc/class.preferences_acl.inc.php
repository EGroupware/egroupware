<?php
/**
 * EGroupware: Preferences ACL
*
* @link http://www.egroupware.org
* @author Ralf Becker <rb@stylite.de>
* @package admin
* @copyright (c) 2013 by Ralf Becker <rb@stylite.de>
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @version $Id$
*/

/**
 * Preferences ACL
 *
 * Uses admin_acl, which already does necessary access-control for non-admin users!
 */
class preferences_acl extends admin_acl
{
	/**
	 * Appname we are running as
	 */
	const APPNAME = 'preferences';

	function __construct()
	{
		translation::add_app('admin');
		egw_framework::includeCSS('admin', 'app');

		parent::__construct();
	}
}