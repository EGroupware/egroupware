<?php
/**
 * eGgroupWare admin - UI for the command queue
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once(EGW_INCLUDE_ROOT.'/admin/inc/class.admin_cmd.inc.php');
require_once(EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate.inc.php');

/**
 * UI for the admin comand queue
 */
class admin_cmds
{
	var $public_functions = array(
		'index' => true,
	);
	
	/**
	 * calling get_rows of our static so_sql instance
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array $readonlys
	 * @return int
	 */
	function get_rows($query,&$rows,$readonlys)
	{
		$total = admin_cmd::get_rows($query,$rows,$readonlys);
		
		$readonlys = array();
		
		if (!$rows) return array();
		
		foreach($rows as &$row)
		{
			try {
				$cmd = admin_cmd::instanciate($row);
				$row['title'] = $cmd->__tostring();	// we call __tostring explicit, as a cast to string requires php5.2+
			}
			catch (Exception $e) {
				$row['title'] = $e->getMessage();
			}
			$readonlys["delete[$row[id]]"] = $row['status'] != admin_cmd::scheduled;
		}
		//_debug_array($rows);
		return $total;
	}
	
	function index(array $content=null)
	{
		$tpl = new etemplate('admin.cmds');
		
		if (!is_array($content))
		{
			$content['nm'] = $GLOBALS['egw']->x; sessions::appsession('cmds','admin');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows' => 'admin.admin_cmds.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter' => true,	// I  disable the 1. filter
					'no_filter2' => true,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat' => true,		// I  disable the cat-selectbox
					'order' => 'cmd_created',
					'sort' => 'DESC',
				);		
			}
		}
		$tpl->exec('admin.admin_cmds.index',$content,array(
			'status' => admin_cmd::$stati,
		));
	}
}
