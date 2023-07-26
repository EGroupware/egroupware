<?php
/**
 * EGroupware Calendar - JsCalendar
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package calendar
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

/**
 * Rendering events as JSON using new JsCalendar format
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8984
 * @link https://jmap.io/spec-calendars.html
 */
class JsCalendar
{
	const MIME_TYPE = "application/jscalendar+json";
	const MIME_TYPE_JSEVENT = "application/jscalendar+json;type=event";
	const MIME_TYPE_JSTASK = "application/jscalendar+json;type=task";
	const MIME_TYPE_JSON = "application/json";

	const TYPE_EVENT = 'Event';

	/**
	 * Get JsEvent for given event
	 *
	 * @param int|array $event
	 * @param bool|"pretty" $encode=true true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @param ?array $exceptions=null
	 * @return string|array
	 * @throws Api\Exception\NotFound
	 */
	public static function JsEvent($event, $encode=true, array $exceptions=[])
	{
		if (is_scalar($event) && !($event = self::getCalendar()->read($event)))
		{
			throw new Api\Exception\NotFound();
		}
		$data = [
			self::AT_TYPE => self::TYPE_EVENT,
			'prodId' => 'EGroupware Calendar '.$GLOBALS['egw_info']['apps']['api']['version'],
			'uid' => self::uid($event['uid']),
			'sequence' => $event['etag'],
			'created' => self::UTCDateTime($event['created']),
			'updated' => self::UTCDateTime($event['modified']),
			'title' => $event['title'],
			'start' => self::DateTime($event['start'], $event['tzid']),
			'timeZone' => $event['tzid'],
			'showWithoutTime' => $event['whole_day'],
			'duration' => self::Duration($event['start'], $event['end'], $event['whole_day']),
			'recurrenceRules' => null,
			'recurrenceOverrides' => null,
			'freeBusyStatus' => $event['non_blocking'] ? 'free' : null,   // default is busy
			'description' => $event['description'],
			'participants' => self::Participants($event),
			'alerts' => self::Alerts($event['alarm']),
			'status' => empty($event['deleted']) ? 'confirmed' : 'cancelled',   // we have no "tentative" event-status (only participants)!
			'priority' => self::Priority($event['priority']),
			'categories' => self::categories($event['category']),
			'privacy' => $event['public'] ? 'public' : 'private',
			'egroupware.org:customfields' => self::customfields($event),
		] + self::Locations($event);

		if (!empty($event['recur_type']))
		{
			$data = array_merge($data, self::Recurrence($event, $data, $exceptions));
		}
		$data = array_filter($data);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	/**
	 * Parse JsEvent
	 *
	 * We use strict parsing for "application/jscalendar+json" content-type, not for "application/json".
	 * Strict parsing checks objects for proper @type attributes and value attributes, non-strict allows scalar values.
	 *
	 * Non-strict parsing also automatic detects patch for POST requests.
	 *
	 * @param string $json
	 * @param array $old=[] existing contact for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array
	 */
	public static function parseJsEvent(string $json, array $old=[], string $content_type=null, $method='PUT')
	{
		try
		{
			$strict = !isset($content_type) || !preg_match('#^application/json#', $content_type);
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH' || !$strict && $method === 'POST' && array_filter(array_keys($data), static function ($key)
			{
				return strpos($key, '/') !== false;
			}))
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::getJsCalendar($old, false) : [], !$old);
			}

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			$event = [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'uid':
						$event['uid'] = self::parseUid($value, $old['uid'], !$strict);
						break;

					case 'title':
						$event['title'] = $value;
						break;

					case 'description':
						$event['description'] = $value;
						break;

					case 'start':
					case 'duration':
					case 'timeZone':
					case 'showWithoutTime':
						if (!isset($event['start']))
						{
							$event += self::parseStartDuration($data);
						}
						break;


					case 'freeBusyStatus':
						$event['non_blocking'] = $value === 'free';   // default is busy
						break;

					case 'participants':
						$event['participants'] = self::parseParticipants($value);
						break;

					case 'priority':
						$event['priority'] = self::parsePriority($value);
						break;

					case 'privacy':
						$event['public'] = $value !== 'private';
						break;

					case 'alerts':
						throw new \Exception('Creating or modifying alerts is NOT (yet) implemented!');
						break;

					case 'recurrenceRules':
					case 'recurrenceOverrides':
					case 'excludedRecurrenceRules':
						throw new \Exception('Creating or modifying recurring events is NOT (yet) implemented!');
						break;

					case 'categories':
						$event['category'] = self::parseCategories($value);
						break;

					case 'egroupware.org:customfields':
						$event += self::parseCustomfields($value, $strict);
						break;

					case 'prodId':
					case 'created':
					case 'updated':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsCalendar Event', $name, $value);
		}

		// if no participant given add current user as CHAIR to the event
		if (empty($event['participants']))
		{
			$event['participants'][$GLOBALS['egw_info']['user']['account_id']] = 'ACHAIR';
		}

		return $event;
	}

	const URN_UUID_PREFIX = 'urn:uuid:';
	const UUID_PREG = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';

	/**
	 * Get UID with either "urn:uuid:" prefix for UUIDs or just the text
	 *
	 * @param string $uid
	 * @return string
	 */
	protected static function uid(string $uid)
	{
		return preg_match(self::UUID_PREG, $uid) ? self::URN_UUID_PREFIX.$uid : $uid;
	}

	/**
	 * Parse and optionally generate UID
	 *
	 * @param string|null $uid
	 * @param string|null $old old value, if given it must NOT change
	 * @param bool $generate_when_empty true: generate UID if empty, false: throw error
	 * @return string without urn:uuid: prefix
	 * @throws \InvalidArgumentException
	 */
	protected static function parseUid(string $uid=null, string $old=null, bool $generate_when_empty=false)
	{
		if (empty($uid) || strlen($uid) < 12)
		{
			if (!$generate_when_empty)
			{
				throw new \InvalidArgumentException("Invalid or missing UID: ".json_encode($uid));
			}
			$uid = \HTTP_WebDAV_Server::_new_uuid();
		}
		if (strpos($uid, self::URN_UUID_PREFIX) === 0)
		{
			$uid = substr($uid, strlen(self::URN_UUID_PREFIX));
		}
		if (isset($old) && $old !== $uid)
		{
			throw new \InvalidArgumentException("You must NOT change the UID ('$old'): ".json_encode($uid));
		}
		return $uid;
	}

	/**
	 * JSON options for errors thrown as exceptions
	 */
	const JSON_OPTIONS_ERROR = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;

	const AT_TYPE = '@type';

	/**
	 * Return EGroupware custom fields
	 *
	 * @param array $contact
	 * @return array
	 */
	protected static function customfields(array $contact)
	{
		$fields = [];
		foreach(Api\Storage\Customfields::get('calendar') as $name => $data)
		{
			$value = $contact['#'.$name];
			if (isset($value))
			{
				switch($data['type'])
				{
					case 'date-time':
						$value = Api\DateTime::to($value, Api\DateTime::RFC3339);
						break;
					case 'float':
						$value = (double)$value;
						break;
					case 'int':
						$value = (int)$value;
						break;
					case 'select':
						$value = explode(',', $value);
						break;
				}
				$fields[$name] = array_filter([
					'value' => $value,
					'type' => $data['type'],
					'label' => $data['label'],
					'values' => $data['values'],
				]);
			}
		}
		return $fields;
	}

	/**
	 * Parse custom fields
	 *
	 * Not defined custom fields are ignored!
	 * Not send custom fields are set to null!
	 *
	 * @param array $cfs name => object with attribute data and optional type, label, values
	 * @return array
	 */
	protected static function parseCustomfields(array $cfs)
	{
		$contact = [];
		$definitions = Api\Storage\Customfields::get('calendar');

		foreach($definitions as $name => $definition)
		{
			$data = $cfs[$name];
			if (isset($data))
			{
				if (is_scalar($data))
				{
					$data = ['value' => $data];
				}
				if (!is_array($data) || !array_key_exists('value', $data))
				{
					throw new \InvalidArgumentException("Invalid customfield object $name: ".json_encode($data, self::JSON_OPTIONS_ERROR));
				}
				switch($definition['type'])
				{
					case 'date-time':
						$data['value'] = Api\DateTime::to($data['value'], 'object');
						break;
					case 'float':
						$data['value'] = (double)$data['value'];
						break;
					case 'int':
						$data['value'] = round($data['value']);
						break;
					case 'select':
						if (is_scalar($data['value'])) $data['value'] = explode(',', $data['value']);
						$data['value'] = array_intersect(array_keys($definition['values']), $data['value']);
						$data['value'] = $data['value'] ? implode(',', (array)$data['value']) : null;
						break;
				}
				$contact['#'.$name] = $data['value'];
			}
			// set not return cfs to null
			else
			{
				$contact['#'.$name] = null;
			}
		}
		// report not existing cfs to log
		if (($not_existing=array_diff(array_keys($cfs), array_keys($definitions))))
		{
			error_log(__METHOD__."() not existing/ignored custom fields: ".implode(', ', $not_existing));
		}
		return $contact;
	}

	/**
	 * Return object of category-name(s) => true
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.5.4
	 * @param ?string $cat_ids comma-sep. cat_id's
	 * @return true[]
	 */
	protected static function categories(?string $cat_ids)
	{
		$cat_ids = array_filter($cat_ids ? explode(',', $cat_ids): []);

		return array_combine(array_map(static function ($cat_id)
		{
			return Api\Categories::id2name($cat_id);
		}, $cat_ids), array_fill(0, count($cat_ids), true));
	}

	/**
	 * Parse categories object
	 *
	 * @param array $categories category-name => true pairs
	 * @return ?string comma-separated cat_id's
	 */
	protected static function parseCategories(array $categories)
	{
		static $bo=null;
		$cat_ids = [];
		if ($categories)
		{
			if (!isset($bo)) $bo = new \calendar_boupdate();
			$cat_ids = $bo->find_or_add_categories(array_keys($categories));
		}
		return $cat_ids ? implode(',', $cat_ids) : null;
	}

	/**
	 * Return a localized string
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-1.5.3
	 * @param string $value
	 * @param ?string $language
	 * @param string[] $localications map with extra language => value pairs
	 * @return array[] with values for keys "value", "language" and "localizations"
	 */
	protected static function localizedString($value, string $language=null, array $localications=[])
	{
		if (empty($value) && !$localications)
		{
			return null;
		}
		return array_filter([
			'value' => $value,
			'language' => $language,
			'localizations' => $localications,
		]);
	}

	/**
	 * Parse localized string
	 *
	 * We're not currently storing/allowing any localization --> they get ignored/thrown away!
	 *
	 * @param string $value =null
	 * @return string
	 */
	protected static function parseString(string $value=null)
	{
		return $value;
	}

	/**
	 * Return a date-time value in UTC
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#section-1.4.4
	 * @param null|string|\DateTime $date
	 * @return string|null
	 */
	protected static function UTCDateTime($date)
	{
		static $utc=null;
		if (!isset($utc)) $utc = new \DateTimeZone('UTC');

		if (!isset($date))
		{
			return null;
		}
		$date = Api\DateTime::to($date, 'object');
		$date->setTimezone($utc);

		// we need to use "Z", not "+00:00"
		return substr($date->format(Api\DateTime::RFC3339), 0, -6).'Z';
	}

	const DATETIME_FORMAT = 'Y-m-d\TH:i:s';

	/**
	 * Return a date-time value in the given timezone
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#name-localdatetime
	 * @param null|string|\DateTime $date
	 * @return string|null
	 */
	protected static function DateTime($date, $timezone)
	{
		static $timezones = [];
		if (!isset($timezones[$timezone])) $timezones[$timezone] = new \DateTimeZone($timezone);

		if (!isset($date))
		{
			return null;
		}
		$date = Api\DateTime::to($date, 'object');
		$date->setTimezone($timezones[$timezone]);

		return $date->format(self::DATETIME_FORMAT);
	}

	/**
	 * Return a duration calculated from given start- and end-time
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#name-duration
	 * @param int|string|\DateTime $start
	 * @param int|string|\DateTime $end
	 * @param bool $whole_day
	 * @return string
	 */
	protected static function Duration($start, $end, bool $whole_day)
	{
		$start = Api\DateTime::to($start, 'object');
		$end = Api\DateTime::to($end, 'object');

		$value = $end->getTimestamp() - $start->getTimestamp() + (int)$whole_day;

		$duration = '';
		if ($value < 0)
		{
			$duration .= '-';
			$value *= -1;
		}
		$duration .= 'P';
		foreach([
			'D' => 86400,
			'H' => 3600,
			'M' => 60,
			'S' => 1,
		] as $unit => $n)
		{
			if ($value >= $n || $unit === 'S' && substr($duration, -2) === 'PT')
			{
				$duration .= intdiv($value, $n).$unit;
				if (!($value %= $n)) break;
			}
			if ($unit === 'D') $duration .= 'T';
		}
		return $duration;
	}

	protected static function parseStartDuration(array $data)
	{
		$parsed = [];

		if (empty($data['start']))
		{
			throw new \InvalidArgumentException("Invalid or missing start: ".json_encode($data['start']));
		}
		else
		{
			$parsed['start'] = new Api\DateTime($data['start'], !empty($data['timeZone']) ? new \DateTimeZone($data['timeZone']) : null);
			$parsed['tzid'] = $data['timeZone'] ?? null;
		}
		if (empty($data['duration']) || !preg_match('/^(-)?P(\d+W)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/', $data['duration'], $matches))
		{
			throw new \InvalidArgumentException("Invalid or missing duration: ".json_encode($data['duration']));
		}
		$duration = new \DateInterval($data['duration']);
		$parsed['end'] = new Api\DateTime($parsed['start']);
		$parsed['end']->add($duration);
		if (($parsed['whole_day'] = !empty($data['showWithoutTime'])))
		{
			$parsed['end']->sub(\DateInterval::createFromDateString('1 sec'));
		}
		return $parsed;
	}

	const TYPE_PARTICIPANT = 'Participant';

	/**
	 * Return participants object
	 *
	 * @param array $event
	 * @return array
	 */
	protected static function Participants(array $event)
	{
		static $status2jscal = [
			'U' => 'needs-action',
			'A' => 'accepted',
			'R' => 'declined',
			'T' => 'tentative',
			//'' => 'delegated',
		];
		$participants = [];
		foreach($event['participants'] as $uid => $status)
		{
			\calendar_so::split_status($status, $quantity, $role);
			\calendar_so::split_user($uid, $user_type, $user_id);
			try {
				$info = self::getCalendar()->resource_info($uid) ?: [];
				switch($info['type'] ?? $info['app'])
				{
					case 'e':   // email
					case 'c':   // contact
					case 'u':   // user
						$info['kind'] = 'individual';
						break;
					case 'g':
						$info['kind'] = 'group';
						break;
					case 'r':
						$info['kind'] = Api\CalDAV\Principals::resource_is_location($user_id) ? 'location' : 'resource';
						break;
				}
			}
			catch (\Exception $e) {
				$info = [];
			}
			$participant = array_filter([
				self::AT_TYPE => self::TYPE_PARTICIPANT,
				'name' => $info['name'] ?? null,
				'email' => $info['email'] ?? null,
				'kind' => $info['kind'] ?? null,
				'roles' => array_filter([
					'owner' => $uid == $event['owner'],
					'chair' => $role === 'CHAIR',
					'attendee' => $role === 'REQ-PARTICIPANT',
					'optional' => $role === 'OPT-PARTICIPANT',
					'informational' => $role === 'NON-PARTICIPANT',
				]),
				'participationStatus' => $status2jscal[$status],
			]);
			$participants[$uid] = $participant;
		}

		return $participants;
	}

	const TYPE_LOCATION = 'Location';
	const TYPE_VIRTALLOCATION = 'VirtualLocation';

	/**
	 * Return locations object
	 *
	 * @param array $event
	 * @return array
	 */
	protected static function Locations(array $event)
	{
		$locations = [];
		if (!empty($event['location']))
		{
			$locations['locations']["1"] = [
				self::AT_TYPE => self::TYPE_LOCATION,
				'name' => $event['location'],
			];
		}
		// ToDo: set virtualLocations with videoconference link
		return $locations;
	}

	/**
	 * Return JsCal priority 0=undefined, 1=highest, ..., 9=lowest
	 *
	 * @param int $priority
	 * @return int
	 */
	protected static function Priority(int $priority)
	{
		static $priority_egw2jscal = array(
			0 => 0,		// undefined
			1 => 9,		// low
			2 => 5,		// normal
			3 => 1,		// high
		);
		return $priority_egw2jscal[$priority];
	}

	const TYPE_RECURRENCE_RULE = 'RecurrenceRule';
	const TYPE_NDAY = 'NDay';

	/**
	 * Return recurrence properties: recurrenceId, recurrenceRules, recurrenceOverrides, ...
	 *
	 * EGroupware only supports a subset of iCal recurrence rules (e.g. only byDay and byMonthDay, no other by-types)!
	 *
	 * @param array $event
	 * @param array $data JSCalendar representation of event to calculate overrides
	 * @param array $exceptions exceptions
	 * @return array
	 */
	protected static function Recurrence(array $event, array $data, array $exceptions=[])
	{
		if (empty($event['recur_type']))
		{
			return [];  // non-recurring event
		}
		$rriter = \calendar_rrule::event2rrule($event, false);
		$rrule = $rriter->generate_rrule('2.0');
		$rule = array_filter([
			self::AT_TYPE => self::TYPE_RECURRENCE_RULE,
			'frequency' => strtolower($rrule['FREQ']),
			'interval' => $rrule['INTERVAL'] ?? null,
			'until' => empty($rrule['UNTIL']) ? null : self::DateTime($rrule['UNTIL'], $event['tzid']),
		]);
		if (!empty($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts']) &&
			$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] !== 'Monday')
		{
			$rule['firstDayOfWeek'] = strtolower(substr($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'], 0, 2));
		}
		if (!empty($rrule['BYDAY']))
		{
			$rule['byDay'] = array_filter([
				self::AT_TYPE => self::TYPE_NDAY,
				'day' => strtolower(substr($rrule['BYDAY'], $rriter->monthly_byday_num ? strlen((string)$rriter->monthly_byday_num) : 0)),
				'nthOfPeriod' => $rriter->monthly_byday_num,
			]);
		}
		elseif (!empty($rrule['BYMONTHDAY']))
		{
			$rule['byMonthDay'] = [$rrule['BYMONTHDAY']];   // EGroupware supports only a single day!
		}

		$overrides = [];
		// adding excludes to the overrides
		if (!empty($event['recur_exception']))
		{
			foreach ($event['recur_exception'] as $timestamp)
			{
				$ex_date = new Api\DateTime($timestamp, Api\DateTime::$server_timezone);
				if (!empty($event['whole_day']))
				{
					$ex_date->setTime(0, 0, 0);
				}
				$overrides[self::DateTime($ex_date, $event['tzid'])] = [
					'excluded' => true,
				];
			}
		}

		// adding exceptions to the overrides
		foreach($exceptions as $exception)
		{
			$overrides[self::DateTime($exception['recurrence'], $event['tzid'])] = self::getPatch(self::JsEvent($exception, false), $data);
		}

		return array_filter([
			'recurrenceRules' => [$rule],
			'recurrenceOverrides' => $overrides,
		]);
	}

	/**
	 * Get patch from an event / recurrence compared to the master event
	 *
	 * @param array $event
	 * @param array $master
	 * @return array with modified attributes
	 */
	public static function getPatch(array $event, array $master=null)
	{
		if (!$master)
		{
			return $event;
		}
		// array_diff_assoc only reports values changed or set in $event for scalar values
		$patch = array_diff_assoc($event, $master);

		// we need to report unset / removed values
		foreach($master as $name => $value)
		{
			if (isset($value) && !isset($event[$name]))
			{
				$patch[$name] = null;
			}
		}

		// for non-scalar values, we have to call ourselves recursive
		foreach($event as $name => $value)
		{
			if (is_array($value) && ($diff = self::getPatch($event[$name], $master[$name])))
			{
				$patch[$name] = $diff;
			}
		}
		return $patch;
	}

	const TYPE_ALERT = 'Alert';
	const TYPE_OFFSET_TRIGGER = 'OffsetTrigger';

	/**
	 * Return alerts object
	 *
	 * @param array|null $alarms
	 * @return array
	 */
	protected static function Alerts(array $alarms=null)
	{
		$alerts = [];
		foreach($alarms ?? [] as $alarm)
		{
			if (!isset($alarm['offset']) || empty($alarm['all']) && $alarm['owner'] != $GLOBALS['egw_info']['user']['account_id'])
			{
				continue;   // do NOT show other users alarms
			}
			$alerts[$alarm['uid']] = array_filter([
				self::AT_TYPE => self::TYPE_ALERT,
				'trigger' => [
					self::AT_TYPE => self::TYPE_OFFSET_TRIGGER,
					'offset' => $alarm['offset'],
				],
				'acknowledged' => empty($alarm['attrs']['ACKNOWLEDGED']['value']) ? null :
					self::UTCDateTime(new Api\DateTime($alarm['attrs']['ACKNOWLEDGED']['value'])),
			]);
		}
		return $alerts;
	}

	/**
	 * Patch JsEvent
	 *
	 * @param array $patches JSON path
	 * @param array $jsevent to patch
	 * @param bool $create =false true: create missing components
	 * @return array patched $jsevent
	 */
	public static function patch(array $patches, array $jsevent, bool $create=false)
	{
		foreach($patches as $path => $value)
		{
			$parts = explode('/', $path);
			$target = &$jsevent;
			foreach($parts as $n => $part)
			{
				if (!isset($target[$part]) && $n < count($parts)-1 && !$create)
				{
					throw new \InvalidArgumentException("Trying to patch not existing attribute with path $path!");
				}
				$parent = $target;
				$target = &$target[$part];
			}
			if (isset($value))
			{
				$target = $value;
			}
			else
			{
				unset($parent[$part]);
			}
		}
		return $jsevent;
	}

	/**
	 * Map all kind of exceptions while parsing to a JsCalendarParseException
	 *
	 * @param \Throwable $e
	 * @param string $type
	 * @param ?string $name
	 * @param mixed $value
	 * @throws JsCalendarParseException
	 */
	protected static function handleExceptions(\Throwable $e, $type='JsCalendar', ?string $name, $value)
	{
		try {
			throw $e;
		}
		catch (\JsonException $e) {
			throw new JsCalendarParseException("Error parsing JSON: ".$e->getMessage(), 422, $e);
		}
		catch (\InvalidArgumentException $e) {
			throw new JsCalendarParseException("Error parsing $type attribute '$name': ".
				str_replace('"', "'", $e->getMessage()), 422);
		}
		catch (\TypeError $e) {
			$message = $e->getMessage();
			if (preg_match('/must be of the type ([^ ]+( or [^ ]+)*), ([^ ]+) given/', $message, $matches))
			{
				$message = "$matches[1] expected, but got $matches[3]: ".
					str_replace('"', "'", json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			throw new JsCalendarParseException("Error parsing $type attribute '$name': $message", 422, $e);
		}
		catch (\Throwable $e) {
			throw new JsCalendarParseException("Error parsing $type attribute '$name': ". $e->getMessage(), 422, $e);
		}
	}

	/**
	 * @return \calendar_boupdate
	 */
	protected static function getCalendar()
	{
		static $calendar_bo=null;
		if (!isset($calendar_bo))
		{
			$calendar_bo = new \calendar_boupdate();
		}
		return $calendar_bo;
	}
}