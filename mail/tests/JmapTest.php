<?php
/**
 * Test mail/jmap.php's supported JMAP method-calls (Mailbox/query, Email/query, Email/get)
 * and the request-level plumbing around them (result-reference resolution, error handling).
 *
 * Runs entirely against the built-in accountId "0" demo fixture plus pure helper functions -
 * no database, session or IMAP connection required. See mail/jmap.php's docblock and
 * mail_jmap_demo_fixture() for what "0" serves.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

define('MAIL_JMAP_TESTS', true);
require_once __DIR__.'/../jmap.php';

class JmapTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Read a Horde_Imap_Client_Search_Query's protected internal state, to assert on
	 * mail_jmap_filter_to_query()'s translation without depending on a live IMAP server.
	 */
	protected static function searchState(\Horde_Imap_Client_Search_Query $query) : array
	{
		$prop = new \ReflectionProperty($query, '_search');
		$prop->setAccessible(true);
		return $prop->getValue($query);
	}

	public function testMailboxQueryTopLevel()
	{
		$responses = \mail_jmap_dispatch([
			['Mailbox/query', ['filter' => ['name' => 'INBOX']], 'c0'],
		]);

		$this->assertSame('Mailbox/query', $responses[0][0]);
		$this->assertSame([base64_encode('INBOX')], $responses[0][1]['ids']);
		$this->assertSame('c0', $responses[0][2]);
	}

	public function testMailboxQueryNestedFolder()
	{
		// mirrors MailJmap.mailboxId()'s per-level walk: one Mailbox/query per path segment
		$responses = \mail_jmap_dispatch([
			['Mailbox/query', ['filter' => ['name' => 'INBOX']], 'p0'],
		]);
		$parentId = $responses[0][1]['ids'][0];

		$responses = \mail_jmap_dispatch([
			['Mailbox/query', ['filter' => ['name' => 'Sent', 'parentId' => $parentId]], 'p1'],
		]);

		$this->assertSame([base64_encode('INBOX/Sent')], $responses[0][1]['ids']);
	}

	public function testEmailQueryAndGetBatchedLikeRealClient()
	{
		// same shape MailJmap.getRows() sends via client.requestMany(): Email/query followed
		// by Email/get whose "ids" is a #-result-reference into the query's "/ids"
		$responses = \mail_jmap_dispatch([
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
		$withPreview = \mail_jmap_dispatch([
			['Email/get', ['accountId' => '0', 'ids' => ['1'], 'properties' => ['id', 'preview']], 'g0'],
		])[0][1]['list'][0];
		$this->assertNotSame('', $withPreview['preview']);

		$withoutPreview = \mail_jmap_dispatch([
			['Email/get', ['accountId' => '0', 'ids' => ['1'], 'properties' => ['id', 'subject']], 'g0'],
		])[0][1]['list'][0];
		$this->assertSame('', $withoutPreview['preview']);
	}

	public function testEmailQueryPagination()
	{
		$responses = \mail_jmap_dispatch([
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
		$responses = \mail_jmap_dispatch([
			['Email/query', [
				'accountId' => '0',
				'filter' => ['inMailbox' => base64_encode('INBOX/Sent')],
			], 'q0'],
		]);

		$this->assertSame(1, $responses[0][1]['total']);
	}

	public function testUnsupportedMethodReturnsErrorResponse()
	{
		$responses = \mail_jmap_dispatch([
			['Foo/bar', [], 'x0'],
		]);

		$this->assertSame('error', $responses[0][0]);
		$this->assertSame('x0', $responses[0][2]);
	}

	public function testEmailGetWithoutPrecedingQueryErrors()
	{
		// a real (non-demo) accountId with no matching Email/query earlier in the same
		// request must fail fast, without ever trying to reach the database/IMAP server
		$responses = \mail_jmap_dispatch([
			['Email/get', ['accountId' => '999', 'ids' => ['1']], 'g0'],
		]);

		$this->assertSame('error', $responses[0][0]);
	}

	public function testResolveRefs()
	{
		$responses = [['Email/query', ['ids' => ['a', 'b']], 'q0']];

		$args = \mail_jmap_resolve_refs([
			'#ids' => ['name' => 'Email/query', 'resultOf' => 'q0', 'path' => '/ids'],
			'accountId' => '0',
		], $responses);

		$this->assertSame(['accountId' => '0', 'ids' => ['a', 'b']], $args);
	}

	public function testResolveRefsThrowsWhenUnresolvable()
	{
		$this->expectException(\Exception::class);
		\mail_jmap_resolve_refs([
			'#ids' => ['name' => 'Email/query', 'resultOf' => 'missing', 'path' => '/ids'],
		], []);
	}

	public function testJsonPath()
	{
		$this->assertSame('c', \mail_jmap_json_path(['a' => ['b' => 'c']], '/a/b'));
		$this->assertNull(\mail_jmap_json_path(['a' => []], '/a/b'));
	}

	public function testFolderPathBase64Roundtrip()
	{
		$this->assertSame('INBOX/Sub', \mail_jmap_folder_path(base64_encode('INBOX/Sub')));
		$this->assertSame('', \mail_jmap_folder_path(''));
	}

	public function testFlagsToKeywords()
	{
		$this->assertSame(
			['$seen' => true, '$answered' => true, '$forwarded' => true, '$label1' => true],
			\mail_jmap_flags_to_keywords(['\\Seen', '\\Answered', '$Forwarded', '$label1']),
		);
	}

	public function testBuildSortDefaultsAndMapping()
	{
		$this->assertSame(
			[\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_DATE],
			\mail_jmap_build_sort([]),
		);
		$this->assertSame(
			[\Horde_Imap_Client::SORT_SUBJECT],
			\mail_jmap_build_sort([['property' => 'subject', 'isAscending' => true]]),
		);
		$this->assertSame(
			[\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_ARRIVAL],
			\mail_jmap_build_sort([['property' => 'receivedAt', 'isAscending' => false]]),
		);
	}

	public function testFilterToQuerySimpleAnd()
	{
		// {operator: 'AND', conditions: [{subject: 'foo'}, {minSize: 1000}]}, same shape
		// MailJmap.buildFilter() produces once more than one condition applies
		$query = \mail_jmap_filter_to_query([
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
		$query = \mail_jmap_filter_to_query([
			'operator' => 'OR',
			'conditions' => [['subject' => 'x'], ['from' => 'x'], ['to' => 'x']],
		]);
		$state = self::searchState($query);

		$this->assertCount(3, $state['or']);
	}

	public function testFilterToQueryNotOfLeaf()
	{
		$query = \mail_jmap_filter_to_query([
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
		$query = \mail_jmap_filter_to_query([
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
		$query = \mail_jmap_filter_to_query(['hasKeyword' => '$flagged']);
		$state = self::searchState($query);
		$this->assertSame('flag', $state['flag']['FLAGGED']['type']);

		$query = \mail_jmap_filter_to_query(['notKeyword' => '$seen']);
		$state = self::searchState($query);
		$this->assertArrayHasKey('SEEN', $state['flag']);
	}

	public function testImapDateConvertsToUserTimezoneNotRealUtc()
	{
		// eTemplate/get_rows convention: dates are shown in the *user's* configured timezone,
		// formatted with a literal "Z" suffix so the browser displays those wall-clock digits
		// as-is (see mail_jmap_imap_date()'s docblock) - NOT real UTC despite the "Z". Horde's
		// DateTime objects carry the server's timezone, so a straight UTC conversion is wrong.
		$previous = \EGroupware\Api\DateTime::$user_timezone;
		\EGroupware\Api\DateTime::$user_timezone = new \DateTimeZone('Europe/Berlin');
		try
		{
			// 21:01 UTC == 23:01 in Berlin (CEST, UTC+2) in August
			$date = new \DateTime('2026-08-05 21:01:00', new \DateTimeZone('UTC'));
			$this->assertSame('2026-08-05T23:01:00Z', \mail_jmap_imap_date($date));
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
			mail_jmap_demo_fixture()['emails'],
			static fn($email) => $email['mailbox'] === 'INBOX',
		)));
		$reversedIds = array_map('strval', $reversedIds);

		$result = \mail_jmap_dispatch([
			['Email/get', ['accountId' => '0', 'ids' => $reversedIds], 'g0'],
		]);

		$this->assertSame($reversedIds, array_column($result[0][1]['list'], 'id'));
	}

	public function testFilterToQueryIgnoresInMailbox()
	{
		// inMailbox is consumed separately by mail_jmap_find_in_mailbox(), not a search criterion
		$query = \mail_jmap_filter_to_query(['inMailbox' => 'aW5ib3g=']);
		$this->assertSame([], self::searchState($query));
	}
}
