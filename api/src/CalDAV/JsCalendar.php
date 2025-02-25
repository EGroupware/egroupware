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
	const TYPE_TASK = 'Task';
	const TYPE_RELATION = 'Relation';

	/**
	 * Get JsEvent for given event
	 *
	 * @param int|array $event
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
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
			'egroupware.org:customfields' => self::customfields($event, null, $event['tzid']),
		] + self::Locations($event);

		if (!empty($event['recur_type']) || $exceptions)
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
				$data = self::patch($data, $old ? self::JsEvent($old, false) : [], !$old || !$strict);
			}

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			$event = $old ? ['id' => $old['id']] : [];
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
						$event['public'] = self::parsePrivacy($value, true);
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
						$event = array_merge($event, self::parseCustomfields($value, 'calendar', $data['timeZone']));
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
	 * Get JsEvent for given event
	 *
	 * @param int|array $entry
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @param ?array $exceptions=null
	 * @return string|array
	 * @throws Api\Exception\NotFound
	 */
	public static function JsTask($entry, $encode=true, array $exceptions=[])
	{
		if (is_scalar($entry) && !($entry = self::getInfolog()->read($entry, false, 'object')))
		{
			throw new Api\Exception\NotFound();
		}
		$data = [
				self::AT_TYPE => self::TYPE_TASK,
				'prodId' => 'EGroupware InfoLog '.$GLOBALS['egw_info']['apps']['api']['version'],
				'uid' => self::uid($entry['info_uid']),
				'sequence' => $entry['info_etag'],
				'created' => self::UTCDateTime($entry['info_created']),
				'updated' => self::UTCDateTime($entry['info_modified']),
				'title' => $entry['info_subject'],
				'start' => $entry['info_startdate'] ? self::DateTime($entry['info_startdate'], Api\DateTime::$user_timezone->getName()) : null,
				'showWithoutTime' => $no_time = Api\DateTime::to($entry['info_startdate'], 'H:i') === '00:00',
				'timeZone' => Api\DateTime::$user_timezone->getName(),
				'due' => $entry['info_enddate'] ? self::DateTime($entry['info_enddate'], Api\DateTime::$user_timezone->getName()) : null,
				'duration' => $entry['info_used_time'] ?
					self::Duration(0, $entry['info_used_time']*60) : null,
				'estimatedDuration' => $entry['info_plannedtime'] ?
					self::Duration(0, $entry['info_plannedtime']*60) : null,
				//'freeBusyStatus' => $entry['non_blocking'] ? 'free' : null,   // default is busy
				'description' => $entry['info_des'],
				'participants' => self::Responsible($entry),
				//'alerts' => self::Alerts($entry['alarm']),
				'status' => in_array($entry['info_status'], ['deleted', 'cancelled']) ? 'cancelled' :
					($entry['info_status'] === 'offer' ? 'tentative' : 'confirmed'),
				'progress' => self::Progress($entry['info_status']),
				'priority' => isset($entry['info_priority']) ? self::Priority($entry['info_priority']) : null,
				'categories' => self::categories($entry['info_cat']),
				'privacy' => $entry['info_access'],
				'percentComplete' => (int)$entry['info_percent'],
				'egroupware.org:type' => $entry['info_type'],
				'egroupware.org:pricelist' => $entry['pl_id'] ? (int)$entry['pl_id'] : null,
				'egroupware.org:price' => $entry['info_price'] ? (double)$entry['info_price'] : null,
				'egroupware.org:completed' => $entry['info_datecomplete'] ?
					self::DateTime($entry['info_datecompleted'], Api\DateTime::$user_timezone->getName()) : null,
			] + self::Locations(['location' => $entry['info_location'] ?? null]) + [
				'relatedTo' => self::relatedTo($entry['info_id_parent'], $entry['info_link_id'], $entry['info_id']),
				'egroupware.org:customfields' => self::customfields($entry, 'infolog', Api\DateTime::$user_timezone->getName()),
			];

		if (!empty($entry['##RRULE']))
		{
			$data = array_merge($data, self::cfRrule2recurrenceRules($entry));
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
	public static function parseJsTask(string $json, array $old=[], string $content_type=null, $method='PUT', int $calendar_owner=null)
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
				$data = self::patch($data, $old ? self::JsTask($old, false) : [], !$old || !$strict);
			}

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			$event = $old ? ['info_id' => $old['info_id']] : [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'uid':
						$event['info_uid'] = self::parseUid($value, $old['info_uid'], !$strict);
						break;

					case 'title':
						$event['info_subject'] = $value;
						break;

					case 'description':
						$event['info_des'] = $value;
						break;

					case 'start':
						$event['info_startdate'] = self::parseDateTime($value, $data['timeZone'] ?? null, $data['showWithoutTime'] ?? false);
						break;

					case 'due':
						$event['info_enddate'] = self::parseDateTime($value, $data['timeZone'] ?? null, $data['showWithoutTime'] ?? false);
						break;

					case 'egroupware.org:completed':
						$event['info_datecompleted'] = self::parseDateTime($value, $data['timeZone'] ?? null);
						break;

					case 'duration':
						$event['info_used_time'] = self::parseSignedDuration($value, true)/60;
						break;

					case 'estimatedDuration':
						$event['info_plannedtime'] = self::parseSignedDuration($value, true)/60;
						break;

					case 'participants':
						$event += self::parseResponsible($value, $strict, $calendar_owner);
						break;

					case 'priority':
						$event['info_priority'] = self::parsePriority($value, true);
						break;

					case 'progress':
						$event['info_status'] = self::parseProgress($value, $event['info_type'] ??
							(isset($data['egroupware.org:type']) ? self::parseInfoType($data['egroupware.org:type']) : 'task'));
						break;

					case 'percentComplete':
						$event['info_percent'] = self::parseInt($value);
						break;

					case 'privacy':
						$event['info_access'] = self::parsePrivacy($value);
						break;

					case 'recurrenceRules':
						$event += self::parseRecuranceRules2cfRrule($data['recurrenceRules']);
						break;

					case 'categories':
						$event['info_cat'] = (int)self::parseCategories($value, false);
						break;

					case 'relatedTo':
						$event += self::parseRelatedTo($value);
						break;

					case 'egroupware.org:customfields':
						$event = array_merge($event, self::parseCustomfields($value, 'infolog', $data['timeZone']));
						break;

					case 'egroupware.org:type':
						$event['info_type'] = self::parseInfoType($value);
						break;

					case 'egroupware.org:price':
						$event['info_price'] = self::parseFloat($value);
						break;

					case 'prodId':
					case 'created':
					case 'updated':
					case 'showWithoutTime':
					case 'timeZone':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsTask', $name, $value);
		}

		return $event;
	}

	protected static $status2progress = [
		'offer' => null,
		'not-started' => 'needs-action',
		'ongoing' => 'in-progress',
		'done' => 'completed',
		'cancelled' => 'cancelled',
		'billed' => null,
		'template' => null,
		'nonactive' => null,
		'archive' => null,
	];

	/**
	 * Convert an InfoLog status to a JsTask progress
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#name-progress
	 * @param string $status
	 * @return void
	 */
	protected static function Progress(string $info_status)
	{
		return self::$status2progress[$info_status] ?? 'egroupware.org:'.$info_status;
	}

	/**
	 * @param string $progress
	 * @param string $info_type
	 * @return string known infolog status, or "not-started"
	 */
	protected static function parseProgress(string $progress, string $info_type=null)
	{
		if (!($status = array_search($progress, self::$status2progress)))
		{
			if (!str_starts_with($progress, 'egroupware.org:') ||
				($status = substr($progress, strlen('egroupware.org:'))) && !(
					isset(self::$status2progress[$status]) ||
					isset($info_type) && isset(self::getInfolog()->status[$info_type][$status])))
			{
				$status = 'not-started';
			}
		}
		return $status;
	}

	/**
	 * Parse categories object
	 *
	 * @param array $categories category-name => true pairs
	 * @param bool $calendar true (default) use calendar AND support multiple categories, false: InfoLog with only one Category
	 * @return ?string comma-separated cat_id's
	 */
	protected static function parseCategories(array $categories, bool $calendar=true)
	{
		$cat_ids = [];
		if ($categories)
		{
			$cat_ids = ($calendar ? self::getCalendar() : self::getInfolog())->find_or_add_categories(array_keys($categories));

			if (!$calendar && count ($cat_ids) > 1)
			{
				throw new \InvalidArgumentException("InfoLog supports only a single category currently!");
			}
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
	 * Return a duration calculated from given start- and end-time or a duration in seconds (start=0)
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#name-duration
	 * @param int|string|\DateTime $start start-time or 0 to use duration in $end
	 * @param int|string|\DateTime $end end-time or duration for $start===0
	 * @param bool $whole_day true: handling for whole-day events, default: false
	 * @return string
	 */
	protected static function Duration($start, $end, bool $whole_day=false)
	{
		if (!$start && is_numeric($end))
		{
			$value = $end;
		}
		else
		{
			$start = Api\DateTime::to($start, 'object');
			$end = Api\DateTime::to($end, 'object');

			$value = $end->getTimestamp() - $start->getTimestamp() + (int)$whole_day;
		}

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
		$parsed['start'] = self::parseDateTime($data['start'], $parsed['tzid'] = $data['timeZone'] ?? null);

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
	 * @param bool $calendar true: calendar, false: infolog only supporting users and email
	 * @return array
	 * @todo Resources and Groups without email
	 */
	protected static function parseParticipants(array $participants, bool $strict=true, int $calendar_owner=null, bool $calendar=true)
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
					'','',false,'OR')) &&
					($calendar || !empty($data['account_id'])))
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

	/**
	 * Return participants object of task aka Responsible
	 *
	 * We add info_owner (as owner), info_responsible (as attendee) and info_cc (as informational)
	 *
	 * @param array $entry
	 * @return array
	 */
	protected static function Responsible(array $entry)
	{
		$participants = [];
		foreach(array_unique(array_merge((array)$entry['info_owner'], $entry['info_responsible'],
			$entry['info_cc'] ? explode(',', $entry['info_cc']) : [])) as $uid)
		{
			if (is_numeric($uid))
			{
				$info = [
					'name'  => Api\Accounts::id2name($uid, $uid < 0 ? 'account_lid' : 'account_fullname'),
					'email' => Api\Accounts::id2name($uid, 'account_email'),
					'kind'  => $uid < 0 ? 'group' : 'individual',
				];
			}
			else
			{
				if (preg_match('/^(.*) <(.*)>$/', $uid, $matches))
				{
					$info = [
						'name'  => $matches[1],
						'email' => $matches[2],
					];
				}
				else
				{
					$info['email'] = $uid;
				}
			}
			$participant = array_filter([
				self::AT_TYPE => self::TYPE_PARTICIPANT,
				'name' => $info['name'] ?? null,
				'email' => $info['email'] ?? null,
				'kind' => $info['kind'] ?? 'individual',
				'roles' => array_filter([
					'owner' => $uid == $entry['info_owner'],
					//'chair' => $role === 'CHAIR',
					'attendee' => is_numeric($uid) && ($uid != $entry['info_owner'] || in_array($uid, $entry['info_responsible']??[])),
					//'optional' => $role === 'OPT-PARTICIPANT',
					'informational' => !is_numeric($uid),   // info_cc emails
				]),
				'participationStatus' => null,
			]);
			$participants[$uid] = $participant;
		}

		return $participants;
	}

	/**
	 * Parse participants object for InfoLog only supporting responsible and CC
	 *
	 * @param array $participants
	 * @param bool $strict true: require @types and objects with attributes name, email, ...
	 * @param ?int $calendar_owner owner of the calendar / collection
	 * @return array with values for info_responsible (int[]) and info_cc (comma-separated string)
	 */
	protected static function parseResponsible(array $participants, bool $strict=true, int $calendar_owner=null)
	{
		$responsible = $cc = [];
		foreach(self::parseParticipants($participants, $strict, $calendar_owner, false) as $uid => $status)
		{
			if (is_numeric($uid))
			{
				// we do NOT store just owner as participant, only if he has further roles / request-participant
				if ($participants[$uid]['roles'] !== ['owner' => true])
				{
					$responsible[] = $uid;
				}
			}
			elseif ($uid[0] === 'e')
			{
				$cc[] = substr($uid, 1);
			}
		}
		return [
			'info_responsible' => $responsible,
			'info_cc' => $cc ? implode(',', $cc) : null,
		];
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
	protected static function Priority(int $priority, bool $infolog=false)
	{
		static $priority_egw2jscal = array(
			0 => 0,		// undefined
			1 => 9,		// low
			2 => 5,		// normal
			3 => 1,		// high
		);
		static $infolog_priority_2egwjscal = array(
			0 => 9,		// low
			1 => 5,		// normal
			2 => 3,		// high
			3 => 1,		// urgent
		);
		return $infolog ? $infolog_priority_2egwjscal[$priority] : $priority_egw2jscal[$priority];
	}

	/**
	 * Parse priority
	 *
	 * @param int $priority
	 * @return int
	 */
	protected static function parsePriority(int $priority, bool $infolog=false)
	{
		static $priority_jscal2egw = [
			9 => 1, 8 => 1, 7 => 1, // low
			6 => 2, 5 => 2, 4 => 2, // normal
			3 => 3, 2 => 3, 1 => 3, // high
			0 => 0, // undefined
		];
		static $infolog_priority_jscal2egw = [
			9 => 0,	8 => 0, 7 => 0,	// low
			6 => 1, 5 => 1, 4 => 1, 0 => 1,	// normal
			3 => 2,	2 => 2,	// high
			1 => 3,			// urgent
		];
		return ($infolog?$infolog_priority_jscal2egw:$priority_jscal2egw)[$priority] ?? throw new \InvalidArgumentException("Priority must be between 0 and 9");
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
	 * @param ?array $rrule array with values for keys "FREQ", "INTERVAL", "UNTIL", ...
	 * @return array
	 */
	protected static function Recurrence(array $event, array $data, array $exceptions=[], ?array $rrule=null)
	{
		$overrides = [];
		if ((!empty($event['recur_type']) || isset($rrule)) && $event['recur_type'] != \calendar_rrule::RDATE)
		{
			if (!isset($rrule))
			{
				$rriter = \calendar_rrule::event2rrule($event, false);
				$rrule = $rriter->generate_rrule('2.0');
			}
			$rule = array_filter([
				self::AT_TYPE => self::TYPE_RECURRENCE_RULE,
				'frequency' => strtolower($rrule['FREQ']),
				'interval' => $rrule['INTERVAL'] ?? null,
				'until' => empty($rrule['UNTIL']) ? null : self::DateTime($rrule['UNTIL'], $event['tzid']),
				'count' => $rrule['COUNT'] ?? null ? (int)$rrule['COUNT'] : null,
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
		}
		elseif (!empty($event['recur_rdates']) && $event['recur_type'] == \calendar_rrule::RDATE)
		{
			foreach($event['recur_rdates'] as $rdate)
			{
				if ($rdate != $event['start'])
				{
					$overrides[self::DateTime($rdate, $event['tzid'])] = [
						'start' => self::DateTime($rdate, $event['tzid']),
					];
				}
			}
		}

		// adding excludes to the overrides
		if (!empty($event['recur_type']) && !empty($event['recur_exception']))
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
			'recurrenceRules' => isset($rule) ? [$rule] : null,
			'recurrenceOverrides' => $overrides,
		]);
	}

	/**
	 * Convert Infolog RRULE stored in cfs to JsCalendar RecurrenceRules
	 *
	 * @param array $cfs
	 * @return array
	 */
	public static function cfRrule2recurrenceRules(array $cfs)
	{
		$rrule = [];
		foreach(explode(';', $cfs['##RRULE']) as $pair)
		{
			[$name, $value] = explode('=', $pair);
			$rrule[$name] = $value;
		}
		return self::Recurrence(['tzid' => Api\DateTime::$user_timezone->getName()], [], [], $rrule);
	}

	/**
	 * Parse RecurrenceRules to InfoLog cf stored RRULE
	 * @param array $recurenaceRules
	 * @return array
	 */
	public static function parseRecuranceRules2cfRrule(array $recurenaceRules=[])
	{
		$rrule = [];
		foreach($recurenaceRules as $rule)
		{
			if ($rrule)
			{
				throw new \InvalidArgumentException("EGroupware currently stores only a single rule!");
			}
			foreach($rule as $name => $value)
			{
				$rrule[] = $name.'='.$value;
			}
		}
		return [
			'##RRULE' => $rrule ? implode(';', $rrule) : null,
		];
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
	 * Parse a privacy value: "public", "private" or "secret" (currently not supported by calendar or infolog)
	 *
	 * @param string $value
	 * @return bool
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#name-privacy
	 */
	protected static function parsePrivacy(string $value, $return_bool=false)
	{
		if (!in_array($value, ['public', 'private'], true))
		{
			throw new \InvalidArgumentException("Privacy value must be either 'public' or 'private' ('secret' is currently NOT supported)!");
		}
		return $return_bool ? ($value === 'public') : $value;
	}

	protected static function relatedTo(?int $info_id_parent, ?int $info_link_id, int $info_id)
	{
		$relatedTo = [];
		if ($info_id_parent && ($parent = self::getInfolog()->read($info_id_parent)))
		{
			$relatedTo[$parent['info_uid']] = [
				self::AT_TYPE => self::TYPE_RELATION,
				'relation' => 'parent',
			];
		}
		if ($info_link_id && ($link = Api\Link::get_link($info_link_id)))
		{
			if ($link['link_app1'] === 'infolog' && $link['link_id1'] == $info_id)
			{
				$relatedTo[$link['link_app2'].':'.$link['link_id2']] = [
					self::AT_TYPE => self::TYPE_RELATION,
					'relation' => 'egroupware.org-primary',
				];
			}
			elseif ($link['link_app2'] === 'infolog' && $link['link_id2'] == $info_id)
			{
				$relatedTo[$link['link_app1'].':'.$link['link_id1']] = [
					self::AT_TYPE => self::TYPE_RELATION,
					'relation' => 'egroupware.org-primary',
				];
			}
		}
		return $relatedTo;
	}

	/**
	 * Parse relatedTo object:
	 * - "<uid>": {"@Type":"Relation","relation":"parent"}
	 * - "<app>:<id>": {"@Type":"Relation","relation":"egroupware.org-primary"}
	 * - "addressbook:<value>:<field>": {"@Type":"Relation","relation":"egroupware.org-primary"}
	 * - you can use null, instead of the relation object, to unset a relation in a PATCH command
	 *
	 * <uid>: InfoLog UID
	 * <app>: EGroupware app the current user has access to, which participates in linking
	 * <id>: ID of EGroupware app
	 * <field>: addressbook field like "id" or "email" (no "contact_" prefix), or "egroupware.org:customfields/<name>"
	 * <value>: the value to search for in <field>, the search must return exactly on result!
	 *
	 * @param array $related_to
	 * @param bool $strict
	 * @return array
	 */
	protected static function parseRelatedTo(array $related_to, bool $strict=false)
	{
		$result = [];
		foreach($related_to as $uid => $relation)
		{
			if ($strict && isset($relation) && $relation[self::AT_TYPE]??null !== self::TYPE_RELATION)
			{
				throw new \InvalidArgumentException("Missing or invalid @Type!");
			}
			switch($relation['relation'] ?? (strpos($uid, ':') === false ? 'parent' : 'egroupware.org-primary'))
			{
				case 'parent':
					if (!($parent = self::getInfolog()->read(['info_uid' => $uid])))
					{
						throw new \InvalidArgumentException("UID '$uid' NOT found!");
					}
					$result['info_id_parent'] = isset($relation) ? $parent['info_id'] : null;
					break;

				case 'egroupware.org-primary':
					[$app, $id, $field] = explode(':', $uid, 3)+[null, null, null];
					if (!isset($GLOBALS['egw_info']['user']['apps'][$app]))
					{
						// invalid app
					}
					elseif (empty($field) && !empty($id) && Api\Link::title($app, $id))
					{
						$result['info_contact'] = ['app' => $app, 'id' => $id];
					}
					elseif ($app === 'addressbook' && !empty($id) && !empty($field))
					{
						$filter = [];
						if (str_starts_with($field, 'egroupware.org:customfields/'))
						{
							$filter['#' . substr($field, 28)] = $id;
						}
						else
						{
							$filter[$field] = $id;
						}
						$contacts = new Api\Contacts();
						if (($rows = $contacts->search(null, true, '', '', '', false, 'AND', [0, 1], $filter)) &&
							$contacts->total === 1)
						{
							$result['info_contact'] = ['app' => $app, 'id' => $rows[0]['id']];
						}
					}
					if (!isset($result['info_contact']))
					{
						throw new \InvalidArgumentException("Unsupported or not found relation: ".json_encode($relation)."!");
					}
					// to delete a primary-link we have to set it to 0
					if (!isset($relation))
					{
						unset($result['info_contact']);
						$result['info_link_id'] = 0;
					}
					break;

				default:
					throw new \InvalidArgumentException("Unsupported relation-type: ".json_encode($relation['relation']??null)."!");
			}
		}
		return $result;
	}

	/**
	 * Parse an InfoLog type
	 *
	 * @param string $type
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	protected static function parseInfoType(string $type)
	{
		$bo = self::getInfolog();
		if (!isset($bo->enums['type'][$type]))
		{
			throw new \InvalidArgumentException("Invalid / non-existing InfoLog type '$type', allowed values are: '".implode("', '", array_keys($bo->enums['type']))."'");
		}
		return $type;
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

	/**
	 * @return \infolog_bo
	 */
	protected static function getInfolog()
	{
		static $infolog_bo=null;
		if (!isset($infolog_bo))
		{
			$infolog_bo = new \infolog_bo();
		}
		return $infolog_bo;
	}
}