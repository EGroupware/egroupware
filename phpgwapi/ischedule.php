<?php
/**
 * EGroupware: iSchedule server
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2012 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * iSchedule server: serverside of iSchedule
 *
 * @link https://tools.ietf.org/html/draft-desruisseaux-ischedule-03 iSchedule draft from 2013-01-22
 */

$GLOBALS['egw_info'] = array(
	'flags' => array(
		'noheader'  => True,
		'currentapp' => 'login',
		'no_exception_handler' => true,
	)
);
// if you move this file somewhere else, you need to adapt the path to the header!
$egw_dir = dirname(dirname(__FILE__));
include($egw_dir.'/header.inc.php');

$ischedule = new ischedule_server();
$ischedule->ServeRequest();
