<?php
/**
 * EGroupware - Mail - Index
 *
 * @link http://www.egroupware.org
 * @package mail
 * @author Klaus Leithoff [kl@stylite.de]
 * @version 1.0
 * @copyright (c) 2013 by Klaus Leithoff <kl-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
header('Location: ../index.php?menuaction=mail.mail_ui.index'.
    	(isset($_GET['sessionid']) ? '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'] : ''));
