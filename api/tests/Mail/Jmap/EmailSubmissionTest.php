<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\EmailSubmission, the real-JMAP-over-HTTP send object
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Base;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-2 entry: the real-JMAP-facing layer had
 * essentially zero tests. Both set() and submit() only ever call $this->jmap->call() - never a
 * real HTTP connection - so a minimal fake session (a Base anonymous subclass) is enough to test
 * the request-shape-building/response-unwrapping logic in isolation, same approach as EmailTest.php.
 */
class EmailSubmissionTest extends \PHPUnit\Framework\TestCase
{
	private function fakeSession(string $accountId, callable $callResponder) : Base
	{
		return new class($accountId, $callResponder) extends Base {
			public array $calls = [];
			public function __construct(public string $accountId, private $callResponder) {}
			public function call(string $method, array $args) : array
			{
				$this->calls[] = [$method, $args];
				return ($this->callResponder)($method, $args, count($this->calls));
			}
		};
	}

	// --- set() ---

	public function testSetOmitsEveryOptionalArgumentWhenGivenNothing()
	{
		$session = $this->fakeSession('acc1', fn() => []);
		$es = new EmailSubmission($session);

		$es->set();

		$this->assertSame(['EmailSubmission/set', ['accountId' => 'acc1']], $session->calls[0],
			"empty create/update/destroy and null onSuccess* must all be filtered out, matching Type::set()'s own convention");
	}

	public function testSetIncludesOnSuccessUpdateEmailAndOnSuccessDestroyEmailAsTopLevelArgsNotPerObjectProperties()
	{
		$session = $this->fakeSession('acc1', fn() => []);
		$es = new EmailSubmission($session);

		$es->set(['s1' => ['emailId' => 'e1', 'identityId' => 'i1']], [], [],
			['#s1' => ['mailboxIds/DRAFTS' => null]], ['#s2']);

		$args = $session->calls[0][1];
		$this->assertSame(['#s1' => ['mailboxIds/DRAFTS' => null]], $args['onSuccessUpdateEmail']);
		$this->assertSame(['#s2'], $args['onSuccessDestroyEmail']);
		$this->assertArrayNotHasKey('update', $args);
		$this->assertArrayNotHasKey('destroy', $args);
	}

	// --- submit() ---

