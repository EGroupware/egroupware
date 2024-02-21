<?php
/**
 * EGroupware Timesheet - JsTimesheet
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Timesheet;

use EGroupware\Api;

/**
 * Rendering events as JSON using new JsCalendar format
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8984
 * @link https://jmap.io/spec-calendars.html
 */
class JsTimesheet extends Api\CalDAV\JsBase
{
	const APP = 'timesheet';

	const TYPE_TIMESHEET = 'timesheet';

	/**
	 * Get JsEvent for given event
	 *
	 * @param int|array $event
	 * @param bool|"pretty" $encode true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data e.g. from listing
	 * @param ?array $exceptions=null
	 * @return string|array
	 * @throws Api\Exception\NotFound
	 */
	public static function JsTimesheet(array $timesheet, $encode=true, array $exceptions=[])
	{
		static $bo = null;
		if (!isset($bo)) $bo = new \timesheet_bo();

		if (isset($timesheet['ts_id']))
		{
			$timesheet = Api\Db::strip_array_keys($timesheet, 'ts_');
		}

		$data = array_filter([
			self::AT_TYPE => self::TYPE_TIMESHEET,
			//'uid' => self::uid($timesheet['uid']),
			'id' => (int)$timesheet['id'],
			'title' => $timesheet['title'],
			'description' => $timesheet['description'],
			'start' => self::UTCDateTime($timesheet['start'], true),
			'duration' => (int)$timesheet['duration'],
			'project' => $timesheet['project_blur'] ?? null,
			'pm_id' => !empty($timesheet['pm_id']) ? (int)$timesheet['pm_id'] : null,
			'quantity' => (double)$timesheet['quantity'],
			'unitprice' => (double)$timesheet['unitprice'],
			'category' => self::categories($timesheet['cat_id']),
			'owner' => self::account($timesheet['owner']),
			'created' => self::UTCDateTime($timesheet['created'], true),
			'modified' => self::UTCDateTime($timesheet['modified'], true),
			'modifier' => self::account($timesheet['modifier']),
			'pricelist' => (int)$timesheet['pl_id'] ?: null,
			'status' => $bo->status_labels[$timesheet['status']] ?? null,
			'egroupware.org:customfields' => self::customfields($timesheet),
			'etag' => ApiHandler::etag($timesheet)
		]);

		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	/**
	 * Parse JsTimesheet
	 *
	 * @param string $json
	 * @param array $old=[] existing contact for patch
	 * @param ?string $content_type=null application/json no strict parsing and automatic patch detection, if method not 'PATCH' or 'PUT'
	 * @param string $method='PUT' 'PUT', 'POST' or 'PATCH'
	 * @return array with "ts_" prefix
	 */
	public static function parseJsTimesheet(string $json, array $old=[], string $content_type=null, $method='PUT')
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			// check if we use patch: method is PATCH or method is POST AND keys contain slashes
			if ($method === 'PATCH')
			{
				// apply patch on JsCard of contact
				$data = self::patch($data, $old ? self::JsTimesheet($old, false) : [], !$old);
			}

			//if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// check required fields
			if (!$old || !$method === 'PATCH')
			{
				static $required = ['title', 'start', 'duration'];
				if (($missing = array_diff_key(array_filter(array_intersect_key($data, array_flip($required))), array_flip($required))))
				{
					throw new Api\CalDAV\JsParseException("Required field(s) ".implode(', ', $missing)." missing");
				}
			}

			$timesheet = $method === 'PATCH' ? $old : [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'title':
					case 'description':
					case 'project':
						$timesheet['ts_'.$name] = $value;
						break;

					case 'start':
						$timesheet['ts_start'] = Api\DateTime::server2user($value, 'ts');
						break;

					case 'duration':
						$timesheet['ts_duration'] = self::parseInt($value);
						// set default quantity, if none explicitly given
						if (!isset($timesheet['ts_quantity']))
						{
							$timesheet['ts_quantity'] = $timesheet['ts_duration'] / 60.0;
						}
						break;

					case 'pricelist':
						$timesheet['pl_id'] = self::parseInt($value);
						break;

					case 'quantity':
					case 'unitprice':
						$timesheet['ts_'.$name] = self::parseFloat($value);
						break;

					case 'owner':
						$timesheet['ts_owner'] = self::parseAccount($value);
						break;

					case 'category':
						$timesheet['cat_id'] = self::parseCategories($value, false);
						break;

					case 'status':
						$timesheet['ts_status'] = self::parseStatus($value);
						break;

					case 'egroupware.org:customfields':
						$timesheet = array_merge($timesheet, self::parseCustomfields($value));
						break;

					case 'prodId':
					case 'created':
					case 'modified':
					case 'modifier':
					case self::AT_TYPE:
					case 'id':
					case 'etag':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsTimesheet', $name, $value);
		}

		return $timesheet;
	}

	/**
	 * Parse a status label into it's numerical ID
	 *
	 * @param string $value
	 * @return int|null
	 * @throws Api\CalDAV\JsParseException
	 */
	protected static function parseStatus(string $value)
	{
		static $bo=null;
		if (!isset($bo)) $bo = new \timesheet_bo();

		if (($status_id = array_search($value, $bo->status_labels)) === false)
		{
			throw new Api\CalDAV\JsParseException("Invalid status value '$value', allowed '".implode("', '", $bo->status_labels)."'");
		}
		return $status_id;
	}
}