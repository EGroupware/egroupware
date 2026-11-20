<?php

/**
 * Date and time placeholders of document merge, for every date and time format a user can pick
 * in their preferences
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage storage
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace Storage;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestMerge.php';

/**
 * Date and time placeholders of Merge, for every date and time format of the preferences
 *
 * Placeholders covered:
 * - {{date}}, {{datetime}} and {{time}}: the current date and time, in the user's formats
 * - {{field}} of a field listed in Merge::$date_fields, plus the {{field/date}} and {{field/time}}
 *   parts generated from it, in the user's formats
 * - {{field:FORMAT}}: the field formatted with a PHP date() format given in the placeholder
 * - a date field alone in a spreadsheet cell becoming a real date cell
 * - the date and date-time fields of contacts (birthday, created, modified)
 *
 * Merge gets the value of a date field in different shapes, depending on the app it merges for:
 * a DateTime object, or a string the app already formatted in the user's date and time format -
 * with a ", " (Api\DateTime::to()'s default), with a space, with seconds (importexport's export
 * conversion, used by eg. timesheet), or a date only.  Merge has to parse each of them back in
 * the user's formats, as PHP's own parser misreads or rejects many of them: it reads 03-15-2026
 * as day-month-year and rejects it, and it reads 2026.03.15 as a time.
 *
 * Setup: TestMerge returns a fixed date (2026-03-15 14:30, a day > 12 so day and month can not
 * be swapped unnoticed) for every entry, and each test sets the user's date and time format
 * (Api\DateTime and the preferences), restored in tearDown().
 *
 * Pass criteria: every placeholder merges to the fixed date formatted as the placeholder asks,
 * without any merge error or exception.
 *
 * Environment: the current-date tests read the clock before and after merging, and accept
 * either, so they do not fail if the minute changes in between.
 */
class MergeDateTest extends LoggedInTest
{
	/**
	 * Date formats offered in the preferences ('dateformat'), see preferences_hooks::settings()
	 */
	const DATE_FORMATS = ['d.m.Y', 'Y-m-d', 'm/d/Y', 'm-d-Y', 'm.d.Y', 'Y/d/m', 'Y-d-m', 'Y.d.m', 'Y/m/d', 'Y.m.d',
						  'd/m/Y', 'd-m-Y', 'd-M-Y'];

	/**
	 * Time formats offered in the preferences ('timeformat')
	 */
	const TIME_FORMATS = ['24', '12'];

	/**
	 * Shapes the value of a date field reaches Merge in
	 */
	const VALUE_SHAPES = ['DateTime object', 'Api\DateTime::to() default', 'date and time', 'with seconds', 'date only'];

	/**
	 * The fixed date-time merged
	 */
	const WHEN = '2026-03-15 14:30:00';

	/**
	 * @var TestMerge
	 */
	protected $merge;

	/**
	 * Date and time formats found before the test, restored in tearDown()
	 *
	 * @var array|null
	 */
	private $saved_formats;

	protected function setUp() : void
	{
		$this->merge = new TestMerge();
	}

	protected function tearDown() : void
	{
		if($this->saved_formats)
		{
			$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
			$prefs['dateformat'] = $this->saved_formats['pref_dateformat'];
			$prefs['timeformat'] = $this->saved_formats['pref_timeformat'];
			Api\DateTime::$user_dateformat = $this->saved_formats['dateformat'];
			Api\DateTime::$user_timeformat = $this->saved_formats['timeformat'];
			$this->saved_formats = null;
		}
		if($this->saved_lang !== false)
		{
			$GLOBALS['egw_info']['user']['preferences']['common']['lang'] = $this->saved_lang;
			$this->saved_lang = false;
		}
		parent::tearDown();
	}

	/**
	 * Use the given date and time format, like a user who picked them in their preferences
	 *
	 * @param string $dateformat date format, one of self::DATE_FORMATS
	 * @param string $timeformat '24' or '12'
	 */
	/**
	 * @var string|null|false language preference as found, false if not changed
	 */
	protected $saved_lang = false;

