<?php
/**
 * EGroupware API - JsContact
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2021 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Contacts;

use EGroupware\Api;

/**
 * Rendering contacts as JSON using new JsContact format
 *
 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07 (newer, here implemented format)
 * @link https://datatracker.ietf.org/doc/html/rfc7095 jCard (older vCard compatible contact data as JSON, NOT implemented here!)
 */
class JsContact
{
	const MIME_TYPE = "application/jscontact+json";
	const MIME_TYPE_JSCARD = "application/jscontact+json;type=card";
	const MIME_TYPE_JSCARDGROUP = "application/jscontact+json;type=cardgroup";
	const MIME_TYPE_JSON = "application/json";

	/**
	 * Get jsCard for given contact
	 *
	 * @param int|array $contact
	 * @param bool|"pretty" $encode=true true: JSON encode, "pretty": JSON encode with pretty-print, false: return raw data eg. from listing
	 * @return string|array
	 * @throws Api\Exception\NotFound
	 */
	public static function getJsCard($contact, $encode=true)
	{
		if (is_scalar($contact) && !($contact = self::getContacts()->read($contact)))
		{
			throw new Api\Exception\NotFound();
		}
		$data = array_filter([
			'uid' => self::uid($contact['uid']),
			'prodId' => 'EGroupware Addressbook '.$GLOBALS['egw_info']['apps']['api']['version'],
			'created' => self::UTCDateTime($contact['created']),
			'updated' => self::UTCDateTime($contact['modified']),
			'kind' => !empty($contact['n_family']) || !empty($contact['n_given']) ? 'individual' :
				(!empty($contact['org_name']) ? 'org' : null),
			//'relatedTo' => [],
			'name' => self::nameComponents($contact),
			'fullName' => $contact['n_fn'],
			//'nickNames' => [],
			'organizations' => self::organizations($contact),
			'titles' => self::titles($contact),
			'emails' => self::emails($contact),
			'phones' => self::phones($contact),
			'online' => self::online($contact),
			'addresses' => array_filter([
				'work' => self::address($contact, 'work', 1),    // as it's the more prominent in our UI
				'home' =>  self::address($contact, 'home'),
			]),
			'photos' => self::photos($contact),
			'anniversaries' => self::anniversaries($contact),
			'notes' => empty($contact['note']) ? null : [$contact['note']],
			'categories' => self::categories($contact['cat_id']),
			'egroupware.org:customfields' => self::customfields($contact),
			'egroupware.org:assistant' => $contact['assistent'],
			'egroupware.org:fileAs' => $contact['fileas'],
		]);
		if ($encode)
		{
			return Api\CalDAV::json_encode($data, $encode === "pretty");
		}
		return $data;
	}

