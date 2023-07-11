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
		Api\Translation::add_app('admin');

		return [
			'label' =>	'Application passwords',
			'name' => 'admin.tokens',
			'prepend' => false,
			'data' => [
				'token' => [
					'default_cols' => '!account_id',
					'add_action' => 'app.preferences.addToken',
				]+self::get_nm_options(),
			],
			'preserve' => [
			],
			'sel_options' => [
			],
			'save_callback' => __CLASS__.'::action',
		];
	}

}