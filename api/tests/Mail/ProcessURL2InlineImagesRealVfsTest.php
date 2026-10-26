<?php
/**
 * EGroupware Api: Test Mail::processURL2InlineImages()'s actual real-VFS-read success path
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../AppTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mailer;
use EGroupware\Api\Vfs;

/**
 * Ticket #126501 follow-up (2026-10-26, Borco Höhns GmbH, still reproducing on 26.10.20261021,
 * which already contains the #126501 fix): ProcessURL2InlineImagesWebdavPrefixTest's own docblock
 * explicitly says "the actual 'real VFS read succeeds' path is NOT covered" - every existing test
 * for this ticket uses a FAKE tracker id (999999), which never actually gets far enough to read a
 * real file (VFS's own app-entry ACL check requires the entry to genuinely exist and be owned by
 * the current user - a bare numeric id with no backing record is correctly refused). This creates
 * a REAL tracker entry (same `tracker_bo`-based setup AjaxActionTest.php/NotificationBodyTest.php
 * already use) to find out whether the "prefix matches AND the file is real" success path - the
 * one path untested since the #126501 fix landed - actually works.
 */
class ProcessURL2InlineImagesRealVfsTest extends Api\AppTest
{
	private \tracker_bo $bo;
	private $tr_id;
	private string $vfsPath;

	protected function setUp() : void
	{
		parent::setUp();
		$this->bo = new \tracker_bo();
	}

	protected function tearDown() : void
	{
		if (isset($this->vfsPath) && Vfs::file_exists($this->vfsPath))
		{
			Vfs::remove($this->vfsPath);
		}
		if ($this->tr_id)
		{
			$this->bo->delete($this->tr_id);
		}
		parent::tearDown();
	}

	public function testRealWebdavImageInsideAllowedPrefixGetsEmbeddedAsCid()
	{
		// a real, owned tracker entry - VFS's own app-entry ACL requires one to exist
		$this->bo->data = [
			'tr_summary' => 'ProcessURL2InlineImagesRealVfsTest '.uniqid(),
			'tr_status' => \tracker_bo::STATUS_OPEN,
			'tr_creator' => $GLOBALS['egw_info']['user']['account_id'],
		];
		$this->bo->save();
		$this->tr_id = $this->bo->data['tr_id'];
		$this->assertIsNumeric($this->tr_id, 'test setup: could not create a real tracker entry');

		// 1x1 transparent PNG
		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
		$filename = 'test-'.uniqid().'.png';
		$this->vfsPath = '/apps/tracker/'.$this->tr_id.'/'.$filename;

		Vfs::load_wrapper('vfs');
		$written = @file_put_contents(Vfs::PREFIX.$this->vfsPath, $png);
		$this->assertNotFalse($written, 'test setup: could not write the sample image into vfs://default - '.
			'a genuinely mounted, writable VFS is required for this test to say anything meaningful');

		$mailer = new Mailer(false);
		$html = '<img src="/webdav.php'.$this->vfsPath.'">';

		$result = Mail::processURL2InlineImages($mailer, $html, null, '/apps/tracker/'.$this->tr_id.'/');

		$this->assertMatchesRegularExpression('#^<img src="cid:[0-9a-f]{32}">$#', $html,
			'a webdav.php url for a file that genuinely exists, inside the allowed prefix, must be '.
			'rewritten to a cid: reference - ticket #126501\'s whole point');
		$this->assertCount(1, $result);
		$this->assertNotSame('', $result[0]['file'], 'a real temp file must have been written for embedding');
		$this->assertSame('image/png', $result[0]['type']);
		@unlink($result[0]['file']);
	}

