<?php
/**
 * EGroupware importexport: tests for importexport_import_csv
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/src/loader/common.php');

use PHPUnit\Framework\TestCase;

/**
 * Test-only subclass exposing the protected static find_select_key(), so it can be
 * tested directly without going through convert()'s much larger surface.
 */
class TestableImportexportImportCsv extends importexport_import_csv
{
	public static function callFindSelectKey($record_value, $selects)
	{
		return static::find_select_key($record_value, $selects);
	}
}

/**
 * importexport_import_csv reads CSV rows from a stream one record at a time. This is
 * the largest DB-free surface in the importexport app: the constructor only stores the
 * stream and options, and get_record()/skip_records()/get_num_of_records()/
 * get_current_position()/find_select_key() touch no session or database as long as no
 * field mapping/conversion/appname-based conversion is used that would need one.
 *
 * All tests use an in-memory php://memory stream, so nothing touches the filesystem
 * either.
 *
 * Pass criteria per test: documented in each method.
 */
class ImportexportImportCsvTest extends TestCase
{
	private function makeStream(string $csv)
	{
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $csv);
		rewind($stream);
		return $stream;
	}

	private function makeImporter(string $csv): importexport_import_csv
	{
		return new importexport_import_csv($this->makeStream($csv), array('fieldsep' => ',', 'charset' => 'utf-8'));
	}

	/**
	 * get_record('next') (the default) reads rows in order, advancing
	 * get_current_position() by one each time, until the stream is exhausted (false).
	 */
	public function testBasicSequentialRead()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\na3,b3\n");

		$this->assertSame(array('a1', 'b1'), $importer->get_record());
		$this->assertSame(1, $importer->get_current_position());

		$this->assertSame(array('a2', 'b2'), $importer->get_record());
		$this->assertSame(array('a3', 'b3'), $importer->get_record());
		$this->assertFalse($importer->get_record());
	}

	/**
	 * get_num_of_records() must scan to the end to count, but restore the current
	 * position/record afterwards, so callers can keep reading from where they were.
	 */
	public function testGetNumOfRecordsRestoresPosition()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\na3,b3\n");
		$importer->get_record();
		$importer->get_record();
		$this->assertSame(2, $importer->get_current_position());

		$this->assertSame(3, $importer->get_num_of_records());
		$this->assertSame(2, $importer->get_current_position(),
			'get_num_of_records() must restore the position it found the reader at');

		// Reading on from here must continue with record 3, not restart.
		$this->assertSame(array('a3', 'b3'), $importer->get_record());
	}

	/**
	 * skip_records($n) advances past $n records without returning them.
	 */
	public function testSkipRecords()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\na3,b3\n");

		$importer->skip_records(1);

		$this->assertSame(array('a2', 'b2'), $importer->get_record());
	}

	/**
	 * Setting $this->mapping (csv column index => new field name) remaps the record
	 * returned by get_record(); columns not mentioned in the mapping are dropped.
	 */
	public function testFieldMapping()
	{
		$importer = $this->makeImporter("a1,b1\n");
		$importer->mapping = array(0 => 'name', 1 => 'email');

		$this->assertSame(array('name' => 'a1', 'email' => 'b1'), $importer->get_record());
	}

	/**
	 * get_record('previous') requires at least two records already read (current
	 * position >= 2) - asking for "previous" right at the start, or after only one
	 * record, must throw rather than return something meaningless.
	 */
	public function testPreviousThrowsBeforeTwoRecordsRead()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\n");

		$this->expectException(Exception::class);
		$importer->get_record('previous');
	}

	public function testPreviousThrowsAfterOnlyOneRecordRead()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\n");
		$importer->get_record();

		$this->expectException(Exception::class);
		$importer->get_record('previous');
	}

	/**
	 * Once at least two records have been read, 'previous' rewinds and re-reads up to
	 * the record just before the current one.
	 */
	public function testPreviousReturnsPriorRecord()
	{
		$importer = $this->makeImporter("a1,b1\na2,b2\n");
		$importer->get_record();
		$importer->get_record();

		$this->assertSame(array('a1', 'b1'), $importer->get_record('previous'));
	}

	/**
	 * A $_position that is neither one of the recognised keywords nor an int must
	 * throw, rather than silently doing nothing.
	 */
	public function testInvalidPositionKeywordThrows()
	{
		$importer = $this->makeImporter("a1,b1\n");

		$this->expectException(Exception::class);
		$importer->get_record('not-a-real-keyword');
	}

	/**
	 * A record where every column is empty is treated as a blank line and silently
	 * skipped by get_record() when reading forward by keyword (eg. 'next') - this only
	 * applies to multi-column rows; see importexport_import_csv::get_record() for the
	 * count(array_unique(...)) < 2 check this relies on.
	 */
	public function testBlankMultiColumnRowIsSkipped()
	{
		$importer = $this->makeImporter("a1,b1,c1\n,,\na2,b2,c2\n");

		$importer->get_record(); // a1,b1,c1
		$this->assertSame(array('a2', 'b2', 'c2'), $importer->get_record(),
			'the blank ,, row in between must be skipped transparently');
	}

	/**
	 * find_select_key() matches a record value against a select box's values
	 * case-insensitively, returning the matching key.
	 */
	public function testFindSelectKeyCaseInsensitiveMatch()
	{
		$selects = array('1' => 'Yes', '0' => 'No');

		// PHP auto-casts these numeric-looking string keys to int in the array literal,
		// and array_search() (used internally) returns that native int key.
		$this->assertSame(1, TestableImportexportImportCsv::callFindSelectKey('yes', $selects));
		$this->assertSame(0, TestableImportexportImportCsv::callFindSelectKey('NO', $selects));
	}

	/**
	 * A value that matches none of the given select options is returned unchanged,
	 * not dropped or turned into an error.
	 */
	public function testFindSelectKeyNoMatchReturnsValueUnchanged()
	{
		$selects = array('1' => 'Yes', '0' => 'No');

		$this->assertSame('maybe', TestableImportexportImportCsv::callFindSelectKey('maybe', $selects));
	}

	/**
	 * A null record value is returned as-is, without attempting to match.
	 */
	public function testFindSelectKeyNullValuePassesThrough()
	{
		$selects = array('1' => 'Yes', '0' => 'No');

		$this->assertNull(TestableImportexportImportCsv::callFindSelectKey(null, $selects));
	}

	/**
	 * A comma-separated record value (representing multiple selections) is split and
	 * each part matched independently, returning an array of matched keys.
	 */
	public function testFindSelectKeyCommaSeparatedValueMatchesEachPart()
	{
		$selects = array('a' => 'Apple', 'b' => 'Banana', 'c' => 'Cherry');

		$this->assertSame(array('a', 'c'), TestableImportexportImportCsv::callFindSelectKey('Apple,Cherry', $selects));
	}
}
