<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Imap's Email/query filter/sort translation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-2 entry: emailQuery()'s real-account IMAP
 * search/fetch translation had zero coverage ("every existing test uses the demo fixture or a
 * mocked adapter that bypasses real search/fetch construction"). The actual translation logic -
 * findInMailbox()/filterToQuery()/applyCondition()/buildSort()/keywordToFlag() - is a set of pure,
 * public static functions with no IMAP/DB dependency at all (only emailQuery() itself, which
 * calls imapServer()->search(), needs a live connection - not attempted here), so this tests them
 * directly, asserting on Horde_Imap_Client_Search_Query's own (string) IMAP search command
 * rendering rather than reimplementing/guessing the expected wire format.
 */
class ImapFilterQueryTest extends \PHPUnit\Framework\TestCase
{
	public function testFindInMailboxReturnsTheTopLevelCondition()
	{
		$this->assertSame('INBOX/Sub', Imap::findInMailbox(['inMailbox' => 'INBOX/Sub', 'subject' => 'x']));
	}

	public function testFindInMailboxRecursesIntoNestedConditions()
	{
		$filter = ['operator' => 'AND', 'conditions' => [['subject' => 'x'], ['inMailbox' => 'INBOX/Sub']]];

		$this->assertSame('INBOX/Sub', Imap::findInMailbox($filter));
	}

	public function testFindInMailboxReturnsNullWhenAbsent()
	{
		$this->assertNull(Imap::findInMailbox(['subject' => 'x']));
	}

	#[DataProvider('leafConditionProvider')]
	public function testApplyConditionViaFilterToQuery(array $filter, string $expected)
	{
		$this->assertSame($expected, (string)Imap::filterToQuery($filter));
	}

	public static function leafConditionProvider() : array
	{
		return [
			'subject' => [['subject' => 'hello world'], 'SUBJECT "hello world"'],
			'from' => [['from' => 'x@y.com'], 'FROM x@y.com'],
			'to' => [['to' => 'x@y.com'], 'TO x@y.com'],
			'cc' => [['cc' => 'x@y.com'], 'CC x@y.com'],
			'body' => [['body' => 'foo'], 'BODY foo'],
			'text' => [['text' => 'foo'], 'TEXT foo'],
			'minSize' => [['minSize' => 1000], 'LARGER 1000'],
			'maxSize' => [['maxSize' => 500], 'SMALLER 500'],
			'after' => [['after' => '2026-01-01'], 'SENTSINCE 1-Jan-2026'],
			'before' => [['before' => '2026-01-01'], 'SENTBEFORE 1-Jan-2026'],
			'hasKeyword system flag' => [['hasKeyword' => '$seen'], 'SEEN'],
			'notKeyword system flag' => [['notKeyword' => '$flagged'], 'UNFLAGGED'],
			'inMailbox alone is not a search criterion, leaving an unconditional ALL match' => [['inMailbox' => 'INBOX/Sub'], 'ALL'],
		];
	}

	public function testInMailboxIsExcludedFromTheSearchCriteriaEvenAlongsideOtherConditions()
	{
		$query = Imap::filterToQuery(['inMailbox' => 'INBOX/Sub', 'subject' => 'x']);

		$this->assertSame('SUBJECT x', (string)$query, "inMailbox is resolved separately via findInMailbox(), never a search term itself");
	}

	public function testHasKeywordOnACustomLabelSearchesTheKeywordDirectlyNotATranslatedSystemFlag()
	{
		$query = Imap::filterToQuery(['hasKeyword' => 'CustomLabel']);

		$this->assertSame('KEYWORD CUSTOMLABEL', (string)$query, "Horde's own flag() uppercases keyword names");
	}

	public function testAndCombinesConditionsWithoutExtraGrouping()
	{
		$filter = ['operator' => 'AND', 'conditions' => [['from' => 'a@x.com'], ['hasKeyword' => '$flagged']]];

		$this->assertSame('FROM a@x.com FLAGGED', (string)Imap::filterToQuery($filter));
	}

	public function testOrWrapsEachConditionInItsOwnGroup()
	{
		$filter = ['operator' => 'OR', 'conditions' => [['from' => 'a@x.com'], ['from' => 'b@x.com']]];
		$rendered = (string)Imap::filterToQuery($filter);

		$this->assertStringContainsString('OR', $rendered);
		$this->assertStringContainsString('FROM a@x.com', $rendered);
		$this->assertStringContainsString('FROM b@x.com', $rendered);
	}

	public function testNotOfASingleLeafConditionNegatesIt()
	{
		$filter = ['operator' => 'NOT', 'conditions' => [['hasKeyword' => '$seen']]];

		$this->assertSame('UNSEEN', (string)Imap::filterToQuery($filter));
	}

	/**
	 * Horde has no query-level negation, only per-condition $not - filterToQuery() pushes NOT down
	 * to each leaf via De Morgan's laws (NOT(AND(a,b)) = OR(NOT a, NOT b)) rather than failing or
	 * ignoring the outer NOT.
	 */
	public function testNotOfAnAndCompoundDistributesViaDeMorgan()
	{
		$filter = ['operator' => 'NOT', 'conditions' => [
			['operator' => 'AND', 'conditions' => [['from' => 'a@x.com'], ['to' => 'b@x.com']]],
		]];
		$rendered = (string)Imap::filterToQuery($filter);

		$this->assertStringContainsString('OR', $rendered);
		$this->assertStringContainsString('NOT FROM a@x.com', $rendered);
		$this->assertStringContainsString('NOT TO b@x.com', $rendered);
	}

	public function testKeywordToFlagMapsTheThreeStandardKeywords()
	{
		$this->assertSame('Seen', Imap::keywordToFlag('$seen'));
		$this->assertSame('Answered', Imap::keywordToFlag('$answered'));
		$this->assertSame('Flagged', Imap::keywordToFlag('$flagged'));
	}

	public function testKeywordToFlagPassesThroughAnUnrecognisedKeywordUnchanged()
	{
		$this->assertSame('CustomLabel', Imap::keywordToFlag('CustomLabel'));
	}

	public function testBuildSortFallsBackToReverseDateWhenGivenNoCriteria()
	{
		$this->assertSame([\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_DATE], Imap::buildSort([]));
	}

	public function testBuildSortMapsAnAscendingCriterionWithoutAReversePrefix()
	{
		$sort = Imap::buildSort([['property' => 'subject', 'isAscending' => true]]);

		$this->assertSame([\Horde_Imap_Client::SORT_SUBJECT], $sort);
	}

	public function testBuildSortPrependsReverseForADescendingCriterion()
	{
		$sort = Imap::buildSort([['property' => 'receivedAt', 'isAscending' => false]]);

		$this->assertSame([\Horde_Imap_Client::SORT_REVERSE, \Horde_Imap_Client::SORT_ARRIVAL], $sort);
	}

	public function testBuildSortFallsBackToDateForAnUnrecognisedProperty()
	{
		$sort = Imap::buildSort([['property' => 'unknownProp', 'isAscending' => true]]);

		$this->assertSame([\Horde_Imap_Client::SORT_DATE], $sort);
	}
}
