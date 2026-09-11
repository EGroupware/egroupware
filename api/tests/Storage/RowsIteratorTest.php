<?php
/**
 * EGroupware Api: tests for Api\Storage\RowsIterator
 *
 * Part of the Api\Storage test-coverage project, Phase 5 (doc/ai/projects/storage-test-coverage.md).
 * RowsIterator was not found to be used anywhere in this checkout (tracked or gitignored apps) as
 * of this writing - this is characterization/regression coverage for existing behavior, kept
 * proportionate to that low-stakes status.
 *
 * @package api
 * @subpackage tests
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use PHPUnit\Framework\TestCase;

/**
 * Fake get_rows() source for RowsIterator tests. Returns one page of rows per constructor-supplied
 * $pages entry, in order, and tracks how many times get_rows() was actually invoked so tests can
 * prove a re-query happened (vs. a stale cached chunk being replayed).
 */
class RowsIteratorFakeSource
{
	public $calls = 0;

	/**
	 * @param array $pages array of pages, each page an array of rows (or a mixed array
	 *   simulating extra non-row entries like 'sel_options')
	 */
	public function __construct(private array $pages)
	{
	}

	public function get_rows(&$query, &$rows, &$readonlys)
	{
		$this->calls++;
		$rows = $this->pages[$this->calls - 1] ?? [];
	}
}

/**
 * Tests for Api\Storage\RowsIterator
 *
 * Pages through any duck-typed object exposing get_rows($query, &$rows, &$readonlys) in
 * RowsIterator::CHUNK_SIZE (500)-row chunks. The constructor only checks method_exists(), so
 * these are pure unit tests against a fake source object - no DB/bootstrap needed.
 */
class RowsIteratorTest extends TestCase
{
	private function rowsOf(int $count, int $offset = 0) : array
	{
		$rows = [];
		for ($i = 0; $i < $count; $i++)
		{
			$rows[] = ['id' => $offset + $i];
		}
		return $rows;
	}

