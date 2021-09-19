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
			'uid' => $contact['uid'],
			'prodId' => 'EGroupware Addressbook '.$GLOBALS['egw_info']['apps']['api']['version'],
			'created' => self::UTCDateTime($contact['created']),
			'updated' => self::UTCDateTime($contact['modified']),
			//'kind' => '', // 'individual' or 'org'
			//'relatedTo' => [],
			'name' => self::nameComponents($contact),
			'fullName' => self::localizedString($contact['n_fn']),
			//'nickNames' => [],
			'organizations' => array_filter(['org' => self::organization($contact)]),
			'titles' => self::titles($contact),
			'emails' => array_filter([
				'work' => empty($contact['email']) ? null : [
					'email' => $contact['email'],
					'contexts' => ['work' => true],
					'pref' => 1,    // as it's the more prominent in our UI
				],
				'private' => empty($contact['email_home']) ? null : [
					'email' => $contact['email_home'],
					'contexts' => ['private' => true],
				],
			]),
			'phones' => self::phones($contact),
			'online' => array_filter([
				'url' => !empty($contact['url']) ? ['resource' => $contact['url'], 'type' => 'uri', 'contexts' => ['work' => true]] : null,
				'url_home' => !empty($contact['url_home']) ? ['resource' => $contact['url_home'], 'type' => 'uri', 'contexts' => ['private' => true]] : null,
			]),
			'addresses' => array_filter([
				'work' => self::address($contact, 'work', 1),    // as it's the more prominent in our UI
				'home' =>  self::address($contact, 'home'),
			]),
			'photos' => self::photos($contact),
			'anniversaries' => self::anniversaries($contact),
			'notes' => empty($contact['note']) ? null : [self::localizedString($contact['note'])],
			'categories' => self::categories($contact['cat_id']),
			'egroupware.org/customfields' => self::customfields($contact),
			'egroupware.org/assistant' => $contact['assistent'],
			'egroupware.org/fileAs' => $contact['fileas'],
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
	 * @param string $json
	 * @return array
	 */
	public static function parseJsCard(string $json)
	{
		try
		{
			$data = json_decode($json, true, 10, JSON_THROW_ON_ERROR);

			if (!isset($data['uid'])) $data['uid'] = null;  // to fail below, if it does not exist

			$contact = [];
			foreach ($data as $name => $value)
			{
				switch ($name)
				{
					case 'uid':
						if (!is_string($value) || empty($value))
						{
							throw new \InvalidArgumentException("Missing or invalid uid value!");
						}
						$contact['uid'] = $value;
						break;

					case 'name':
						$contact += self::parseNameComponents($value);
						break;

					case 'fullName':
						$contact['n_fn'] = self::parseLocalizedString($value);
						break;

					case 'organizations':
						$contact += self::parseOrganizations($value);
						break;

					case 'titles':
						$contact += self::parseTitles($value);
						break;

					case 'emails':
						$contact += self::parseEmails($value);
						break;

					case 'phones':
						$contact += self::parsePhones($value);
						break;

					case 'online':
						$contact += self::parseOnline($value);
						break;

					case 'addresses':
						$contact += self::parseAddresses($value);
						break;

					case 'photos':
						$contact += self::parsePhotos($value);
						break;

					case 'anniversaries':
						$contact += self::parseAnniversaries($value);
						break;

					case 'notes':
						$contact['note'] = implode("\n", array_map(static function ($note) {
							return self::parseLocalizedString($note);
						}, $value));
						break;

					case 'categories':
						$contact['cat_id'] = self::parseCategories($value);
						break;

					case 'egroupware.org/customfields':
						$contact += self::parseCustomfields($value);
						break;

					case 'egroupware.org/assistant':
						$contact['assistent'] = $value;
						break;

					case 'egroupware.org/fileAs':
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
		catch (\JsonException $e) {
			throw new JsContactParseException("Error parsing JSON: ".$e->getMessage(), 422, $e);
		}
		catch (\InvalidArgumentException $e) {
			throw new JsContactParseException("Error parsing JsContact field '$name': ".
				str_replace('"', "'", $e->getMessage()), 422);
		}
		catch (\TypeError $e) {
			$message = $e->getMessage();
			if (preg_match('/must be of the type ([^ ]+), ([^ ]+) given/', $message, $matches))
			{
				$message = "$matches[1] expected, but got $matches[2]: ".
					str_replace('"', "'", json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			throw new JsContactParseException("Error parsing JsContact field '$name': $message", 422, $e);
		}
		catch (\Throwable $e) {
			throw new JsContactParseException("Error parsing JsContact field '$name': ". $e->getMessage(), 422, $e);
		}
		return $contact;
	}

	/**
	 * JSON options for errors thrown as exceptions
	 */
	const JSON_OPTIONS_ERROR = JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE;

	/**
	 * Return organisation
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.2.4
	 * @param array $contact
	 * @return array
	 */
	protected static function organization(array $contact)
	{
		if (empty($contact['org_name']))
		{
			return null;    // name is mandatory
		}
		return array_filter([
			'name' => self::localizedString($contact['org_name']),
			'units' => empty($contact['org_unit']) ? null : ['org_unit' => self::localizedString($contact['org_unit'])],
		]);
	}

	/**
	 * Parse Organizations
	 *
	 * As we store only one organization, the rest get lost, multiple units get concatenated by space.
	 *
	 * @param array $orgas
	 * @return array
	 */
	protected static function parseOrganizations(array $orgas)
	{
		$contact = [];
		foreach($orgas as $orga)
		{
			$contact['org_name'] = self::parseLocalizedString($orga['name']);
			$contact['org_unit'] = implode(' ', array_map(static function($unit)
			{
				return self::parseLocalizedString($unit);
			}, $orga['units']));
			break;
		}
		if (count($orgas) > 1)
		{
			error_log(__METHOD__."() more then 1 organization --> ignored");
		}
		return $contact;
	}

	/**
	 * Return titles of a contact
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.2.5
	 * @param array $contact
	 */
	protected static function titles(array $contact)
	{
		return array_filter([
			'title' => self::localizedString($contact['title']),
			'role' => self::localizedString($contact['role']),
		]);
	}

	/**
	 * Parse titles, thought we only have "title" and "role" available for storage
	 *
	 * @param array $titles
	 * @return array
	 */
	protected static function parseTitles(array $titles)
	{
		$contact = [];
		if (isset($titles[$id='title']) || isset($contact[$id='jobTitle']))
		{
			$contact['title'] = self::parseLocalizedString($titles[$id]);
			unset($titles[$id]);
		}
		if (isset($titles[$id='role']))
		{
			$contact['role'] = self::parseLocalizedString($titles[$id]);
			unset($titles[$id]);
		}
		if (!isset($contact['title']) && $titles)
		{
			$contact['title'] = self::parseLocalizedString(array_shift($titles));
		}
		if (!isset($contact['role']) && $titles)
		{
			$contact['role'] = self::parseLocalizedString(array_shift($titles));
		}
		if (count($titles))
		{
			error_log(__METHOD__."() only 2 titles can be stored --> rest is ignored!");
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
			if (isset($data[$name]))
			{
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
		return !$address ? [] : $address+[
			'contexts' => [$type => true],
			'pref' => $preference,
		];
	}

	/**
	 * Parse addresses object containing multiple addresses
	 *
	 * @param array $addresses
	 * @return array
	 */
	protected static function parseAddresses(array $addresses)
	{
		$n = 0;
		$last_type = null;
		$contact = [];
		foreach($addresses as $id => $address)
		{
			$contact += ($values=self::parseAddress($address, $id, $last_type));
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
	 * @return array
	 */
	protected static function parseAddress(array $address, string $id, string &$last_type=null)
	{
		$type = !isset($last_type) && (empty($address['contexts']['private']) || $id === 'work') ||
			$last_type === 'home' ? 'work' : 'home';
		$last_type = $type;
		$prefix = $type === 'work' ? 'adr_one_' : 'adr_two_';

		$contact = [$prefix.'street' => null, $prefix.'street2' => null];
		list($contact[$prefix.'street'], $contact[$prefix.'street2']) = self::parseStreetComponents($address['street']);
		foreach(self::$jsAddress2attr+self::$jsAddress2workAttr as $js => $attr)
		{
			if (isset($address[$js]) && !is_string($address[$js]))
			{
				throw new \InvalidArgumentException("Invalid address object with id '$id'");
			}
			$contact[$prefix.$attr] = $address[$js];
		}
		return $contact;
	}

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
					$components[] = ['type' => 'separator', 'value' => "\n"];
				}
				$components[] = ['type' => 'name', 'value' => $street];
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
	 * @param array $components
	 * @return string[] street and street2 values
	 */
	protected static function parseStreetComponents(array $components)
	{
		$street = [];
		$last_type = null;
		foreach($components as $component)
		{
			if (!is_array($component) || !is_string($component['value']))
			{
				throw new \InvalidArgumentException("Invalid street-component: ".json_encode($component, self::JSON_OPTIONS_ERROR));
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
	 * @return array
	 */
	protected static function parsePhones(array $phones)
	{
		$contact = [];

		// check for good matches
		foreach($phones as $id => $phone)
		{
			if (!is_array($phone) || !is_string($phone['phone']))
			{
				throw new \InvalidArgumentException("Invalid phone: " . json_encode($phone, self::JSON_OPTIONS_ERROR));
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

	/**
	 * Parse online resource objects
	 *
	 * We currently only support 2 URLs, rest get's ignored!
	 *
	 * @param array $values
	 * @return array
	 */
	protected static function parseOnline(array $values)
	{
		$contact = [];
		foreach($values as $id => $value)
		{
			if (!is_array($value) || !is_string($value['resource']))
			{
				throw new \InvalidArgumentException("Invalid online resource with id '$id': ".json_encode($value, self::JSON_OPTIONS_ERROR));
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

	/**
	 * Parse emails object
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.1
	 * @param array $emails id => object with attribute "email" and optional "context"
	 * @return array
	 */
	protected static function parseEmails(array $emails)
	{
		$contact = [];
		foreach($emails as $id => $value)
		{
			if (!is_array($value) || !is_string($value['email']))
			{
				throw new \InvalidArgumentException("Invalid email object (requires email attribute): ".json_encode($value, self::JSON_OPTIONS_ERROR));
			}
			if (!isset($contact['email']) && $id !== 'private' && empty($value['context']['private']))
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

	/**
	 * Return id => photo objects of a contact pairs
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.4
	 * @param array $contact
	 * @return array
	 */
	protected static function photos(array $contact)
	{
		return [];
	}

	/**
	 * Parse photos object
	 *
	 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07#section-2.3.4
	 * @param array $photos id => photo objects of a contact pairs
	 * @return array
	 */
	protected static function parsePhotos(array $photos)
	{
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
			return ['type' => $type, 'value' => $value];
		}, array_keys($components), array_values($components));
	}

	/**
	 * parse nameComponents
	 *
	 * @param array $components
	 * @return array
	 */
	protected static function parseNameComponents(array $components)
	{
		$contact = array_combine(array_values(self::$nameType2attribute),
			array_fill(0, count(self::$nameType2attribute), null));

		foreach($components as $component)
		{
			if (empty($component['type']) || isset($component) && !is_string($component['value']))
			{
				throw new \InvalidArgumentException("Invalid name-component (must have type and value attributes): ".json_encode($component, self::JSON_OPTIONS_ERROR));
			}
			$contact[self::$nameType2attribute[$component['type']]] = $component['value'];
		}
		return $contact;
	}

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
	 * @return array
	 */
	protected static function parseAnniversaries(array $anniversaries)
	{
		$contact = [];
		foreach($anniversaries as $id => $anniversary)
		{
			if (!is_array($anniversary) || !is_string($anniversary['date']) ||
				!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anniversary['date']) ||
				(!list($year, $month, $day) = explode('-', $anniversary['date'])) ||
				!(1 <= $month && $month <= 12 && 1 <= $day && $day <= 31))
			{
				throw new \InvalidArgumentException("Invalid anniversary object with id '$id': ".json_encode($anniversary, self::JSON_OPTIONS_ERROR));
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
	 * @param array $value object with attribute "value"
	 * @return string
	 */
	protected static function parseLocalizedString(array $value)
	{
		if (!is_string($value['value']))
		{
			throw new \InvalidArgumentException("Invalid localizedString: ".json_encode($value, self::JSON_OPTIONS_ERROR));
		}
		return $value['value'];
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
			'uid' => $group['list_uid'],
			'name' => $group['list_name'],
			'card' => self::getJsCard([
				'uid' => $group['list_uid'],
				'n_fn' => $group['list_name'],  // --> fullName
				'modified' => $group['list_modified'],  // no other way to send modification date
			], false),
			'members' => [],
		]);
		foreach($group['members'] as $uid)
		{
			$data['members'][$uid] = true;
		}
		if ($encode)
		{
			$data = Api\CalDAV::json_encode($data, $encode === 'pretty');
		}
		return $data;
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
