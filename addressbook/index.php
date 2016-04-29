<?php
/**
 * eGroupWare Addressbook
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'addressbook',
		'noheader'   => True,
		'nonavbar'   => True
));
include('../header.inc.php');

// check if we have an advanced search and reset it in case
$old_state = Api\Cache::getSession('addressbook', 'index');
if ($old_state['advanced_search'])
{
	unset($old_state['advanced_search']);
	Api\Cache::setSession('addressbook', 'index', $old_state);
}
Api\Egw::redirect_link('/index.php','menuaction=addressbook.addressbook_ui.index');
