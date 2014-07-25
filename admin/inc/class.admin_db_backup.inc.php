<?php
/**
 * EGroupware - Admin - DB backup and restore
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 * @version $Id$
 */

class admin_db_backup
{
	var $public_functions = array(
		'index' => true,
	);
	var $db_backup;

	/**
	 * Method for sheduled backups, called via asynservice
	 */
	function do_backup()
	{
		$this->db_backup = new db_backup();

 		if (($f = $this->db_backup->fopen_backup()))
 		{
			$this->db_backup->backup($f);
			if(is_resource($f))
				fclose($f);
			/* Remove old backups. */
			$this->db_backup->housekeeping();
		}
	}

	/**
	 * includes setup's db_backup to display/access it inside admin
	 */
	function index()
	{
		$tpl_root = EGW_SERVER_ROOT.'/setup/templates/default';
		$self = $GLOBALS['egw']->link('/index.php',array('menuaction'=>'admin.admin_db_backup.index'));
		translation::add_app('setup');
		egw_framework::csp_script_src_attrs('unsafe-inline');

		include EGW_SERVER_ROOT.'/setup/db_backup.php';

		unset($tpl_root, $self);
		common::egw_footer();
	}
}
