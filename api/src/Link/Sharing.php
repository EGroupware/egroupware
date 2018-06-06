<?php

/**
 * EGroupware entry sharing
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage Link
 * @copyright (c) 2018  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Link;

/**
 * Description of Sharing
 *
 * @author nathan
 */
class Sharing extends \EGroupware\Api\Sharing
{

	/**
	 * Create sharing session
	 *
	 * Certain cases:
	 * a) there is not session $keep_session === null
	 *    --> create new anon session with just specified application rights
	 * b) there is a session $keep_session === true
	 *  b1) current user is share owner (eg. checking the link)
	 *      --> Show entry, preferrably not destroying current session
	 *  b2) current user not share owner
	 *		--> Need a limited UI to show entry
	 *
	 * @param boolean $keep_session =null null: create a new session, true: try mounting it into existing (already verified) session
	 * @return string with sessionid
	 */
	public static function create_session($keep_session=null)
	{
		$share = array();
		$success = static::check_token($keep_session, $share);
		if($success)
		{
			static::setup_entry($share);
			return static::login($keep_session, $share);
		}
		return '';
	}

	protected static function setup_entry(&$share)
	{

	}

	/**
	 * The anonymous user probably doesn't have the needed permissions to access
	 * the record, so we should set that up to avoid permission errors
	 */
	protected function after_login()
	{
		list($app) = explode('::', $this->share['share_path']);

		// allow app (gets overwritten by session::create)
		$GLOBALS['egw_info']['flags']['currentapp'] = $app;
		$GLOBALS['egw_info']['user']['apps'] = array(
			$app => $GLOBALS['egw_info']['apps'][$app]
		);
	}

	/**
	 * Get actions for sharing an entry from the given app
	 *
	 * @param string $appname
	 * @param int $group Current menu group
	 */
	public static function get_actions($appname, $group = 6)
	{
		$actions = array(
		'share' => array(
				'caption' => lang('Share'),
				'icon' => 'api/share',
				'group' => $group,
				'allowOnMultiple' => false,
				'children' => array(
					'shareReadonlyLink' => array(
						'caption' => lang('Readonly Share'),
						'group' => 1,
						'icon' => 'view',
						'order' => 11,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'onExecute' => "javaScript:app.$appname.share_link"
					),
					'shareWritableLink' => array(
						'caption' => lang('Writable Share'),
						'group' => 1,
						'icon' => 'edit',
						'allowOnMultiple' => false,
						'order' => 11,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'onExecute' => "javaScript:app.$appname.share_link"
					),
					'shareFiles' => array(
						'caption' => lang('Share files'),
						'group' => 2,
						'enabled' => "javaScript:app.$appname.is_share_enabled",
						'checkbox' => true
					)
				),
		));
		if(!$GLOBALS['egw_info']['apps']['stylite'])
		{
			array_unshift($actions['share']['children'], array(
				'caption' => lang('EPL Only'),
				'group' => 0
			));
			foreach($actions['share']['children'] as &$child)
			{
				$child['enabled'] = false;
			}
		}
		return $actions;
	}

	/**
	 * Get a user interface for shared directories
	 */
	public function get_ui()
	{
		echo lang('EPL Only');
	}

}