	/**
	 * Set the user's language preference, restored in tearDown()
	 */
	protected function setUserLanguage(string $lang) : void
	{
		$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		if($this->saved_lang === false)
		{
			$this->saved_lang = $prefs['lang'] ?? null;
		}
		$prefs['lang'] = $lang;
	}

	protected function setUserFormat(string $dateformat, string $timeformat) : void
	{
		$prefs =& $GLOBALS['egw_info']['user']['preferences']['common'];
		if(!$this->saved_formats)
		{
			$this->saved_formats = [
				'pref_dateformat' => $prefs['dateformat'] ?? null,
				'pref_timeformat' => $prefs['timeformat'] ?? null,
				'dateformat'      => Api\DateTime::$user_dateformat,
				'timeformat'      => Api\DateTime::$user_timeformat,
			];
		}
		$prefs['dateformat'] = $dateformat;
		$prefs['timeformat'] = $timeformat;
		Api\DateTime::setUserPrefs(Api\DateTime::$user_timezone->getName(), $dateformat, $timeformat);
	}

	/**
	 * The fixed date-time in the given shape, formatted with the current user's formats
	 *
	 * @param string $shape one of self::VALUE_SHAPES
	 * @return Api\DateTime|string
	 */
	protected function dateValue(string $shape)
	{
		$when = new Api\DateTime(self::WHEN);
		switch($shape)
		{
			case 'DateTime object':
				return $when;
			case 'Api\DateTime::to() default':
				return Api\DateTime::to($when);
			case 'date and time':
				return $when->format(Api\DateTime::$user_dateformat . ' ' . Api\DateTime::$user_timeformat);
			case 'with seconds':    // as importexport_export_csv::convert() formats date-time fields
				return $when->format(Api\DateTime::$user_dateformat . ' ' .
									 (Api\DateTime::$user_timeformat == 'H:i' ? 'H:i:s' : 'h:i:s a'));
			case 'date only':
				return $when->format(Api\DateTime::$user_dateformat);
		}
		throw new \InvalidArgumentException("Unknown shape '$shape'");
	}

	/**
	 * The date-time the given shape stands for: a date only is midnight
	 *
	 * @param string $shape
	 * @return Api\DateTime
	 */
	protected function expectedTime(string $shape) : Api\DateTime
	{
		return new Api\DateTime($shape == 'date only' ? substr(self::WHEN, 0, 10) . ' 00:00:00' : self::WHEN);
	}

	/**
	 * Merge $target with the field 'due' (a date field) set to $value
	 *
	 * Fails the test with the message of an exception thrown, instead of erroring out, to show
	 * which date format and shape broke the merge.
	 *
	 * @param string $target
	 * @param Api\DateTime|string $value
	 * @param string $mimetype
	 * @param string $field name of the date field
	 * @param ?string $charset charset to pass to merge_string(), as eg. Api\Mail does
	 * @return string
	 */
	protected function mergeDate(string $target, $value, string $mimetype, string $field = 'due', ?string $charset = null) : string
	{
		$this->merge->date_fields = [$field];
		$this->merge->setReplacements(['$$' . $field . '$$' => $value]);
		$errors = [];
		try
		{
			$result = $this->merge->merge_string($target, [1], $errors, $mimetype, null, $charset);
		}
		catch (\Throwable $e)
		{
			$this->fail('Merging ' . (is_string($value) ? "'$value'" : get_class($value)) . ' failed with ' .
						get_class($e) . ': ' . $e->getMessage());
		}
		$this->assertEmpty($errors, 'Errors when merging');

		return $result;
	}

	/**
	 * Every date format x time format
	 */
	public static function userFormatProvider() : array
	{
		$data = [];
		foreach(self::DATE_FORMATS as $dateformat)
		{
			foreach(self::TIME_FORMATS as $timeformat)
			{
				$data["$dateformat {$timeformat}h"] = [$dateformat, $timeformat];
			}
		}
		return $data;
	}

	/**
	 * Every date format x time format x shape of the value
	 */
	public static function userFormatAndShapeProvider() : array
	{
		$data = [];
		foreach(self::userFormatProvider() as $name => $formats)
		{
			foreach(self::VALUE_SHAPES as $shape)
			{
				$data["$name, $shape"] = array_merge($formats, [$shape]);
			}
		}
		return $data;
	}

