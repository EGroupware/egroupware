<?php
/**
 * EGroupware REST API - JsBase base-class
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

/**
 * Shared base-class of all REST API / JMAP classes
 *
 * @link https://datatracker.ietf.org/doc/html/rfc8984
 * @link https://jmap.io/spec-calendars.html
 */
class JsBase
{
	const APP = null;

	const MIME_TYPE_JSON = "application/json";

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
	 * Return a date-time value in UTC
	 *
	 * @link https://datatracker.ietf.org/doc/html/rfc8984#section-1.4.4
	 * @param null|string|\DateTime $date
	 * @param bool $user false: timestamp in server-time, true: timestamp in user-time, does NOT matter for DateTime objects
	 * @return string|null
	 */
	protected static function UTCDateTime($date, bool $user=false)
	{
		static $utc=null;
		if (!isset($utc)) $utc = new \DateTimeZone('UTC');

		if (!isset($date))
		{
			return null;
		}
		$date = $user ? Api\DateTime::user2server($date, 'object') : Api\DateTime::to($date, 'object');
		$date->setTimezone($utc);

		// we need to use "Z", not "+00:00"
		return substr($date->format(Api\DateTime::RFC3339), 0, -6).'Z';
	}

	/**
	 * Output an account as email, if available, username or as last resort the numerical account_id
	 *
	 * @param int|null $account_id
	 * @return string|int|null
	 * @throws \Exception
	 */
	protected static function account(int $account_id=null)
	{
		if (!$account_id)
		{
			return null;
		}
		return Api\Accounts::id2name($account_id, 'account_email') ?: Api\Accounts::id2name($account_id) ?: $account_id;
	}

