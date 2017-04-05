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
	 * @var EGroupware\Api\Db
	 */
	var $db;
	var $table_name = 'egw_applications';

	/**************************************************************************\
	* Standard constructor for setting $this->account_id                       *
	\**************************************************************************/

	/**
	 * standard constructor for setting $this->account_id
	 *
	 * @param $account_id account id
	 */
	function __construct($account_id = '')
	{
		if (is_object($GLOBALS['egw_setup']) && is_object($GLOBALS['egw_setup']->db))
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
	 * populate array with a list of installed apps
	 *
	 */
	function read_installed_apps()
	{
		$GLOBALS['egw_info']['apps'] = Api\Cache::getInstance(__CLASS__, 'apps', function()
		{
			$apps = array();
			foreach($this->db->select($this->table_name,'*',false,__LINE__,__FILE__,false,'ORDER BY app_order ASC') as $row)
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
	 * Invalidate cached apps
	 */
	public static function invalidate()
	{
		Api\Cache::unsetInstance(__CLASS__, 'apps');
	}
}
