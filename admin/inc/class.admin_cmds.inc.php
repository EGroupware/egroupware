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
		'remotes' => true,
	);
	
	/**
	 * calling get_rows of our static so_sql instance
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	static function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('cmds','admin',$query);

		$total = admin_cmd::get_rows($query,$rows,$readonlys);
		
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
	
	/**
	 * Showing the command history and the scheduled commands
	 *
	 * @param array $content=null
	 */
	static function index(array $content=null)
	{
		$tpl = new etemplate('admin.cmds');

		if (!is_array($content))
		{
			$content['nm'] = $GLOBALS['egw']->session->appsession('cmds','admin');
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
		elseif ($content['nm']['rows']['delete'])
		{
			list($id) = each($content['nm']['rows']['delete']);
			unset($content['nm']['rows']);
			
			if (($cmd = admin_cmd::read($id)))
			{
				$cmd->delete();
			}
			unset($cmd);
		}
		$tpl->exec('admin.admin_cmds.index',$content,array(
			'status' => admin_cmd::$stati,
			'remote_id' => admin_cmd::remote_sites(),
		),array(),$content);
	}
	
	/**
	 * get_rows for remote instances
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	static function get_remotes($query,&$rows,&$readonlys)
	{
		return admin_cmd::get_remotes($query,$rows,$readonlys);
	}

	/**
	 * Showing remote administration instances
	 *
	 * @param array $content=null
	 */
	static function remotes(array $content=null)
	{
		$tpl = new etemplate('admin.remotes');

		if (!is_array($content))
		{
			$content['nm'] = $GLOBALS['egw']->session->appsession('remotes','admin');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows' => 'admin.admin_cmds.get_remotes',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter' => true,	// I  disable the 1. filter
					'no_filter2' => true,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat' => true,		// I  disable the cat-selectbox
					'order' => 'remote_name',
					'sort' => 'ASC',
					'header_right' => 'admin.remotes.header_right',
				);
			}
		}
		else
		{
			//_debug_array($content);
			unset($content['msg']);

			if ($content['nm']['rows']['edit'])
			{
				list($id) = each($content['nm']['rows']['edit']);
				unset($content['nm']['rows']);
				
				$content['remote'] = admin_cmd::read_remote($id);
			}
			elseif($content['remote']['button'])
			{
				list($button) = each($content['remote']['button']);
				unset($content['remote']['button']);
				switch($button)
				{
					case 'save':
					case 'apply':
						if ($content['remote']['install_id'] && !$content['remote']['config_passwd'] || 
							!$content['remote']['install_id'] && $content['remote']['config_passwd'] || 
							!$content['remote']['remote_hash'] && !$content['remote']['install_id'] && !$content['remote']['config_passwd'])
						{
							$content['msg'] = lang('You need to enter Install ID AND Password!');
							break;
						}
						if (($content['remote'] = admin_cmd::save_remote($content['remote'])))
						{
							$content['msg'] = lang('Remote instance saved');
						}
						if ($button == 'apply') break;
						// fall through for save
					case 'cancel':
						unset($content['remote']);
						break;
				}
			}
			elseif ($content['nm']['add'])
			{
				$content['remote'] = array('remote_domain' => 'default');
				unset($content['nm']['add']);
			}
		}
		$tpl->exec('admin.admin_cmds.remotes',$content,array(),array(),$content);
		
	}
}