	/**
	 * {{due/date}} and {{due/time}} are the date and time of the field {{due}}, in the user's
	 * date and time format.  They are generated for every merged document, so checked for a
	 * text/plain one and an XML one (ODF text), which escapes the values before replacing them.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testDateAndTimeParts(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$expected = '[' . $this->expectedTime($shape)->format(Api\DateTime::$user_dateformat) . ']' .
			'[' . $this->expectedTime($shape)->format(Api\DateTime::$user_timeformat) . ']';

		$result = $this->mergeDate('[{{due/date}}][{{due/time}}]', $this->dateValue($shape), 'text/plain');
		$this->assertSame($expected, $result, 'text/plain');

		$result = $this->mergeDate('<text:p>[{{due/date}}][{{due/time}}]</text:p>', $this->dateValue($shape),
								   'application/vnd.oasis.opendocument.text');
		$this->assertSame("<text:p>$expected</text:p>", $result, 'ODF text');
	}

	/**
	 * The field itself stays as the app formatted it, whatever the format
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testFieldItselfUnchanged(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$value = $this->dateValue($shape);
		if(!is_string($value))
		{
			$this->markTestSkipped('Apps format their date fields as string');
		}

		$result = $this->mergeDate('[{{due}}]', $value, 'text/plain');

		$this->assertSame("[$value]", $result);
	}

	/**
	 * {{due:FORMAT}} formats the field with a date() format of the document's choice, independent
	 * of the user's format, in every type of document: checked for an ODF text document and a
	 * plain text one.
	 *
	 * The format can be any date() format, including the "/" and ":" separators and characters
	 * escaped with a backslash; only "$" and XML markup or entities ("<", ">", "&") end it.
	 * The same field can be used with several formats in one document.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testFormatInPlaceholder(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$expected = $this->expectedTime($shape);
		$formats = ['Y-m-d', 'd.m.Y', 'm-d-Y', 'D, d M Y', 'l j F Y', 'H.i', 'g.i a', 'Y-m-d H.i',
					'm/d/Y', 'd/m/Y', 'H:i', 'h:i:s A', 'm/d/Y H:i', 'jS \o\f F Y', 'Y-m-d\TH:i:s', 'W', 'N'];
		$odf = $odf_expected = $text = $text_expected = '';
		foreach($formats as $format)
		{
			$odf .= "<text:p>{{due:$format}}</text:p>";
			$odf_expected .= '<text:p>' . $expected->format($format) . '</text:p>';
			$text .= "[{{due:$format}}]";
			$text_expected .= '[' . $expected->format($format) . ']';
		}
		$odf = "<office:document-content><office:body>$odf</office:body></office:document-content>";
		$odf_expected = "<office:document-content><office:body>$odf_expected</office:body></office:document-content>";

		$result = $this->mergeDate($odf, $this->dateValue($shape), 'application/vnd.oasis.opendocument.text');
		$this->assertSame($odf_expected, $result, 'ODF text');

		$result = $this->mergeDate($text, $this->dateValue($shape), 'text/plain');
		$this->assertSame($text_expected, $result, 'text/plain');
	}

	/**
	 * {{due:FORMAT}} in an RTF document, which Merge converts to its own charset (iso-8859-1).
	 * Backslashes are RTF's control characters, so the formats here do without them.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatProvider')]
	public function testFormatInPlaceholderRtf(string $dateformat, string $timeformat)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$expected = $this->expectedTime('date and time');

		$result = $this->mergeDate('{\rtf1 {{due:m/d/Y H:i}} {{due:l, j. F Y}}\par}', $this->dateValue('date and time'),
								   'application/rtf');

		$this->assertSame('{\rtf1 ' . $expected->format('m/d/Y H:i') . ' ' . $expected->format('l, j. F Y') . '\par}',
						  $result);
	}

	/**
	 * Email documents (message/rfc822) are not merged as a whole: Api\Mail::importMessageToMergeAndSend()
	 * merges each part on its own, headers and subject as text/plain, the text body as text/plain
	 * and the HTML body as text/html, passing the mail charset (utf-8).  Checked here with those
	 * same calls, as a real email document needs a mail account to merge.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testEmailParts(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$expected = $this->expectedTime($shape);
		$value = $this->dateValue($shape);
		$parts = '{{due}} {{due/date}} {{due/time}} {{due:m/d/Y H:i}} {{due:l, jS \o\f F}}';
		$expected_parts = (is_string($value) ? $value : (string)$value) . ' ' .
			$expected->format(Api\DateTime::$user_dateformat) . ' ' . $expected->format(Api\DateTime::$user_timeformat) . ' ' .
			$expected->format('m/d/Y H:i') . ' ' . $expected->format('l, jS \o\f F');

		$result = $this->mergeDate("Subject: $parts", $value, 'text/plain', 'due', 'utf-8');
		$this->assertSame("Subject: $expected_parts", $result, 'Subject or text body');

		$result = $this->mergeDate("<html><body><p>$parts</p></body></html>", $value, 'text/html', 'due', 'utf-8');
		$this->assertSame("<html><body><p>$expected_parts</p></body></html>", $result, 'HTML body');
	}

	/**
	 * An empty date field merges {{due:FORMAT}} to nothing, not to the current date
	 */
	public function testFormatInPlaceholderOfEmptyField()
	{
		$this->setUserFormat('m-d-Y', '24');

		$result = $this->mergeDate('<text:p>[{{due:Y-m-d}}][{{due}}]</text:p>', '', 'application/vnd.oasis.opendocument.text');
		$this->assertSame('<text:p>[][]</text:p>', $result, 'ODF text');

		$result = $this->mergeDate('[{{due:Y-m-d}}][{{due}}]', '', 'text/plain');
		$this->assertSame('[][]', $result, 'text/plain');
	}

