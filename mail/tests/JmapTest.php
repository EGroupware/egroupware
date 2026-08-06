<?php
/**
 * Test EGroupware\Mail\JmapShim's supported JMAP method-calls (Mailbox/query, Email/query,
 * Email/get, Email/set) and the request-level plumbing around them (result-reference resolution, error
 * handling).
 *
 * Runs entirely against the built-in accountId "0" demo fixture plus pure helper methods -
 * no database, session or IMAP connection required. See JmapShim's docblock and
 * JmapShim::demoFixture() for what "0" serves.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

class JmapTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Read a Horde_Imap_Client_Search_Query's protected internal state, to assert on
	 * JmapShim::filterToQuery()'s translation without depending on a live IMAP server.
	 */
	protected static function searchState(\Horde_Imap_Client_Search_Query $query) : array
	{
		$prop = new \ReflectionProperty($query, '_search');
		$prop->setAccessible(true);
		return $prop->getValue($query);
	}

	public function testMailboxQueryTopLevel()
	{
		$responses = JmapShim::dispatch([
			['Mailbox/query', ['filter' => ['name' => 'INBOX']], 'c0'],
		]);

		$this->assertSame('Mailbox/query', $responses[0][0]);
		$this->assertSame([base64_encode('INBOX')], $responses[0][1]['ids']);
		$this->assertSame('c0', $responses[0][2]);
	}

	public function testMailboxQueryNestedFolder()
	{
		// mirrors MailJmap.mailboxId()'s per-level walk: one Mailbox/query per path segment
		$responses = JmapShim::dispatch([
			['Mailbox/query', ['filter' => ['name' => 'INBOX']], 'p0'],
		]);
		$parentId = $responses[0][1]['ids'][0];

		$responses = JmapShim::dispatch([
			['Mailbox/query', ['filter' => ['name' => 'Sent', 'parentId' => $parentId]], 'p1'],
		]);

		$this->assertSame([base64_encode('INBOX/Sent')], $responses[0][1]['ids']);
	}

	public function testEmailQueryAndGetBatchedLikeRealClient()
	{
		// same shape MailJmap.getRows() sends via client.requestMany(): Email/query followed
		// by Email/get whose "ids" is a #-result-reference into the query's "/ids"
		$responses = JmapShim::dispatch([
			['Email/query', [
				'accountId' => '0',
				'filter' => ['inMailbox' => base64_encode('INBOX')],
				'position' => 0,
				'limit' => 5,
			], 'q0'],
			['Email/get', [
				'accountId' => '0',
				'#ids' => ['name' => 'Email/query', 'resultOf' => 'q0', 'path' => '/ids'],
				'properties' => ['id', 'subject', 'keywords', 'from'],
			], 'g0'],
		]);

		[$queryMethod, $queryResult, $queryCallId] = $responses[0];
		$this->assertSame('Email/query', $queryMethod);
		$this->assertSame('q0', $queryCallId);
		$this->assertCount(5, $queryResult['ids']);
		// fixture has 8 INBOX messages (one of the 9 lives in INBOX/Sent)
		$this->assertSame(8, $queryResult['total']);

		[$getMethod, $getResult, $getCallId] = $responses[1];
		$this->assertSame('Email/get', $getMethod);
		$this->assertSame('g0', $getCallId);
		$this->assertSame($queryResult['ids'], array_column($getResult['list'], 'id'));
		$this->assertSame([], $getResult['notFound']);

		$first = $getResult['list'][0];
		foreach (['id', 'subject', 'keywords', 'from', 'to', 'cc', 'bcc', 'size', 'sentAt', 'receivedAt', 'hasAttachment', 'preview'] as $property)
		{
			$this->assertArrayHasKey($property, $first);
		}
		$this->assertIsArray($first['from']);
		$this->assertArrayHasKey('email', $first['from'][0]);
	}

	public function testEmailGetPreviewOnlyFetchedWhenRequested()
	{
		// mirrors mail_ui::get_rows()'s "fetchPreview" behaviour (filter2 / mail.ShowDetails
		// preference, the "Sneak preview in list" toggle): MailJmap.getRows() only puts
		// "preview" in the requested properties when that toggle is on
		$withPreview = JmapShim::dispatch([
			['Email/get', ['accountId' => '0', 'ids' => ['1'], 'properties' => ['id', 'preview']], 'g0'],
		])[0][1]['list'][0];
		$this->assertNotSame('', $withPreview['preview']);

		$withoutPreview = JmapShim::dispatch([
			['Email/get', ['accountId' => '0', 'ids' => ['1'], 'properties' => ['id', 'subject']], 'g0'],
		])[0][1]['list'][0];
		$this->assertSame('', $withoutPreview['preview']);
	}

	public function testEmailQueryPagination()
	{
		$responses = JmapShim::dispatch([
			['Email/query', [
				'accountId' => '0',
				'filter' => ['inMailbox' => base64_encode('INBOX')],
				'position' => 6,
				'limit' => 5,
			], 'q0'],
		]);

		// only 2 left after skipping 6 of the 8 INBOX fixture messages
		$this->assertCount(2, $responses[0][1]['ids']);
		$this->assertSame(8, $responses[0][1]['total']);
	}

	public function testEmailQueryScopedToFolder()
	{
		$responses = JmapShim::dispatch([
			['Email/query', [
				'accountId' => '0',
				'filter' => ['inMailbox' => base64_encode('INBOX/Sent')],
			], 'q0'],
		]);

		$this->assertSame(1, $responses[0][1]['total']);
	}

	public function testUnsupportedMethodReturnsErrorResponse()
	{
		$responses = JmapShim::dispatch([
			['Foo/bar', [], 'x0'],
		]);

		$this->assertSame('error', $responses[0][0]);
		$this->assertSame('x0', $responses[0][2]);
	}

	/**
	 * Email/set accepts independent label changes and the mutually-exclusive custom-flag
	 * patches produced by MailJmap, including the accompanying standard flagged keyword.
	 * The demo account avoids a live IMAP dependency; pass criteria are a JMAP-shaped
	 * updated map and no per-id failures.
	 */
	public function testEmailSetAcceptsWritableKeywordPatches()
	{
		$responses = JmapShim::dispatch([
			['Email/set', [
				'accountId' => '0',
				'mailboxId' => base64_encode('INBOX'),
					'update' => [
						'1' => [
							'keywords/$label1' => true,
							'keywords/$customflag1' => null,
							'keywords/$customflag2' => true,
							'keywords/$flagged' => true,
					],
				],
			], 's0'],
		]);

		$this->assertSame('Email/set', $responses[0][0]);
		$this->assertArrayHasKey('1', (array)$responses[0][1]['updated']);
		$this->assertSame([], (array)$responses[0][1]['notUpdated']);
	}

	/**
	 * JMAP's $flagged system keyword must be written as IMAP's \Flagged system flag,
	 * while custom flags remain ordinary IMAP keywords.  Pass criteria are the exact
	 * values consumed by Horde's store operation.
	 */
	public function testWritableKeywordMappingTranslatesFlaggedSystemKeyword()
	{
		$keywords = JmapShim::writableKeywords();

		$this->assertSame('\\Flagged', $keywords['$flagged']);
		$this->assertSame('$customflag2', $keywords['$customflag2']);
	}

	/**
	 * The local shim must not be an arbitrary IMAP-keyword write endpoint.  An unknown
	 * keyword is rejected per id and no update is reported.
	 */
	public function testEmailSetRejectsUnknownKeyword()
	{
		$result = JmapShim::dispatch([
			['Email/set', [
				'accountId' => '0',
				'update' => ['1' => ['keywords/$not-configured' => true]],
			], 's0'],
		])[0][1];

		$this->assertSame([], (array)$result['updated']);
		$this->assertSame('invalidProperties', ((array)$result['notUpdated'])['1']['type']);
	}

	/**
	 * Only numeric mailbox-local UIDs are valid Email ids in the local shim.
	 */
	public function testEmailSetRejectsOpaqueIdForLocalShim()
	{
		$result = JmapShim::dispatch([
			['Email/set', [
				'accountId' => '0',
				'update' => ['opaque' => ['keywords/$label1' => true]],
			], 's0'],
		])[0][1];

		$this->assertSame('invalidArguments', ((array)$result['notUpdated'])['opaque']['type']);
	}

	public function testEmailGetWithoutPrecedingQueryErrors()
	{
		// a real (non-demo) accountId with no matching Email/query earlier in the same
		// request must fail fast, without ever trying to reach the database/IMAP server
		$responses = JmapShim::dispatch([
			['Email/get', ['accountId' => '999', 'ids' => ['1']], 'g0'],
		]);

		$this->assertSame('error', $responses[0][0]);
	}

	public function testResolveRefs()
	{
		$responses = [['Email/query', ['ids' => ['a', 'b']], 'q0']];

		$args = JmapShim::resolveRefs([
			'#ids' => ['name' => 'Email/query', 'resultOf' => 'q0', 'path' => '/ids'],
			'accountId' => '0',
		], $responses);

		$this->assertSame(['accountId' => '0', 'ids' => ['a', 'b']], $args);
	}

	public function testResolveRefsThrowsWhenUnresolvable()
	{
		$this->expectException(\Exception::class);
		JmapShim::resolveRefs([
			'#ids' => ['name' => 'Email/query', 'resultOf' => 'missing', 'path' => '/ids'],
		], []);
	}

	public function testJsonPath()
	{
		$this->assertSame('c', JmapShim::jsonPath(['a' => ['b' => 'c']], '/a/b'));
		$this->assertNull(JmapShim::jsonPath(['a' => []], '/a/b'));
	}

	public function testFolderPathBase64Roundtrip()
	{
		$this->assertSame('INBOX/Sub', JmapShim::folderPath(base64_encode('INBOX/Sub')));
		$this->assertSame('', JmapShim::folderPath(''));
	}

	public function testFlagsToKeywords()
	{
		$this->assertSame(
			['$seen' => true, '$answered' => true, '$forwarded' => true, '$label1' => true,
				'$project' => true, '$customflag2' => true],
			JmapShim::flagsToKeywords(['\\Seen', '\\Answered', '$Forwarded', '$label1', '$Project', '$customFlag2']),
		);
	}

	public function testBuildSortDefaultsAndMapping()
	{
		$this->assertSame(
			[\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_DATE],
			JmapShim::buildSort([]),
		);
		$this->assertSame(
			[\Horde_Imap_Client::SORT_SUBJECT],
			JmapShim::buildSort([['property' => 'subject', 'isAscending' => true]]),
		);
		$this->assertSame(
			[\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_ARRIVAL],
			JmapShim::buildSort([['property' => 'receivedAt', 'isAscending' => false]]),
		);
	}

	public function testFilterToQuerySimpleAnd()
	{
		// {operator: 'AND', conditions: [{subject: 'foo'}, {minSize: 1000}]}, same shape
		// MailJmap.buildFilter() produces once more than one condition applies
		$query = JmapShim::filterToQuery([
			'operator' => 'AND',
			'conditions' => [['subject' => 'foo'], ['minSize' => 1000]],
		]);
		$state = self::searchState($query);

		$this->assertCount(2, $state['and']);
		$subState = self::searchState($state['and'][0]);
		$this->assertSame('SUBJECT', $subState['header'][0]['header']);
		$this->assertSame('foo', $subState['header'][0]['text']);
		$sizeState = self::searchState($state['and'][1]);
		$this->assertSame(1000.0, $sizeState['size']['LARGER']['size']);
	}

	public function testFilterToQueryOr()
	{
		// same shape buildTokenizedFilter() sends for a single term across several fields
		$query = JmapShim::filterToQuery([
			'operator' => 'OR',
			'conditions' => [['subject' => 'x'], ['from' => 'x'], ['to' => 'x']],
		]);
		$state = self::searchState($query);

		$this->assertCount(3, $state['or']);
	}

	public function testFilterToQueryNotOfLeaf()
	{
		$query = JmapShim::filterToQuery([
			'operator' => 'NOT',
			'conditions' => [['subject' => 'spam']],
		]);
		$state = self::searchState($query);

		$this->assertTrue($state['header'][0]['not']);
	}

	public function testFilterToQueryNotOfOrPushesDownViaDeMorgan()
	{
		// NOT(subject:x OR from:x) must become AND(NOT subject:x, NOT from:x), since Horde
		// only supports per-leaf negation, not a query-level NOT combinator
		$query = JmapShim::filterToQuery([
			'operator' => 'NOT',
			'conditions' => [[
				'operator' => 'OR',
				'conditions' => [['subject' => 'x'], ['from' => 'x']],
			]],
		]);
		$state = self::searchState($query);

		$this->assertArrayNotHasKey('or', $state);
		$this->assertCount(2, $state['and']);
		foreach ($state['and'] as $sub)
		{
			$subState = self::searchState($sub);
			$this->assertTrue($subState['header'][0]['not']);
		}
	}

	public function testFilterToQueryHasAndNotKeyword()
	{
		$query = JmapShim::filterToQuery(['hasKeyword' => '$flagged']);
		$state = self::searchState($query);
		$this->assertSame('flag', $state['flag']['FLAGGED']['type']);

		$query = JmapShim::filterToQuery(['notKeyword' => '$seen']);
		$state = self::searchState($query);
		$this->assertArrayHasKey('SEEN', $state['flag']);

		$query = JmapShim::filterToQuery(['hasKeyword' => '$project']);
		$state = self::searchState($query);
		$this->assertArrayHasKey('$PROJECT', $state['flag']);

		$query = JmapShim::filterToQuery(['notKeyword' => '$project']);
		$state = self::searchState($query);
		$this->assertFalse($state['flag']['$PROJECT']['set'] ?? false);
	}

	public function testImapDateConvertsToUserTimezoneNotRealUtc()
	{
		// eTemplate/get_rows convention: dates are shown in the *user's* configured timezone,
		// formatted with a literal "Z" suffix so the browser displays those wall-clock digits
		// as-is (see JmapShim::imapDate()'s docblock) - NOT real UTC despite the "Z". Horde's
		// DateTime objects carry the server's timezone, so a straight UTC conversion is wrong.
		$previous = \EGroupware\Api\DateTime::$user_timezone;
		\EGroupware\Api\DateTime::$user_timezone = new \DateTimeZone('Europe/Berlin');
		try
		{
			// 21:01 UTC == 23:01 in Berlin (CEST, UTC+2) in August
			$date = new \DateTime('2026-08-05 21:01:00', new \DateTimeZone('UTC'));
			$this->assertSame('2026-08-05T23:01:00Z', JmapShim::imapDate($date));
		}
		finally
		{
			\EGroupware\Api\DateTime::$user_timezone = $previous;
		}
	}

	public function testEmailGetPreservesEmailQuerySortOrder()
	{
		// Email/get's list must come back in the order Email/query's ids were given, not
		// whatever order the fixture (or, for a real account, the IMAP FETCH response) happens
		// to iterate in - otherwise a correctly-sorted Email/query gets silently undone
		$reversedIds = array_reverse(array_keys(array_filter(
			JmapShim::demoFixture()['emails'],
			static fn($email) => $email['mailbox'] === 'INBOX',
		)));
		$reversedIds = array_map('strval', $reversedIds);

		$result = JmapShim::dispatch([
			['Email/get', ['accountId' => '0', 'ids' => $reversedIds], 'g0'],
		]);

		$this->assertSame($reversedIds, array_column($result[0][1]['list'], 'id'));
	}

	public function testFilterToQueryIgnoresInMailbox()
	{
		// inMailbox is consumed separately by JmapShim::findInMailbox(), not a search criterion
		$query = JmapShim::filterToQuery(['inMailbox' => 'aW5ib3g=']);
		$this->assertSame([], self::searchState($query));
	}
}
