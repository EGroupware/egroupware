<?php
/**
 * EGroupware - Home - user interface
 *
 * @link www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2013 by Nathan Gray
 * @package home
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'                => true,
		'nonavbar'                => true,
		'currentapp'              => 'home',
	)
);

include('../header.inc.php');
$GLOBALS['egw_info']['flags']['nonavbar']=false;

// check and if neccessary force user to chane password
auth::check_password_age('home','index');

// Home is treated specially, so a redirect won't work.
$home = new home_ui();
echo $home->index();
