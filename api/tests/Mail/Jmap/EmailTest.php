<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Email, the real-JMAP-over-HTTP Email convenience wrapper
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api;
use EGroupware\Api\Jmap\Base;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-2 entry: the real-JMAP-facing layer had
 * essentially zero tests. Every method here only ever calls $this->jmap->call()/jmapCall() (or,
 * via getMailboxId(), the same on $this->jmap->mailbox) - never a real HTTP/IMAP connection - so a
 * minimal fake session (a Base anonymous subclass implementing both) is enough to test the
 * request-shape-building/response-parsing logic in isolation.
 */
class EmailTest extends \PHPUnit\Framework\TestCase
{
	private function fakeSession(string $accountId, ?callable $callResponder=null, ?callable $jmapCallResponder=null) : Base
	{
		return new class($accountId, $callResponder, $jmapCallResponder) extends Base {
			protected array $types = ['mailbox' => Mailbox::class];
			public array $calls = [];
			public array $jmapCalls = [];
			public function __construct(public string $accountId, private $callResponder, private $jmapCallResponder) {}
			public function call(string $method, array $args) : array
			{
				$this->calls[] = [$method, $args];
				return $this->callResponder ? ($this->callResponder)($method, $args, count($this->calls)) : [];
			}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				$this->jmapCalls[] = $methodCalls;
				return $this->jmapCallResponder ? ($this->jmapCallResponder)($methodCalls, count($this->jmapCalls)) : [];
			}
		};
	}

	// --- emailGet() ---

	public function testEmailGetIncludesFetchAllBodyValuesOnlyWhenTrue()
	{
		$session = $this->fakeSession('acc1', fn() => ['list' => [['id' => 'e1']]]);
		$email = new Email($session);

		$email->emailGet('e1', ['subject'], true);
		$this->assertArrayHasKey('fetchAllBodyValues', $session->calls[0][1]);

		$email->emailGet('e1', ['subject'], false);
		$this->assertArrayNotHasKey('fetchAllBodyValues', $session->calls[1][1]);
	}

	public function testEmailGetBuildsTheExpectedArgsAndReturnsTheFirstListEntry()
	{
		$session = $this->fakeSession('acc1', fn() => ['list' => [['id' => 'e1', 'subject' => 'Hi']]]);
		$email = new Email($session);

		$result = $email->emailGet('e1', ['subject', 'from']);

		$this->assertSame(['acc1', ['e1'], ['subject', 'from']],
			[$session->calls[0][1]['accountId'], $session->calls[0][1]['ids'], $session->calls[0][1]['properties']]);
		$this->assertSame(['id' => 'e1', 'subject' => 'Hi'], $result);
	}

	public function testEmailGetThrowsWhenTheMessageIsNotFound()
	{
		$session = $this->fakeSession('acc1', fn() => ['list' => []]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailGet('missing', ['subject']);
	}

	// --- emailQuery() ---

	public function testEmailQueryBuildsAnAndFilterWithInMailboxFirstPlusGivenConditions()
	{
		$session = $this->fakeSession('acc1',
			fn() => ['ids' => ['e1', 'e2']],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]
		);
		$email = new Email($session);

		$ids = $email->emailQuery('INBOX', [['notKeyword' => '$seen']], 'subject', true, 100);

		$this->assertSame(['e1', 'e2'], $ids);
		$args = $session->calls[0][1];
		$this->assertSame('Email/query', $session->calls[0][0]);
		$this->assertSame(['operator' => 'AND', 'conditions' => [['inMailbox' => 'mbx1'], ['notKeyword' => '$seen']]], $args['filter']);
		$this->assertSame([['property' => 'subject', 'isAscending' => true]], $args['sort']);
		$this->assertSame(100, $args['limit']);
	}

	public function testEmailQueryThrowsAndNeverCallsEmailQueryWhenTheFolderIsNotFound()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => [['Mailbox/query', ['ids' => []], '0']]]);
		$email = new Email($session);

		try
		{
			$email->emailQuery('Nonexistent', []);
			$this->fail('expected an Api\Exception');
		}
		catch (Api\Exception $e)
		{
			$this->assertSame("Folder 'Nonexistent' not found", $e->getMessage());
		}
		$this->assertCount(0, $session->calls, "Email/query itself must never be issued once the folder fails to resolve");
	}

	public function testEmailQueryThrowsOnAnUnexpectedResponseShape()
	{
		$session = $this->fakeSession('acc1', fn() => ['somethingElse' => true],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailQuery('INBOX', []);
	}

	// --- emailImport() ---

	public function testEmailImportPassesGivenKeywordsThrough()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['x' => ['id' => 'new-e1']]],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]);
		$email = new Email($session);

		$email->emailImport('blob1', 'INBOX', ['$seen' => true]);

		$this->assertSame(['$seen' => true], $session->calls[0][1]['emails']['x']['keywords']);
	}

	public function testEmailImportDefaultsKeywordsToAnEmptyObjectRatherThanAnEmptyArray()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['x' => ['id' => 'new-e1']]],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]);
		$email = new Email($session);

		$email->emailImport('blob1', 'INBOX', []);

		$this->assertInstanceOf(\stdClass::class, $session->calls[0][1]['emails']['x']['keywords'],
			"JMAP distinguishes an empty object {} from an empty array [] - a bare [] would serialize wrong");
	}

	public function testEmailImportBuildsMailboxIdsAndReturnsTheNewId()
	{
		$session = $this->fakeSession('acc1', fn() => ['created' => ['x' => ['id' => 'new-e1']]],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]);
		$email = new Email($session);

		$id = $email->emailImport('blob1', 'INBOX');

		$this->assertSame('new-e1', $id);
		$this->assertSame(['mbx1' => true], $session->calls[0][1]['emails']['x']['mailboxIds']);
		$this->assertSame('blob1', $session->calls[0][1]['emails']['x']['blobId']);
	}

	public function testEmailImportThrowsAndNeverCallsEmailImportWhenTheFolderIsNotFound()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => [['Mailbox/query', ['ids' => []], '0']]]);
		$email = new Email($session);

		try
		{
			$email->emailImport('blob1', 'Nonexistent');
			$this->fail('expected an Api\Exception');
		}
		catch (Api\Exception $e)
		{
			$this->assertSame("Mailbox 'Nonexistent' not found", $e->getMessage());
		}
		$this->assertCount(0, $session->calls);
	}

	public function testEmailImportThrowsWithTheNotCreatedDetailOnFailure()
	{
		$session = $this->fakeSession('acc1', fn() => ['notCreated' => ['x' => ['type' => 'invalidProperties']]],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['mbx1']], '0']]]);
		$email = new Email($session);

		try
		{
			$email->emailImport('blob1', 'INBOX');
			$this->fail('expected an Api\Exception');
		}
		catch (Api\Exception $e)
		{
			$this->assertStringContainsString('invalidProperties', $e->getMessage());
		}
	}

	// --- emailDestroy() ---

	public function testEmailDestroyBuildsADestroyOnlyEmailSetCall()
	{
		$session = $this->fakeSession('acc1', fn() => ['destroyed' => ['e1'], 'notDestroyed' => []]);
		$email = new Email($session);

		$email->emailDestroy(['e1', 'e2']);

		$this->assertSame(['Email/set', ['accountId' => 'acc1', 'destroy' => ['e1', 'e2']]], $session->calls[0]);
	}

	public function testEmailDestroyThrowsWhenNotDestroyedIsNonEmpty()
	{
		$session = $this->fakeSession('acc1', fn() => ['notDestroyed' => ['e1' => ['type' => 'notFound']]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailDestroy(['e1']);
	}

	public function testEmailDestroyDoesNotThrowWhenNothingFailed()
	{
		$session = $this->fakeSession('acc1', fn() => ['destroyed' => ['e1'], 'notDestroyed' => []]);
		$email = new Email($session);

		$email->emailDestroy(['e1']);
		$this->assertTrue(true); // reaching here without an exception is the assertion
	}

	// --- emailSetKeywords() ---

	public function testEmailSetKeywordsIsANoOpForAnEmptyIdList()
	{
		$session = $this->fakeSession('acc1', fn() => $this->fail('Email/set must not be called'));
		$email = new Email($session);

		$email->emailSetKeywords([], ['keywords/$seen' => true]);

		$this->assertCount(0, $session->calls);
	}

	public function testEmailSetKeywordsAppliesTheSamePatchToEveryId()
	{
		$session = $this->fakeSession('acc1', fn() => ['notUpdated' => []]);
		$email = new Email($session);

		$email->emailSetKeywords(['e1', 'e2'], ['keywords/$seen' => true, 'keywords/$flagged' => null]);

		$patch = ['keywords/$seen' => true, 'keywords/$flagged' => null];
		$this->assertSame(['e1' => $patch, 'e2' => $patch], $session->calls[0][1]['update']);
	}

	public function testEmailSetKeywordsThrowsWhenNotUpdatedIsNonEmpty()
	{
		$session = $this->fakeSession('acc1', fn() => ['notUpdated' => ['e1' => ['type' => 'notFound']]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailSetKeywords(['e1'], ['keywords/$seen' => true]);
	}

	// --- emailMove() ---

	public function testEmailMoveIsANoOpForAnEmptyIdListAndNeverResolvesTheFolder()
	{
		$session = $this->fakeSession('acc1',
			fn() => $this->fail('Email/set must not be called'),
			fn() => $this->fail('getMailboxId() must not be called')
		);
		$email = new Email($session);

		$email->emailMove([], 'INBOX/Trash');

		$this->assertCount(0, $session->calls);
		$this->assertCount(0, $session->jmapCalls);
	}

	public function testEmailMoveReplacesMailboxIdsWithOnlyTheTargetFolder()
	{
		$session = $this->fakeSession('acc1', fn() => ['notUpdated' => []],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['trash-id']], '0']]]);
		$email = new Email($session);

		$email->emailMove(['e1', 'e2'], 'INBOX/Trash');

		$expected = ['mailboxIds' => ['trash-id' => true]];
		$this->assertSame(['e1' => $expected, 'e2' => $expected], $session->calls[0][1]['update'],
			"a move is a full mailboxIds REPLACE, not a mailboxIds/<id> add-only patch");
	}

	public function testEmailMoveThrowsAndNeverCallsEmailSetWhenTheTargetFolderIsNotFound()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => [['Mailbox/query', ['ids' => []], '0']]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailMove(['e1'], 'Nonexistent');
	}

	public function testEmailMoveThrowsWhenNotUpdatedIsNonEmpty()
	{
		$session = $this->fakeSession('acc1', fn() => ['notUpdated' => ['e1' => ['type' => 'notFound']]],
			fn() => ['methodResponses' => [['Mailbox/query', ['ids' => ['trash-id']], '0']]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->emailMove(['e1'], 'INBOX/Trash');
	}

	// --- getStates() ---

	public function testGetStatesBuildsTheChainedMailboxAndEmailStateQuery()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['sessionState' => 'sess1', 'methodResponses' => [
			['Mailbox/query', ['queryState' => 'qs1'], 't0'],
			['Email/get', ['state' => 'es1'], 't1'],
		]]);
		$email = new Email($session);

		$states = $email->getStates('INBOX', null, $sessionState);

		$this->assertSame(['Mailbox' => 'qs1', 'Email' => 'es1'], $states);
		$this->assertSame('sess1', $sessionState);
		$methodCalls = $session->jmapCalls[0];
		$this->assertSame(['name' => 'INBOX'], $methodCalls[0][1]['filter']);
		$this->assertSame(['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 't0'], $methodCalls[1][1]['#inMailbox']);
	}

	public function testGetStatesThrowsWhenTheMailboxQueryStateIsMissing()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => [
			['Mailbox/query', [], 't0'],
			['Email/get', ['state' => 'es1'], 't1'],
		]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->getStates('INBOX');
	}

	public function testGetStatesThrowsWhenTheEmailStateIsMissing()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => [
			['Mailbox/query', ['queryState' => 'qs1'], 't0'],
			['Email/get', [], 't1'],
		]]);
		$email = new Email($session);

		$this->expectException(Api\Exception::class);
		$email->getStates('INBOX');
	}

	// --- getChanges() ---

	private function changesResponder() : callable
	{
		return fn($methodCalls) => ['sessionState' => 'sess2',
			'methodResponses' => array_map(fn($mc) => [$mc[0], ['probe' => $mc[2]], $mc[2]], $methodCalls)];
	}

	public function testGetChangesWithOnlyAMailboxStateBuildsOnlyTheThreeMailboxCalls()
	{
		$session = $this->fakeSession('acc1', null, $this->changesResponder());
		$email = new Email($session);

		$email->getChanges(null, ['Mailbox' => 'm1'], 'INBOX');

		$this->assertSame(['mailbox-changes', 'mailbox-created', 'mailbox-updated'],
			array_column($session->jmapCalls[0], 2));
	}

	public function testGetChangesWithOnlyAnEmailStateBuildsOnlyTheThreeEmailCalls()
	{
		$session = $this->fakeSession('acc1', null, $this->changesResponder());
		$email = new Email($session);

		$email->getChanges(null, ['Email' => 'e1'], 'INBOX');

		$this->assertSame(['email-changes', 'email-created', 'email-updated'],
			array_column($session->jmapCalls[0], 2));
	}

	public function testGetChangesWithBothStatesBuildsAllSixCallsMailboxFirst()
	{
		$session = $this->fakeSession('acc1', null, $this->changesResponder());
		$email = new Email($session);

		$email->getChanges(null, ['Mailbox' => 'm1', 'Email' => 'e1'], 'INBOX');

		$this->assertSame(
			['mailbox-changes', 'mailbox-created', 'mailbox-updated', 'email-changes', 'email-created', 'email-updated'],
			array_column($session->jmapCalls[0], 2)
		);
	}

	public function testGetChangesReturnsResponsesKeyedByEachCallsOwnIdAndSetsSessionState()
	{
		$session = $this->fakeSession('acc1', null, $this->changesResponder());
		$email = new Email($session);

		$ret = $email->getChanges(null, ['Mailbox' => 'm1'], 'INBOX', $sessionState);

		$this->assertSame(['mailbox-changes', 'mailbox-created', 'mailbox-updated'], array_keys($ret));
		$this->assertSame(['probe' => 'mailbox-changes'], $ret['mailbox-changes']);
		$this->assertSame('sess2', $sessionState);
	}

	public function testGetChangesWithNeitherStateStillIssuesAnEmptyBatchCallAndReturnsNoResults()
	{
		$session = $this->fakeSession('acc1', null, fn() => ['methodResponses' => []]);
		$email = new Email($session);

		$ret = $email->getChanges(null, [], 'INBOX');

		$this->assertSame([], $ret);
		$this->assertSame([], $session->jmapCalls[0], "no state keys means no method calls, but jmapCall() is still invoked with an empty batch");
	}

	/**
	 * Documents (does not fix) dead code found while writing this coverage: the $mailbox
	 * parameter, for anything other than the literal (case-insensitive) "inbox", triggers an
	 * extra getMailboxId() lookup via a SEPARATE jmapCall() batch - but the resolved mailbox id is
	 * never actually used anywhere in the rest of getChanges() (the change-tracking queries are
	 * always global, not folder-scoped). Every real caller (Http.php, Api\Mail\Imap\Jmap.php) only
	 * ever uses the default "INBOX", so this extra round-trip never fires in production today -
	 * left alone since fixing/removing it is a refactor, not a coverage gap.
	 */
	public function testGetChangesWithANonInboxFolderNameTriggersAnExtraUnusedGetMailboxIdLookup()
	{
		$session = $this->fakeSession('acc1', null, function($methodCalls, $callNum) {
			if ($callNum === 1)
			{
				return ['methodResponses' => [['Mailbox/query', ['ids' => ['other-id']], '0']]];
			}
			return $this->changesResponder()($methodCalls);
		});
		$email = new Email($session);

		$ret = $email->getChanges(null, ['Mailbox' => 'm1'], 'INBOX/DeadCodeProbeFolder');

		$this->assertCount(2, $session->jmapCalls, "the wasted getMailboxId() lookup is a SEPARATE batch before the real change-tracking one");
		$this->assertSame(['mailbox-changes', 'mailbox-created', 'mailbox-updated'], array_keys($ret),
			"the resolved mailbox id has no effect on which/how the change-tracking calls are built");
	}
}