	/**
	 * Behaviour: a single page with fewer than CHUNK_SIZE rows must not trigger a second
	 * get_rows() call (the "previous page short => done" short-circuit in next()).
	 */
	public function testSinglePageUnderChunkSize()
	{
		$source = new RowsIteratorFakeSource([$this->rowsOf(50)]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$collected = [];
		foreach ($iterator as $row)
		{
			$collected[] = $row['id'];
		}
		$this->assertCount(50, $collected, 'Did not iterate all 50 rows of the single page');
		$this->assertSame(range(0, 49), $collected);
		$this->assertSame(1, $source->calls, 'A single under-chunk-size page must not trigger a re-query');
	}

	/**
	 * Behaviour: exactly CHUNK_SIZE rows on the first page is ambiguous (could be more) - the
	 * iterator must re-query, and an empty second page ends iteration cleanly.
	 */
	public function testExactChunkBoundaryThenEmptyPage()
	{
		$source = new RowsIteratorFakeSource([$this->rowsOf(500), []]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$count = 0;
		foreach ($iterator as $row) { $count++; }

		$this->assertSame(500, $count, 'Must yield exactly the 500 rows from the first page');
		$this->assertSame(2, $source->calls, 'Must re-query after an exact-CHUNK_SIZE page to check for more');
	}

	/**
	 * Behaviour: a full first page followed by a shorter second page must yield both pages'
	 * rows combined, and must NOT re-query a third time once a short page is seen.
	 */
	public function testMultiPageThenShortPageStops()
	{
		$source = new RowsIteratorFakeSource([$this->rowsOf(500), $this->rowsOf(200, 500)]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$collected = [];
		foreach ($iterator as $row)
		{
			$collected[] = $row['id'];
		}
		$this->assertCount(700, $collected, 'Must yield all rows across both pages');
		$this->assertSame(range(0, 699), $collected);
		$this->assertSame(2, $source->calls, 'Must stop after the short second page, not re-query a third time');
	}

	/**
	 * Behaviour: zero rows from the very first get_rows() call must produce an immediately-empty
	 * iterator (valid()===false right away), with exactly one get_rows() call made.
	 */
	public function testZeroRowsFromStart()
	{
		$source = new RowsIteratorFakeSource([[]]);
		$iterator = new Api\Storage\RowsIterator($source, []);
		$iterator->rewind();

		$this->assertFalse($iterator->valid(), 'valid() must be false when the first page is empty');
		$this->assertSame(1, $source->calls, 'Exactly one get_rows() call should have been made');

		$collected = [];
		foreach ($iterator as $row) { $collected[] = $row; }
		$this->assertSame([], $collected, 'foreach over a zero-row source must yield nothing');
	}

	/**
	 * Behaviour: key() without an explicit $key constructor argument returns $this->start plus
	 * the row's position within the current chunk's array pointer. $this->start is NOT a plain
	 * "rows consumed so far" counter though: next() increments it by CHUNK_SIZE immediately after
	 * EVERY successful get_rows() call, including the very first one that fills page 1 - so by
	 * the time the first row of page 1 is actually current(), $this->start is already CHUNK_SIZE,
	 * not 0. key() therefore returns values one CHUNK_SIZE "ahead" of what a naive reading of the
	 * class docblock ("$this->start + key returned by get_rows") suggests - this is real,
	 * surprising behavior, not a testing mistake; locked down as-is since nothing in this
	 * checkout actually consumes RowsIterator's key() output today (see the coverage doc).
	 */
	public function testKeyWithoutExplicitKeyColumnIsOffsetByOneChunkAhead()
	{
		$source = new RowsIteratorFakeSource([$this->rowsOf(500), $this->rowsOf(3, 500)]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$iterator->rewind();
		$this->assertSame(500, $iterator->key(),
			'key() of the very FIRST row must already be CHUNK_SIZE (500), not 0, because '.
			'$this->start is incremented immediately after the first successful get_rows() call');

		// advance to the first row of the SECOND page
		for ($i = 0; $i < 500; $i++) { $iterator->next(); }

		$this->assertTrue($iterator->valid());
		$this->assertSame(1000, $iterator->key(),
			'key() of the first row of page 2 must be 2*CHUNK_SIZE (1000) for the same reason');
	}

	/**
	 * Behaviour: key() WITH an explicit $key constructor argument returns that column's value
	 * from the current row instead of a computed offset.
	 */
	public function testKeyWithExplicitKeyColumnUsesRowValue()
	{
		$rows = [
			['id' => 'row-a', 'title' => 'A'],
			['id' => 'row-b', 'title' => 'B'],
		];
		$source = new RowsIteratorFakeSource([$rows]);
		$iterator = new Api\Storage\RowsIterator($source, [], 'id');

		$iterator->rewind();
		$this->assertSame('row-a', $iterator->key(), 'key() must use the named column of the current row');
		$iterator->next();
		$this->assertSame('row-b', $iterator->key());
	}

	/**
	 * Behaviour: calling rewind() mid-iteration must restart from page 1 via a genuine new
	 * get_rows() call - NOT replay a cached first chunk. Proven by having the fake source return
	 * DIFFERENT content on its second call than its first, and confirming rewind() yields the
	 * SECOND call's content.
	 */
	public function testRewindMidIterationReQueriesRatherThanReplaying()
	{
		$firstCallRows = [['id' => 'first-call-row']];
		$secondCallRows = [['id' => 'second-call-row']];
		$source = new RowsIteratorFakeSource([$firstCallRows, $secondCallRows]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$iterator->rewind();
		$this->assertSame(1, $source->calls);
		$this->assertSame('first-call-row', $iterator->current()['id']);

		$iterator->rewind();

		$this->assertSame(2, $source->calls, 'rewind() must trigger a fresh get_rows() call, not reuse the cached chunk');
		$this->assertSame('second-call-row', $iterator->current()['id'],
			'rewind() must reflect the NEW get_rows() call result, proving it re-queried rather than replaying');
	}

	/**
	 * Behaviour: entries in the $rows array returned by get_rows() that are not [int key => array
	 * value] (e.g. a 'sel_options' entry, or a non-array value under any key) must be stripped
	 * before iteration, per the "remove non-rows returned" step in next().
	 */
	public function testNonRowEntriesAreStripped()
	{
		$page = [
			'sel_options' => ['some' => 'options'],
			0 => ['id' => 'real-row-0'],
			1 => ['id' => 'real-row-1'],
			'foo' => 'not-an-array-value',
		];
		$source = new RowsIteratorFakeSource([$page]);
		$iterator = new Api\Storage\RowsIterator($source, []);

		$collected = [];
		foreach ($iterator as $row)
		{
			$collected[] = $row['id'];
		}
		$this->assertSame(['real-row-0', 'real-row-1'], $collected,
			'Non-int-keyed and non-array entries must be stripped before iteration');
	}

	/**
	 * Behaviour: the constructor requires an object exposing a get_rows() method - anything else
	 * must throw Api\Exception\WrongParameter immediately, not fail later/silently.
	 */
	public function testConstructorThrowsWithoutGetRowsMethod()
	{
		$this->expectException(Api\Exception\WrongParameter::class);
		new Api\Storage\RowsIterator(new stdClass(), []);
	}
}
