<?php

 /*
 * Egroupware Weather widget
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package home
 * @subpackage portlet
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

 /**
  * Widget displaying the weather
  *
  * This widget displays more or less data depending on the portlet size using
  * a combination of the disabled attribute in the template, and unsetting
  * things to fit.  It also uses some CSS to make sure things fit according to
  * the grid size.
  *
  * We use openweathermap.org as a data source.
  */
class home_weather_portlet extends home_portlet
{

	const API_URL = "http://api.openweathermap.org/data/2.5/";
	const ICON_URL = 'http://openweathermap.org/img/w/';
	const API_KEY = '45484f039c5caa14d31aefe7f5514292';
	const CACHE_TIME = 3600; // Cache weather for an hour

	/**
	 * Context for this portlet
	 */
	public function __construct(Array &$context = array(), &$need_reload = false)
	{
		// City not set for new widgets created via context menu
		if(!$context['city'] || $context['height'] < 2)
		{
			// Set initial size to 3x2, default is too small
			$context['width'] = 3;
			$context['height'] = 2;
		}

		$need_reload = true;
		
		$this->context = $context;
	}

	public function exec($id = null, etemplate_new &$etemplate = null)
	{
		// Allow to submit directly back here
		if(is_array($id) && $id['id'])
		{
			$id = $id['id'];
		}
		$etemplate->read('home.weather');

		$etemplate->set_dom_id($id);
		$content = $this->context;
		$request = array(
			'q'	=> $this->context['city'],
			'units'	=> $this->context['units'] ? $this->context['units'] : 'metric',
			'lang'	=> $GLOBALS['egw_info']['user']['preferences']['common']['lang'],
			// Always get (& cache) 10 days, we'll cut down later
			'cnt'	=> 10
		);

		if($this->context['city'])
		{
			$content += $this->get_weather($request);
		}

		// Adjust data to match portlet size
		if($this->context['height'] <= 2 && $this->context['width'] <= 3)
		{
			// Too small for the other days
			unset($content['list']);
		}
		else if ($this->context['height'] == 2 && $this->context['width'] > 3)
		{
			// Wider, but not taller
			unset($content['current']);
		}
		// Even too small for current high/low
		if($this->context['width'] < 3)
		{
			$content['current']['no_current_temp'] = true;
		}
		

		// Direct to full forecast page
		$content['attribution'] ='http://openweathermap.org/city/'.$content['city_id'];
		
		$etemplate->exec('home.home_weather_portlet.exec',$content,array(),array('__ALL__'=>true),array('id' =>$id));
	}

