<?php
/**
 * eGroupWare Addressbook
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'currentapp' => 'addressbook',
		'noheader'   => True,
		'nonavbar'   => True
));
include('../header.inc.php');

// check if we have an advanced search and reset it in case
$old_state = $GLOBALS['egw']->session->appsession('index','addressbook');
if ($old_state['advanced_search'])
{
	unset($old_state['advanced_search']);
	$GLOBALS['egw']->session->appsession('index','addressbook',$old_state);
}
$GLOBALS['egw']->redirect_link('/index.php','menuaction=addressbook.addressbook_ui.index');
