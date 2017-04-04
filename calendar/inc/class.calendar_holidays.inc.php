<?php
/**
 * EGroupware - Calendar holidays
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2016 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Calendar holidays
 *
 * Holidays are read from:
 * - a given iCal URL or
 * - json file with 2-digit iso country-code: URL pairs is read from https://community.egroupware.org or
 * - json file is read from /calendar/setup/ical_holiday_urls.json
 *
 * Holidays are cached on tree or instance level, later for custom urls.
 * As fetching and parsing iCal files is expensive, we always render them
 * from previous (requested) year until next 5 years.
 *
 * Holiday urls are from Mozilla Calendar project:
 * @link https://www.mozilla.org/en-US/projects/calendar/holidays/
 * @link https://www.mozilla.org/media/caldata/calendars.json (json from which above page is generated)
 * @link https://github.com/mozilla/bedrock/tree/master/media/caldata
 */
class calendar_holidays
{
	const URL_CACHE_TIME = 864000;
	const URL_FAIL_CACHE_TIME = 300;
	const EGW_HOLIDAY_URL = 'https://community.egroupware.org/egw';
	const HOLIDAY_PATH = '/calendar/setup/ical_holiday_urls.json';
	const HOLIDAY_CACHE_TIME = 864000; // 10 days

	/**
	 * Read holidays for given country/url and year
	 *
	 * @param string $country 2-digit iso country code or URL
	 * @param int $year =null default current year
	 * @return array of Ymd => array of array with values for keys 'occurence','month','day','name', (commented out) 'title'
	 */
	public static function read($country, $year=null)
	{
		if (!$year) $year = (int)Api\DateTime::to('now', 'Y');
		$level = self::is_url($country) ? Api\Cache::INSTANCE : Api\Cache::TREE;

		$holidays = Api\Cache::getCache($level, __CLASS__, $country.':'.$year);

		// if we dont find holidays in cache, we render from previous year until next 5 years
		if (!isset($holidays) && ($years = self::render($country, $year-1, $year+5)))
		{
			foreach($years as $y => $data)
			{
				Api\Cache::setCache($level, __CLASS__, $country.':'.$y, $data, self::HOLIDAY_CACHE_TIME);
			}
			$holidays = $years[$year];
		}
		return (array)$holidays;
	}

	/**
	 * Fetch holiday iCal and convert it to usual holiday format
	 *
	 * @param string $country 2-digit iso country code or URL
	 * @param int $year =null default current year
	 * @param int $until_year =null default, fetch only one year, if given result is indexed additional by year
	 * @return array of Ymd => array of array with values for keys 'occurence','month','day','name', (commented out) 'title'
	 */
	public static function render($country, $year=null, $until_year=null)
	{
		if (!$year) $year = (int)Api\DateTime::to('now', 'Y');
		$end_year = $until_year && $year < $until_year ? $until_year : $year;

		$starttime = microtime(true);
		if (!($holidays = self::fetch($country)))
		{
			return array();
		}
		$years = array();
		foreach($holidays as $event)
		{
			$start = new Api\DateTime($event['start']);
			$end = new Api\DateTime($event['end']);
			if ($start->format('Y') > $end_year) continue;
			if ($end->format('Y') < $year && !$event['recur_type']) continue;

			// recuring events
			if ($event['recur_type'])
			{
				// calendar_rrule limits no enddate, to 5 years
				if (!$event['recur_enddate']) $event['recur_enddate'] = (1+$end_year).'0101';

				$rrule = calendar_rrule::event2rrule($event);
				if ($rrule->enddate && $rrule->enddate->format('Y') < $year) continue;

				foreach($rrule as $rtime)
				{
					if (($y = (int)$rtime->format('Y')) < $year) continue;
					if ($y > $end_year) break;

					$ymd = (int)$rtime->format('Ymd');
					$years[$y][(string)$ymd][] = array(
						'day' => $ymd % 100,
						'month' => ($ymd / 100) % 100,
						'occurence'  => $y,
						'name' => $event['title'],
						//'title' => $event['description'],
					);
				}
			}
			else
			{
				$end_ymd = (int)$end->format('Ymd');
				while(($ymd = (int)$start->format('Ymd')) <= $end_ymd)
				{
					$y = (int)$start->format('Y');
					$years[$y][(string)$ymd][] = array(
						'day' => $ymd % 100,
						'month' => ($ymd / 100) % 100,
						'occurence'  => $y,
						'name' => $event['title'],
						//'title' => $event['description'],
					);
					$start->add('1day');
				}
			}
		}
		foreach($years as $y => &$data)
		{
			ksort($data);
		}
		error_log(__METHOD__."('$country', $year, $end_year) took ".  number_format(microtime(true)-$starttime, 3).'s to fetch '.count(call_user_func_array('array_merge', $years)).' events');
		unset($starttime);

		return $until_year ? $years : $years[$year];
	}

