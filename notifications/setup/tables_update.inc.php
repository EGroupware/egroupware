<?php

	/**
	 * eGroupWare - Setup
	 * http://www.egroupware.org 
	 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package notifications
	 * @subpackage setup
	 * @version $Id: class.db_tools.inc.php 21408 2006-04-21 10:31:06Z nelius_weiss $
	 */

	$test[] = '0.5';
	function notifications_upgrade0_5()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_notificationpopup','account_id',array(
			'type' => 'int',
			'precision' => '20',
			'nullable' => False
		));

		return $GLOBALS['setup_info']['notifications']['currentver'] = '0.6';
	}
?>
