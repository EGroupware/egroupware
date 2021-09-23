<?php
/**
 * CalDAV tests base class
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker
 * @package api
 * @subpackage caldav
 * @copyright (c) 2020 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

// so tests can run standalone
require_once __DIR__.'/../src/loader/common.php';	// autoloader

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client, GuzzleHttp\RequestOptions;
use Horde_Icalendar, Horde_Icalendar_Exception;
use Psr\Http\Message\ResponseInterface;

/**
 * Abstract CalDAVTest using GuzzleHttp\Client against EGroupware CalDAV/CardDAV server
 *
 * @see http://docs.guzzlephp.org/en/v6/quickstart.html
 *
 * @package EGroupware\Api
 */
abstract class CalDAVTest extends TestCase
{
	/**
	 * Base URL of CalDAV server
	 */
	const CALDAV_BASE = 'http://localhost/egroupware/groupdav.php';

	/**
	 * Get full URL for a CalDAV path
	 *
	 * @param string $path CalDAV path
	 * @return string URL
	 */
	protected function url($path='/')
	{
		$base = self::CALDAV_BASE;
		if (!empty($GLOBALS['EGW_DOMAIN']) && $GLOBALS['EGW_DOMAIN'] !== 'default')
		{
			$base = str_replace('localhost', $GLOBALS['EGW_DOMAIN'], $base);
		}
		return $base.$path;
	}

	/**
	 * Default options for GuzzleHttp\Client
	 *
	 * @var array
	 * @see http://docs.guzzlephp.org/en/v6/request-options.html
	 */
	protected $client_options = [
		RequestOptions::HTTP_ERRORS => false,	// return all HTTP status, not throwing exceptions
		RequestOptions::HEADERS => [
			'Cookie' => 'XDEBUG_SESSION=PHPSTORM',
			//'User-Agent' => 'CalDAVSynchronizer',
		],
	];

	/**
	 * Get HTTP client for tests
	 *
	 * It will use by default the user configured in phpunit.xml: demo/guest (use [] to NOT authenticate).
	 * Additional users need to be created with $this->createUser("name").
	 *
	 * @param string|array $user_or_options =null string with account_lid of user for authentication or array of options
	 * @return Client
	 * @see http://docs.guzzlephp.org/en/v6/request-options.html
	 * @see http://docs.guzzlephp.org/en/v6/quickstart.html
	 */
	protected function getClient($user_or_options=null)
	{
		if (!is_array($user_or_options))
		{
			$user_or_options = $this->auth($user_or_options);
		}
		return new Client(array_merge($this->client_options, $user_or_options));
	}

	/**
	 * Create a number of users with optional ACL rights too
	 *
	 * Example with boss granting secretary full rights on his calendar, plus one other user:
	 *
	 * $users = [
	 * 	'boss' => [],
	 * 	'secretary' => ['rights' => ['boss'  => Acl::READ|Acl::ADD|Acl::EDIT|Acl::DELETE]],
	 * 	'other' => [],
	 * ];
	 * self::createUsersACL($users);
	 *
	 * @param array& $users $account_lid => array with values for keys with (defaults) "firstname" ($_acount_lid), "lastname" ("User"),
	 *    "email" ("$_account_lid@example.org"), "password" (random string), "primary_group" ("NoGroups" to not set rights)
	 *    "rights" array with $grantee => $rights pairs (need to be created before!)
	 * @param string $app app to create the rights for, default "calendar"
	 * @throws \Exception
	 */
	protected function createUsersACL(array &$users, $app='calendar')
	{
		foreach($users as $user => $data)
		{
			$data['id'] = self::createUser($user, $data);

			foreach($data['rights'] ?? [] as $grantee => $rights)
			{
				self::addAcl('calendar', $data['id'], $grantee, $rights);
			}
		}
	}

	/**
	 * Array to track created users for tearDown and authentication
	 *
	 * @var array $account_lid => array with other data pairs
	 */
	private static $created_users = [];

