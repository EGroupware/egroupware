<?php
/**
 * EGroupware Calendar - CalDAV sync to subscribe other
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package calendar
 * @copyright (c) 2025 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

require EGW_INCLUDE_ROOT.'/doc/REST-CalDAV-CardDAV/api-client.php';

class Sync
{
	protected $url;
	protected $user;
	protected $password;
	protected $sync_type;

	/**
	 * Constructor
	 *
	 * @param string $url
	 * @param string|null $user
	 * @param string|null $password
	 * @param string|null $sync_type
	 */
	public function __construct(string $url, ?string $user=null, ?string $password=null, ?string $sync_type=null)
	{
		$this->url = $url;
		$this->user = $user;
		$this->password = $password;
		$this->sync_type = $sync_type;
	}

	/**
	 * Check given URL is a CalDAV calendar collection optionally supporting sync-collection report
	 *
	 * @ToDo autodetection if only a hostname is given
	 * @param-out string $sync_type "calendar-get", "calendar-get-etag" or "sync-collection-report"
	 * @return string full URL after autodetection
	 * @throws \Exception
	 */
	public function test(?string &$sync_type=null)
	{
		if (!parse_url($this->url, PHP_URL_SCHEME))
		{
			$this->url = 'https://'.$this->url;
		}
		elseif (str_starts_with($this->url, 'webcal://'))
		{
			$this->url = 'https://'.substr($this->url, 9);
		}
		// check for an ics-file with Content-Type: text/calendar
		try {
			// iCloud always returns 400 Bad Request for HEAD requests
			try {
				// for iCloud try directly with GET, for others retry with GET on 400 Bad Request
				$method = preg_match('#^https://[^.]+\.icloud\.com/#i', $this->url) ? 'GET' : 'HEAD';
				api($this->url, $method, '', $this->header(['Accept: text/calendar']), $response_header);
			}
			catch (\HttpException $e) {
				if ($method === 'HEAD' && $e->getCode() == 400)
				{
					api($this->url, 'GET', '', $this->header(['Accept: text/calendar']), $response_header);
				}
				else
				{
					throw $e;
				}
			}
			if (preg_match('#^text/calendar(;|$)#', $response_header['content-type']) ||
				$response_header['content-type'] === 'application/octet-stream' &&
				str_starts_with(api($this->url, 'GET', '', $this->header(['Accept: text/calendar']), $response_header), 'BEGIN:VCALENDAR'))
			{
				$sync_type = 'calendar-get'.(!empty($response_header['etag']) ? '-etag' : '');
				return $this->url;
			}
		}
		catch (\HttpException $e) {
			// iCloud (caldav.icloud.com) will respond with 400 Bad Request to a HEAD request --> try CalDAV
		}
		try {
			$xml_reader = $this->propfind(0, [
				'resourcetype', 'supported-report-set', 'current-user-principal', 'displayname',
				['ns' => Api\CalDAV::CALDAV, 'name' => 'supported-calendar-component-set'],
				['ns' => Api\CalDAV::CALDAV, 'name' => 'calendar-home-set'],
				['ns' => Api\CalDAV::CALDAV, 'name' => 'calendar-description'],
				['ns' => Api\CalDAV::CALENDARSERVER, 'name' => 'getctag'],
			], [], $response_header);
		}
		catch (\HttpException $e) {
			if (in_array($e->getCode(), [200, 405]) && !parse_url(rtrim($this->url, '/'), PHP_URL_PATH))
			{
				$this->url = rtrim($this->url, '/').'/.well-known/caldav';
				return $this->test($sync_type);
			}
			throw $e;
		}
		if (($current_user_principal=(string)$xml_reader->response->propstat->prop->{'current-user-principal'}->href))
		{
			$this->url = ($current_user_principal[0] === '/'? 'https://'.parse_url($this->url, PHP_URL_HOST) : '').$current_user_principal;
			$xml_reader = $this->propfind(0, [
				['ns' => Api\CalDAV::CALDAV, 'name' => 'calendar-home-set'],
			], [], $response_header);
			// as calendar-home-set is CalDAV, we need to switch NS before AND after with children($ns)!
			if (!($calendar_home_set=(string)$xml_reader->response->propstat->prop->children(Api\CalDAV::CALDAV)->{'calendar-home-set'}->children()->href))
			{
				throw new \Exception(lang('Given URL is not a CalDAV server: missing %1!', "CALDAV property 'calendar-home-set'"));
			}
			$this->url = ($calendar_home_set[0] === '/'? 'https://'.parse_url($this->url, PHP_URL_HOST) : '').$calendar_home_set;
			$xml_reader = $this->propfind(1, [
				'resourcetype', 'supported-report-set', 'displayname',
				['ns' => Api\CalDAV::CALDAV, 'name' => 'supported-calendar-component-set'],
				['ns' => Api\CalDAV::CALDAV, 'name' => 'calendar-description'],
				['ns' => Api\CalDAV::CALENDARSERVER, 'name' => 'getctag'],
			], [], $response_header);
		}
		$urls = [];
		foreach($xml_reader->xpath('//D:response') as $calendar)
		{
			$reports = $resource_types = [];
			if (isset($calendar_home_set) && (string)$calendar->href === parse_url($this->url, PHP_URL_PATH))
			{
				continue;   // self-url of propfind depth 1
			}
			foreach ($calendar->propstat->prop->resourcetype->children(Api\CalDAV::CALDAV) as $type)
			{
				$resource_types[] = $type->getName();
			}
			if (!in_array('calendar', $resource_types))
			{
				if (!empty($calendar_home_set)) continue;
				throw new \Exception(lang('Given URL is not a CalDAV server: missing %1!', "resourcetype 'calendar'"));
			}
			// check if the collection allows VEVENT
			$supported_calendar_component_set = $calendar->propstat->prop->children(Api\CalDAV::CALDAV)->{'supported-calendar-component-set'};
			if ($supported_calendar_component_set &&
				!str_contains($supported_calendar_component_set->asXML(), 'name="VEVENT"'))
			{
				continue;   // ignore collections not for events
			}
			// no idea how to get SimpleXMLElement to parse that :(
			$xml = $calendar->propstat->prop->{'supported-report-set'}->asXML();
			$reports = preg_match_all('#<report><([^ /]+)[^/]*/></report>#', $xml, $matches, PREG_PATTERN_ORDER) ?
				$matches[1] : [];
			// check if sync-collection is supported and prefer it
			if (in_array('sync-collection', $reports))
			{
				$sync_type = 'sync-collection-report';
			}
			else
			{
				$sync_type = 'calendar-propfind';
				// check getctag is supported to use calendar-query report
				$calendar->registerXPathNamespace('CS', Api\CalDAV::CALENDARSERVER);
				if ($calendar->xpath('//CS:getctag'))
				{
					$sync_type .= '-ctag';
				}
			}
			if (($url = (string)$calendar->href) && $url[0] === '/')
			{
				$url = 'https://'.parse_url($this->url, PHP_URL_HOST).$url;
			}
			$urls[$url] = (string)$calendar->propstat->prop->displayname ?: basename((string)$calendar->href);
		}
		return count($urls) === 1 ? key($urls) : $urls;
	}

	/**
	 * Return request-headers: User-Agent and Authorization
	 *
	 * @param array $header additional headers to return
	 * @return array
	 */
	protected function header(array $header=[])
	{
		$header[] = 'User-Agent: '.str_replace('\\', '/', __CLASS__).'/'.$GLOBALS['egw_info']['apps']['api']['version'];

		if (!empty($this->user) && !empty($this->password))
		{
			$header[] = 'Authorization: Basic '.base64_encode($this->user.':'.$this->password);
		}
		return $header;
	}

	/**
	 * Make a PROPFIND request
	 *
	 * @param int|"infinit" $depth
	 * @param array[]|null $props default null="DAV:allprop" or requested props as array[] with values for keys "ns" and "name"
	 * @param string[] $header values are strings header-name: header-value
	 * @param-out array|null &$response_header keys are lowercased header-names
	 * @return \SimpleXMLElement
	 */
	protected function propfind($depth=0, ?array $props=null, array $header=[], ?array &$response_header=null)
	{
		$namespaces = [];
		if (!$props)
		{
			$prop_xml = "<allprop/>";
		}
		else
		{
			$prop_xml = "<prop>\n";
			foreach($props as $prop)
			{
				if (is_string($prop) || $prop['ns'] === 'DAV:')
				{
					$prop_xml .= "\t\t<$prop/>\n";
				}
				elseif (!is_array($prop) || empty($prop['ns']) || empty($prop['name']))
				{
					throw new \Exception("Invalid prop " . json_encode($prop) . '!');
				}
				else
				{
					if (!($prefix = array_search($prop['ns'], $namespaces)))
					{
						for($prefix='A'; isset($namespaces[$prefix]); $prefix=chr(ord($prefix)+1)){}
						$namespaces[$prefix] = $prop['ns'];
					}
					$prop_xml .= "\t\t<$prefix:$prop[name] xmlns:$prefix=\"$prop[ns]\"/>\n";
				}
			}
			$prop_xml .= "\t</prop>";
		}
		$xml = api($this->url, 'PROPFIND', $body=<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<propfind xmlns="DAV:">
	$prop_xml
</propfind>
EOT, $this->header([
			"Content-Type: application/xml; charset=utf8",
			'Accept: application/xml',
			"Depth: $depth",
		]), $response_header);
		if (($code=(int)explode(' ', $response_header[0])[1]) !== 207)
		{
			throw new \HttpException(lang('Given URL is not a CalDAV server: missing %1!', "HTTP status: 207 Multi-Status"),
				$code, 'PROPFIND', $this->url, $body, $response_header, $xml);
		}
		// SimpleXMLElement fails if toplevel namespace used a prefix :(
		if (str_contains($xml, '<D:multistatus xmlns:D="DAV:">'))
		{
			$xml = preg_replace('#(</?)D:#', '$1',
				str_replace('<D:multistatus xmlns:D="DAV:">', '<D:multistatus xmlns="DAV:">', $xml));
		}
		$simple_xml = new \SimpleXMLElement($xml);
		$simple_xml->registerXPathNamespace('D', 'DAV:');
		$simple_xml->registerXPathNamespace('CALDAV', Api\CalDAV::CALDAV);
		if (!$simple_xml->response)
		{
			throw new \HttpException(lang('Given URL is not a CalDAV server: missing %1!', "XML response element"),
				$code, 'PROPFIND', $this->url, $body, $response_header, $xml);
		}
		return $simple_xml;
	}

	/**
	 * Multistatus response for sync-collection REPORT
	 *
	 * @param string|null &$sync_token
	 * @param bool $yield_href_ical=false
	 * @return \Generator $yield_href_ical=false: SimpleXMLElement $response->children('DAV:') with: (all need to be cast to string!)
	 * ->href
	 * ->propstat->status
	 * ->propstat->prop->etag
	 * ->propstat->prop->children(Api\CalDAV::CALDAV)->{'calendar-data'} (iCal)
	 * $yield_href_ical = true: key: href, value: iCal string or null (for 404 Not found)
	 * @throws \Exception on error
	 */
	protected function sync_collection(?string &$sync_token=null, bool $yield_href_ical=false)
	{
		$xml = api($this->url, 'REPORT', $body=<<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<D:sync-collection xmlns:D="DAV:">
  <D:sync-token>$sync_token</D:sync-token>
  <D:sync-level>1</D:sync-level>
  <D:prop>
    <D:getetag/>
    <C:calendar-data xmlns:C="urn:ietf:params:xml:ns:caldav"/>
  </D:prop>
</D:sync-collection>
EOT, $this->header([
			'Content-Type: application/xml; charset=utf-8',
			'Accept: application/xml',
			'Depth: 1',
		]), $response_header);
		if ((int)($http_status = explode(' ', $response_header[0], 2)[1]) != 207)
		{
			throw new \Exception("Unexpected HTTP status code $http_status for sync-collection REPORT: ".
				($response_header['www-authenticate'] ?? ''), (int)$http_status,
				'REPORT', $this->url, $body, $response_header);
		}
		$xml_reader = new \SimpleXMLElement($xml, 0, false, 'DAV', false);
		$xml_reader->registerXPathNamespace('D', 'DAV:');

		foreach($xml_reader->xpath('//D:response') as $response)
		{
			if ((string)$response->href === parse_url($this->url, PHP_URL_PATH))
			{
				continue;   // ignore self-url
			}
			$dav_children = $response->children(Api\CalDAV::DAV);
			if ($yield_href_ical)
			{
				list(, $status) = explode(' ', (string)$dav_children->propstat->status);
				if ($status === 404)
				{
					yield (string)$dav_children->href => null;
				}
				// iCloud does not send calendar-data, even if requested
				if (!($ical = (string)$dav_children->propstat->prop->children(Api\CalDAV::CALDAV)->{'calendar-data'}))
				{
					$ical = api('https://'.parse_url($this->url, PHP_URL_HOST).(string)$dav_children->href,
						'GET', '', $this->header([
							'Accept: text/calendar; charset=utf-8',
						]));
				}
				yield (string)$dav_children->href => $ical;
			}
			else
			{
				yield $dav_children;
			}
		}
		$sync_token = (string)$xml_reader->xpath('//D:sync-token')[0];
	}

	/**
	 * Sync the subscribed calendar using $this->sync_type
	 *
	 * @param string|null &$sync_token current sync_token or etag and on return new one
	 * @param array $modifications
	 * @throws \Exception
	 */
	public function sync(?string &$sync_token, array $modifications)
	{
		if (empty($this->sync_type))
		{
			$this->test($this->sync_type);
		}
		switch($this->sync_type)
		{
			case 'calendar-get':
			case 'calendar-get-etag':
				return $this->sync_calendar_get($sync_token, $modifications);

			case 'sync-collection-report':
				return $this->sync_collection_report($sync_token, $modifications);

			case 'calendar-propfind':
			case 'calendar-propfind-ctag':
				return $this->calendar_propfind($sync_token, $modifications);

			default:
				throw new \Exception("Invalid sync_type '$this->sync_type'!");
		}
	}

	/**
	 * Modify imported event
	 *
	 * @param array $event
	 * @param array $modifications
	 * @return array
	 */
	protected static function modify(array $event, array $modifications)
	{
		$event['category'] = empty($event['category']) ? $modifications['cat_id'] : $event['category'].','.$modifications['cat_id'];
		foreach($modifications['participants'] as $uid)
		{
			if (!isset($event['participants'][$uid]))
			{
				$event['participants'][$uid] = 'U';
			}
		}
		if (!empty($modifications['set_private']))
		{
			$event['public'] = 0;
		}
		if (!empty($modifications['non_blocking']))
		{
			$event['non_blocking'] = 1;
		}
		$event['deleted'] = null;   // in case it's an existing event that has been deleted
		return $event;
	}

	/**
	 * @param string|null $sync_token
	 * @param array $modifications
	 * @return void
	 * @throws \Exception
	 */
	protected function sync_collection_report(?string &$sync_token, array $modifications)
	{
		$ical_class = new \calendar_ical();
		foreach($this->sync_collection($sync_token, true) as $href => $ical)
		{
			if ($ical)
			{
				$ical_class->event_callback = static function(array &$event) use ($href, $modifications)
				{
					$event = self::modify($event, $modifications);
					$event['#sync-href'] = $href;
					return true;
				};
				$ical_class->importVCal($ical, -1, null, false, 0, '',
					null, null, null, true);
			}
			elseif (($event = $ical_class->read(['#sync-href' => $href])))
			{
				$ical_class->delete($event['id'], 0, true, true);
			}
		}
	}

	/**
	 * Run PROPFIND on a calendar collection
	 *
	 * @param-out string|null &$getctag
	 * @return \Generator href => propstat
	 * @throws \Exception
	 */
	protected function profind_collection(?string &$getctag=null)
	{
		$xml = $this->propfind(1, [
			['ns' => Api\CalDAV::CALENDARSERVER, 'name' => 'getctag'],
			'getetag',
			['ns' => Api\CalDAV::CALDAV, 'name' => 'calendar-data'],
		]);
		$xml->registerXPathNamespace('D', 'DAV:');
		foreach($xml->xpath('//D:response') as $key => $response)
		{
			if (!$key) continue;    // do NOT return the collection itself

			$dav_children = $response->children(Api\CalDAV::DAV);
			yield (string)$dav_children->href => (int)(string)$dav_children->propstat->status === 404 ?
				null : (array)$dav_children->propstat->prop->children(Api\CalDAV::CALDAV) +
				(array)$dav_children->propstat->prop->children('DAV:');
		}
		$xml->registerXPathNamespace('CS', Api\CalDAV::CALENDARSERVER);
		$getctag = ($val=$xml->xpath('//CS:getctag')) ? (string)$val[0] : null;
	}

	/**
	 * Sync via PROPFIND on a calendar collection (optionally using getctag)
	 *
	 * @param string|null $getctag
	 * @param array $modifications
	 * @return void
	 * @throws \Exception
	 * @ToDo: request only etag first, check with $old_events if changed, and then use calendar-multiget report to only get the changes
	 */
	protected function calendar_propfind(?string &$getctag, array $modifications)
	{
		// check if we already have a ctag and the current one is identical
		if (!empty($getctag))
		{
			$xml = $this->propfind(0, [['ns'=>Api\CalDAV::CALENDARSERVER,'name'=>'getctag']]);
			$xml->registerXPathNamespace('CS', Api\CalDAV::CALENDARSERVER);
			if (((string)$xml->xpath('//CS:getctag')[0]) === $getctag)
			{
				return;
			}
		}
		$ical_class = new \calendar_ical();
		// fetch current events, to be able to delete the ones no longer returned
		$old_events = $ical_class->search(['cat_id' => $modifications['cat_id'], 'enum_recuring' => false]);
		foreach($this->profind_collection($getctag) as $href => $props)
		{
			if (($ical = $props['calendar-data'] ?? null))
			{
				$ical_class->event_callback = static function(array &$event) use ($href, $modifications, &$old_events, $props)
				{
					$event = self::modify($event, $modifications);
					$event['#sync-href'] = $href;
					$event['#sync-etag'] = $props['getetag'];
					// delete imported event from $old_events (plus non-calendar event with not-numeric ids)
					$old_events = array_filter($old_events, static function(array $old_event) use($event)
					{
						return is_numeric($old_event['id']) && $old_event['uid'] !== $event['uid'];
					});
					return true;
				};
				$ical_class->importVCal($ical, -1, null, false, 0, '',
					null, null, null, true);
			}
		}
		// delete NOT imported $old_events
		foreach($old_events as $old_event)
		{
			// make sure to NOT delete other, not synced, events, or already deleted ones
			if (empty($old_event['deleted']) && !empty($old_event['category']) &&
				strstr(','.$old_event['category'].',', ','.$modifications['cat_id'].',') !== false)
			{
				$ical_class->delete($old_event['id'], 0, false, true);
			}
		}
	}

	/**
	 * Sync via ics file with or without ETag
	 *
	 * Existing event of given $cat_id will be queried before and deleted, if they are no longer in the imported file.
	 *
	 * @param string|null $etag
	 * @param array $modifications
	 * @return array|void
	 * @throws \JsonException
	 */
	protected function sync_calendar_get(?string &$etag, array $modifications)
	{
		// check for an ics-file with Content-Type: text/calendar
		if (!empty($etag))
		{
			try {
				// for iCloud try directly with GET, for others retry with GET on 400 Bad Request
				$method = preg_match('#^https://[^.]+\.icloud\.com/#i', $this->url) ? 'GET' : 'HEAD';
				$response = api($this->url, $method, '', $this->header([
					'Accept: text/calendar',
					'If-None-Match: '.$etag,
				]), $response_header);
			}
			catch (\HttpException $e) {
				if ($method === 'HEAD' && $e->getCode() == 400)
				{
					$response = api($this->url, 'GET', '', $this->header([
						'Accept: text/calendar',
						'If-None-Match: '.$etag,
					]), $response_header);
				}
				else
				{
					throw $e;
				}
			}
			// check for 304 Not Modified or unchanged ETag
			if (explode(' ', $response_header[0])[1] == 304 || $response_header['etag'] === $etag)
			{
				return;
			}
		}
		$ical_class = new \calendar_ical();
		// fetch current events, to be able to delete the ones no longer returned
		$old_events = $ical_class->search(['cat_id' => $modifications['cat_id']]);
		$ical_class->event_callback = static function(array &$event) use ($modifications, &$old_events)
		{
			$event = self::modify($event, $modifications);
			// delete imported event from $old_events (plus non-calendar event with not-numeric ids)
			$old_events = array_filter($old_events, static function(array $old_event) use($event)
			{
				return is_numeric($old_event['id']) && $old_event['uid'] !== $event['uid'];
			});
			return true;
		};
		$ical_class->importVCal($response ?? api($this->url, 'GET', '', $this->header([
			'Accept: text/calendar',
		]), $response_header), -1, null, false, 0, '',
			null, null, null, true);

		// delete NOT imported $old_events
 		foreach($old_events as $old_event)
		{
			// make sure to NOT delete other, not synced, events, or already deleted ones
			if (empty($old_event['deleted']) && !empty($old_event['category']) &&
				strstr(','.$old_event['category'].',', ','.$modifications['cat_id'].',') !== false)
			{
				$ical_class->delete($old_event['id'], 0, false, true);
			}
		}
		$etag = $response_header['etag'];
	}

	/**
	 * Run subscribed calendar sync as cronjob
	 *
	 * @param int $cat_id
	 */
	public static function cronjob(int $cat_id)
	{
		if (($data = self::readSubscription($cat_id)))
		{
			try {
				$self = new self($data['url'], $data['user']??null, $data['password']??null, $data['sync_type']??null);
				$self->sync($data['sync_token'], array_intersect_key($data, array_flip(['cat_id', 'participants', 'set_private', 'non_blocking'])));
				unset($data['error_time'], $data['error_msg'], $data['error_trace']);
			}
			catch (\Throwable $e) {
				_egw_log_exception($e);
				$data['error_time'] = Api\DateTime::to('now');
				$data['error_msg'] = $e->getMessage();
				$data['error_trace'] = $e->getTrace();
			}
			self::writeSubscription($data);
		}
	}

	/**
	 * Read a subscription
	 *
	 * @param int $id cat_id
	 * @return array values for keys "cat_id", "name", "url", "user", "password", "color", "icon", ...
	 * @throws Api\Exception\AssertionFailed
	 * @throws Api\Exception\NotFound
	 * @throws Api\Exception\WrongParameter
	 */
	public static function readSubscription(int $id) : array
	{
		$cats = new Api\Categories('', 'calendar');
		if (!($cat = $cats->read($id)))
		{
			throw new Api\Exception\NotFound("Unknown subscription-id='$id'!");
		}
		return [
			'cat_id' => $cat['id'],
			'name' => $cat['name'],
			'password' => empty($cat['data']['password']) || strlen($cat['data']['password']) <= 32 ? $cat['data']['password'] :
				Api\Mail\Credentials::decrypt(['cred_pw_enc' => Api\Mail\Credentials::SYSTEM_AES, 'cred_password' => $cat['data']['password']]),
		]+$cat['data'];
	}

	public const SUBSCRIBED_CALENDAR = '*SUBSCRIBED-CALENDAR*';

	/**
	 * Update/create subscription
	 *
	 * @param array $data values for keys "cat_id", "name", "url", "user", "password", "color", "icon", ...
	 * @return int cat_id
	 */
	public static function writeSubscription(array $data) : int
	{
		$cats = new Api\Categories('', 'calendar');
		return $cats->add([
			'id' => $data['cat_id'] ?? null,
			'name' => $data['name'],
			'access' => 'private',
			'description' => lang('Subscribed calendar').' '.
				(!empty($data['user'])?$data['user'].'@':'').parse_url($data['url'], PHP_URL_HOST),
			'data' => [
					'type' => self::SUBSCRIBED_CALENDAR,
					// encrypt password with system secret (can NOT use user-password as running as cronjob!)
					'password' => Api\Mail\Credentials::encrypt($data['password'], 0, $pw_enc, true),
				]+array_diff_key($data, array_flip(['cat_id','name'])),
		]);
	}

	/**
	 * List subscriptions of current user
	 *
	 * @return array cat_id => name pairs
	 */
	public static function listSubscriptions() : array
	{
		$cats = new Api\Categories('', 'calendar');
		$subscriptions = [];
		foreach($cats->return_array('appandmains', 0, false) as $cat)
		{
			if ($cat['owner'] == $GLOBALS['egw_info']['user']['account_id'] &&
				$cat['access'] === 'private' &&
				isset($cat['data']['type']) && $cat['data']['type'] === Api\CalDAV\Sync::SUBSCRIBED_CALENDAR)
			{
				$subscriptions[$cat['id']] = $cat['name'];
			}
		}
		return $subscriptions;
	}

	/**
	 * Delete subscription
	 *
	 * @param int $id cat_id
	 * @return void
	 */
	public static function deleteSubscription(int $id)
	{
		$cats = new Api\Categories('', 'calendar');
		$cats->delete($id);
	}
}