	/**
	 * Custom fields of type date or date-time are date fields named "#<name>", so the "#" has to
	 * work in {{#name:FORMAT}}, {{#name/date}} and {{#name/time}} too
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatProvider')]
	public function testCustomFieldDatePlaceholders(string $dateformat, string $timeformat)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$expected = $this->expectedTime('date and time');

		$result = $this->mergeDate(
			'<text:p>{{#due:d.m.Y H.i}}</text:p><text:p>{{#due/date}}</text:p><text:p>{{#due/time}}</text:p>',
			Api\DateTime::to(new Api\DateTime(self::WHEN)), 'application/vnd.oasis.opendocument.text', '#due'
		);

		$this->assertSame('<text:p>' . $expected->format('d.m.Y H.i') . '</text:p>' .
						  '<text:p>' . $expected->format(Api\DateTime::$user_dateformat) . '</text:p>' .
						  '<text:p>' . $expected->format(Api\DateTime::$user_timeformat) . '</text:p>', $result);
	}

	/**
	 * A date field alone in a cell of an ODF spreadsheet becomes a date cell (office:value-type
	 * "date"), whose office:date-value is the ISO date-time, shown in the cell's own number format.
	 * Its /date, /time and :FORMAT parts in a text cell stay text, in the user's resp. the given format.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testSpreadsheetDateCell(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$value = $this->dateValue($shape);
		$expected = $this->expectedTime($shape);

		$result = $this->mergeDate(
			'<office:document-content><office:body><office:spreadsheet><table:table><table:table-row>' .
			'<table:table-cell office:value-type="string" calcext:value-type="string"><text:p>{{due}}</text:p></table:table-cell>' .
			'<table:table-cell office:value-type="string" calcext:value-type="string"><text:p>[{{due/date}}][{{due/time}}][{{due:m/d/Y H:i}}]</text:p></table:table-cell>' .
			'</table:table-row></table:table></office:spreadsheet></office:body></office:document-content>',
			$value, 'application/vnd.oasis.opendocument.spreadsheet'
		);

		$this->assertMatchesRegularExpression('/office:value-type="date"/', $result,
											  'Cell is not a date cell');
		$this->assertStringContainsString('office:date-value="' . $expected->format('Y-m-d\TH:i:s') . '"', $result,
										  'Wrong date value of the cell');
		$this->assertStringContainsString(
			'<text:p>[' . $expected->format(Api\DateTime::$user_dateformat) . '][' .
			$expected->format(Api\DateTime::$user_timeformat) . '][' . $expected->format('m/d/Y H:i') . ']</text:p>',
			$result, 'Date parts in a text cell'
		);
	}

	/**
	 * A date field alone in a cell of an Excel 2003 XML spreadsheet becomes a DateTime cell,
	 * whose value is the ISO date-time
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatAndShapeProvider')]
	public function testExcel2003DateCell(string $dateformat, string $timeformat, string $shape)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$value = $this->dateValue($shape);
		$expected = $this->expectedTime($shape);

		$result = $this->mergeDate(
			'<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?>' .
			'<Workbook><Worksheet><Table><Row>' .
			'<Cell><Data ss:Type="String">{{due}}</Data></Cell>' .
			'<Cell><Data ss:Type="String">[{{due/date}}][{{due:m/d/Y H:i}}]</Data></Cell>' .
			'</Row></Table></Worksheet></Workbook>',
			$value, 'application/xml'
		);

		$this->assertStringContainsString('<Data ss:Type="DateTime">' . $expected->format('Y-m-d\TH:i:s') . '</Data>',
										  $result, 'Not a DateTime cell with the right value');
		$this->assertStringContainsString('<Data ss:Type="String">[' . $expected->format(Api\DateTime::$user_dateformat) .
										  '][' . $expected->format('m/d/Y H:i') . ']</Data>', $result, 'Date parts in a text cell');
	}

	/**
	 * {{date}}, {{datetime}} and {{time}} are the current date, date + time and time in the
	 * user's formats.  {{datetime}} uses Api\DateTime::to()'s default format: date, ", ", time.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatProvider')]
	public function testCurrentDateAndTime(string $dateformat, string $timeformat)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$this->merge->setReplacements(['$$name$$' => 'entry']);    // no replacements means no entry
		$errors = [];

		$before = new Api\DateTime('now');
		$result = $this->merge->merge_string('[{{date}}][{{datetime}}][{{time}}]', [1], $errors, 'text/plain');
		$after = new Api\DateTime('now');

		$this->assertEmpty($errors, 'Errors when merging');
		$expected = [];
		foreach([$before, $after] as $now)
		{
			$expected[] = '[' . $now->format(Api\DateTime::$user_dateformat) . ']' .
				'[' . $now->format(Api\DateTime::$user_dateformat . ', ' . Api\DateTime::$user_timeformat) . ']' .
				'[' . $now->format(Api\DateTime::$user_timeformat) . ']';
		}
		$this->assertContains($result, array_unique($expected));
	}

	/**
	 * Contacts' birthday is a date, created and modified are date-times, all in the user's
	 * formats.  Merge::contact_replacements() formats them itself, from the values as stored:
	 * a Y-m-d string for the birthday, timestamps for created and modified.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('userFormatProvider')]
	public function testContactDates(string $dateformat, string $timeformat)
	{
		$this->setUserFormat($dateformat, $timeformat);
		$when = new Api\DateTime(self::WHEN);
		$contact = [
			'id'       => 0,
			'n_fn'     => 'Date Test',
			'bday'     => '2026-03-15',
			'created'  => $when->format('ts'),
			'modified' => $when->format('ts'),
		];

		$replacements = $this->merge->contact_replacements($contact);

		$this->assertSame($when->format(Api\DateTime::$user_dateformat), $replacements['$$bday$$'] ?? null,
						  'Birthday');
		$date_time = $when->format(Api\DateTime::$user_dateformat . ', ' . Api\DateTime::$user_timeformat);
		$this->assertSame($date_time, $replacements['$$created$$'] ?? null, 'Created');
		$this->assertSame($date_time, $replacements['$$modified$$'] ?? null, 'Modified');
	}

	/**
	 * Names of weekdays and months are in the user's language: {{field:FORMAT}}, {{field/date}}
	 * with a date format holding the month's name, and the field itself, which apps format with
	 * an English month.  D and M are the first three letters of the name.
	 *
	 * Setup: user language German, date format d-M-Y, 2026-03-15 (a Sunday in March).
	 */
	public function testDateNamesInUserLanguage()
	{
		$this->setUserFormat('d-M-Y', '24');
		$this->setUserLanguage('de');

		$result = $this->mergeDate('[{{due:l, j. F Y}}][{{due:D M}}][{{due/date}}][{{due}}][{{due:\\l}}]',
			Api\DateTime::to(new Api\DateTime(self::WHEN)), 'text/plain');

		$this->assertSame('[Sonntag, 15. März 2026][Son Mär][15-Mär-2026][15-Mär-2026, 14:30][l]', $result);
	}

