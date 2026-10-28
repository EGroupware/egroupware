<?php
/**
 * EGroupware Notifications: get_backend_data() email/popup_data merge regression test
 *
 * @link http://www.egroupware.org
 * @package notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Ticket #126501: send()'s per-backend loop used to REPLACE $this->popup_data with just
 * ['reply_to' => ...] (or nothing at all) for the 'email' backend, instead of merging the two.
 * $this->popup_data is set identically by every Api\Storage\Tracking subclass (tracker,
 * projectmanager, infolog, calendar, resources, contacts, timesheet) via set_popupdata(), and
 * carries the notified entity's own 'appname'/'id' - the exact data notifications_email::send()
 * needs to compute Api\Mail::processURL2InlineImages()'s $allowed_webdav_prefix security check
 * (which app/entry's own VFS attachments may be embedded inline in the notification body).
 * Losing it meant inline image embedding silently never happened for ANY email notification
 * through this path, regardless of how correctly $allowed_webdav_prefix's own host-matching logic
 * behaved.
 */
class BackendDataEmailMergeTest extends LoggedInTest
{
	private function getBackendData(notifications $n, string $backend) : ?array
	{
		$method = new ReflectionMethod(notifications::class, 'get_backend_data');
		$method->setAccessible(true);
		return $method->invoke($n, $backend);
	}

	private function setPopupData(notifications $n, ?array $data) : void
	{
		$prop = new ReflectionProperty(notifications::class, 'popup_data');
		$prop->setAccessible(true);
		$prop->setValue($n, $data);
	}

	private function setReplyTo(notifications $n, $reply_to) : void
	{
		$prop = new ReflectionProperty(notifications::class, 'reply_to');
		$prop->setAccessible(true);
		$prop->setValue($n, $reply_to);
	}

	public function testEmailBackendKeepsAppnameAndIdWithoutReplyTo()
	{
		$n = new notifications();
		$n->set_popupdata('tracker', ['id' => 126501, 'some' => 'link-data'], 126501);

		$data = $this->getBackendData($n, 'email');

		$this->assertSame('tracker', $data['appname'] ?? null,
			"'appname' set via set_popupdata() must survive into the email backend's own data");
		$this->assertSame(126501, $data['id'] ?? null,
			"'id' set via set_popupdata() must survive into the email backend's own data");
		$this->assertArrayNotHasKey('reply_to', $data, "no reply_to was set, so none should appear");
	}

	public function testEmailBackendMergesAppnameIdAndReplyTo()
	{
		$n = new notifications();
		$n->set_popupdata('projectmanager', ['id' => 42], 42);
		$this->setReplyTo($n, 'someone@example.org');

		$data = $this->getBackendData($n, 'email');

		$this->assertSame('projectmanager', $data['appname'] ?? null);
		$this->assertSame(42, $data['id'] ?? null);
		$this->assertSame('someone@example.org', $data['reply_to'] ?? null);
	}

	public function testEmailBackendStillWorksWithOnlyReplyTo()
	{
		// no set_popupdata() call at all (eg. a notification type that never calls it) - must not
		// start throwing or silently drop reply_to just because popup_data is still its default
		$n = new notifications();
		$this->setReplyTo($n, 'someone@example.org');

		$data = $this->getBackendData($n, 'email');

		$this->assertSame('someone@example.org', $data['reply_to'] ?? null);
		$this->assertArrayNotHasKey('appname', $data);
	}

	public function testEmailBackendReturnsNullWithNeitherSet()
	{
		$n = new notifications();

		$this->assertNull($this->getBackendData($n, 'email'),
			"with nothing set at all, the email backend must get null, exactly as before this fix");
	}

	public function testPopupBackendIsUnaffectedByThisChange()
	{
		$n = new notifications();
		$n->set_popupdata('tracker', ['id' => 126501], 126501);
		$this->setReplyTo($n, 'someone@example.org');

		$data = $this->getBackendData($n, 'popup');

		$this->assertSame('tracker', $data['appname'] ?? null);
		// popup never mixes in reply_to - that's an email-only concept
		$this->assertArrayNotHasKey('reply_to', $data);
	}

	public function testUnknownBackendGetsNull()
	{
		$n = new notifications();
		$n->set_popupdata('tracker', ['id' => 126501], 126501);
		$this->setReplyTo($n, 'someone@example.org');

		$this->assertNull($this->getBackendData($n, 'jpopup'),
			"no backend-specific data has ever been defined for jpopup/winpopup/...");
	}
}
