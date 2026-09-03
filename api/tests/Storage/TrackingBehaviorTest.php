<?php
/**
 * Tests for Api\Storage\Tracking (beyond sanitize_custom_message, see TrackingTest.php)
 *
 * Part of the Api\Storage test-coverage project, see doc/ai/projects/storage-test-coverage.md.
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Storage;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestTracking.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;

/**
 * Records every send_notification() call instead of actually sending anything, so
 * do_notifications()'s ORCHESTRATION logic (creator/group/assigned/copy resolution,
 * assignment_changed computation, email dedup) can be tested without needing real
 * preferences or a live mail backend.
 */
class TestTrackingNotifyRecorder extends TestTracking
{
	/**
	 * @var array of array('email'=>...,'user_or_lang'=>...,'check'=>...,'assignment_changed'=>...)
	 */
	public $calls = array();

	/**
	 * If true (default) every recorded call counts as "successfully notified" (like a real send)
	 * @var bool
	 */
	public $succeed = true;

	public function send_notification($data, $old, $email, $user_or_lang, $check = null, $assignment_changed = true, $deleted = null)
	{
		$this->calls[] = array(
			'email' => $email,
			'user_or_lang' => $user_or_lang,
			'check' => $check,
			'assignment_changed' => $assignment_changed,
		);
		return $this->succeed;
	}
}

/**
 * Minimal stand-in for the real `notifications` class (api/src/../notifications.inc.php,
 * outside the Api\Storage namespace) - Tracking::send_notification() does `new $class()` where
 * $class is Tracking::$notification_class, so any class with these methods works. Used to prove
 * the real (non-overridden) send_notification() -> notification-object wiring, for the one
 * scenario that doesn't need real user preferences: notification "copy" addresses, whose
 * non-numeric $user_or_lang path skips the preference check entirely (see send_notification()).
 */
class TrackingMockNotification
{
	/** @var array recorded set_*() calls, keyed by method name -> last args */
	public static $log = array();

	public function __call($name, $args)
	{
		self::$log[$name] = $args;
	}

	public function send()
	{
		self::$log['send'] = true;
	}

	public function errors($bool = false)
	{
		return array();
	}
}

class TrackingBehaviorTest extends LoggedInTest
{
	const APP = 'test';

	/** @var TestTracking */
	private $tracking;

	/** @var array of [app, record_id] pairs to clean from egw_history_log in tearDown() */
	private $history_cleanup = array();

	protected function setUp(): void
	{
		parent::setUp();
		$this->tracking = new TestTracking();
		$this->tracking->field2history = array(
			'title' => 'title',
			'desc' => 'desc',
			'when' => 'when',
			'tags' => 'tags',
			'#cf' => '#cf',
			'participants' => array('uid', 'status'),
		);
	}

	public function tearDown(): void
	{
		foreach ($this->history_cleanup as $record_id)
		{
			(new History(self::APP))->delete($record_id);
		}
		$this->history_cleanup = array();
		parent::tearDown();
	}

	private function track_id()
	{
		// distinctive, unlikely to collide with real app data or a concurrent test run
		$id = 900000000 + random_int(0, 99999999);
		$this->history_cleanup[] = $id;
		return $id;
	}

	// ---- changed_fields() -----------------------------------------------------------------

	/**
	 * Behavior: changed_fields($data, null) treats a null $old as "brand new entry" and
	 * reports every key in $data as changed, without consulting field2history at all.
	 * Pass criteria: return value === array_keys($data).
	 */
	public function testChangedFieldsNullOldReturnsAllKeys()
	{
		$data = array('title' => 'Foo', 'unrelated_key' => 'bar');
		$this->assertSame(array_keys($data), $this->tracking->changed_fields($data, null));
	}

