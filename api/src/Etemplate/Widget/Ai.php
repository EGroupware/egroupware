<?php
/**
 * EGroupware eTemplate2 - AI widget server-side
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\Etemplate;
use EGroupware\AiTools;

/**
 * eTemplate AI widget offers text-tools for wrapped widgets
 */
class Ai extends Etemplate\Widget
{


	// Make settings available globally
	const GLOBAL_VALS = '~ai~';

	/**
	 * Disable Ai Widget own UI, if no model is defined
	 *
	 * @param string $cname
	*/
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);

		if(($enabled = self::enabled()))
		{
			Api\Translation::add_app(self::PROVIDER_APP);
			if($enabled == 2)
			{
				// Only translations, no general prompts - "#translate" is not a real prompt id/object
				// (never referenced anywhere client-side), leaving the client with a single broken,
				// blank menu-item: icon showing but no usable menu behind it (ticket #124681 follow-up,
				// reported live 2026-09-17 after disabling the main AI provider while DeepL stayed
				// configured). Use the real translate-only prompt list instead, same shape/method the
				// fully-enabled branch's own translate submenu already uses (Bo::get_predefined_prompts()).
				self::setElementAttribute($this->id ?: self::GLOBAL_VALS, 'prompts',
					array_values((new AiTools\Bo())->get_predefined_prompts(false, true)));
			}
		}
		else
		{
			self::setElementAttribute($this->id ?: self::GLOBAL_VALS, 'uiDisabled', true);
		}
	}

	/**
	 * App to check for run rights
	 */
	const PROVIDER_APP = 'aitools';

	/**
	 * Check and cache, if AI texttools are available / configured and enabled for the user
	 *
	 * @return int 0: NOT enabled, 1: fully enabled, 2: only translations / DeepL supported options
	 */
	public static function enabled() : int
	{
		// user has no run-rights for the provider
		if (empty($GLOBALS['egw_info']['user']['apps'][self::PROVIDER_APP]))
		{
			return 0;
		}
		//Api\Cache::unsetInstance(self::PROVIDER_APP, 'configured');
		return Api\Cache::getInstance(self::PROVIDER_APP, 'configured', static function ()
		{
			if (!class_exists('EGroupware\\AiTools\\Bo'))
			{
				return 0;
			}
			try {
				return (int)AiTools\Bo::test_api_connection();
			}
			catch (\Exception $e) {
				try {
					// deeplTargetLanguages() only checks its OWN $config param, never reads the
					// real one itself - called bare (no args, like eg. Hooks::configValidate()'s
					// own call does NOT do), $config stays null, empty($config['deepl_api_key'])
					// is unconditionally true, and it always returns [] regardless of the real,
					// saved DeepL config. That silently kept this branch from ever reaching 2.
					return AiTools\Bo::deeplTargetLanguages(Api\Config::read(AiTools\Bo::APP)) ? 2 : 0;
				}
				catch (\Exception $e) {}
			}
			return 0;
		}, [], 7200);
	}

	/**
	 * Run prompt via provider-app
	 *
	 * @param string $action
	 * @param ...$params
	 * @return void
	 */
	public static function ajaxApi(string $action, ...$params)
	{
		if (empty($GLOBALS['egw_info']['user']['apps'][self::PROVIDER_APP]))
		{
			throw new Api\Exception\NoPermission\App();
		}
		$bo = new AiTools\Bo();
		$bo->ajax_api($action, ...$params);
	}
}
Etemplate\Widget::registerWidget(Ai::class, 'et2-ai');