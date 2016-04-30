<?php
/**
 * EGroupware: Preferences ACL
*
* @link http://www.egroupware.org
* @author Ralf Becker <rb@stylite.de>
* @package admin
* @copyright (c) 2013-16 by Ralf Becker <rb@stylite.de>
* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
* @version $Id$
*/

use EGroupware\Api;
use EGroupware\Api\Framework;

/**
 * Preferences ACL
 *
 * Uses admin_acl, which already does necessary access-control for non-admin users!
 */
class preferences_acl extends admin_acl
{
	function __construct()
	{
		Api\Translation::add_app('admin');
		Framework::includeCSS('admin', 'app');

		parent::__construct();
	}
}