	/**
	 * Parse an account specified as email, account_lid or account_id
	 *
	 * @param string|int $value
	 * @param ?bool $user
	 * @return int
	 * @throws \Exception
	 */
	protected static function parseAccount(string $value, bool $user=true)
	{
		if (is_numeric($value) && ($exists = Api\Accounts::getInstance()->exists(value)) &&
			(!isset($user) || $exists === ($user ? 1 : 2)))
		{
			$account_id = (int)$value;
		}
		else
		{
			$account_id = Api\Accounts::getInstance()->name2id($value,
				strpos($value, '@') !== false ? 'account_email' : 'account_lid',
				isset($user) ? ($user ? 'u' : 'g') : null);
		}
		if (!$account_id)
		{
			throw new JsParseException("Invalid or non-existing account '$value'");
		}
		return $account_id;
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
		foreach(Api\Storage\Customfields::get(static::APP) as $name => $data)
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
		$definitions = Api\Storage\Customfields::get(static::APP);

		foreach($definitions as $name => $definition)
		{
			$data = $cfs[$name] ?? null;
			if (isset($data))
			{
				if (is_scalar($data) || is_array($data) && !isset($data['value']))
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
		// error not existing cfs
		if (($not_existing=array_diff(array_keys($cfs), array_keys($definitions))))
		{
			throw new JsParseException("Trying to update not existing custom field(s): ".implode(', ', $not_existing));
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
	 * @param bool $multiple
	 * @return ?string comma-separated cat_id's
	 * @todo make that generic, so JsContact & JSCalendar have not to overwrite it
	 */
	protected static function parseCategories(array $categories, bool $multiple=true)
	{
		static $bo=null;
		$cat_ids = [];
		if ($categories)
		{
			if (count($categories) > 1 && !$multiple)
			{
				throw new JsParseException("Only a single category is supported!");
			}
			if (!isset($bo)) $bo = new Api\Categories($GLOBALS['egw_info']['user']['account_id'], static::APP);
			foreach($categories as $name => $true)
			{
				if (!($cat_id = $bo->name2id($name)))
				{
					$cat_id = $bo->add(array('name' => $name, 'descr' => $name, 'access' => 'private'));
				}
				$cat_ids[] = $cat_id;
			}
		}
		return $cat_ids ? implode(',', $cat_ids) : null;
	}

	/**
	 * Get links / link-objects
	 *
	 * @param string $prefix
	 * @param string $app
	 * @param string $id
	 * @return array
	 */
	public static function getLinks(string $prefix, string $app, string $id)
	{
		$links = [];
		foreach(Api\Link::get_links($app, $id, '', 'link_lastmod DESC', true) as $link_id => $data)
		{
			$path = rtrim($prefix, '/').'/'.$link_id;
			if ($data['app'] === 'file')
			{
				$links[$path] = array_filter([
					self::AT_TYPE => 'Link',
					'href' => Api\Framework::getUrl(Api\Framework::link('/webdav.php/apps/'.$app.'/'.$id.'/'.$data['id'])),
					'contentType' => $data['type'],
					'size' => $data['size'],
					'title' => Api\Link::title($data['app'], $data['id']),
					'egroupware.org-remark' => $data['remark'],
				]);
			}
			else
			{
				$links[$path] = array_filter([
					self::AT_TYPE => 'Link',
					'href' => Api\Framework::getUrl(Api\Framework::link('/groupdav.php/'.$GLOBALS['egw_info']['user']['account_lid'].'/'.$data['app'].'/'.$data['id'])),
					'contentType' => 'application/json',
					'title' => Api\Link::title($data['app'], $data['id']),
					'egroupware.org-app' => $data['app'],
					'egroupware.org-id'  => $data['id'],
					'egroupware.org-remark' => $data['remark'],
				]);
			}
		}
		return $links;
	}

	/**
	 * Patch JsCard
	 *
	 * @param array $patches JSON path
	 * @param array $jscard to patch
	 * @param bool $create =false true: create missing components
	 * @return array patched $jscard
	 */
	public static function patch(array $patches, array $jscard, bool $create=false)
	{
		foreach($patches as $path => $value)
		{
			$parts = explode('/', $path);
			$target = &$jscard;
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
				// objects need to be merged, to not unset all not given attributes
				if (is_array($value) && !array_key_exists(0, $value))
				{
					$target = array_merge($target, $value);
				}
				else
				{
					$target = $value;
				}
			}
			else
			{
				unset($parent[$part]);
			}
		}
		return $jscard;
	}

	/**
	 * Parse an integer
	 *
	 * @param int $value
	 * @return int
	 * @throws \TypeError
	 */
	public static function parseInt(int $value)
	{
		return $value;
	}

	/**
	 * Parse an float value
	 *
	 * @param float $value
	 * @return float
	 * @throws \TypeError
	 */
	public static function parseFloat(float $value)
	{
		return $value;
	}

	/**
	 * Map all kind of exceptions while parsing to a JsCalendarParseException
	 *
	 * @param \Throwable $e
	 * @param string $type
	 * @param ?string $name
	 * @param mixed $value
	 * @throws JsParseException
	 */
	protected static function handleExceptions(\Throwable $e, $type='JsCalendar', ?string $name, $value)
	{
		try {
			throw $e;
		}
		catch (\JsonException $e) {
			throw new JsParseException("Error parsing JSON: ".$e->getMessage(), 422, $e);
		}
		catch (\InvalidArgumentException $e) {
			throw new JsParseException("Error parsing $type attribute '$name': ".
				str_replace('"', "'", $e->getMessage()), 422);
		}
		catch (\TypeError $e) {
			$message = $e->getMessage();
			if (preg_match('/must be of the type ([^ ]+( or [^ ]+)*), ([^ ]+) given/', $message, $matches))
			{
				$message = "$matches[1] expected, but got $matches[3]: ".
					str_replace('"', "'", json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			throw new JsParseException("Error parsing $type attribute '$name': $message", 422, $e);
		}
		catch (\Throwable $e) {
			throw new JsParseException("Error parsing $type attribute '$name': ". $e->getMessage(), 422, $e);
		}
	}
}