	/**
	 * Behavior: values that are "empty" (per PHP empty()) on both sides are treated as equal,
	 * even if they're not identical (e.g. null vs '' vs 0 vs false) - "treat all sorts of empty
	 * equally" per the source comment.
	 */
	public function testChangedFieldsEmptyEqualsEmptySkipped()
	{
		$old = array('title' => null, 'desc' => 0);
		$data = array('title' => '', 'desc' => false);
		$this->assertSame(array(), $this->tracking->changed_fields($data, $old),
			'null/""/0/false should all be treated as equally "empty" and not reported as changed');
	}

	/**
	 * Behavior: a genuine content change on a non-empty field IS reported.
	 */
	public function testChangedFieldsSimpleScalarChange()
	{
		$old = array('title' => 'Old title');
		$data = array('title' => 'New title');
		$this->assertSame(array('title'), $this->tracking->changed_fields($data, $old));
	}

	/**
	 * Behavior: two DateTime values are compared with 1-second precision via
	 * Api\DateTime::to(...,DATABASE) - sub-second differences must NOT count as a change,
	 * a difference of a full second (or more) must.
	 */
	public function testChangedFieldsDateTimeOneSecondPrecision()
	{
		$base = new Api\DateTime('2026-01-01 12:00:00.100000');
		$almost_same = new Api\DateTime('2026-01-01 12:00:00.900000');	// same second, different microseconds
		$one_sec_later = new Api\DateTime('2026-01-01 12:00:01.100000');

		$field2history = array('when' => 'when');
		$this->tracking->field2history = $field2history;

		$this->assertSame(array(),
			$this->tracking->changed_fields(array('when' => $almost_same), array('when' => $base)),
			'sub-second-only difference must not be reported as changed');
		$this->assertSame(array('when'),
			$this->tracking->changed_fields(array('when' => $one_sec_later), array('when' => $base)),
			'a full second of difference must be reported as changed');
	}

	/**
	 * Behavior: array-valued fields (e.g. multiselects) are compared ignoring element order -
	 * same elements, different order, must NOT be reported as changed. A comma-separated string
	 * is also accepted and exploded for comparison against an array value.
	 */
	public function testChangedFieldsArrayOrderInsensitive()
	{
		$this->tracking->field2history = array('tags' => 'tags');

		$this->assertSame(array(),
			$this->tracking->changed_fields(array('tags' => array('b', 'a')), array('tags' => array('a', 'b'))),
			'same elements in different order must not count as changed');
		$this->assertSame(array(),
			$this->tracking->changed_fields(array('tags' => array('a', 'b')), array('tags' => 'b,a')),
			'a comma-separated string must compare equal to the equivalent array, order-insensitively');
		$this->assertSame(array('tags'),
			$this->tracking->changed_fields(array('tags' => array('a', 'c')), array('tags' => array('a', 'b'))),
			'genuinely different array contents must be reported as changed');
	}

	/**
	 * Behavior: a difference consisting only of \r (CRLF vs LF line endings) is ignored -
	 * "change only in CR (e.g. different OS)".
	 */
	public function testChangedFieldsCrlfOnlyDifferenceIgnored()
	{
		$this->tracking->field2history = array('desc' => 'desc');

		$this->assertSame(array(),
			$this->tracking->changed_fields(array('desc' => "line1\r\nline2"), array('desc' => "line1\nline2")),
			'a difference that is only \r vs no \r must not be reported as changed');
		$this->assertSame(array('desc'),
			$this->tracking->changed_fields(array('desc' => "line1\r\nline2X"), array('desc' => "line1\nline2")),
			'a real content change alongside CRLF differences must still be reported');
	}