	protected static function is_url($url)
	{
		return $url[0] == '/' || strpos($url, '://') !== false;
	}

	/**
	 * Fetch iCal for given country
	 *
	 * @param string $country 2-digit iso country code or URL
	 * @return array|Iterator parsed events
	 */
	protected static function fetch($country)
	{
		if (!($url = self::is_url($country) ? $country : self::ical_url($country)))
		{
			error_log("No holiday iCal for '$country'!");
			return array();
		}
		if (!($f = fopen($url, 'r', false, Api\Framework::proxy_context())))
		{
			error_log("Can NOT open holiday iCal '$url' for country '$country'!");
			return array();
		}
		// php does not automatic gzip decode, but it does not accept that in request headers
		// iCloud eg. always gzip compresses: https://p16-calendars.icloud.com/holidays/au_en-au.ics
		foreach($http_response_header as $h)
		{
			if (preg_match('/^content-encoding:.*gzip/i', $h))
			{
				stream_filter_append($f, 'zlib.inflate', STREAM_FILTER_READ, array('window' => 15|16));
				break;
			}
		}
		$parser = new calendar_ical();
		if (!($icals = $parser->icaltoegw($f)))
		{
			error_log("Error parsing holiday iCal '$url' for country '$country'!");
			return array();
		}
		return $icals;
	}

	/**
	 * Get iCal url for holidays of given country
	 *
	 * We first try to fetch urls from https://community.egroupware.org and if that fails we use the local one.
	 *
	 * @param string $country
	 * @return string|boolean|null string with url, false if we cant load urls, NULL if $country is not included
	 */
	protected static function ical_url($country)
	{
		$urls = Api\Cache::getTree(__CLASS__, 'ical_holiday_urls');

		if (!isset($urls))
		{
			if (!($json = file_get_contents(self::EGW_HOLIDAY_URL.self::HOLIDAY_PATH, false,
				Api\Framework::proxy_context(null, null, array('timeout' => 1)))))
			{
				$json = file_get_contents(EGW_SERVER_ROOT.self::HOLIDAY_PATH);
			}
			if (!$json || !($urls = json_decode($json, true)))
			{
				error_log(__METHOD__."() cant read ical_holiday_urls.json!");
				$urls = false;
			}
			Api\Cache::setTree(__CLASS__, 'ical_holiday_urls', $urls, $urls ? self::URL_CACHE_TIME : self::URL_FAIL_CACHE_TIME);
		}
		return $urls[$country];
	}

}

// some tests when url is called direct
if (isset($_SERVER['SCRIPT_FILENAME']) && $_SERVER['SCRIPT_FILENAME'] == __FILE__)
{
	$GLOBALS['egw_info'] = array(
		'flags' => array(
			'currentapp' => 'login',
		)
	);
	include('../../header.inc.php');

	$country = !empty($_GET['country']) && preg_match('/^[A-Z]{2}$/i', $_GET['country']) ? strtoupper($_GET['country']) : 'DE';
	$year = !empty($_GET['year']) && (int)$_GET['year'] > 2000 ? (int)$_GET['year'] : (int)date('Y');
	$year_until = !empty($_GET['year_until']) && (int)$_GET['year_until'] >= $year ? (int)$_GET['year_until'] : $year;

	Api\Header\Content::type('holidays-'.$country.'.txt', 'text/plain', 0, true, false);
	print_r(calendar_holidays::render($country, $year, $year_until));
}
