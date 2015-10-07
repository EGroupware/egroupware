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
	function popup ($content)
	{
		//Allow youtube frame to pass the CSP check
		egw_framework::csp_frame_src_attrs(array('www.youtube.com'));
		
		$tmpl = new etemplate_new('home.tutorial');
		
		// Get tutorial object id
		$tuid_indx = explode('-',$_GET['tuid']);
		if (!is_array($content))
		{
			// read tutorials json file to fetch data
			$tutorials = json_decode(self::getJsonData(), true);
			$apps = array();
			foreach ($tutorials as $app => $val)
			{
				$apps [$app] = $app;
			}
			$sel_options = array(
				'apps' => $apps,
			);
			$content = array (
				'src' => $tutorials[$tuid_indx[0]][$tuid_indx[1]][$tuid_indx[2]]['src'],
				'title' => $tutorials[$tuid_indx[0]][$tuid_indx[1]][$tuid_indx[2]]['title'],
			);
		}
				
		$tmpl->exec('home.home_tutorial_ui.popup', $content,$sel_options,array(),array(),array(),2);
	}
	
	/**
	 * Ajax function to retrive selected app's tutorials based on prefered user lang
	 *
	 * @param type $_app application name
	 */
	function ajax_getAppsTutorials($_app)
	{
		$tutorials = json_decode(self::getJsonData(), true);
		$lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$response = egw_json_response::get();
		$response->data($tutorials[$_app][$lang]);
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
			// Cache the json object for one month
			egw_cache::addCache(egw_cache::TREE, 'home', 'egw_tutorial_json', $json, 3600 * 720);
		}
		
		return $json;
	}
}	