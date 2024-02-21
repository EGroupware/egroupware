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
class JsCalendar extends JsBase
{
	const APP = 'calendar';

	const MIME_TYPE = "application/jscalendar+json";
	const MIME_TYPE_JSEVENT = "application/jscalendar+json;type=event";
	const MIME_TYPE_JSTASK = "application/jscalendar+json;type=task";

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
	 * @param ?int $calendar_owner owner of the collection
	 * @return array
	 */
	public static function parseJsEvent(string $json, array $old=[], string $content_type=null, $method='PUT', int $calendar_owner=null)
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
				// apply patch on JsEvent
				$data = self::patch($data, $old ? self::getJsCalendar($old, false) : [], !$old || !$strict);
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
						$event['participants'] = self::parseParticipants($value, $strict, $calendar_owner);
						break;

					case 'priority':
						$event['priority'] = self::parsePriority($value);
						break;

					case 'privacy':
						$event['public'] = $value !== 'private';
						break;

					case 'alerts':
					case 'useDefaultAlerts':
						if (!isset($event['alarm']))
						{
							$event['alarm'] = self::parseAlerts($data, $strict, $calendar_owner);
						}
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
						$event = array_merge($event, self::parseCustomfields($value, $strict));
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
			$event['participants'][$calendar_owner ?? $GLOBALS['egw_info']['user']['account_id']] = 'ACHAIR';
		}

		return $event;
	}

	/**
	 * Parse categories object
	 *
	 * @param array $categories category-name => true pairs
	 * @param bool $multiple
	 * @return ?string comma-separated cat_id's
	 */
	protected static function parseCategories(array $categories, bool $multiple=true)
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
		$parsed['start'] = new Api\DateTime($data['start'], !empty($data['timeZone']) ? new \DateTimeZone($data['timeZone']) : null);
		$parsed['tzid'] = $data['timeZone'] ?? null;

		$duration = self::parseSignedDuration($data['duration'] ?? null);
		$parsed['end'] = new Api\DateTime($parsed['start']);
		$parsed['end']->add($duration);
		if (($parsed['whole_day'] = !empty($data['showWithoutTime'])))
		{
			$parsed['end']->sub(\DateInterval::createFromDateString('1 sec'));
		}
		return $parsed;
	}

	/**
	 * Parse a signed duration
	 *
	 * @param string $duration
	 * @param bool $return_secs true: return seconds as integer, false/default: return \DateInterval
	 * @return \DateInterval|int
	 * @throws \Exception
	 */
	protected static function parseSignedDuration(string $duration, bool $return_secs=false)
	{
		if (empty($duration) || !preg_match('/^(-)?P(\d+W)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/', $duration))
		{
			throw new \InvalidArgumentException("Invalid or missing duration: ".json_encode($duration));
		}
		$interval = new \DateInterval($duration);

		if ($return_secs)
		{
			$reference = new \DateTimeImmutable('now');
			$endtime = $reference->add($interval);
			return $endtime->getTimestamp() - $reference->getTimestamp();
		}
		return $interval;
	}

	const TYPE_PARTICIPANT = 'Participant';

	static $status2jscal = [
		'U' => 'needs-action',
		'A' => 'accepted',
		'R' => 'declined',
		'T' => 'tentative',
		//'' => 'delegated',
	];

	/**
	 * Return participants object
	 *
	 * @param array $event
	 * @return array
	 * @todo Resources and Groups without email	 */
	protected static function Participants(array $event)
	{
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
				'participationStatus' => self::$status2jscal[$status],
			]);
			$participants[$uid] = $participant;
		}

		return $participants;
	}

	/**
	 * Parse participants object
	 *
	 * @param array $participants
	 * @param bool $strict true: require @types and objects with attributes name, email, ...
	 * @param ?int $calendar_owner owner of the calendar / collection
	 * @return array
	 * @todo Resources and Groups without email
	 */
	protected static function parseParticipants(array $participants, bool $strict=true, int $calendar_owner=null)
	{
		$parsed = [];

		foreach($participants as $uid => $participant)
		{
			if ($strict && (!is_array($participant) || $participant[self::AT_TYPE] !== self::TYPE_PARTICIPANT))
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($participant, self::JSON_OPTIONS_ERROR));
			}
			elseif (!is_array($participant))
			{
				$participant = [
					'email' => $participant,
				];
			}
			// check if the uid is valid and matches the data in the object
			if (($test_uid = self::Participants(['participants' => [
				$uid => 'U'
			]])) && ($test_uid['email'] ?? null) === $participant['email'] &&
				($test_uid['kind'] ?? null) === ($participant['kind'] ?? null) &&
				($test_uid['name'] ?? null) === ($participant['name'] ?? null))
			{
				// use $uid as is
			}
			else
			{
				if (empty($participant['email']) || !preg_match(Api\Etemplate\Widget\Url::EMAIL_PREG, $participant['email']))
				{
					throw new \InvalidArgumentException("Missing or invalid email address: ".json_encode($participant, self::JSON_OPTIONS_ERROR));
				}
				static $contacts = null;
				if (!isset($contacts)) $contacts = new Api\Contacts();
				if ((list($data) = $contacts->search([
						'email' => $participant['email'],
						'email_home' => $participant['email'],
					], ['id','egw_addressbook.account_id as account_id','n_fn'],
					'egw_addressbook.account_id IS NOT NULL DESC, n_fn IS NOT NULL DESC',
					'','',false,'OR')))
				{
					// found an addressbook entry
					$uid = $data['account_id'] ? (int)$data['account_id'] : 'c'.$data['id'];
				}
				else
				{
					$uid = 'e'.(empty($participant['name']) ? $participant['email'] : $participant['name'].' <'.$participant['email'].'>');
				}
			}
			$default_status = $uid === $GLOBALS['egw_info']['user']['account_id'] ? 'A' : 'U';
			$default_role = $uid === $calendar_owner ? 'CHAIR' : 'REQ-PARTICIPANT';
			$parsed[$uid] = \calendar_so::combine_status(array_search($participant['participationStatus'] ?? $default_status, self::$status2jscal) ?: $default_status,
				1, self::jscalRoles2role($participant['roles'] ?? null, $default_role));
		}

		return $parsed;
	}

	protected static function jscalRoles2role(array $roles=null, string $default_role=null)
	{
		$role = $default_role ?? 'REQ-PARTICIPANT';
		foreach($roles ?? [] as $name => $value)
		{
			if ($value && $role !== 'CHAIR')
			{
				switch($name)
				{
					case 'owner':   // we ignore the owner, it's set automatic to the owner of the calendar/collection
						break;
					case 'attendee':
						$role = 'REQ-PARTICIPANT';
						break;
					case 'optional':
						$role = 'OPT-PARTICIPANT';
						break;
					case 'informational':
						$role = 'NON-PARTICIPANT';
						break;
					case 'chair':
						$role = 'CHAIR';
						break;
				}
			}
		}
		return $role;
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

	/**
	 * Parse priority
	 *
	 * @param int $priority
	 * @return int
	 */
	protected static function parsePriority(int $priority)
	{
		static $priority_jscal2egw = [
			9 => 1, 8 => 1, 7 => 1, // low
			6 => 2, 5 => 2, 4 => 2, // normal
			3 => 3, 2 => 3, 1 => 3, // high
			0 => 0, // undefined
		];
		return $priority_jscal2egw[$priority] ?? throw new \InvalidArgumentException("Priority must be between 0 and 9");
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
	const TYPE_ABSOLUTE_TRIGGER = 'AbsoluteTrigger';

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
					'offset' => self::Duration(0, $alarm['offset'], false),
				],
				'acknowledged' => empty($alarm['attrs']['ACKNOWLEDGED']['value']) ? null :
					self::UTCDateTime(new Api\DateTime($alarm['attrs']['ACKNOWLEDGED']['value'])),
			]);
		}
		return $alerts;
	}

	/**
	 * Parse alerts object / $data['alerts']
	 *
	 * @param array $data full JsCalendar object, not just alerts and useDefaultAlerts attribute
	 * @param bool $strict true: require JsCalendar @type, false: relaxed parsing
	 * @return array of alerts
	 * @throws Api\Exception
	 */
	protected static function parseAlerts(array $data, bool $strict=false, int $calendar_owner=null)
	{
		$alarms = [];
		if (!empty($data['useDefaultAlerts']))
		{
			$alarm_pref = !empty($data['showWithoutTime']) ? 'default-alarm-wholeday' : 'default-alarm';
			$cal_prefs = $GLOBALS['egw_info']['user']['preferences']['calendar'];
			// if default alarm set in prefs --> add it
			// we assume here that user does NOT have a whole-day but no regular default-alarm, no whole-day!
			if(isset($cal_prefs[$alarm_pref]) && (string)$cal_prefs[$alarm_pref] !== '')
			{
				$alarms[] = [
					'default' => 1,
					'offset'  => 60 * $cal_prefs[$alarm_pref],
					'all'     => $cal_prefs['default-alarm-for'] === 'all',
					'owner'   => $calendar_owner ?? $GLOBALS['egw_info']['user']['account_id'],
				];
			}
		}
		foreach($data['alerts'] ?? [] as $uid => $alert)
		{
			$alarm = [
				'uid' => $uid,
				'owner' => $calendar_owner ?? $GLOBALS['egw_info']['user']['account_id'],
			];
			if ($strict && ($alert[self::AT_TYPE] ?? null) !== self::TYPE_ALERT)
			{
				throw new \InvalidArgumentException("Missing @type: Alert");
			}
			if (empty($alert['trigger']) || $strict && empty($alert['trigger'][self::AT_TYPE]))
			{
				throw new \InvalidArgumentException("Missing or invalid Alert trigger without @type: ".json_encode($alert['trigger'] ?? null));
			}
			switch ($alert['trigger'][self::AT_TYPE] ?? ($strict || !isset($alert['trigger']['offset']) ? null : self::TYPE_OFFSET_TRIGGER))
			{
				case self::TYPE_OFFSET_TRIGGER:
					$alarm['offset'] = self::parseSignedDuration($alert['trigger']['offset'] ?? null, true);
					if (isset($alert['trigger']['relativeTo']) && $alert['trigger']['relativeTo'] === 'end')
					{
						$alarm['offset'] += self::parseSignedDuration($data['duration'], true);
					}
					break;

				case self::TYPE_ABSOLUTE_TRIGGER:
					if (!empty($alert['trigger']['when']))
					{
						$alarm['offset'] = (new Api\DateTime($alert['trigger']['when']))->getTimestamp() - self::parseStartDuration($data)['start']->getTimeStamp();
						break;
					}
					// fall through
				default:
					throw new \InvalidArgumentException("Invalid Alert trigger: ".json_encode($alert['trigger'] ?? null));
			}
			if (isset($alert['acknowledged']))
			{
				$alarm['attrs'] = ['ACKNOWLEDGED' => ['value' => (new Api\DateTime($alert['acknowledged']))->getTimestamp()]];
			}
			$alarms[] = $alarm;
		}
		return $alarms;
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