	/**
	 * Behavior: for a field marked as a 1:N relation in field2history (array value = column
	 * list), rows are compacted into delimited strings first, then diffed as unordered sets -
	 * so re-ordering the SAME rows must not count as changed, but adding/removing/altering a
	 * row must.
	 */
	public function testChangedFields1NRelationCompaction()
	{
		$this->tracking->field2history = array('participants' => array('uid', 'status'));

		$same_rows_reordered_old = array(
			array('uid' => 1, 'status' => 'A'),
			array('uid' => 2, 'status' => 'U'),
		);
		$same_rows_reordered_new = array(
			array('uid' => 2, 'status' => 'U'),
			array('uid' => 1, 'status' => 'A'),
		);
		$this->assertSame(array(),
			$this->tracking->changed_fields(
				array('participants' => $same_rows_reordered_new),
				array('participants' => $same_rows_reordered_old)),
			'same 1:N rows in a different order must not be reported as changed');

		$status_changed_new = array(
			array('uid' => 1, 'status' => 'A'),
			array('uid' => 2, 'status' => 'R'),	// status changed U -> R
		);
		$this->assertSame(array('participants'),
			$this->tracking->changed_fields(
				array('participants' => $status_changed_new),
				array('participants' => $same_rows_reordered_old)),
			'a changed column value within a 1:N row must be reported as changed');
	}

	/**
	 * Behavior: a "##"-prefixed key in $data (double-hash, NOT declared in field2history) is
	 * always reported as changed if its value differs (strict !==) from $old - this is the
	 * vCard/iCal X-attribute passthrough path, checked in a second loop over $data directly.
	 */
	public function testChangedFieldsDoubleHashKeyAlwaysCheckedRegardlessOfFieldToHistory()
	{
		// intentionally NOT in field2history
		$this->assertSame(array('##xprop'),
			$this->tracking->changed_fields(array('##xprop' => 'new'), array('##xprop' => 'old')),
			'a changed ##-prefixed key must be reported even though it is not in field2history');
		$this->assertSame(array(),
			$this->tracking->changed_fields(array('##xprop' => 'same'), array('##xprop' => 'same')),
			'an unchanged ##-prefixed key must not be reported');
	}

	/**
	 * Behavior: a "#"-prefixed (single-hash, custom field) key that is declared in
	 * field2history but simply absent from $data (not merely empty) is treated as unchanged,
	 * even if $old had a real value - "not set customfields are not stored, therefore not
	 * changed". This is a real, easy-to-miss asymmetry: explicitly clearing a CF (data['#cf']='')
	 * IS caught by the empty-equals-empty rule when old was also empty, but REMOVING the key
	 * entirely from $data is unconditionally skipped regardless of what $old held.
	 */
	public function testChangedFieldsUnsetCustomFieldNotReportedEvenIfOldHadValue()
	{
		$old = array('#cf' => 'previous value');
		$data = array();	// '#cf' key entirely absent, not just empty
		$this->assertSame(array(),
			$this->tracking->changed_fields($data, $old),
			'a #-prefixed field missing from $data entirely must not be reported as changed, ' .
			'even though $old had a real value');
	}

	// ---- track() / save_history() contract -------------------------------------------------

	/**
	 * Behavior: for a brand-new entry (old===null), track()'s own save_history() branch is
	 * gated by `if ($old && $this->field2history)` - since $old is null/falsy, save_history()
	 * is NEVER called, so no history rows are written by track() itself for a new entry. The
	 * caller is expected to have already written its own "created" history entry separately.
	 * Confirmed here by directly checking History::search() finds nothing after track($data,null).
	 */
	public function testTrackNewEntryNeverWritesHistory()
	{
		$id = $this->track_id();
		$data = array('t_id' => $id, 'title' => 'Brand new');

		$result = $this->tracking->track($data, null, null, null, null, true);	// skip_notification=true, irrelevant here

		$this->assertTrue((bool)$result, 'track() of a new entry should report success (no changes to log is not an error)');
		$rows = (new History(self::APP))->search(array('history_record_id' => $id));
		$this->assertSame(array(), $rows, 'track() must not write any history row for a brand-new entry ($old===null)');
	}

	/**
	 * Behavior: for an update (old given) with a real change, track() DOES call save_history(),
	 * which writes one egw_history_log row per changed field.
	 */
	public function testTrackUpdateWritesHistoryForChangedFields()
	{
		$id = $this->track_id();
		$old = array('t_id' => $id, 'title' => 'Before', 'desc' => 'same');
		$data = array('t_id' => $id, 'title' => 'After', 'desc' => 'same');

		$this->tracking->track($data, $old, null, null, null, true);	// skip_notification=true

		$rows = (new History(self::APP))->search(array('history_record_id' => $id));
		$statuses = array_column($rows, 'status');
		$this->assertSame(array('title'), $statuses,
			'only the genuinely-changed field (title) should get a history row; the unchanged desc must not');
	}