	/**
	 * Fetch weather data from provider openweathermap.org
	 *
	 * @see http://openweathermap.org/api
	 * @param array $query
	 */
	public function get_weather(Array $query, $api_url = '')
	{
		static $debug = true;
		if(!$api_url)
		{
			$api_url = self::API_URL . '/weather?';
		}
		if(self::API_KEY)
		{
			$query['APPID'] = self::API_KEY;
		}
		$data = egw_cache::getTree('home', json_encode($query), function($query) use(&$clear_cache) {
			$debug = false;
			if($debug) error_log('Fetching fresh data from ' . static::API_URL);

			$url = static::API_URL.'forecast/daily?'. http_build_query($query);
			$forecast = file_get_contents($url);

			$url = static::API_URL.'weather?'. http_build_query($query);
			$current = file_get_contents($url);
			if($debug) error_log(__METHOD__ . ' current: ' . $current);

			return array_merge(array('current' => json_decode($current,true)), json_decode($forecast,true));
		}, array($query), self::CACHE_TIME);

		// Some sample data, if you need to test
		//error_log('Using hardcoded data instead of ' . $api_url . http_build_query($query));
		//$weather = '{"coord":{"lon":-114.05,"lat":53.23},"sys":{"message":0.3098,"country":"Canada","sunrise":1420559329,"sunset":1420587344},"weather":[{"id":802,"main":"Clouds","description":"scattered clouds","icon":"03n"}],"base":"cmc stations","main":{"temp":-21.414,"temp_min":-21.414,"temp_max":-21.414,"pressure":947.79,"sea_level":1050.73,"grnd_level":947.79,"humidity":69},"wind":{"speed":3,"deg":273.5},"clouds":{"all":32},"dt":1420502430,"id":0,"name":"Thorsby","cod":200}';
		//$weather = '{"cod":"200","message":0.1743,"city":{"id":"5978233","name":"Thorsby","coord":{"lon":-114.051,"lat":53.2285},"country":"Canada","population":0},"cnt":6,"list":[{"dt":1420743600,"temp":{"day":-17.49,"min":-27.86,"max":-16.38,"night":-27.86,"eve":-19.91,"morn":-16.77},"pressure":966.21,"humidity":66,"weather":[{"id":800,"main":"Clear","description":"sky is clear","icon":"01d"}],"speed":6.91,"deg":312,"clouds":0,"snow":0.02},{"dt":1420830000,"temp":{"day":-24.86,"min":-29.71,"max":-17.98,"night":-18.31,"eve":-18.32,"morn":-29.51},"pressure":948.46,"humidity":54,"weather":[{"id":801,"main":"Clouds","description":"few clouds","icon":"02d"}],"speed":3.21,"deg":166,"clouds":20},{"dt":1420916400,"temp":{"day":-18.51,"min":-25.57,"max":-17.86,"night":-23.83,"eve":-23.91,"morn":-19.28},"pressure":947.22,"humidity":74,"weather":[{"id":802,"main":"Clouds","description":"scattered clouds","icon":"03d"}],"speed":1.97,"deg":314,"clouds":48},{"dt":1421002800,"temp":{"day":-26.69,"min":-29.86,"max":-20.19,"night":-21.82,"eve":-24.66,"morn":-28.85},"pressure":951.93,"humidity":22,"weather":[{"id":800,"main":"Clear","description":"sky is clear","icon":"02d"}],"speed":1.36,"deg":196,"clouds":8},{"dt":1421089200,"temp":{"day":0.9,"min":-8.24,"max":0.9,"night":-4.99,"eve":-0.21,"morn":-8.24},"pressure":929.31,"humidity":0,"weather":[{"id":800,"main":"Clear","description":"sky is clear","icon":"01d"}],"speed":6.01,"deg":302,"clouds":5,"snow":0},{"dt":1421175600,"temp":{"day":-1.53,"min":-6.7,"max":2.23,"night":-3.65,"eve":2.23,"morn":-6.7},"pressure":934.51,"humidity":0,"weather":[{"id":800,"main":"Clear","description":"sky is clear","icon":"01d"}],"speed":3.9,"deg":201,"clouds":78}]}';

		if($debug)
		{
			error_log(__METHOD__ .' weather info:');
			foreach($data as $key => $val)
			{
				error_log($key . ': ' .array2string($data[$key]));
			}
		}
		if(is_string($data['message']))
		{
			$desc = $this->get_description();
			egw_framework::message($desc['displayName'] . ': ' . $desc['title'] . "\n".$data['message'], 'warning');
			return array();
		}

		if(array_key_exists('city', $data))
		{
			$data['city_id'] = $data['city']['id'];
		}
		elseif ($data['city'])
		{
			$data['city_id'] = $data['id'];
		}
		if($data['list'])
		{
			$massage =& $data['list'];
			
			for($i = 0; $i <  min(count($massage), $this->context['width']); $i++)
			{
				$forecast =& $massage[$i];
				$forecast['day'] = egw_time::to($forecast['dt'],'l');
				self::format_forecast($forecast);
			}
			// Chop data to fit into portlet
			for($i; $i < count($massage); $i++)
			{
				unset($massage[$i]);
			}
		}
		if($data['current'] && is_array($data['current']))
		{
			// Current weather
			$data['current']['temp'] = $data['current']['main'];
			self::format_forecast($data['current']);
		}


		if ($data['list'])
		{
			$data['current']['temp'] = array_merge($data['current']['temp'],$data['list'][0]['temp']);
		}
		return $data;
	}

	/**
	 * Format weather to our liking
	 */
	protected static function format_forecast(&$data)
	{
		$weather =& $data['weather'] ? $data['weather'] : $data;
		$temp =& $data['temp'] ? $data['temp'] : $data;

		// Full URL for icon
		if(is_array($weather))
		{
			foreach($weather as &$w)
			{
				$w['icon'] = static::ICON_URL . $w['icon'].'.png';
			}
		}

		// Round
		foreach(array('temp','temp_min','temp_max','min','max') as $temp_name)
		{
			if(array_key_exists($temp_name, $temp))
			{
				$temp[$temp_name] = ''.round($temp[$temp_name]);
			}
		}
	}

	public function get_actions()
	{
		$actions = array(
		);
		return $actions;
	}

	/**
	 * Return a list of settings to customize the portlet.
	 *
	 * Settings should be in the same style as for preferences.  It is OK to return an empty array
	 * for no customizable settings.
	 *
	 * These should be already translated, no further translation will be done.
	 *
	 * @see preferences/inc/class.preferences_settings.inc.php
	 * @return Array of settings.  Each setting should have the following keys:
	 * - name: Internal reference
	 * - type: Widget type for editing
	 * - label: Human name
	 * - help: Description of the setting, and what it does
	 * - default: Default value, for when it's not set yet
	 */
	public function get_properties()
	{
		$properties = parent::get_properties();

		$properties[] = array(
			'name'	=>	'city',
			'type'	=>	'textbox',
			'label'	=>	lang('Location'),
		);
		return $properties;
	}

	public function get_description()
	{
		return array(
			'displayName'=> lang('Weather'),
			'title'=>	$this->context['city'],
			'description'=>	lang('Weather')
		);
	}
}