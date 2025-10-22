<?php
/**
 * EGroupware API - Applications
 *
 * @link http://www.egroupware.org
 * @author Mark Peters <skeeter@phpgroupware.org>
 * Copyright (C) 2001 Mark Peters
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage egw
 * @version $Id$
 */

namespace EGroupware\Api\Egw;
use EGroupware\Api;

/**
 * Application (sub-)object of Egw-object used to load $GLOBALS['egw_info'](['user'])['apps']
 */
class Applications
{
	var $account_id;
	/**
	 * Reference to the global db class
	 *
	 * @var Api\Db
	 */
	var $db;
	var $table_name = 'egw_applications';

	/**************************************************************************\
	* Standard constructor for setting $this->account_id                       *
	\**************************************************************************/

	/**
	 * standard constructor for setting $this->account_id
	 *
	 * @param int|string $account_id account-id or -lid
	 */
	function __construct($account_id = '')
	{
		if (isset($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']->db))
		{
			$this->db = $GLOBALS['egw_setup']->db;
		}
		else
		{
			$this->db = $GLOBALS['egw']->db;
		}

		$this->account_id = get_account_id($account_id);
	}

	/**
	 * Get applications of user
	 *
	 * Used to populate $GLOBALS['egw_info']['user']['apps'] in Api\Session
	 */
	function read_repository()
	{
		if (!isset($GLOBALS['egw_info']['apps']) ||	!is_array($GLOBALS['egw_info']['apps']))
		{
			$this->read_installed_apps();
		}
		if(!$this->account_id)
		{
			return False;
		}
		return array_intersect_key($GLOBALS['egw_info']['apps'],
			$GLOBALS['egw']->acl->get_user_applications($this->account_id));
	}

	/**
	 * Default color for apps not specified below
	 * @var string
	 */
	public static $default_app_color = '#797979';
	/**
	 * Default color for some apps
	 * @var string[]
	 */
	public static $default_app_colors = [
		'addressbook'	=> '#003366',
		'admin'	=> '#333333',
		'bookmarks'	=> '#CC6633',
		'calendar'	=> '#CC0033',
		'filemanager'	=> '#ff9933',
		'infolog'	=> '#660033',
		'mail'	=> '#006699',
		'projectmanager'	=> '#669999',
		'resources'	=> '#003333',
		'timesheet'	=> '#330066',
		'tracker'	=> '#009966',
		'wiki'	=> '#797979',
		'ranking'	=> '#404040',
		'default'	=> '#797979',
		'kanban'	=> '#4663c8',
		'smallpart'	=> '#303333',
	];

	/**
	 * Populate array with a list of installed apps
	 *
	 * egw_applications.app_enabled = -1 is NOT installed, but an uninstalled autoinstall app!
	 *
	 * @return array[]
	 */
	function read_installed_apps()
	{
		$GLOBALS['egw_info']['apps'] = Api\Cache::getInstance(__CLASS__, 'apps', function()
		{
			$apps = array();
			foreach($this->db->select($this->table_name,'*', ['app_enabled != -1'],__LINE__,__FILE__,false,'ORDER BY app_order ASC') as $row)
			{
				$apps[$row['app_name']] = Array(
					'title'   => $row['app_name'],
					'name'    => $row['app_name'],
					'enabled' => True,
					'status'  => $row['app_enabled'],
					'id'      => (int)$row['app_id'],
					'order'   => (int)$row['app_order'],
					'version' => $row['app_version'],
					'index'   => $row['app_index'],
					'icon'    => $row['app_icon'],
					'icon_app'=> $row['app_icon_app'],
				);
				if (!empty($row['app_extra']))
				{
					$apps[$row['app_name']] += json_decode($row['app_extra'],true);
				}
				if (empty($apps[$row['app_name']]['color']))
				{
					$apps[$row['app_name']]['color'] = self::$default_app_colors[$row['app_name']] ?? self::$default_app_color;
				}
			}
			return $apps;
		});

		if (!empty($GLOBALS['egw_info']['user']['preferences']['common']['lang']))
		{
			foreach($GLOBALS['egw_info']['apps'] as &$app)
			{
				$app['title'] = lang($app['title']);
			}
		}
	}

	/**
	 * Update application color
	 *
	 * @param string $app
	 * @param string $color
	 * @return void
	 * @throws Api\Db\Exception
	 * @throws Api\Db\Exception\InvalidSql
	 */
	public function ajax_updateAppColor(string $app, string $color)
	{
		if (!empty($GLOBALS['egw_info']['user']['apps']['admin']) &&
			isset($GLOBALS['egw_info']['user']['apps'][$app]) &&
			preg_match('/^#[0-9a-fA-F]{6}$/', $color) &&
			$GLOBALS['egw_info']['user']['apps'][$app]['color'] !== $color)
		{
			$extra = $this->db->select($this->table_name,'app_extra', [
				'app_name' => $app,
			], __LINE__, __FILE__)->fetchColumn();
			$extra = $extra ? json_decode($extra,true) : [];
			$extra['color'] = $color;
			$this->db->update($this->table_name, [
				'app_extra' => json_encode($extra),
				], [
					'app_name' => $app,
				], __LINE__, __FILE__);

			self::invalidate();
		}
	}

	/**
	 * Invalidate cached apps
	 */
	public static function invalidate()
	{
		try {
			Api\Cache::unsetInstance(__CLASS__, 'apps');
		}
		catch (\Exception $e) {
			// ignore exceptions caused by not existing install_id during installation
		}
	}
}