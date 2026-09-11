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
}
