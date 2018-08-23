<?php
/**
 * EGroupware - Tutorial
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage framework
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2015-16 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

class Tutorial
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'popup' => true
	);

	/**
	 * Popup window to display youtube video
	 *
	 * @param array $content
	 */
	function popup ($content=null)
	{
		// check and if not done register tutorial_menu hook
		if (!Api\Hooks::exists('sidebox_all', 'api') ||
			Api\Hooks::exists('sidebox_all', 'api', true) != 'EGroupware\\Api\\Framework\\Tutorial::tutorial_menu')
		{
			Api\Hooks::read(true);
		}

		//Allow youtube frame to pass the CSP check
		Api\Header\ContentSecurityPolicy::add('frame-src', array('https://www.youtube.com'));

		$tmpl = new Etemplate('api.tutorial');
		if (!is_array($content))
		{
			// Get tutorial object id
			$tuid_indx = explode('-',$_GET['tuid']);
			$appName = $tuid_indx[0];
			$lang = $tuid_indx[1];
			$id = $tuid_indx[2];
		}
		else // set the first video of selected app
		{
			$appName = $content['list']['apps'];
			$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
			$id ="0";
		}
		// read tutorials json file to fetch data
		$tutorials = json_decode(self::getJsonData(), true);
		$apps = array('introduction' => lang('Introduction'));
		foreach (array_keys($tutorials) as $app)
		{
			// show only apps user has access to them
			if (in_array($app, array_keys($GLOBALS['egw_info']['user']['apps']))) $apps [$app] = $app;
		}
		$sel_options = array(
			'apps' => $apps,
		);
		// Check if the user has right to see the app's tutorial
		if (in_array($appName, array_keys($GLOBALS['egw_info']['user']['apps'])) || $appName === "introduction")
		{
			// fallback to english video
			$tutorial = $tutorials[$appName][$lang][$id]? $tutorials[$appName][$lang][$id]:
				$tutorials[$appName]['en'][$id];

			$list = array(
				'apps' => $appName,
				'0' => ''
			);
			foreach (isset($tutorials[$appName][$lang]) ? $tutorials[$appName][$lang] : $tutorials[$appName]['en'] as $v)
			{
				$v ['onclick'] = 'app[egw.app_name()].tutorial_videoOnClick("'.$v['src'].'")';
				array_push($list, $v);
			}
			$content = array (
				'src' => $tutorial['src'],
				'title' => $tutorial['title'],
				'desc' => $tutorial['desc'],
				'list' => $list
			);
		}
		else
		{
			$content = array();
			Api\Framework::message(lang('You do not have permission to see this tutorial!'));
		}

		$tmpl->exec('api.EGroupware\\Api\\Framework\\Tutorial.popup', $content, $sel_options, array(), array(), 2);
	}

	/**
	 * Ajax function to get videos links as json
	 */
	public static function ajax_data()
	{
		$response = Api\Json\Response::get();
		$response->data(json_decode(self::getJsonData()));
	}

	/**
	 * Function to fetch data from tutorials.json file
	 *
	 * @return string returns json string
	 */
	static function getJsonData()
	{
		if (!($json = Api\Cache::getCache(Api\Cache::TREE, __CLASS__, 'egw_tutorial_json')))
		{
			$json = file_get_contents('https://www.egroupware.org/videos/tutorials.json',
				false, Api\Framework::proxy_context());
			// Fallback tutorials.json
			if (!$json) $json = file_get_contents('api/setup/tutorials.json');
			// Cache the json object for two hours
			Api\Cache::setCache(Api\Cache::TREE, __CLASS__, 'egw_tutorial_json', $json, 7200);
		}

		return $json;
	}

	/**
	 * Static function to build egw tutorial sidebox menu
	 *
	 */
	public static function tutorial_menu()
	{
		if (Api\Header\UserAgent::mobile()) return;
		$tutorials = json_decode(self::getJsonData(),true);
		$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		if (!is_array($tutorials[$appname])) return false;
		if (!$GLOBALS['egw_info']['server']['egw_tutorial_disable']
			|| $GLOBALS['egw_info']['server']['egw_tutorial_disable'] == 'intro')
		{
			$file = Array (
				array(
					'text'    => '<div id="egw_tutorial_'.$appname.'_sidebox" class="egwTutorial"/>',
					'no_lang' => true,
					'link'    => false,
					'icon'    => false,
				)
			);
			display_sidebox($appname, lang('Video Tutorials'), $file);
		}
	}
}