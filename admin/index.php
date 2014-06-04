<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Stephen Brown <steve@dataclarity.net> distribute admin across the application directories
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

header('Location: ../index.php?menuaction=admin.admin_ui.index&ajax=true'.
	(isset($_GET['sessionid']) ? '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'] : ''));