	/**
	 * Create a user
	 *
	 * Created users are automatic deleted in tearDown() and can be passed to auth() or getClient() methods.
	 * Users have random passwords to force new/different sessions!
	 *
	 * @param string $_account_lid
	 * @param array& $data =[] values for keys with (defaults) "firstname" ($_acount_lid), "lastname" ("User"),
	 *    "email" ("$_account_lid@example.org"), "password" (random string), "primary_group" ("NoGroups" to not set rights)
	 *  on return: with defaults set
	 * @return int account_id of created user
	 * @throws \Exception
	 */
	protected static function createUser($_account_lid, array &$data=[])
	{
		// add some defaults
		$data = array_merge([
			'firstname' => ucfirst($_account_lid),
			'lastname'  => 'User',
			'email'     => $_account_lid.'@example.org',
			'password'  => 'secret',//Auth::randomstring(12),
			'primary_group' => 'NoGroup',
		], $data);

		$data['id'] = self::getSetup()->add_account($_account_lid, $data['firstname'], $data['lastname'],
			$data['password'], $data['primary_group'], false, $data['email']);

		// give use run rights for CalDAV apps, as NoGroup does NOT!
		self::addAcl(['groupdav','calendar','infolog','addressbook'], 'run', $data['id']);

		self::$created_users[$_account_lid] = $data;

		return $data['id'];
	}

	/**
	 * Get authentication information for given user to use
	 *
	 * @param string $_account_lid =null default EGW_USER configured in phpunit.xml
	 * @return array
	 */
	protected function auth($_account_lid=null)
	{
		if (!isset($_account_lid) || $_account_lid === $GLOBALS['EGW_USER'])
		{
			$_account_lid = $GLOBALS['EGW_USER'];
			$password = $GLOBALS['EGW_PASSWORD'];
		}
		elseif (!isset(self::$created_users[$_account_lid]))
		{
			throw new \InvalidArgumentException("No user '$_account_lid' exist, need to create it with createUser('$_account_lid')");
		}
		else
		{
			$password = self::$created_users[$_account_lid]['password'];
		}
		return [RequestOptions::AUTH => [$_account_lid, $password]];
	}

	/**
	 * Tear down:
	 * - delete users created by createUser() incl. their ACL and data
	 *
	 * @ToDo: implement eg. with admin_cmd_delete_user to also delete ACL and data
	 */
	public static function tearDownAfterClass() : void
	{
		$setup = self::getSetup();

		foreach(self::$created_users as $account_lid => $data)
		{
//			if ($id) $setup->accounts->delete($data['id']);
			unset(self::$created_users[$account_lid]);
		}
	}

	/**
	 * Add ACL rights
	 *
	 * @param string|array $apps app-names
	 * @param string $location eg. "run"
	 * @param int|string $account accountid or account_lid
	 * @param int $rights rights to set, default 1
	 */
	protected static function addAcl($apps, $location, $account, $rights=1)
	{
		return self::getSetup()->add_acl($apps, $location, $account, $rights);
	}

	/**
	 * Return instance of setup object eg. to create users
	 *
	 * @return \setup
	 */
	private static function getSetup()
	{
		static $setup=null;
		if (!isset($setup))
		{
			if (!isset($_REQUEST['domain']))
			{
				$_REQUEST['domain'] = $GLOBALS['EGW_DOMAIN'] ?? 'default';
			}
			$_REQUEST['ConfigDomain'] = $_REQUEST['domain'];

			$GLOBALS['egw_info'] = array(
				'flags' => array(
					'noheader' => True,
					'nonavbar' => True,
					'currentapp' => 'setup',
					'noapi' => True
				));
			if (file_exists(__DIR__ . '/../../header.inc.php'))
			{
				include_once(__DIR__ . '/../../header.inc.php');
			}
			$setup = new \setup();
		}
		return $setup;
	}

	/**
	 * Check HTTP status in response
	 *
	 * @param int|array $expected one or more valid status codes
	 * @param ResponseInterface $response
	 * @param string $message ='' additional message to prefix result message
	 */
	protected function assertHttpStatus($expected, ResponseInterface $response, $message='')
	{
		$status = $response->getStatusCode();
		$this->assertEquals(in_array($status, (array)$expected) ? $status : ((array)$expected)[0], $status,
			(!empty($message) ? $message.': ' : ''). 'Expected HTTP status: '.json_encode($expected).
			", Server returned: $status ".$response->getReasonPhrase());
	}

