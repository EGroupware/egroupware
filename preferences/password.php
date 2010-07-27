<?php
/**
 * EGroupware preferences password change without preferences rights
 *
 * @package preferences
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'password',
	)
);
include('../header.inc.php');

$GLOBALS['egw']->template = new Template(common::get_tpl_dir('preferences'));

ExecMethod('preferences.uipassword.change');
common::egw_footer();
