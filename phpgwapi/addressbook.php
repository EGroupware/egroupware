<?php
/**
 * Addressbook - email popup
 *
 * @deprecated use addressbook.uicontacts.emailpopup
 * @link www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

$url = '../index.php?menuaction=addressbook.uicontacts.emailpopup&compat=1';
if (isset($_GET['sessionid']) && isset($_GET['kp3']))
{
	$url .= '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'];
}
header('Location: '.$url);
