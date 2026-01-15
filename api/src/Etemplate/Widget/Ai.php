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

		if(self::enabled())
		{
			self::setElementAttribute($this->id ?: self::GLOBAL_VALS, 'endpoint', self::class . '::ajaxApi');
		}
	}

	/**
	 * App to check for run rights
	 */
	const PROVIDER_APP = 'aitools';

	/**
	 * Check and cache, if AI texttools are available / configured and enabled for the user
	 */
	public static function enabled() : bool
	{
		// user has no run-rights for the provider
		if (empty($GLOBALS['egw_info']['user']['apps'][self::PROVIDER_APP]))
		{
			return false;
		}
		//Api\Cache::unsetInstance(self::PROVIDER_APP, 'configured');
		return Api\Cache::getInstance(self::PROVIDER_APP, 'configured', static function ()
		{
			if (!class_exists('EGroupware\\AiTools\\Bo'))
			{
				return false;
			}
			return AiTools\Bo::test_api_connection();
		}, [],3600);
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
		$bo = new AiTools\Bo();
		$bo->ajax_api($action, ...$params);
	}
}
Etemplate\Widget::registerWidget(Ai::class, 'et2-ai');