	/**
	 * Asserts an iCal file matches an expected one taking into account $_overwrites
	 *
	 * @param string $_expected
	 * @param string $_acctual
	 * @param string $_message
	 * @param array $_overwrites =[] eg. ['vEvent' => [['ATTENDEE' => ['mailto:boss@example.org' => ['PARTSTAT' => 'DECLINED']]]]]
	 *  (first vEvent attendee with value 'mailto:boss@...' has param 'PARTSTAT=DECLINED')
	 * @throws Horde_Icalendar_Exception
	 */
	protected function assertIcal($_expected, $_acctual, $_message=null, $_overwrites=[])
	{
		// enable to see full iCals
		//$this->assertEquals($_expected, (string)$_acctual, $_message.": iCal not byte-by-byte identical");

		$expected = new Horde_Icalendar();
		$expected->parsevCalendar($_expected);
		$acctual = new Horde_Icalendar();
		$acctual->parsevCalendar($_acctual);

		if (($msgs = $this->checkComponentEqual($expected, $acctual, $_overwrites)))
		{
			$this->assertEquals($_expected, (string)$_acctual, ($_message ? $_message.":\n" : '').implode("\n", $msgs));
		}
		else
		{
			$this->assertTrue(true);	// due to $_overwrite probable $_expected !== $_acctual
		}
	}

	/**
	 * Check two iCal components are equal modulo overwrites / expected difference
	 *
	 * Only a whitelist of attributes per component are checked, see $component_attrs2check variable.
	 *
	 * @param Horde_Icalendar $_expected
	 * @param Horde_Icalendar $_acctual
	 * @param string $_message
	 * @param array $_overwrites =[] eg. ['ATTENDEE' => ['boss@example.org' => ['PARTSTAT' => 'DECLINED']]]
	 * @throws Horde_Icalendar_Exception
	 * @return array message(s) what's not equal
	 */
	protected function checkComponentEqual(Horde_Icalendar $_expected, Horde_Icalendar $_acctual, $_overwrites=[])
	{
		// only following attributes in these components are checked:
		static $component_attrs2check = [
			'vcalendar' => ['VERSION'],
			'vTimeZone' => ['TZID'],
			'vEvent' => ['UID', 'SUMMARY', 'LOCATION', 'DESCRIPTION', 'DTSTART', 'DTEND', 'ORGANIZER', 'ATTENDEE'],
		];

		if ($_expected->getType() !== $_acctual->getType())
		{
			return ["component type not equal"];
		}
		$msgs = [];
		foreach ($component_attrs2check[$_expected->getType()] ?? [] as $attr)
		{
			$acctualAttrs = $_acctual->getAllAttributes($attr);
			foreach($_expected->getAllAttributes($attr) as $expectedAttr)
			{
				$found = false;
				foreach($acctualAttrs as $acctualAttr)
				{
					if (count($acctualAttrs) === 1 || $expectedAttr['value'] === $acctualAttr['value'])
					{
						$found = true;
						break;
					}
				}
				if (!$found)
				{
					$msgs[] = "No $attr {$expectedAttr['value']} found";
					continue;
				}
				// remove / ignore X-parameters, eg. X-EGROUPWARE-UID in ATTENDEE or ORGANIZER
				$acctualAttr['params'] = array_filter($acctualAttr['params'], function ($key) {
					return substr($key, 0, 2) !== 'X-';
				}, ARRAY_FILTER_USE_KEY);

				if (isset($_overwrites[$attr]) && is_scalar($_overwrites[$attr]))
				{
					$expectedAttr = [
						'name' => $attr,
						'value' => $_overwrites[$attr],
						'values' => [$_overwrites[$attr]],
						'params' => [],
					];
				}
				elseif (isset($_overwrites[$attr]) && is_array($_overwrites[$attr]))
				{
					foreach ($_overwrites[$attr] as $value => $params)
					{
						if ($value === $expectedAttr['value'])
						{
							$expectedAttr['params'] = array_merge($expectedAttr['params'], $params);
						}
					}
				}
				if ($expectedAttr != $acctualAttr)
				{
					$this->assertEquals($expectedAttr, $acctualAttr, "$attr not equal");
					$msgs[] = "$attr not equal";
				}
			}
		}
		// check sub-components, overrites use an index by type eg. 1. vEvent: ['vEvent'=>[[<overwrites for 1. vEvent]]]
		$idx_by_type = [];
		foreach($_expected->getComponents() as $idx => $component)
		{
			if (!isset($idx_by_type[$type = $component->getType()])) $idx_by_type[$type] = 0;
			$msgs = array_merge($msgs, $this->checkComponentEqual($component, $_acctual->getComponent($idx),
				 $_overwrites[$type][$idx_by_type[$type]] ?? []));
			$idx_by_type[$type]++;
		}
		return $msgs;
	}
}