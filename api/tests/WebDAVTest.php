<?php
/**
 * WebDAV server (webdav.php / filemanager) tests base class
 *
 * Performs real HTTP requests against the same EGroupware WebDAV server
 * machinery ({@see \HTTP_WebDAV_Server}) that also backs groupdav.php - unlike
 * the in-process api/tests/Vfs/* tests, webdav.php runs as a SEPARATE PHP
 * process behind the real webserver, so there is no way to share a
 * non-persistent scratch Vfs::mount() with it; tests work under a freshly
 * createUser()'d account's own /home/<lid>/ instead (created + chown()ed
 * automatically by the Vfs\Hooks::addaccount hook fired from
 * setup::add_account(), same as every other CalDAV test account).
 *
 * Extends {@see CalDAVTest} purely to reuse its "heavy lifting" (user/ACL
 * setup, HTTP client/auth, assertHttpStatus(), account cleanup) - same
 * relationship {@see RestBase} already has. Only url() differs (points at
 * webdav.php instead of groupdav.php); CalDAV/CardDAV-specific helpers that
 * also call $this->url() internally (putResource(), reportSyncCollection(),
 * ...) are simply never called from WebDAV tests.
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package api
 * @subpackage webdav
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

require_once __DIR__.'/CalDAVTest.php';

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

abstract class WebDAVTest extends CalDAVTest
{
	/**
	 * Base URL of the WebDAV server, overriding CalDAVTest::url()'s groupdav.php target.
	 *
	 * Mirrors getCaldavBaseUrl()'s override order (EGW_URL env/phpunit var, else
	 * self::CALDAV_BASE with the "localhost" -> $GLOBALS['EGW_DOMAIN'] substitution),
	 * just for "/webdav.php" instead of "/groupdav.php".
	 *
	 * @param string $path
	 * @return string
	 */
	protected function url($path='/')
	{
		$egw_url = getenv('EGW_URL') ?: ($_ENV['EGW_URL'] ?? null) ?: ($GLOBALS['EGW_URL'] ?? null);
		if (!empty($egw_url))
		{
			$base = rtrim($egw_url, '/').'/webdav.php';
		}
		else
		{
			$base = str_replace('/groupdav.php', '/webdav.php', self::CALDAV_BASE);
			if (!empty($GLOBALS['EGW_DOMAIN']) && $GLOBALS['EGW_DOMAIN'] !== 'default')
			{
				$base = str_replace('localhost', $GLOBALS['EGW_DOMAIN'], $base);
			}
		}
		return rtrim($base, '/').$path;
	}

	/**
	 * Generate a randomized, never-reused account_lid.
	 *
	 * MUST be used instead of a fixed literal for any account_lid a test class
	 * creates via createUser(): a fixed lid reused across separate test RUNS
	 * (as opposed to reused within one run, which is fine) can carry over
	 * stale mail-credential state from a prior run and change how
	 * setup::add_account()'s changepassword hook behaves the next time
	 * around - see doc/ai/projects/webdav-test-coverage.md's "stale
	 * account_lid" finding (an unrelated Mail\Smtp\Stalwart JMAP-discovery
	 * exception escaped a try/catch that should have caught it, traced to
	 * this, not to WebDAV code).
	 *
	 * @param string $prefix short, human-readable prefix, e.g. "webdavget"
	 * @return string
	 */
	protected static function randomLid(string $prefix) : string
	{
		return $prefix.substr(md5(uniqid('', true)), 0, 8);
	}

	/**
	 * Create a user AND grant the "filemanager" run right webdav.php requires
	 * (CalDAVTest::createUser() only grants groupdav/calendar/infolog/addressbook).
	 *
	 * @param string $_account_lid
	 * @param array& $data =[] see CalDAVTest::createUser()
	 * @return int account_id of created user
	 */
	protected static function createUser($_account_lid, array &$data=[])
	{
		$id = parent::createUser($_account_lid, $data);
		self::addAcl('filemanager', 'run', $id);
		return $id;
	}

	/**
	 * Strip the "/webdav.php" server-prefix from an href, leaving "/home/<user>/<name>".
	 *
	 * Overrides {@see CalDAVTest::hrefSuffix()}, which strips "/groupdav.php" instead.
	 */
	protected function hrefSuffix(string $href) : string
	{
		$marker = '/webdav.php';
		$pos = strpos($href, $marker);
		return $pos !== false ? substr($href, $pos + strlen($marker)) : $href;
	}

	/**
	 * Hard-delete a user's entire /home/<user> VFS subtree directly via the DB,
	 * bypassing Vfs::remove()/WebDAV DELETE entirely.
	 *
	 * This dev environment's default "/" mount is stylite.s3://, which routes
	 * through Versioning\StreamWrapper - unlink() there is a SOFT delete
	 * (fs_active=0) and even a directory containing only INACTIVE rows still
	 * fails Vfs::remove()'s "dir is not empty" check (confirmed empirically -
	 * not just a Versioning quirk with visible rows, empty-looking dirs can
	 * still block removal). admin_cmd_delete_account() (used by
	 * CalDAVTest::tearDownAfterClass() to remove test accounts) does NOT clean
	 * up the account's VFS home directory either - so without this, repeated
	 * test runs accumulate orphaned rows under a reused account_lid and can
	 * make later MKCOL/PUT calls unexpectedly fail (405/409) against
	 * pre-existing paths from a PRIOR run.
	 *
	 * @param string $user account_lid whose /home/<user> subtree to wipe
	 */
	protected static function hardDeleteHomeTree(string $user) : void
	{
		if (empty($GLOBALS['egw']) || empty($GLOBALS['egw']->db))
		{
			return;
		}
		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		try
		{
			$stat = Vfs::stat('/home/'.$user);
			if (!$stat)
			{
				return;
			}
			// breadth-first walk of ALL rows (active or not) under this ino
			$inos = [$stat['ino']];
			for ($n = 0; $n < count($inos); $n++)
			{
				$rs = $GLOBALS['egw']->db->select('egw_sqlfs', 'fs_id', ['fs_dir' => $inos[$n]], __LINE__, __FILE__);
				while ($row = $rs->fetch())
				{
					$inos[] = (int)$row['fs_id'];
				}
			}
			foreach (array_reverse($inos) as $ino)
			{
				$GLOBALS['egw']->db->delete('egw_sqlfs', ['fs_id' => $ino], __LINE__, __FILE__);
			}
			Vfs::clearstatcache();
		}
		finally
		{
			Vfs::$is_root = $backup;
		}
	}

	/**
	 * URL of a user's home directory collection, e.g. "/home/<user>/"
	 *
	 * @param string $user account_lid of the home directory owner
	 * @return string
	 */
	protected function homeCollection(string $user) : string
	{
		return '/home/'.$user.'/';
	}

	/**
	 * URL of a file inside a user's home directory.
	 *
	 * @param string $user account_lid of the home directory owner
	 * @param string $name file name (no leading slash)
	 * @return string
	 */
	protected function homeFile(string $user, string $name) : string
	{
		return $this->homeCollection($user).$name;
	}

	/**
	 * PUT a file.
	 *
	 * @param string $path eg. from homeFile()
	 * @param string $body raw content
	 * @param string $content_type ='application/octet-stream'
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @param array $headers additional headers, e.g. ['Content-Range' => 'bytes 0-99/200']
	 * @return ResponseInterface
	 */
	protected function putFile(string $path, string $body, string $content_type='application/octet-stream', ?string $user=null, array $headers=[]) : ResponseInterface
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
	 * PUT a single Content-Range chunk of a (possibly multi-request) chunked upload.
	 *
	 * @param string $path eg. from homeFile()
	 * @param string $chunk raw bytes of just this chunk
	 * @param int $start offset of $chunk's first byte within the final file
	 * @param int $end offset of $chunk's last byte within the final file (inclusive)
	 * @param int|string $total final file size, or '*' if not (yet) known
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @return ResponseInterface
	 */
	protected function putFileRange(string $path, string $chunk, int $start, int $end, $total, ?string $user=null) : ResponseInterface
	{
		return $this->putFile($path, $chunk, 'application/octet-stream', $user, [
			'Content-Range' => "bytes $start-$end/$total",
		]);
	}

	/**
	 * GET a file/collection, optionally with a Range: header.
	 *
	 * @param string $path eg. from homeFile()/homeCollection()
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @param array $headers additional headers, e.g. ['Range' => 'bytes=0-99']
	 * @return ResponseInterface
	 */
	protected function getFileResponse(string $path, ?string $user=null, array $headers=[]) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->get($this->url($path), [
			RequestOptions::HEADERS => $headers,
		]);
	}

	/**
	 * Perform a PROPFIND request.
	 *
	 * An empty body defaults to "allprop" behavior (see _parse_propfind.php's
	 * "if no input was parsed" fallback), which is all these tests need.
	 *
	 * @param string $path eg. from homeFile()/homeCollection()
	 * @param int|string $depth 0, 1 or "infinity"
	 * @param ?string $user account_lid to authenticate as, default organizer/EGW_USER
	 * @param ?string $body =null raw PROPFIND XML request body, default empty/allprop
	 * @return ResponseInterface
	 */
	protected function propfind(string $path, $depth=1, ?string $user=null, ?string $body=null) : ResponseInterface
	{
		$user = $user ?: $this->organizerLid();
		return $this->getClient($user)->request('PROPFIND', $this->url($path), [
			RequestOptions::HEADERS => [
				'Depth' => (string)$depth,
				'Content-Type' => 'application/xml; charset=utf-8',
			],
			RequestOptions::BODY => $body ?? '',
		]);
	}

	/**
	 * Parse a 207 Multi-Status PROPFIND/REPORT response body into per-resource results.
	 *
	 * @param ResponseInterface $response
	 * @return array<int,array{href:string,status:string,props:array<string,string>}> one entry
	 *  per D:response, "props" keyed by unprefixed property name (e.g. "resourcetype",
	 *  "getcontentlength") from the first (i.e. successful, HTTP/1.1 200 OK) propstat only
	 */
	protected function multistatusResponses(ResponseInterface $response) : array
	{
		$xml = new \SimpleXMLElement((string)$response->getBody());
		$xml->registerXPathNamespace('D', 'DAV:');

		$result = [];
		foreach ($xml->xpath('//D:response') as $node)
		{
			$dav = $node->children('DAV:');
			$href = $this->hrefSuffix((string)$dav->href);
			$status = (string)$dav->status;
			$props = [];
			foreach ($node->xpath('.//D:propstat') as $propstat)
			{
				$propstat_dav = $propstat->children('DAV:');
				if (strpos((string)$propstat_dav->status, '200') === false)
				{
					continue;	// only collect properties that were actually found
				}
				foreach ($propstat_dav->prop->children('DAV:') as $prop)
				{
					$props[$prop->getName()] = trim((string)$prop);
				}
				break;	// first successful propstat only, matching the docblock
			}
			$result[] = [
				'href'   => $href,
				'status' => $status,
				'props'  => $props,
			];
		}
		return $result;
	}

	/**
	 * Find one multistatusResponses() entry by its href suffix (e.g. the file/collection name).
	 *
	 * @param array $responses from multistatusResponses()
	 * @param string $href_suffix e.g. from homeFile()/homeCollection()
	 * @return array|null
	 */
	protected function findMultistatusResponse(array $responses, string $href_suffix) : ?array
	{
		foreach ($responses as $entry)
		{
			if (rtrim($entry['href'], '/') === rtrim($href_suffix, '/'))
			{
				return $entry;
			}
		}
		return null;
	}
}
