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
require_once __DIR__.'/../../admin/inc/class.admin_cmd_delete_account.inc.php';

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
		return $this->getCaldavBaseUrl() . $path;
	}

	/**
	 * Get CalDAV base URL with optional local overrides
	 *
	 * Override order:
	 * 1) EGW_CALDAV_BASE environment or phpunit var
	 * 2) EGW_URL environment or phpunit var (+ /groupdav.php)
	 * 3) self::CALDAV_BASE (with EGW_DOMAIN localhost replacement for legacy behavior)
	 *
	 * @return string
	 */
	protected function getCaldavBaseUrl()
	{
		$egw_url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null);
		if(!empty($egw_url))
		{
			return rtrim($egw_url, '/') . '/groupdav.php';
		}

		$base = self::CALDAV_BASE;
		if (!empty($GLOBALS['EGW_DOMAIN']) && $GLOBALS['EGW_DOMAIN'] !== 'default')
		{
			$base = str_replace('localhost', $GLOBALS['EGW_DOMAIN'], $base);
		}
		return rtrim($base, '/');
	}

	/**
	 * Default options for GuzzleHttp\Client
	 *
	 * @var array
	 * @see http://docs.guzzlephp.org/en/v6/request-options.html
	 */
	protected $client_options = [
		RequestOptions::HTTP_ERRORS => false,	// return all HTTP status, not throwing exceptions
		// Prevent CI hangs from indefinite network waits if CalDAV endpoint is unreachable/stalled.
		RequestOptions::CONNECT_TIMEOUT => 5,
		RequestOptions::TIMEOUT         => 10,
		RequestOptions::HEADERS => [
			'Cookie' => 'XDEBUG_SESSION=PHPSTORM',
			//'User-Agent' => 'CalDAVSynchronizer',
		],
	];

	/**
	 * Tracked calendar IDs keyed by test class.
	 *
	 * @var array<string,int[]>
	 */
	private static $tracked_cal_ids = [];

	/**
	 * Tracked event UIDs keyed by test class.
	 *
	 * @var array<string,string[]>
	 */
	private static $tracked_uids = [];

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
	 * Organizer account_lid from phpunit config.
	 */
	protected function organizerLid() : string
	{
		return $GLOBALS['EGW_USER'];
	}

	/**
	 * Organizer email used in iCal payload.
	 */
	protected function organizerMail() : string
	{
		return !empty($GLOBALS['egw_info']['user']['account_email']) ?
			$GLOBALS['egw_info']['user']['account_email'] :
			$this->organizerLid().'@example.org';
	}

	/**
	 * Build event URL in a specific user's calendar.
	 */
	protected function eventUrlFor(string $user, string $uid) : string
	{
		return '/'.$user.'/calendar/'.$uid.'.ics';
	}

	/**
	 * URL of an app's collection, e.g. "/<user>/addressbook/" or "/<user>/infolog/"
	 *
	 * Shared by CalDAV/CardDAV (native XML) and REST (JSON) tests alike.
	 *
	 * @param string $app eg. "addressbook", "infolog" or "calendar"
	 * @param string $user account_lid of the collection owner
	 * @return string
	 */
	protected function collectionUrl(string $app, string $user) : string
	{
		return '/'.$user.'/'.$app.'/';
	}

	/**
	 * PUT an arbitrary resource (vCard/iCalendar) to a CalDAV/CardDAV path.
	 *
	 * Unlike REST, CalDAV/CardDAV clients choose the resource name themselves,
	 * so no id needs to be extracted from the response.
	 *
	 * @param string $path eg. "/<user>/addressbook/<name>.vcf"
	 * @param string $content_type eg. "text/vcard" or "text/calendar"
	 * @param string $body raw vCard/iCalendar content
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @param array $headers additional headers
	 * @return ResponseInterface
	 */
	protected function putResource(string $path, string $content_type, string $body, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->put($this->url($path), [
			RequestOptions::HEADERS => array_merge([
				'Content-Type' => $content_type,
			], $headers),
			RequestOptions::BODY => $body,
		]);
	}

	/**
	 * Strip the "/groupdav.php" server-prefix from an href, leaving "/<user>/<app>/<name>".
	 */
	protected function hrefSuffix(string $href) : string
	{
		$marker = '/groupdav.php';
		$pos = strpos($href, $marker);
		return $pos !== false ? substr($href, $pos + strlen($marker)) : $href;
	}

	/**
	 * DELETE a CalDAV/CardDAV resource.
	 *
	 * @param string $path eg. "/<user>/addressbook/<name>.vcf"
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @return ResponseInterface
	 */
	protected function deleteResource(string $path, ?string $user=null) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->delete($this->url($path));
	}

	/**
	 * Perform a native rfc6578 sync-collection REPORT (XML) request against a collection.
	 *
	 * @param string $collection eg. from collectionUrl()
	 * @param ?string $sync_token ='' empty string for the initial/full sync
	 * @param ?int $nresults =null limit number of results per chunk, null for no limit
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @return array with keys "hrefs" (ordered list of "/<user>/<app>/<name>" paths, including deleted
	 *  ones - they keep their place in the oldest-modified-first order), "deleted" (subset of "hrefs"
	 *  reported without properties, i.e. status 404 - removed since the given sync-token), "sync-token"
	 *  (string to resume from, or null) and "more-results" (bool)
	 */
	protected function reportSyncCollection(string $collection, ?string $sync_token='', ?int $nresults=null, ?string $user=null) : array
	{
		$user = $user ?: $this->organizerLid();
		$limit = isset($nresults) ? "  <D:limit><D:nresults>$nresults</D:nresults></D:limit>\n" : '';
		$body = '<?xml version="1.0" encoding="utf-8"?>'."\n".
			'<D:sync-collection xmlns:D="DAV:">'."\n".
			'  <D:sync-token>'.htmlspecialchars((string)$sync_token).'</D:sync-token>'."\n".
			"  <D:sync-level>1</D:sync-level>\n".
			$limit.
			"  <D:prop>\n".
			"    <D:getetag/>\n".
			"    <D:getlastmodified/>\n".
			"  </D:prop>\n".
			"</D:sync-collection>\n";

		$response = $this->getClient($user)->request('REPORT', $this->url($collection), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'application/xml; charset=utf-8',
				'Depth' => '1',
			],
			RequestOptions::BODY => $body,
		]);
		$this->assertHttpStatus(207, $response, 'sync-collection REPORT');

		$xml = new \SimpleXMLElement((string)$response->getBody());
		$xml->registerXPathNamespace('D', 'DAV:');

		$hrefs = [];
		$deleted = [];
		$more_results = false;
		foreach($xml->xpath('//D:response') as $node)
		{
			$dav = $node->children('DAV:');
			// only set for entries without a propstat, i.e. the 507 more-results marker or a 404 deletion
			$status = (string)$dav->status;
			if (strpos($status, '507') !== false)
			{
				$more_results = true;
				continue;	// marker response for the collection itself, not a real resource
			}
			$href = $this->hrefSuffix((string)$dav->href);
			$hrefs[] = $href;
			if (strpos($status, '404') !== false)
			{
				$deleted[] = $href;
			}
		}
		$sync_token_nodes = $xml->xpath('//D:sync-token');

		return [
			'hrefs' => $hrefs,
			'deleted' => $deleted,
			'sync-token' => $sync_token_nodes ? (string)$sync_token_nodes[0] : null,
			'more-results' => $more_results,
		];
	}

	/**
	 * Extract numeric cal_id from ETag header and track it for cleanup.
	 */
	protected function addCalendarID($response) : int
	{
		$etag = $response->getHeader('ETag')[0] ?? '';
		$array = explode(':', trim($etag, '[]"'));
		$cal_id = !empty($array[0]) ? (int)$array[0] : 0;
		if($cal_id > 0)
		{
			self::trackCalId($cal_id);
		}
		return $cal_id;
	}

	/**
	 * Generate unique test UID and track it for cleanup.
	 */
	protected function makeUid(string $prefix) : string
	{
		$stamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('YmdHis');
		$uid = $prefix.'-'.$stamp.'-'.bin2hex(random_bytes(2));
		self::trackUid($uid);
		return $uid;
	}

	/**
	 * Create event in organizer calendar via CalDAV PUT.
	 */
	protected function putEvent(string $uid, string $ical, ?string $user=null) : int
	{
		$user = $user ?: $this->organizerLid();
		$response = $this->getClient($user)->put($this->url($this->eventUrlFor($user, $uid)), [
			RequestOptions::HEADERS => [
				'Content-Type' => 'text/calendar',
				'Prefer' => 'return=representation',
			],
			RequestOptions::BODY => $ical,
		]);
		$this->assertHttpStatus([200, 201], $response);
		return $this->addCalendarID($response);
	}

	protected function getEventIcal(string $uid, ?string $user=null) : string
	{
		$user = $user ?: $this->organizerLid();
		$response = $this->getClient($user)->get($this->url($this->eventUrlFor($user, $uid)));
		$this->assertHttpStatus(200, $response);
		return (string)$response->getBody();
	}

	protected function unfoldIcal(string $ical) : string
	{
		return preg_replace("/\r\n[ \t]/", '', $ical);
	}

	/**
	 * Return full VEVENT block containing a given RECURRENCE-ID.
	 */
	protected function exceptionBlock(string $ical, string $recurrence_id) : string
	{
		$pattern = "/BEGIN:VEVENT\r\n(?:(?!BEGIN:VEVENT).)*RECURRENCE-ID:" .
			preg_quote($recurrence_id, '/') .
			"(?:(?!BEGIN:VEVENT).)*END:VEVENT/s";
		if (preg_match($pattern, $ical, $matches))
		{
			return $matches[0];
		}
		return '';
	}

	protected function renderFixture(string $fixture_file, array $tokens) : string
	{
		$template = file_get_contents($fixture_file);
		$this->assertNotFalse($template, "Unable to load fixture $fixture_file");
		return strtr($template, $tokens);
	}

	/**
	 * Track event cal_id for current test class.
	 */
	protected static function trackCalId(int $cal_id) : void
	{
		$class = static::class;
		if (!isset(self::$tracked_cal_ids[$class]))
		{
			self::$tracked_cal_ids[$class] = [];
		}
		self::$tracked_cal_ids[$class][] = $cal_id;
	}

	/**
	 * Track event UID for current test class.
	 */
	protected static function trackUid(string $uid) : void
	{
		$class = static::class;
		if (!isset(self::$tracked_uids[$class]))
		{
			self::$tracked_uids[$class] = [];
		}
		self::$tracked_uids[$class][] = $uid;
	}

	/**
	 * Cleanup tracked cal_ids and UIDs for current test class.
	 */
	protected static function cleanupTrackedEvents() : void
	{
		$class = static::class;
		$cal_ids = self::$tracked_cal_ids[$class] ?? [];
		$uids = self::$tracked_uids[$class] ?? [];

		if(!empty($GLOBALS['egw']) && !empty($GLOBALS['egw']->db))
		{
			$so = new \calendar_so();
			foreach(array_unique($cal_ids) as $cal_id)
			{
				if((int)$cal_id > 0)
				{
					$so->delete((int)$cal_id);
				}
			}
			foreach(array_unique($uids) as $uid)
			{
				foreach(array_keys($so->read($uid) ?: []) as $cal_id)
				{
					$so->delete((int)$cal_id);
				}
			}
		}
		unset(self::$tracked_cal_ids[$class], self::$tracked_uids[$class]);
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
	protected static function createUsersACL(array &$users, $app = 'calendar')
	{
		foreach($users as $user => &$data)
		{
			$data['id'] = self::createUser($user, $data);

			foreach($data['rights'] ?? [] as $grantee => $rights)
			{
				self::addAcl('calendar', $data['id'], $grantee, $rights);
			}
		}
		unset($data);
	}

	/**
	 * Array to track created users for tearDown and authentication
	 *
	 * @var array $account_lid => array with other data pairs
	 */
	private static $created_users = [];

	/**
	 * Cached setup instance for CalDAV helper account / ACL operations.
	 *
	 * @var \setup|null
	 */
	private static $setup = null;

	/**
	 * Snapshot of globals touched by getSetup(), restored in tearDownAfterClass().
	 *
	 * @var array<string,mixed>|null
	 */
	private static $setup_global_snapshot = null;

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
		static::cleanupTrackedEvents();

		if(self::$setup && self::$created_users)
		{
			// admin_cmd_delete_account requires the CURRENT in-process identity to be a real admin.
			// This class never establishes a real login session (createUser() uses setup::add_account()
			// directly, bypassing ACL entirely, and there's no LoggedInTest::switchUser() available
			// here) - temporarily borrow the dedicated admin test account's identity for just this
			// cleanup loop, then restore whatever was there before.
			$saved_account_id = $GLOBALS['egw_info']['user']['account_id'] ?? null;
			if ($GLOBALS['egw']->accounts ?? null)
			{
				$GLOBALS['egw_info']['user']['account_id'] = $GLOBALS['egw']->accounts->name2id($GLOBALS['EGW_ADMIN_USER']);
			}

			foreach(self::$created_users as $account_lid => $data)
			{
				if (!empty($data['id']))
				{
					self::logoutAccessLog((int)$data['id']);

					try {
						$command = new \admin_cmd_delete_account((int)$data['id'], null, true);
						$command->comment = 'Removing in tearDownAfterClass for '.static::class;
						$command->run();
					}
					catch (\Throwable $e) {
						// ignore cleanup failures to avoid masking original test result
					}
				}
				unset(self::$created_users[$account_lid]);
			}

			$GLOBALS['egw_info']['user']['account_id'] = $saved_account_id;
		}
		self::$created_users = [];
		self::resetSharedRuntimeState();
		self::restoreSetupGlobals();
		self::$setup = null;
	}

	/**
	 * Mark a test-created account's egw_access_log row(s) as logged out.
	 *
	 * getClient()/auth() authenticate via HTTP Basic Auth against groupdav.php, which Session::create()
	 * only ever logs as a "pseudo session" (see Session::get_sessionid()) - never closed by the DAV
	 * request itself, only by session-timeout GC. Left open, each createUser()'d account keeps counting
	 * as a concurrent user (Stylite license check) even after we delete the account below; since
	 * account_id is unique per test account, it's safe to close every open row for it here.
	 */
	private static function logoutAccessLog(int $account_id) : void
	{
		if(empty($GLOBALS['egw']) || empty($GLOBALS['egw']->db))
		{
			return;
		}
		$GLOBALS['egw']->db->update(Session::ACCESS_LOG_TABLE, ['lo' => time()],
			['account_id' => $account_id, 'lo' => null], __LINE__, __FILE__);
	}

	/**
	 * Reset process-wide API singletons/caches CalDAV helper paths can touch.
	 */
	private static function resetSharedRuntimeState() : void
	{
		// Reset Link runtime caches/object instances used by query/title/file access helpers
		// BEFORE resetting the Accounts singleton below: Link::init_static(true) triggers hooks
		// (eg. some CTI backends construct Api\Accounts::getInstance() eagerly), which must still
		// see the current, properly-configured Accounts instance - not a freshly (default-config)
		// re-instantiated one, which can otherwise try to contact a backend that isn't reachable
		// in this context (eg. an LDAP/AD server).
		// In some teardown contexts (eg. CI ordering), egwd db can already be gone.
		if (!empty($GLOBALS['egw']) && !empty($GLOBALS['egw']->db))
		{
			\EGroupware\Api\Link::init_static(true);
		}

		// Reset Accounts singleton/caches to avoid cross-suite backend/capability leaks.
		$accounts_instance = new \ReflectionProperty(\EGroupware\Api\Accounts::class, '_instance');
		$accounts_instance->setAccessible(true);
		$accounts_instance->setValue(null, null);

		$accounts_cache = new \ReflectionProperty(\EGroupware\Api\Accounts::class, 'cache');
		$accounts_cache->setAccessible(true);
		$accounts_cache->setValue(null, []);
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
		if(!isset(self::$setup))
		{
			self::snapshotSetupGlobals();

			if (empty($_REQUEST['domain']))
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
			// api/src/loader.php can unset $GLOBALS['egw_domain'] for security.
			// CalDAV test helpers still need DB connection details to create fixture users.
			if (empty($GLOBALS['egw_domain']) &&
				($header = @file_get_contents(__DIR__ . '/../../header.inc.php')))
			{
				$domain_pattern = "/\\\$GLOBALS\\['egw_domain'\\]\\['([^']+)'\\]\\s*=\\s*array\\((.*?)\\);/s";
				if (preg_match_all($domain_pattern, $header, $domains, PREG_SET_ORDER))
				{
					foreach($domains as $domain_match)
					{
						$domain = $domain_match[1];
						$values = [];
						if (preg_match_all("/'([^']+)'\\s*=>\\s*'((?:\\\\'|[^'])*)'/", $domain_match[2], $pairs, PREG_SET_ORDER))
						{
							foreach($pairs as $pair)
							{
								$values[$pair[1]] = str_replace("\\'", "'", $pair[2]);
							}
						}
						if (!empty($values))
						{
							$GLOBALS['egw_domain'][$domain] = $values;
						}
					}
				}
			}
			// Resolve the requested domain (eg. phpunit.xml's literal "default") against
			// whatever domains are actually configured, the same way
			// LoggedInTest::load_egw() resolves EGW_DOMAIN via Api\Session::search_instance() -
			// a domain literally named "default" need not exist (this repo's header.inc.php
			// commonly only has real, named domains like "boulder.egroupware.org");
			// search_instance() falls back to matching HTTP_HOST/SERVER_NAME or, failing that,
			// the first configured domain, instead of a literal string match.
			if (!empty($GLOBALS['egw_domain']) && empty($GLOBALS['egw_domain'][$_REQUEST['domain']]['db_host']))
			{
				$default_domain = $GLOBALS['egw_info']['server']['default_domain'] ?? null;
				$_REQUEST['domain'] = $_REQUEST['ConfigDomain'] = Session::search_instance(
					null, $_REQUEST['domain'], $default_domain,
					array($_SERVER['HTTP_HOST'] ?? '', $_SERVER['SERVER_NAME'] ?? ''),
					$GLOBALS['egw_domain']);
			}
			// Some setup / account code paths (eg. push token generation) require an install_id
			// in egw_info['server'], which may not yet be populated in CLI test bootstrap.
			if (empty($GLOBALS['egw_info']['server']['install_id']))
			{
				$domain = $_REQUEST['domain'] ?? 'default';
				$header_install_id = $GLOBALS['egw_domain'][$domain]['server']['install_id']
					?? $GLOBALS['egw_info']['server']['install_id']
					?? null;
				$GLOBALS['egw_info']['server']['install_id'] = $header_install_id ?: md5(microtime(true).__FILE__);
			}
			self::$setup = new \setup();
		}
		return self::$setup;
	}

	/**
	 * Snapshot globals likely to be changed while bootstrapping setup helpers.
	 */
	private static function snapshotSetupGlobals() : void
	{
		if(self::$setup_global_snapshot !== null)
		{
			return;
		}
		self::$setup_global_snapshot = [
			'_REQUEST_domain'       => $_REQUEST['domain'] ?? null,
			'_REQUEST_ConfigDomain' => $_REQUEST['ConfigDomain'] ?? null,
			'egw_info'              => $GLOBALS['egw_info'] ?? null,
			'egw_setup'             => $GLOBALS['egw_setup'] ?? null,
			'egw'                   => $GLOBALS['egw'] ?? null,
		];
	}

	/**
	 * Restore global state after setup helper use to avoid leaking into other tests.
	 */
	private static function restoreSetupGlobals() : void
	{
		if(self::$setup_global_snapshot === null)
		{
			return;
		}
		if(self::$setup_global_snapshot['_REQUEST_domain'] !== null)
		{
			$_REQUEST['domain'] = self::$setup_global_snapshot['_REQUEST_domain'];
		}
		else
		{
			unset($_REQUEST['domain']);
		}
		if(self::$setup_global_snapshot['_REQUEST_ConfigDomain'] !== null)
		{
			$_REQUEST['ConfigDomain'] = self::$setup_global_snapshot['_REQUEST_ConfigDomain'];
		}
		else
		{
			unset($_REQUEST['ConfigDomain']);
		}
		if(self::$setup_global_snapshot['egw_info'] !== null)
		{
			$GLOBALS['egw_info'] = self::$setup_global_snapshot['egw_info'];
		}
		else
		{
			unset($GLOBALS['egw_info']);
		}
		if(self::$setup_global_snapshot['egw_setup'] !== null)
		{
			$GLOBALS['egw_setup'] = self::$setup_global_snapshot['egw_setup'];
		}
		else
		{
			unset($GLOBALS['egw_setup']);
		}
		if(self::$setup_global_snapshot['egw'] !== null)
		{
			$GLOBALS['egw'] = self::$setup_global_snapshot['egw'];
		}
		else
		{
			// getSetup() bootstrapped its own $GLOBALS['egw'] (under 'setup'/noapi context) because
			// none existed yet - discard it, so a subsequent LoggedInTest::load_egw() (eg. in a test
			// class running right after, due to alphabetic test-file ordering) builds a properly
			// flagged Egw instance instead of silently reusing this minimal one via its reuse-guard
			// (empty($GLOBALS['egw']) check), which left ACL/rights loaded for the wrong context.
			if(!empty($GLOBALS['egw']) && !empty($GLOBALS['egw']->db))
			{
				$GLOBALS['egw']->db->disconnect();
			}
			unset($GLOBALS['egw']);
		}
		self::$setup_global_snapshot = null;
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
