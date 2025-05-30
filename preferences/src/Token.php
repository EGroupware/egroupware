<?php
/**
 * EGroupware - Admin - Application passwords / tokens
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @package admin
 * @copyright (c) 2023 by Ralf Becker <rb-AT-egroupware.org>
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Preferences;

use EGroupware\Api;
use EGroupware\Admin;

class Token extends Admin\Token
{
	const APP = 'preferences';

	/**
	 * Methods callable via menuaction GET parameter
	 *
	 * @var array
	 */
	public $public_functions = [
		'edit'  => true,
	];

	/**
	 * Answers preferences_password_security hook
	 *
	 * @param array $data
	 */
	public static function security(array $data)
	{
		// add token / app passwords for non-admins only if not disabled for memberships
		if (empty($GLOBALS['egw_info']['user']['apps']['admin']) &&
			!empty($GLOBALS['egw_info']['server']['deny_application_passwords']) &&
			array_intersect($GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'], true),
				(array)$GLOBALS['egw_info']['server']['deny_application_passwords']))
		{
			return;
		}

		Api\Translation::add_app('admin');

		return [
			'label' =>	'Application passwords',
			'name' => 'admin.tokens',
			'prepend' => false,
			'data' => [
				'token' => [
					'add_action' => 'app.preferences.addToken',
					'no_account_id' => true,
				]+self::get_nm_options(),
			],
			'preserve' => [
			],
			'sel_options' => [
			],
			'readonlys' => [
				'token[add]' => self::templatesOnly(),
			],
			'save_callback' => __CLASS__.'::save_callback',
		];
	}

	public static function save_callback(array &$content)
	{
		Api\Translation::add_app('admin');
		try {
			Api\Json\Response::get()->call('app.preferences.refreshToken', self::action($content['token']['action'], $content['token']['selected']), 'success');
		}
		catch (\Exception $e) {
			Api\Framework::message($e->getMessage(), 'error');
		}
	}
}