<?php
/**
 * EGroupware - Admin - DB backup and restore
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package admin
 */

use EGroupware\Api;
use EGroupware\Stylite\Vfs\S3;

class admin_db_backup
{
	/**
	 * @var true[]
	 */
	public $public_functions = array(
		'index' => true,
	);
	/**
	 * @var Api\Db\Backup
	 */
	protected $db_backup;

	/**
	 * Method for scheduled backups, called via asynservice
	 */
	function do_backup()
	{
		if (class_exists(S3\Backup::class) && S3\Backup::available())
		{
			$this->db_backup = new S3\Backup();
		}
		else
		{
			$this->db_backup = new Api\Db\Backup();
		}

		try {
			$f = $this->db_backup->fopen_backup();
			$this->db_backup->backup($f);
			if (is_resource($f))
			{
				fclose($f);
			}
			/* Remove old backups. */
			$this->db_backup->housekeeping();
		}
 		catch (\Exception $e) {
			// log error
		    _egw_log_exception($e);
	    }
	}

	/**
	 * includes setup's db_backup to display/access it inside admin
	 */
	function index()
	{
		$tpl_root = EGW_SERVER_ROOT.'/setup/templates/default';
		$self = $GLOBALS['egw']->link('/index.php',array('menuaction'=>'admin.admin_db_backup.index'));
		Api\Translation::add_app('setup');
		Api\Header\ContentSecurityPolicy::add('script-src', 'unsafe-inline');

		include EGW_SERVER_ROOT.'/setup/db_backup.php';

		unset($tpl_root, $self);
		echo $GLOBALS['egw']->framework->footer();
	}
}