	/**
	 * English stays English, and a DateTime object gets the same names
	 */
	public function testDateNamesInEnglish()
	{
		$this->setUserFormat('d-M-Y', '24');
		$this->setUserLanguage('en');

		$result = $this->mergeDate('[{{due:l, j. F Y}}][{{due:D M}}][{{due/date}}]', new Api\DateTime(self::WHEN), 'text/plain');

		$this->assertSame('[Sunday, 15. March 2026][Sun Mar][15-Mar-2026]', $result);
	}

	/**
	 * A document naming its language gets the names in that language, not the user's
	 */
	public function testDateNamesInDocumentLanguage()
	{
		$this->setUserFormat('d-M-Y', '24');
		$this->setUserLanguage('de');
		(new \ReflectionProperty($this->merge, 'document_lang'))->setValue($this->merge, 'fr');

		$result = $this->mergeDate('<text:p>{{due:l j F Y}} {{due/date}}</text:p>', new Api\DateTime(self::WHEN),
			'application/vnd.oasis.opendocument.text');

		$this->assertSame('<text:p>Dimanche 15 Mars 2026 15-Mar-2026</text:p>', $result);
	}

	/**
	 * The language a document names in its default style resp. document defaults, as EGroupware
	 * language: with country if EGroupware has that, without if not, none if it has neither.
	 */
	public static function documentLanguageProvider() : array
	{
		$odt = 'application/vnd.oasis.opendocument.text';
		$docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
		return [
			'ODF German'           => [$odt, 'de', 'DE', 'de'],
			'ODF Brazilian'        => [$odt, 'pt', 'BR', 'pt-br'],
			'ODF English'          => [$odt, 'en', 'CA', 'en'],
			'ODF unknown'          => [$odt, 'xx', 'XX', null],
			'ODF none'             => [$odt, null, null, null],
			'Word German'          => [$docx, 'de', 'DE', 'de'],
			'Word Spanish (Spain)' => [$docx, 'es', 'ES', 'es-es'],
			'Word French'          => [$docx, 'fr', null, 'fr'],
			'Word none'            => [$docx, null, null, null],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('documentLanguageProvider')]
	public function testDocumentLanguage(string $mimetype, ?string $language, ?string $country, ?string $expected)
	{
		$archive = tempnam(sys_get_temp_dir(), 'merge-lang') . '.zip';
		$zip = new \ZipArchive();
		$zip->open($archive, \ZipArchive::CREATE);
		if(str_contains($mimetype, 'opendocument'))
		{
			$zip->addFromString('styles.xml', '<office:document-styles><office:styles>' .
				'<style:default-style style:family="graphic"><style:text-properties fo:language="it" fo:country="IT"/></style:default-style>' .
				'<style:default-style style:family="paragraph"><style:paragraph-properties fo:orphans="2"/>' .
				'<style:text-properties style:font-name="Liberation Serif"' .
				($language ? ' fo:language="' . $language . '"' : '') . ($country ? ' fo:country="' . $country . '"' : '') .
				'/></style:default-style></office:styles></office:document-styles>');
		}
		else
		{
			$zip->addFromString('word/styles.xml', '<w:styles><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Liberation Serif"/>' .
				($language ? '<w:lang w:val="' . $language . ($country ? '-' . $country : '') . '" w:eastAsia="zh-CN" w:bidi="hi-IN"/>' : '') .
				'</w:rPr></w:rPrDefault></w:docDefaults><w:style w:type="paragraph"><w:rPr><w:lang w:val="it-IT"/></w:rPr></w:style></w:styles>');
		}
		$zip->close();
		try
		{
			$this->assertSame($expected, (new \ReflectionMethod($this->merge, 'document_language'))->invoke(null, $archive, $mimetype));
		}
		finally
		{
			unlink($archive);
		}
	}
}