	/**
	 * Behavior: $skip_notification=true must prevent do_notifications() from running at all -
	 * confirmed via the recorder subclass, which would otherwise log a send_notification() call.
	 */
	public function testTrackSkipNotificationPreventsNotifications()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->field2history = array('title' => 'title');
		$recorder->creator_field = 'creator';
		$id = $this->track_id();
		// use the OTHER account so self-notify-suppression doesn't also explain zero calls
		$other = $this->get_another_user();
		$old = array('t_id' => $id, 'title' => 'Before', 'creator' => $other);
		$data = array('t_id' => $id, 'title' => 'After', 'creator' => $other);

		$recorder->track($data, $old, null, null, null, true);	// skip_notification=true

		$this->assertSame(array(), $recorder->calls,
			'skip_notification=true must prevent any send_notification() call');
	}

	/**
	 * Behavior: without $skip_notification, do_notifications() DOES run and (via the creator
	 * branch) calls send_notification() for the entry's creator.
	 */
	public function testTrackWithoutSkipNotificationCallsNotifications()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->field2history = array('title' => 'title');
		$recorder->creator_field = 'creator';
		$id = $this->track_id();
		$other = $this->get_another_user();
		$old = array('t_id' => $id, 'title' => 'Before', 'creator' => $other);
		$data = array('t_id' => $id, 'title' => 'After', 'creator' => $other);

		$recorder->track($data, $old, null, null, null, false);	// skip_notification=false (default)

		$this->assertNotEmpty($recorder->calls, 'creator should have been notified when not skipped');
	}

	// ---- do_notifications() orchestration (via TestTrackingNotifyRecorder) -----------------

	/**
	 * Behavior: the current user (the one making the change) is added to the "already
	 * notified" set up-front (unless notify_current_user=true), so they never get a
	 * send_notification() call for their own change - even if they're also the entry's creator.
	 */
	public function testDoNotificationsSuppressesNotifyingCurrentUserAboutOwnChange()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->creator_field = 'creator';
		$me = $GLOBALS['egw_info']['user']['account_id'];
		// do_notifications()'s self-suppression checks $this->user - normally set by track(),
		// set it directly here since we're calling do_notifications() on its own
		$recorder->user = $me;
		$data = array('t_id' => $this->track_id(), 'creator' => $me);

		$recorder->do_notifications($data, null);

		$this->assertSame(array(), $recorder->calls,
			'the current user must not be notified about their own change via the creator branch');
	}

	/**
	 * Behavior: dedup by email - if the SAME person is both creator and assignee, they must
	 * only be notified once (the second branch sees their email already in $email_sent).
	 */
	public function testDoNotificationsDedupsSamePersonAsCreatorAndAssignee()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->creator_field = 'creator';
		$recorder->assigned_field = 'assigned';
		$other = $this->get_another_user();
		$data = array('t_id' => $this->track_id(), 'creator' => $other, 'assigned' => array($other));

		$recorder->do_notifications($data, null);

		$this->assertCount(1, $recorder->calls,
			'the same person as both creator and assignee must only be notified once, not twice: ' .
			json_encode($recorder->calls));
	}

	/**
	 * Behavior: assignment_changed is computed as a symmetric-difference check between the
	 * assignee set now vs. before - true when the given user's assigned-state (assigned vs. not)
	 * differs between $data and $old, false when unchanged.
	 */
	public function testDoNotificationsAssignmentChangedFlag()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->assigned_field = 'assigned';
		$other = $this->get_another_user();

		// newly assigned: not in old, in new -> assignment_changed = true
		$recorder->calls = array();
		$recorder->do_notifications(
			array('t_id' => $this->track_id(), 'assigned' => array($other)),
			array('t_id' => $this->track_id(), 'assigned' => array())
		);
		$this->assertCount(1, $recorder->calls);
		$this->assertTrue($recorder->calls[0]['assignment_changed'], 'newly-assigned user must have assignment_changed=true');

		// unchanged assignment -> assignment_changed = false
		$recorder->calls = array();
		$recorder->do_notifications(
			array('t_id' => $this->track_id(), 'assigned' => array($other)),
			array('t_id' => $this->track_id(), 'assigned' => array($other))
		);
		$this->assertCount(1, $recorder->calls);
		$this->assertFalse($recorder->calls[0]['assignment_changed'], 'an unchanged assignee must have assignment_changed=false');

		// removed assignment: was in old, not in new -> still notified, assignment_changed = true
		$recorder->calls = array();
		$recorder->do_notifications(
			array('t_id' => $this->track_id(), 'assigned' => array()),
			array('t_id' => $this->track_id(), 'assigned' => array($other))
		);
		$this->assertCount(1, $recorder->calls, 'a removed assignee must still be notified (of the removal)');
		$this->assertTrue($recorder->calls[0]['assignment_changed'], 'a removed assignee must have assignment_changed=true');
	}

	/**
	 * Behavior: get_config('copy') addresses are only notified if they contain '@' (a real
	 * email, not eg. an account id or garbage config value).
	 */
	public function testDoNotificationsCopyAddressesFilteredByAtSign()
	{
		$recorder = new TestTrackingNotifyRecorder();
		$recorder->config['copy'] = array('valid@example.invalid', 'not-an-email', '123');
		$recorder->config['lang'] = 'en';

		$recorder->do_notifications(array('t_id' => $this->track_id()), null);

		$emails = array_column($recorder->calls, 'email');
		$this->assertSame(array('valid@example.invalid'), $emails,
			'only copy addresses containing "@" must be notified');
	}

	/**
	 * Behavior: real (non-overridden) send_notification() wiring - the "copy" notification path
	 * uses a non-numeric $user_or_lang (a language code), which skips the per-user preference
	 * check entirely, so it's the one path testable end-to-end with a mock notification class
	 * and no real preference data. Confirms the notification object actually gets the right
	 * receiver/subject wired up.
	 */
	public function testSendNotificationRealWiringForCopyAddress()
	{
		TrackingMockNotification::$log = array();
		$tracking = new TestTracking(null, TrackingMockNotification::class);
		$tracking->config['copy'] = array('copy-target@example.invalid');
		$tracking->config['lang'] = 'en';
		$id = $this->track_id();

		if (empty($GLOBALS['egw_info']['apps']['notifications']['enabled']))
		{
			$this->markTestSkipped('notifications app not enabled on this install');
		}

		$ok = $tracking->do_notifications(array('t_id' => $id), null);

		$this->assertTrue($ok, 'do_notifications() should report success: ' . json_encode($tracking->errors));
		$this->assertSame(array(array('copy-target@example.invalid')), TrackingMockNotification::$log['set_receivers'] ?? null,
			'the mock notification object should have received the copy address as its receiver');
		$this->assertArrayHasKey('set_subject', TrackingMockNotification::$log,
			'a subject should have been set on the notification object');
	}

	/**
	 * Find another (not the current) account that has a real email address set - do_notifications()
	 * silently skips notifying anyone whose id2name(...,'account_email') is empty, so a random
	 * "other" account (as CustomfieldsTest's helper picks) is not good enough here: several real
	 * accounts on a shared dev box have no email configured at all.
	 */
	private function get_another_user()
	{
		$accounts = $GLOBALS['egw']->accounts->search(array('type' => 'accounts'));
		unset($accounts[$GLOBALS['egw_info']['user']['account_id']]);
		foreach (array_keys($accounts) as $account_id)
		{
			if ($GLOBALS['egw']->accounts->id2name($account_id, 'account_email'))
			{
				return $account_id;
			}
		}
		$this->markTestSkipped('Need another user with a real email address to check notifications');
	}
}
