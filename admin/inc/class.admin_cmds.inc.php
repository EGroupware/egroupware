<?php
/**
 * EGgroupware admin - UI for the command queue
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package admin
 * @copyright (c) 2007-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Etemplate;

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
	 * calling get_rows of our static Api\Storage\Base instance
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	static function get_rows(array $query,&$rows,&$readonlys)
	{
		Api\Cache::setSession('admin', 'cmds', $query);

		// show also old api
		if ($query['col_filter']['app'] === 'api')
		{
			$query['col_filter']['app'] = ['api', 'phpgwapi'];
		}

		return admin_cmd::get_rows($query,$rows,$readonlys);
	}

	/**
	 * Showing the command history and the scheduled commands
	 *
	 * @param array $content =null
	 */
	static function index(array $content=null)
	{
		$tpl = new Etemplate('admin.cmds');

		if (!is_array($content))
		{
			$content['nm'] = Api\Cache::getSession('admin', 'cmds');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows' => 'admin.admin_cmds.get_rows',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter' => true,	// I  disable the 1. filter
					'no_filter2' => true,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat' => true,		// I  disable the cat-selectbox
					'order' => 'cmd_created',
					'sort' => 'DESC',
					'row_id' => 'id',
					'default_cols' => 'title,created,creator,status',
				);
			}
			$content['nm']['actions'] = self::cmd_actions();
		}
		elseif (!empty($content['nm']['rows']['delete']))
		{
			$id = key($content['nm']['rows']['delete']);
			unset($content['nm']['rows']);

			if (($cmd = admin_cmd::read($id)))
			{
				$cmd->delete();
			}
			unset($cmd);
		}
		$periodic = array(
			0 => 'no',
			1 => 'yes'
		);
		$sel_options = array(
			'periodic' => $periodic,
			'status' => admin_cmd::$stati,
			'remote_id' => admin_cmd::remote_sites(),
			'type' => admin_cmd::get_cmd_labels()
		);

		$tpl->exec('admin.admin_cmds.index',$content,$sel_options,array(),$content);
	}

	/**
	 * Acctions for command list/index
	 *
	 * As we only allow to delete scheduled command, which we currently can only create via admin-cli,
	 * I have not (yet) implemented delete of scheduled commands.
	 *
	 * @return array
	 */
	static function cmd_actions()
	{
		return array(

		);
	}

	/**
	 * get_rows for remote instances
	 *
	 * @param array $query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	static function get_remotes(array $query,&$rows,&$readonlys)
	{
		return admin_cmd::get_remotes($query,$rows,$readonlys);
	}

	/**
	 * Showing remote administration instances
	 *
	 * @param array $content =null
	 */
	static function remotes(array $content=null)
	{
		$tpl = new Etemplate('admin.remotes');

		if (!is_array($content))
		{
			$content['nm'] = Api\Cache::getSession('admin', 'remotes');
			if (!is_array($content['nm']))
			{
				$content['nm'] = array(
					'get_rows' => 'admin.admin_cmds.get_remotes',	// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
					'no_filter' => true,	// I  disable the 1. filter
					'no_filter2' => true,	// I  disable the 2. filter (params are the same as for filter)
					'no_cat' => true,		// I  disable the cat-selectbox
					'order' => 'remote_name',
					'sort' => 'ASC',
					'row_id' => 'remote_id',
					'actions' => self::remote_actions(),
				);
			}
		}
		else
		{
			//_debug_array($content);
			unset($content['msg']);

			if ($content['nm']['action'])
			{
				switch($content['nm']['action'])
				{
					case 'edit':
						$content['remote'] = admin_cmd::read_remote($content['nm']['selected'][0]);
						break;
					case 'add':
						$content['remote'] = array('remote_domain' => 'default');
				}
				unset($content['nm']['action']);
			}
			elseif($content['remote']['button'])
			{
				$button = key($content['remote']['button']);
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
						try {
							$content['remote']['remote_id'] = admin_cmd::save_remote($content['remote']);
							$content['msg'] = lang('Remote instance saved');
						} catch (Exception $e) {
							$content['msg'] = lang('Error saving').': '.$e->getMessage().' ('.$e->getCode().')';
							break;
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

	/**
	 * Actions for remotes list
	 *
	 * @return array
	 */
	static function remote_actions()
	{
		return array(
			'edit' => array(
				'caption' => 'Edit',
				'default' => true,
				'allowOnMultiple' => false,
				'nm_action' => 'submit',
				'group' => $group=0,
			),
			'add' => array(
				'caption' => 'Add',
				'nm_action' => 'submit',
				'group' => ++$group,
			),
			/* not (yet) implemented
			'delete' => array(
				'caption' => 'Delete',
				'nm_action' => 'submit',
				'group' => ++$group,
			),*/
		);
	}
}
