<?php
/**
 * EGroupware - Notifications
 *
 * serves the hook "framework_header" to register notifications/js/app.ts's own bundle
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package notifications
 * @subpackage ajaxpoup
 * @link http://www.egroupware.org
 */

use EGroupware\Api;

// Api\Framework\Ajax::header()'s _get_header() call (which resolves get_script_links() into the
// page's own JS include list, via _get_js()) runs BEFORE the 'after_navbar' hook - registering this
// bundle from hook_after_navbar.inc.php was too late to ever reach the rendered page (found live as
// a regression: the notification bell showed nothing and stopped reacting to clicks, because
// notifications/js/app.ts never actually loaded). 'framework_header' fires earlier, before
// _get_header(), so this is the one hook location where includeJS() here actually has an effect.
if (!($args['popup'] ?? false) && $GLOBALS['egw_info']['user']['apps']['notifications'])
{
	Api\Framework::includeJS('/notifications/js/app.min.js');
}
