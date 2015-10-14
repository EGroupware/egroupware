<?php
/**
 * EGroupware - Tutorial
 *
 * @link http://www.egroupware.org
 * @package home
 * @author Hadi Nategh [hn@stylite.de]
 * @copyright (c) 2015 by Stylite AG <info-AT-stylite.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id:$
 */
	
class home_tutorial_ui {
	
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
	 * @param type $content
	 */
	function popup ($content=null)
	{
		//Allow youtube frame to pass the CSP check
		egw_framework::csp_frame_src_attrs(array('www.youtube.com'));
		
		$tmpl = new etemplate_new('home.tutorial');
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
		foreach ($tutorials as $app => $val)
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
			foreach ($tutorials[$appName][$lang] as $v)
			{
				$v ['onclick'] = 'app.home.tutorial_videoOnClick("'.$v['src'].'")';
				array_push($list, $v);
			}
			$content = array (
				'src' => $tutorial['src'],
				'title' => $tutorial['title'],
				'list' => $list
			);
		}
		else
		{
			$content = array();
			egw_framework::message(lang('You do not have permission to see this tutorial!'));
		}
		// If its the autoloading tutorial
		if ($tuid_indx[3] === 'a')
		{
			$content ['discardbox'] = true;
		}
				
		$tmpl->exec('home.home_tutorial_ui.popup', $content,$sel_options,array(),array(),array(),2);
	}
	
	/**
	 * Ajax function to get videos links as json
	 */
	function ajax_data()
	{
		$response = egw_json_response::get();
		$response->data(json_decode(self::getJsonData()));
	}
	
	/**
	 * Function to fetch data from tutorials.json file
	 * @return string returns json string
	 *
	 * @TODO: implement tree level caching
	 */
	static function getJsonData()
	{
		if (!($json = egw_cache::getCache(egw_cache::TREE, 'home', 'egw_tutorial_json')))
		{
			$json = file_get_contents('http://www.egroupware.de/videos/tutorials.json');
			// Cache the json object for two hours
			egw_cache::setCache(egw_cache::TREE, 'home', 'egw_tutorial_json', $json, 720);
		}
		
		return $json;
	}
}	