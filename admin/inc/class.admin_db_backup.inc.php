<?php
	/**************************************************************************\
	* eGroupWare - Admin - DB backup and restore                               *
	* http://www.egroupware.org                                                *
	* Written by RalfBecker@outdoor-training.de                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

 	/* $Id$ */

 	class admin_db_backup
 	{
 		var $public_functions = array(
 			'do_backup' => true,
 			'index' => true,
 		);
 		var $db_backup;
 
 		function admin_db_backup()
 		{
  		}
 		
 		/**
 		 * Method for sheduled backups, called via asynservice
 		 */
 		function do_backup()
 		{
			$this->db_backup = CreateObject('phpgwapi.db_backup');

	 		if ($f = $this->db_backup->fopen_backup())
	 		{
				$this->db_backup->backup($f);
				fclose($f);
			}
 		}
		
 		/**
 		 * includes setup's db_backup to display/access it inside admin
 		 */
 		function index()
 		{
	 		$tpl_root = PHPGW_SERVER_ROOT.'/setup/templates/default';
			$self = $GLOBALS['phpgw']->link('/index.php',array('menuaction'=>'admin.admin_db_backup.index'));
			$GLOBALS['phpgw']->translation->add_app('setup');
			
			include PHPGW_SERVER_ROOT.'/setup/db_backup.php';

			$GLOBALS['phpgw']->common->phpgw_footer();
 		}
 	}