	/**
	 * Parse JsCard
	 *
	 * We use strict parsing for "application/jscontact+json" content-type, not for "application/json".
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
	public static function parseJsCard(string $json, array $old=[], string $content_type=null, $method='PUT')
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
				$data = self::patch($data, $old ? self::getJsCard($old, false) : [], !$old);
			}

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			$contact = [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'uid':
						$contact['uid'] = self::parseUid($value, $old['uid'], !$strict);
						break;

					case 'name':
						$contact += self::parseNameComponents($value, $strict);
						break;

					case 'fullName':
						$contact['n_fn'] = self::parseString($value);
						// if no separate name-components given, simply split first word off as n_given and rest as n_family
						if (!isset($data['name']) && !empty($contact['n_fn']))
						{
							if (preg_match('/^([^ ,]+)(,?) (.*)$/', $contact['n_fn'], $matches))
							{
								if (!empty($matches[2]))
								{
									list(, $contact['n_family'], , $contact['n_given']) = $matches;
								}
								else
								{
									list(, $contact['n_given'], , $contact['n_family']) = $matches;
								}
							}
							else
							{
								$contact['n_family'] = $contact['n_fn'];
							}
						}
						break;

					case 'organizations':
						$contact += self::parseOrganizations($value, $strict);
						break;

					case 'titles':
						$contact += self::parseTitles($value, $strict);
						break;

					case 'emails':
						$contact += self::parseEmails($value, $strict);
						break;

					case 'phones':
						$contact += self::parsePhones($value, $strict);
						break;

					case 'online':
						$contact += self::parseOnline($value, $strict);
						break;

					case 'addresses':
						$contact += self::parseAddresses($value, $strict);
						break;

					case 'photos':
						$contact += self::parsePhotos($value, $strict);
						break;

					case 'anniversaries':
						$contact += self::parseAnniversaries($value, $strict);
						break;

					case 'notes':
						$contact['note'] = implode("\n", array_map(static function ($note) {
							return self::parseString($note);
						}, (array)$value));
						break;

					case 'categories':
						$contact['cat_id'] = self::parseCategories($value);
						break;

					case 'egroupware.org:customfields':
						$contact += self::parseCustomfields($value, $strict);
						break;

					case 'egroupware.org:assistant':
						$contact['assistent'] = $value;
						break;

					case 'egroupware.org:fileAs':
						$contact['fileas'] = $value;
						break;

					case 'prodId':
					case 'created':
					case 'updated':
					case 'kind':
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsContact Card', $name, $value);
		}
		return $contact;
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
	const TYPE_ORGANIZATION = 'Organization';

	/**
	 * Return organizations
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.2.4
	 * @param array $contact
	 * @return array
	 */
	protected static function organizations(array $contact)
	{
		$org = array_filter([
			'name' => $contact['org_name'],
			'units' => empty($contact['org_unit']) ? null : ['org_unit' => $contact['org_unit']],
		]);
		if (!$org || empty($contact['org_name']))
		{
			return null;    // name is mandatory
		}
		return ['org' => [self::AT_TYPE => self::TYPE_ORGANIZATION]+$org];
	}

	/**
	 * Parse Organizations
	 *
	 * As we store only one organization, the rest get lost, multiple units get concatenated by space.
	 *
	 * @param array $orgas
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseOrganizations(array $orgas, bool $stict=true)
	{
		$contact = [];
		foreach($orgas as $orga)
		{
			if ($stict && $orga[self::AT_TYPE] !== self::TYPE_ORGANIZATION)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($orga, self::JSON_OPTIONS_ERROR));
			}
			$contact['org_name'] = self::parseString($orga['name']);
			$contact['org_unit'] = implode(' ', array_map(static function($unit)
			{
				return self::parseString($unit);
			}, (array)$orga['units']));
			break;
		}
		if (count($orgas) > 1)
		{
			error_log(__METHOD__."() more then 1 organization --> ignored");
		}
		return $contact;
	}

	const TYPE_TITLE = 'Title';

	/**
	 * Return titles of a contact
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.2.5
	 * @param array $contact
	 */
	protected static function titles(array $contact)
	{
		$titles = [];
		foreach([
			'title' => $contact['title'],
			'role' => $contact['role'],
		] as $id => $value)
		{
			if (!empty($value))
			{
				$titles[$id] = [
					self::AT_TYPE => self::TYPE_TITLE,
					'title' => $value,
					'organization' => 'org',    // the single organization we support use "org" as Id
				];
			}
		}
		return $titles;
	}