	/**
	 * Ticket #126501's own real-world trigger (found live 2026-10-26, Borco Höhns GmbH, still
	 * reproducing on a release that already had the original fix): tracker's "Ticket per Mail"
	 * mail-polling job (tracker_mailhandler.inc.php) - and any other notification triggered from a
	 * genuine CLI/cron context - runs with NO live request at all, so $_SERVER['HTTP_HOST'] is
	 * simply unset.
	 *
	 * The webdav.php url actually embedded in a real notification body is always fully qualified
	 * (Et2HtmlArea's own upload endpoint builds it via Api\Framework::link() -> Session::link(),
	 * which prepends the 'webserver_url' Setup config) - a root-relative url (as used by the
	 * sibling test above) would trivially pass the $url_host === null short-circuit in
	 * $same_origin regardless of this fix, so it can't tell the two code paths apart the way this
	 * host mismatch actually manifested live.
	 *
	 * Deliberately sets 'webserver_url' only, leaving 'hostname'/$_SERVER['HTTP_HOST'] at whatever
	 * this environment's own values happen to be (confirmed live, ralf: these are two independent
	 * Setup config fields that routinely disagree on an on-premise install - 'webserver_url'
	 * defaults to the relative "/egroupware" there, since the container has no way to know its own
	 * public domain at install time, and EGroupware's own support team routinely tells customers to
	 * change specifically THAT field to a full absolute URL, eg. so notification email links are
	 * clickable, without necessarily ever touching the separate, auto-captured 'hostname' field to
	 * match) - proves the fix reads 'webserver_url', not a Setup-hostname fallback that could easily
	 * disagree with it.
	 */
	public function testStillEmbedsWithoutAnyHttpHostLikeARealCronJobWouldRun()
	{
		$this->bo->data = [
			'tr_summary' => 'ProcessURL2InlineImagesRealVfsTest (no HTTP_HOST) '.uniqid(),
			'tr_status' => \tracker_bo::STATUS_OPEN,
			'tr_creator' => $GLOBALS['egw_info']['user']['account_id'],
		];
		$this->bo->save();
		$this->tr_id = $this->bo->data['tr_id'];

		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
		$filename = 'test-'.uniqid().'.png';
		$this->vfsPath = '/apps/tracker/'.$this->tr_id.'/'.$filename;

		Vfs::load_wrapper('vfs');
		$written = @file_put_contents(Vfs::PREFIX.$this->vfsPath, $png);
		$this->assertNotFalse($written, 'test setup: could not write the sample image into vfs://default');

		$html = '<img src="https://borco-onprem.example.invalid/egroupware/webdav.php'.$this->vfsPath.'">';

		$original_webserver_url = $GLOBALS['egw_info']['server']['webserver_url'] ?? null;
		$original_http_host = $_SERVER['HTTP_HOST'] ?? null;
		$GLOBALS['egw_info']['server']['webserver_url'] = 'https://borco-onprem.example.invalid/egroupware';
		unset($_SERVER['HTTP_HOST']);
		try
		{
			$result = Mail::processURL2InlineImages($mailer = new Mailer(false), $html, null,
				'/apps/tracker/'.$this->tr_id.'/');
		}
		finally
		{
			$GLOBALS['egw_info']['server']['webserver_url'] = $original_webserver_url;
			if ($original_http_host !== null)
			{
				$_SERVER['HTTP_HOST'] = $original_http_host;
			}
		}

		$this->assertMatchesRegularExpression('#^<img src="cid:[0-9a-f]{32}">$#', $html,
			'must still embed when there is no live request to derive the own host from, using the '.
			'webserver_url config instead - ticket #126501\'s actual real-world trigger (an '.
			'async/cron-run notification on an on-premise install with webserver_url set per the '.
			'usual support recommendation)');
		@unlink($result[0]['file'] ?? '');
	}

	/**
	 * The config-field-mismatch half of the above, isolated: a 'hostname' Setup field that still
	 * disagrees with (or is entirely unset/'localhost' relative to) 'webserver_url' must not affect
	 * the outcome at all - only 'webserver_url' may ever decide $same_origin here, since it is the
	 * one PROVABLY used to build the original url (Session::link()).
	 */
	public function testIgnoresAMismatchedHostnameSetupField()
	{
		$this->bo->data = [
			'tr_summary' => 'ProcessURL2InlineImagesRealVfsTest (mismatched hostname) '.uniqid(),
			'tr_status' => \tracker_bo::STATUS_OPEN,
			'tr_creator' => $GLOBALS['egw_info']['user']['account_id'],
		];
		$this->bo->save();
		$this->tr_id = $this->bo->data['tr_id'];

		$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
		$filename = 'test-'.uniqid().'.png';
		$this->vfsPath = '/apps/tracker/'.$this->tr_id.'/'.$filename;

		Vfs::load_wrapper('vfs');
		$written = @file_put_contents(Vfs::PREFIX.$this->vfsPath, $png);
		$this->assertNotFalse($written, 'test setup: could not write the sample image into vfs://default');

		$html = '<img src="https://borco-onprem.example.invalid/egroupware/webdav.php'.$this->vfsPath.'">';

		$original_webserver_url = $GLOBALS['egw_info']['server']['webserver_url'] ?? null;
		$original_hostname = $GLOBALS['egw_info']['server']['hostname'] ?? null;
		$GLOBALS['egw_info']['server']['webserver_url'] = 'https://borco-onprem.example.invalid/egroupware';
		// an internal Docker-internal hostname an on-premise Setup run might have auto-captured -
		// deliberately NOT matching the public domain above at all
		$GLOBALS['egw_info']['server']['hostname'] = 'egroupware-docker.internal';
		try
		{
			$result = Mail::processURL2InlineImages($mailer = new Mailer(false), $html, null,
				'/apps/tracker/'.$this->tr_id.'/');
		}
		finally
		{
			$GLOBALS['egw_info']['server']['webserver_url'] = $original_webserver_url;
			$GLOBALS['egw_info']['server']['hostname'] = $original_hostname;
		}

		$this->assertMatchesRegularExpression('#^<img src="cid:[0-9a-f]{32}">$#', $html,
			'a mismatched/unrelated "hostname" Setup field must never prevent embedding - only '.
			'webserver_url may decide this');
		@unlink($result[0]['file'] ?? '');
	}
}