	public function testSubmitBuildsTheMinimalCreateShapeWithoutAnEnvelopeWhenNoneGiven()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);

		$es->submit('email1', 'ident1');

		$create = $session->calls[0][1]['create']['s1'];
		$this->assertSame(['emailId' => 'email1', 'identityId' => 'ident1'], $create);
		$this->assertArrayNotHasKey('onSuccessUpdateEmail', $session->calls[0][1]);
		$this->assertArrayNotHasKey('onSuccessDestroyEmail', $session->calls[0][1]);
	}

	public function testSubmitIncludesTheEnvelopeWhenGiven()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);
		$envelope = ['mailFrom' => ['email' => 'a@x.com'], 'rcptTo' => [['email' => 'b@x.com']]];

		$es->submit('email1', 'ident1', $envelope);

		$this->assertSame($envelope, $session->calls[0][1]['create']['s1']['envelope']);
	}

	public function testSubmitWrapsTheSentEmailPatchUnderItsOwnCreationIdBackReference()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);
		$patch = ['mailboxIds/DRAFTS' => null, 'mailboxIds/SENT' => true];

		$es->submit('email1', 'ident1', null, $patch);

		$this->assertSame(['#s1' => $patch], $session->calls[0][1]['onSuccessUpdateEmail']);
		$this->assertArrayNotHasKey('onSuccessDestroyEmail', $session->calls[0][1]);
	}

	public function testSubmitRequestsDestroyingTheSentEmailViaItsOwnCreationIdBackReference()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);

		$es->submit('email1', 'ident1', null, null, true);

		$this->assertSame(['#s1'], $session->calls[0][1]['onSuccessDestroyEmail']);
		$this->assertArrayNotHasKey('onSuccessUpdateEmail', $session->calls[0][1]);
	}

	public function testSubmitReturnsTheCreatedEmailSubmissionObjectOnSuccess()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1', 'sendAt' => '2026-09-09T00:00:00Z']]]);
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['id' => 'sub1', 'sendAt' => '2026-09-09T00:00:00Z'], $result);
	}

	public function testSubmitReturnsTheNotCreatedSetErrorWrappedWhenCreationFails()
	{
		$session = $this->fakeSession('acc1', fn() => ['notCreated' => ['s1' => ['type' => 'invalidProperties']]]);
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['notCreated' => ['type' => 'invalidProperties']], $result);
	}

	public function testSubmitReturnsANullNotCreatedWhenTheResponseHasNeitherCreatedNorNotCreatedForThisId()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => [], 'notCreated' => []]);
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['notCreated' => null], $result);
	}

	// --- submit()'s post-success destroy ($destroySubmission) ---

	public function testSubmitDestroysTheJustCreatedSubmissionBySelfByDefault()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);

		$es->submit('email1', 'ident1');

		$this->assertCount(2, $session->calls, 'create + self-cleanup destroy');
		$this->assertSame(['sub1'], $session->calls[1][1]['destroy']);
	}

	public function testSubmitSkipsTheSelfCleanupDestroyWhenDestroySubmissionIsFalse()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['s1' => ['id' => 'sub1']]]);
		$es = new EmailSubmission($session);

		$es->submit('email1', 'ident1', null, null, false, false);

		$this->assertCount(1, $session->calls, 'no destroy call at all');
	}

	public function testSubmitSwallowsAFailureToDestroyTheJustCreatedSubmission()
	{
		$session = $this->fakeSession('acc1', function($method, $args, $n) {
			if ($n === 1) return ['created' => ['s1' => ['id' => 'sub1']]];
			throw new \RuntimeException('destroy failed');
		});
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['id' => 'sub1'], $result, 'the successful submission result must still be returned');
	}

	// --- submit()'s overQuota self-heal retry ---

	public function testSubmitPurgesStaleSubmissionsAndRetriesOnceOnOverQuota()
	{
		$session = $this->fakeSession('acc1', function($method, $args, $n) {
			return match([$method, $n]) {
				['EmailSubmission/set', 1] => ['notCreated' => ['s1' => ['type' => 'overQuota']]],
				['EmailSubmission/query', 2] => ['ids' => ['old1']],
				['EmailSubmission/get', 3] => ['list' => [['id' => 'old1', 'sendAt' => '2000-01-01T00:00:00Z']]],
				['EmailSubmission/set', 4] => ['destroyed' => ['old1']],
				// the whole (one-object) page was stale, so destroyStaleSubmissions() re-queries
				// once more to check for further pages before giving up
				['EmailSubmission/query', 5] => ['ids' => []],
				['EmailSubmission/set', 6] => ['created' => ['s1' => ['id' => 'sub1']]],
				['EmailSubmission/set', 7] => ['destroyed' => ['sub1']],	// submit()'s own self-cleanup
				default => throw new \RuntimeException("unexpected call #$n: $method"),
			};
		});
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['id' => 'sub1'], $result, 'must succeed after the self-heal retry');
		$this->assertSame(['old1'], $session->calls[3][1]['destroy'], 'must have purged the stale backlog');
		$this->assertSame(['emailId' => 'email1', 'identityId' => 'ident1'], $session->calls[5][1]['create']['s1'],
			'the retry must resend the exact same create');
	}

	public function testSubmitReturnsTheOverQuotaErrorWhenItPersistsAfterTheRetry()
	{
		$session = $this->fakeSession('acc1', function($method, $args, $n) {
			return match([$method, $n]) {
				['EmailSubmission/set', 1] => ['notCreated' => ['s1' => ['type' => 'overQuota']]],
				['EmailSubmission/query', 2] => ['ids' => []],	// nothing stale enough to purge
				['EmailSubmission/set', 3] => ['notCreated' => ['s1' => ['type' => 'overQuota']]],
				default => throw new \RuntimeException("unexpected call #$n: $method"),
			};
		});
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['notCreated' => ['type' => 'overQuota']], $result);
		$this->assertCount(3, $session->calls, 'must give up after exactly one retry, no self-cleanup destroy on failure');
	}

	public function testSubmitDoesNotRetryForAnyOtherNotCreatedErrorType()
	{
		$session = $this->fakeSession('acc1', fn() => ['notCreated' => ['s1' => ['type' => 'invalidProperties']]]);
		$es = new EmailSubmission($session);

		$result = $es->submit('email1', 'ident1');

		$this->assertSame(['notCreated' => ['type' => 'invalidProperties']], $result);
		$this->assertCount(1, $session->calls);
	}

	// --- destroyStaleSubmissions() ---

	public function testDestroyStaleSubmissionsDestroysOnlyTheOnesOlderThanTheThreshold()
	{
		$old = gmdate('Y-m-d\TH:i:s\Z', time() - 3600);
		$recent = gmdate('Y-m-d\TH:i:s\Z', time() - 1);
		$session = $this->fakeSession('acc1', function($method, $args, $n) use ($old, $recent) {
			return match([$method, $n]) {
				['EmailSubmission/query', 1] => ['ids' => ['old1', 'old2', 'recent1']],
				['EmailSubmission/get', 2] => ['list' => [
					['id' => 'old1', 'sendAt' => $old],
					['id' => 'old2', 'sendAt' => $old],
					['id' => 'recent1', 'sendAt' => $recent],
				]],
				['EmailSubmission/set', 3] => ['destroyed' => ['old1', 'old2']],
				default => throw new \RuntimeException("unexpected call #$n: $method"),
			};
		});
		$es = new EmailSubmission($session);

		$destroyed = $es->destroyStaleSubmissions();

		$this->assertSame(2, $destroyed);
		$this->assertEqualsCanonicalizing(['old1', 'old2'], $session->calls[2][1]['destroy']);
	}

	public function testDestroyStaleSubmissionsStopsAsSoonAsNoneAreStale()
	{
		$session = $this->fakeSession('acc1', fn() => ['ids' => []]);
		$es = new EmailSubmission($session);

		$destroyed = $es->destroyStaleSubmissions();

		$this->assertSame(0, $destroyed);
		$this->assertCount(1, $session->calls, 'must not call get()/set() when query() already found nothing');
	}

	public function testDestroyStaleSubmissionsTreatsAMissingSendAtAsNotStale()
	{
		$session = $this->fakeSession('acc1', function($method, $args, $n) {
			return match([$method, $n]) {
				['EmailSubmission/query', 1] => ['ids' => ['nodate1']],
				['EmailSubmission/get', 2] => ['list' => [['id' => 'nodate1']]],
				default => throw new \RuntimeException("unexpected call #$n: $method"),
			};
		});
		$es = new EmailSubmission($session);

		$destroyed = $es->destroyStaleSubmissions();

		$this->assertSame(0, $destroyed, 'an object with no sendAt at all must never be purged');
	}
}