	/**
	 * Parse titles, thought we only have "title" and "role" available for storage.
	 *
	 * @param array $titles
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseTitles(array $titles, bool $stict=true)
	{
		$contact = [];
		foreach($titles as $id => $title)
		{
			if (!$stict && is_string($title))
			{
				$title = ['title' => $title];
			}
			if ($stict && $title[self::AT_TYPE] !== self::TYPE_TITLE)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: " . json_encode($title[self::AT_TYPE]));
			}
			if (empty($title['title']) || !is_string($title['title']))
			{
				throw new \InvalidArgumentException("Missing or invalid title attribute in title with id '$id': " . json_encode($title));
			}
			// put first title as "title", unless we have an Id "title"
			if (!isset($contact['title']) && ($id === 'title' || !isset($titles['title'])))
			{
				$contact['title'] = $title['title'];
			}
			// put second title as "role", unless we have an Id "role"
			elseif (!isset($contact['role']) && ($id === 'role' || !isset($titles['role'])))
			{
				$contact['role'] = $title['title'];
			}
			else
			{
				error_log(__METHOD__ . "() only 2 titles can be stored --> rest is ignored!");
			}
		}
		return $contact;
	}

	/**
	 * Return EGroupware custom fields
	 *
	 * @param array $contact
	 * @return array
	 */
	protected static function customfields(array $contact)
	{
		$fields = [];
		foreach(Api\Storage\Customfields::get('addressbook') as $name => $data)
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
		$definitions = Api\Storage\Customfields::get('addressbook');

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
			if (!isset($bo)) $bo = new Api\Contacts();
			$cat_ids = $bo->find_or_add_categories(array_keys($categories));
		}
		return $cat_ids ? implode(',', $cat_ids) : null;
	}

	/**
	 * @var string[] address attribute => contact attr pairs
	 */
	protected static $jsAddress2attr = [
		'locality' => 'locality',
		'region' => 'region',
		'country' => 'countryname',
		//'postOfficeBox' => '',
		'postcode' => 'postalcode',
		'countryCode' => 'countrycode',
	];
	/**
	 * @var string[] address attribute => contact attr pairs we have only once
	 */
	protected static $jsAddress2workAttr = [
		'fullAddress' => 'label',
		'coordinates' =>  'geo',
		'timeZone' => 'tz',
	];

	const TYPE_ADDRESS = 'Address';

	/**
	 * Return address object
	 *
	 * @param array $contact
	 * @param string $type "work" or "home" only currently
	 * @param ?int $preference 1=highest, ..., 100=lowest (=null)
	 * @return array
	 */
	protected static function address(array $contact, string  $type, int $preference=null)
	{
		$prefix = $type === 'work' ? 'adr_one_' : 'adr_two_';
		$js2attr = self::$jsAddress2attr;
		if ($type === 'work') $js2attr += self::$jsAddress2workAttr;

		$address = array_filter(array_map(static function($attr) use ($contact, $prefix)
		{
			return $contact[$prefix.$attr];
		}, $js2attr) + [
			'street' => self::streetComponents($contact[$prefix.'street'], $contact[$prefix.'street2']),
		]);
		// only add contexts and preference to non-empty address
		return !$address ? [] : array_filter([
			self::AT_TYPE => self::TYPE_ADDRESS,
			]+$address+[
			'contexts' => [$type => true],
			'pref' => $preference,
		]);
	}

	/**
	 * Parse addresses object containing multiple addresses
	 *
	 * @param array $addresses
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseAddresses(array $addresses, bool $stict=true)
	{
		$n = 0;
		$last_type = null;
		$contact = [];
		foreach($addresses as $id => $address)
		{
			if ($stict && $address[self::AT_TYPE] !== self::TYPE_ADDRESS)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($address));
			}
			$contact += ($values=self::parseAddress($address, $id, $last_type, $stict));

			if (++$n > 2)
			{
				error_log(__METHOD__."() Ignoring $n. address id=$id: ".json_encode($address, self::JSON_OPTIONS_ERROR));
				break;
			}
		}
		// make sure our address-unspecific attributes get not lost, because they were sent in 2nd address object
		foreach(self::$jsAddress2workAttr as $attr)
		{
			if (!empty($contact[$attr]) && !empty($values[$attr]))
			{
				$contact[$attr] = $values[$attr];
			}
		}
		return $contact;
	}

	/**
	 * Parse address object
	 *
	 * As we only have a work and a home address we need to make sure to no fill one twice.
	 *
	 * @param array $address address-object
	 * @param string $id index
	 * @param ?string $last_type "work" or "home"
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseAddress(array $address, string $id, string &$last_type=null, bool $stict=true)
	{
		$type = !isset($last_type) && (empty($address['contexts']['private']) || $id === 'work') ||
			$last_type === 'home' ? 'work' : 'home';
		$last_type = $type;
		$prefix = $type === 'work' ? 'adr_one_' : 'adr_two_';

		$contact = [$prefix.'street' => null, $prefix.'street2' => null];
		if (!empty($address['street']))
		{
			list($contact[$prefix.'street'], $contact[$prefix.'street2']) = self::parseStreetComponents($address['street'], $stict);
		}
		foreach(self::$jsAddress2attr+self::$jsAddress2workAttr as $js => $attr)
		{
			if (isset($address[$js]) && !is_string($address[$js]))
			{
				throw new \InvalidArgumentException("Invalid address object with id '$id'");
			}
			$contact[$prefix.$attr] = $address[$js];
		}
		// no country-code but a name translating to a code --> use it
		if (empty($contact[$prefix.'countrycode']) && !empty($contact[$prefix.'countryname']) &&
			strlen($code = Api\Country::country_code($contact[$prefix.'countryname'])) === 2)
		{
			$contact[$prefix.'countrycode'] = $code;
		}
		// if we have a valid code, the untranslated name as our UI does
		if (!empty($contact[$prefix.'countrycode']) && !empty($name = Api\Country::get_full_name($contact[$prefix.'countrycode'], false)))
		{
			$contact[$prefix.'countryname'] = $name;
		}
		return $contact;
	}

	const TYPE_STREET_COMPONENT = 'StreetComponent';

	/**
	 * Our data module does NOT distinguish between all the JsContact components therefore we only send a "name" component
	 *
	 * Trying to automatic parse following examples with eg. '/^(\d+[^ ]* )?(.*?)( \d+[^ ]*)?$/':
	 * 1. "Streetname 123" --> name, number --> Ok
	 * 2. "123 Streetname" --> number, name --> Ok
	 * 3. "Streetname 123 App. 3" --> name="Streetname 123 App.", number="3" --> Wrong
	 *
	 * ==> just use "name" for now and concatenate incoming data with one space
	 * ==> add 2. street line with separator "\n" and again name
	 *
	 * @param string $street
	 * @param ?string $street2=null 2. address line
	 * @return array[] array of objects with attributes type and value
	 */
	protected static function streetComponents(?string $street, ?string $street2=null)
	{
		$components = [];
		foreach(func_get_args() as $street)
		{
			if (!empty($street))
			{
				if ($components)
				{
					$components[] = [
						self::AT_TYPE => self::TYPE_STREET_COMPONENT,
						'type' => 'separator',
						'value' => "\n",
					];
				}
				$components[] = [
					self::AT_TYPE => self::TYPE_STREET_COMPONENT,
					'type' => 'name',
					'value' => $street,
				];
			}
		}
		return $components;
	}

	/**
	 * Parse street components
	 *
	 * As we have only 2 address-lines, we combine all components, with one space as separator, if none given.
	 * Then we split it into 2 lines.
	 *
	 * @param array|string $components string only for relaxed parsing
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return string[] street and street2 values
	 */
	protected static function parseStreetComponents($components, bool $stict=true)
	{
		if (!$stict && is_string($components))
		{
			$components = [['type' => 'name', 'value' => $components]];
		}
		if (!is_array($components))
		{
			throw new \InvalidArgumentException("Invalid street-components: ".json_encode($components, self::JSON_OPTIONS_ERROR));
		}
		$street = [];
		$last_type = null;
		foreach($components as $component)
		{
			if (!is_array($component) || !is_string($component['value']))
			{
				throw new \InvalidArgumentException("Invalid street-component: ".json_encode($component, self::JSON_OPTIONS_ERROR));
			}
			if ($stict && $component[self::AT_TYPE] !== self::TYPE_STREET_COMPONENT)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($component, self::JSON_OPTIONS_ERROR));
			}
			if ($street && $last_type !== 'separator')  // if we have no separator, we add a space
			{
				$street[] = ' ';
			}
			$street[] = $component['value'];
			$last_type = $component['type'];
		}
		return preg_split("/\r?\n/", implode('', $street), 2);
	}

	/**
	 * @var array mapping contact-attribute-names to jscontact phones
	 */
	protected static $phone2jscard = [
		'tel_work' => ['features' => ['voice' => true], 'contexts' => ['work' => true]],
		'tel_cell' => ['features' => ['cell' => true], 'contexts' => ['work' => true]],
		'tel_fax' => ['features' => ['fax' => true], 'contexts' => ['work' => true]],
		'tel_assistent' => ['features' => ['voice' => true], 'contexts' => ['assistant' => true]],
		'tel_car' => ['features' => ['voice' => true], 'contexts' => ['car' => true]],
		'tel_pager' => ['features' => ['pager' => true], 'contexts' => ['work' => true]],
		'tel_home' => ['features' => ['voice' => true], 'contexts' => ['private' => true]],
		'tel_fax_home' => ['features' => ['fax' => true], 'contexts' => ['private' => true]],
		'tel_cell_private' => ['features' => ['cell' => true], 'contexts' => ['private' => true]],
		'tel_other' => ['features' => ['voice' => true], 'contexts' => ['work' => true]],
	];

	const TYPE_PHONE = 'Phone';

	/**
	 * Return "phones" resources
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.2
	 * @param array $contact
	 * @return array[]
	 */
	protected static function phones(array $contact)
	{
		$phones = [];
		foreach(self::$phone2jscard as $name => $attributes)
		{
			if (!empty($contact[$name]))
			{
				$phones[$name] = array_filter([
					self::AT_TYPE => self::TYPE_PHONE,
					'phone' => $contact[$name],
					'pref' => $name === $contact['tel_prefer'] ? 1 : null,
					'label' => '',
				]+$attributes);
			}
		}
		return $phones;
	}

	/**
	 * Parse phone objects
	 *
	 * @param array $phones $id => object with attribute "phone" and optional "features" and "context"
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parsePhones(array $phones, bool $stict=true)
	{
		$contact = [];

		// check for good matches
		foreach($phones as $id => $phone)
		{
			if (!$stict && is_string($phone))
			{
				$phone = ['phone' => $phone];
			}
			if (!is_array($phone) || !is_string($phone['phone']))
			{
				throw new \InvalidArgumentException("Invalid phone: " . json_encode($phone, self::JSON_OPTIONS_ERROR));
			}
			if ($stict && $phone[self::AT_TYPE] !== self::TYPE_PHONE)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($phone, self::JSON_OPTIONS_ERROR));
			}
			// first check for "our" id's
			if (isset(self::$phone2jscard[$id]) && !isset($contact[$id]))
			{
				$contact[$id] = $phone['phone'];
				unset($phones[$id]);
				continue;
			}
			// check if we have a phone with at least one matching features AND one matching contexts
			foreach (self::$phone2jscard as $attr => $data)
			{
				if (!isset($contact[$attr]) &&
					isset($phone['features']) && array_intersect(array_keys($data['features']), array_keys($phone['features'])) &&
					isset($phone['contexts']) && array_intersect(array_keys($data['contexts']), array_keys($phone['contexts'])))
				{
					$contact[$attr] = $phone['phone'];
					unset($phones[$id]);
					break;
				}
			}
		}
		// check for not so good matches
		foreach($phones as $id => $phone)
		{
			// check if only one of them matches
			foreach (self::$phone2jscard as $attr => $data)
			{
				if (!isset($contact[$attr]) &&
					isset($phone['features']) && array_intersect(array_keys($data['features']), array_keys($phone['features'])) ||
					isset($phone['contexts']) && array_intersect(array_keys($data['contexts']), array_keys($phone['contexts'])))
				{
					$contact[$attr] = $phone['phone'];
					unset($phones[$id]);
					break;
				}
			}
		}
		// store them where we still have space
		foreach($phones as $id => $phone)
		{
			// store them where we still have space
			foreach(self::$phone2jscard as $attr => $data)
			{
				if (!isset($contact[$attr]))
				{
					$contact[$attr] = $phone['phone'];
					unset($phones[$id]);
				}
			}
		}
		if ($phones)
		{
			error_log(__METHOD__."() more then the supported ".count(self::$phone2jscard)." phone found --> ignoring access ones");
		}
		return $contact;
	}

	const TYPE_RESOURCE = 'Resource';

	/**
	 * Get online resources
	 *
	 * @param array $contact
	 * @return mixed
	 */
	protected static function online(array $contact)
	{
		return array_filter([
			'url' => !empty($contact['url']) ? [
				self::AT_TYPE => self::TYPE_RESOURCE,
				'resource' => $contact['url'],
				'type' => 'uri',
				'contexts' => ['work' => true],
			] : null,
			'url_home' => !empty($contact['url_home']) ? [
				self::AT_TYPE => self::TYPE_RESOURCE,
				'resource' => $contact['url_home'],
				'type' => 'uri',
				'contexts' => ['private' => true],
			] : null,
		]);
	}

	/**
	 * Parse online resource objects
	 *
	 * We currently only support 2 URLs, rest get's ignored!
	 *
	 * @param array $values
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseOnline(array $values, bool $stict)
	{
		$contact = [];
		foreach($values as $id => $value)
		{
			if (!$stict && is_string($value))
			{
				$value = ['resource' => $value];
			}
			if (!is_array($value) || !is_string($value['resource']))
			{
				throw new \InvalidArgumentException("Invalid online resource with id '$id': ".json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			if ($stict && $value[self::AT_TYPE] !== self::TYPE_RESOURCE)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			// check for "our" id's
			if (in_array($id, ['url', 'url_home']))
			{
				$contact[$id] = $value['resource'];
				unset($values[$id]);
			}
			// check for matching context
			elseif (!isset($contact['url']) && empty($value['contexts']['private']))
			{
				$contact['url'] = $value['resource'];
				unset($values[$id]);
			}
			// check it's free
			elseif (!isset($contact['url_home']))
			{
				$contact['url_home'] = $value['resource'];
			}
		}
		if ($values)
		{
			error_log(__METHOD__."() more then 2 email addresses --> ignored");
		}
		return $contact;
	}

	const TYPE_EMAIL = 'EmailAddress';

	/**
	 * Return emails
	 *
	 * @param array $contact
	 * @return array
	 */
	protected static function emails(array $contact)
	{
		return array_filter([
			'work' => empty($contact['email']) ? null : [
				self::AT_TYPE => self::TYPE_EMAIL,
				'email' => $contact['email'],
				'contexts' => ['work' => true],
				'pref' => 1,    // as it's the more prominent in our UI
			],
			'private' => empty($contact['email_home']) ? null : [
				self::AT_TYPE => self::TYPE_EMAIL,
				'email' => $contact['email_home'],
				'contexts' => ['private' => true],
			],
		]);
	}

	/**
	 * Parse emails object
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.1
	 * @param array $emails id => object with attribute "email" and optional "context"
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseEmails(array $emails, bool $stict=true)
	{
		$contact = [];
		foreach($emails as $id => $value)
		{
			if (!$stict && is_string($value))
			{
				$value = ['email' => $value];
			}
			if ($stict && $value[self::AT_TYPE] !== self::TYPE_EMAIL)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			if (!is_array($value) || !is_string($value['email']))
			{
				throw new \InvalidArgumentException("Invalid email object (requires email attribute): ".json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			if (!isset($contact['email']) && ($id === 'work' || empty($value['contexts']['private']) || isset($contact['email_home'])))
			{
				$contact['email'] = $value['email'];
			}
			elseif (!isset($contact['email_home']))
			{
				$contact['email_home'] = $value['email'];
			}
			else
			{
				error_log(__METHOD__."() can not store more then 2 email addresses currently --> ignored");
			}
		}
		return $contact;
	}

	const TYPE_FILE = 'File';

	/**
	 * Return id => photo objects
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.4
	 * @param array $contact
	 * @return array
	 */
	protected static function photos(array $contact)
	{
		$photos = [];
		if (!empty($contact['photo']))
		{
			$photos['photo'] = [
				self::AT_TYPE => self::TYPE_FILE,
				'href' => $contact['photo'],
				'mediaType' => 'image/jpeg',
				//'size' => ''
			];
		}
		return $photos;
	}

	/**
	 * Parse photos object
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.4
	 * @param array $photos id => photo objects of a contact pairs
	 * @return array
	 * @ToDo
	 */
	protected static function parsePhotos(array $photos, bool $stict)
	{
		foreach($photos as $id => $photo)
		{
			if ($stict && $photo[self::AT_TYPE] !== self::TYPE_FILE)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($photo, self::JSON_OPTIONS_ERROR));
			}
			error_log(__METHOD__."() importing attribute photos not yet implemented / ignored!");
		}
		return [];
	}

	/**
	 * @var string[] name-component type => attribute-name pairs
	 */
	protected static $nameType2attribute = [
		'prefix'     => 'n_prefix',
		'personal'   => 'n_given',
		'additional' => 'n_middle',
		'surname'    => 'n_family',
		'suffix'     => 'n_suffix',
	];

	const TYPE_NAME_COMPONENT = 'NameComponent';

	/**
	 * Return name-components objects with "type" and "value" attributes
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.2.1
	 * @param array $contact
	 * @return array[]
	 */
	protected static function nameComponents(array $contact)
	{
		$components = array_filter(array_map(function($attr) use ($contact)
		{
			return $contact[$attr];
		}, self::$nameType2attribute));
		return array_map(function($type, $value)
		{
			return [
				self::AT_TYPE => self::TYPE_NAME_COMPONENT,
				'type' => $type,
				'value' => $value,
			];
		}, array_keys($components), array_values($components));
	}

	/**
	 * parse nameComponents
	 *
	 * @param array $components
	 * @return array
	 */
	protected static function parseNameComponents(array $components, bool $stict=true)
	{
		$contact = array_combine(array_values(self::$nameType2attribute),
			array_fill(0, count(self::$nameType2attribute), null));

		foreach($components as $type => $component)
		{
			// for relaxed checks, allow $type => $value pairs
			if (!$stict && is_string($type) && is_scalar($component))
			{
				$component = ['type' => $type, 'value' => $component];
			}
			if (empty($component['type']) || isset($component) && !is_string($component['value']))
			{
				throw new \InvalidArgumentException("Invalid name-component (must have type and value attributes): ".json_encode($component, self::JSON_OPTIONS_ERROR));
			}
			if ($stict && $component[self::AT_TYPE] !== self::TYPE_NAME_COMPONENT)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($component, self::JSON_OPTIONS_ERROR));
			}
			$contact[self::$nameType2attribute[$component['type']]] = $component['value'];
		}
		return $contact;
	}

	const TYPE_ANNIVERSARY = 'Anniversary';

	/**
	 * Return anniversaries / birthday
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.5.1
	 * @param array $contact
	 * @return array
	 */
	protected static function anniversaries(array $contact)
	{
		return empty($contact['bday']) ? [] : ['bday' => [
			self::AT_TYPE => self::TYPE_ANNIVERSARY,
			'type' => 'birth',
			'date' => $contact['bday'],
			//'place' => '',
		]];
	}

	/**
	 * Parse anniversaries / birthday
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.5.1
	 * @param array $anniversaries id => object with attribute date and optional type
	 * @param bool $stict true: check if objects have their proper @type attribute
	 * @return array
	 */
	protected static function parseAnniversaries(array $anniversaries, bool $stict=true)
	{
		$contact = [];
		foreach($anniversaries as $id => $anniversary)
		{
			if (!$stict && is_string($anniversary))
			{
				// allow German date format "dd.mm.yyyy"
				if (preg_match('/^(\d+)\.(\d+).(\d+)$/', $anniversary, $matches))
				{
					$matches = sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[2], (int)$matches[1]);
				}
				// allow US date format "mm/dd/yyyy"
				elseif (preg_match('#^(\d+)/(\d+)/(\d+)$#', $anniversary, $matches))
				{
					$matches = sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[1], (int)$matches[2]);
				}
				$anniversary = ['type' => $id, 'date' => $anniversary];
			}
			if (!is_array($anniversary) || !is_string($anniversary['date']) ||
				!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anniversary['date']) ||
				(!list($year, $month, $day) = explode('-', $anniversary['date'])) ||
				!(1 <= $month && $month <= 12 && 1 <= $day && $day <= 31))
			{
				throw new \InvalidArgumentException("Invalid anniversary object with id '$id': ".json_encode($anniversary, self::JSON_OPTIONS_ERROR));
			}
			if ($stict && $anniversary[self::AT_TYPE] !== self::TYPE_ANNIVERSARY)
			{
				throw new \InvalidArgumentException("Missing or invalid @type: ".json_encode($anniversary, self::JSON_OPTIONS_ERROR));
			}
			if (!isset($contact['bday']) && ($id === 'bday' || $anniversary['type'] === 'birth'))
			{
				$contact['bday'] = $anniversary['date'];
			}
			else
			{
				error_log(__METHOD__."() only one birtday is supported, ignoring aniversary: ".json_encode($anniversary, self::JSON_OPTIONS_ERROR));
			}
		}
		return $contact;
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
	 * Return a date-time value
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-1.5.5
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

	/**
	 * Get jsCardGroup for given group
	 *
	 * @param int|array $group
	 * @param bool|"pretty" $encode=true true: JSON, "pretty": JSON pretty-print, false: array
	 * @return array|string
	 * @throws Api\Exception\NotFound
	 */
	public static function getJsCardGroup($group, $encode=true)
	{
		if (is_scalar($group) && !($group = self::getContacts()->read_lists($group)))
		{
			throw new Api\Exception\NotFound();
		}
		$data = array_filter([
			'uid' => self::uid($group['list_uid']),
			'name' => $group['list_name'],
			'card' => self::getJsCard([
				'uid' => self::uid($group['list_uid']),
				'n_fn' => $group['list_name'],  // --> fullName
				'modified' => $group['list_modified'],  // no other way to send modification date
			], false),
			'members' => [],
		]);
		foreach($group['members'] as $uid)
		{
			$data['members'][self::uid($uid)] = true;
		}
		if ($encode)
		{
			$data = Api\CalDAV::json_encode($data, $encode === 'pretty');
		}
		return $data;
	}

	/**
	 * Parse JsCard
	 *
	 * @param string $json
	 * @return array
	 */
	public static function parseJsCardGroup(string $json)
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			// make sure missing mandatory members give an error
			$data += ['uid' => null, 'members' => null];
			$group = [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'uid':
						$group['uid'] = self::parseUid($value);
						break;

					case 'name':
						$group['n_fn'] = $value;
						break;

					case 'card':
						$card = self::parseJsCard(json_encode($value, self::JSON_OPTIONS_ERROR));
						// prefer name over card-fullName
						if (!empty($card['n_fn']) && empty($group['n_fn']))
						{
							$group['n_fn'] = $card['n_fn'];
						}
						break;

					case 'members':
						$group['members'] = self::parseMembers($value);
						break;

					default:
						error_log(__METHOD__ . "() $name=" . json_encode($value, self::JSON_OPTIONS_ERROR) . ' --> ignored');
						break;
				}
			}
		}
		catch (\Throwable $e) {
			self::handleExceptions($e, 'JsContact CardGroup', $name, $value);
		}
		return $group;
	}

	/**
	 * Parse members object
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-3.1.2
	 * @param array $values uid => true pairs
	 * @return array of uid's
	 */
	protected static function parseMembers(array $values)
	{
		$members = [];
		foreach($values as $uid => $value)
		{
			if (!is_string($uid) || $value !== true)
			{
				throw new \InvalidArgumentException('Invalid members object: '.json_encode($values, self::JSON_OPTIONS_ERROR));
			}
			$members[] = self::parseUid($uid);
		}
		return $members;
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
				$target = $value;
			}
			else
			{
				unset($parent[$part]);
			}
		}
		return $jscard;
	}

	/**
	 * Map all kind of exceptions while parsing to a JsContactParseException
	 *
	 * @param \Throwable $e
	 * @param string $type
	 * @param ?string $name
	 * @param mixed $value
	 * @throws JsContactParseException
	 */
	protected static function handleExceptions(\Throwable $e, $type='JsContact', ?string $name, $value)
	{
		try {
			throw $e;
		}
		catch (\JsonException $e) {
			throw new JsContactParseException("Error parsing JSON: ".$e->getMessage(), 422, $e);
		}
		catch (\InvalidArgumentException $e) {
			throw new JsContactParseException("Error parsing $type attribute '$name': ".
				str_replace('"', "'", $e->getMessage()), 422);
		}
		catch (\TypeError $e) {
			$message = $e->getMessage();
			if (preg_match('/must be of the type ([^ ]+( or [^ ]+)*), ([^ ]+) given/', $message, $matches))
			{
				$message = "$matches[1] expected, but got $matches[3]: ".
					str_replace('"', "'", json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			throw new JsContactParseException("Error parsing $type attribute '$name': $message", 422, $e);
		}
		catch (\Throwable $e) {
			throw new JsContactParseException("Error parsing $type attribute '$name': ". $e->getMessage(), 422, $e);
		}
	}

	/**
	 * @return Api\Contacts
	 */
	protected static function getContacts()
	{
		static $contacts=null;
		if (!isset($contacts))
		{
			$contacts = new Api\Contacts();
		}
		return $contacts;
	}
}
