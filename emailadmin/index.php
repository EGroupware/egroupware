<?php
	/***************************************************************************\
	* EGroupWare - EMailAdmin 
	* @link http://www.egroupware.org
	* @package emailadmin
	* @author Klaus Leithoff <kl-AT-stylite.de>
	* @copyright (c) 2009-10 by Klaus Leithoff <kl-AT-stylite.de>
	* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	* @version $Id$
	\***************************************************************************/

header('Location: ../index.php?menuaction=emailadmin.emailadmin_ui.index'.
	(isset($_GET['sessionid']) ? '&sessionid='.$_GET['sessionid'].'&kp3='.$_GET['kp3'] : ''));
