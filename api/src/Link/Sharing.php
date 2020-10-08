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
	protected static function after_login(array $share)
	{
		list($app) = explode('::', $share['share_path']);

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
		$actions = parent::get_actions($appname, $group);

		// Add in merge to mail document
		if ($GLOBALS['egw_info']['user']['apps']['mail'] && class_exists($appname.'_merge'))
		{
			$documents = call_user_func(array($appname.'_merge', 'document_action'),
				$GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir'],
				1, 'Insert in document', 'shareDocument_'
			);
			$documents['order'] = 20;

			// Mail only
			if ($documents['children']['message/rfc822'])
			{
				// Just email already filtered out
				$documents['children'] = $documents['children']['message/rfc822']['children'];
			}
			foreach($documents['children'] as $key => &$document)
			{
				if(strpos($document['target'],'compose_') === FALSE)
				{
					unset($documents['children'][$key]);
					continue;
				}

				$document['allowOnMultiple'] = true;
				$document['onExecute'] = "javaScript:app.$appname.share_merge";
			}
			$documents['enabled'] = (boolean)$documents['children'] && !!($GLOBALS['egw_info']['user']['apps']['stylite']) ?
					"javaScript:app.$appname.is_share_enabled" : false;
			$actions['share']['children']['shareDocuments'] = $documents;
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

	/**
	 * Check that a share path still exists (and is readable)
	 */
	protected static function check_path($share)
	{
		list($app, $id) = explode('::', $share['share_path']);
		if(!\EGroupware\Api\Link::$app_register[$app])
		{
			\EGroupware\Api\Link::init_static();
		}
		return (boolean) \EGroupware\Api\Link::title($app, $id);
	}

}
