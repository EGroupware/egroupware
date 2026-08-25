<?php

namespace Storage;

use EGroupware\Api\LoggedInTest;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestMerge.php';

class MergeTest extends LoggedInTest
{
	const SIMPLE_TARGET = "{{replacement}}";

	protected function setUp() : void
	{
		$this->merge = new TestMerge();
	}

	/**
	 * Test plain text into a simple text document
	 *
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('textToTextProvider')]
	public function testTextToText($testText, $expectedText)
	{
		$errors = [];
		$this->merge->setReplacements(['$$replacement$$' => $testText]);
		$result = $this->merge->merge_string(self::SIMPLE_TARGET, [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals($expectedText, $result);
	}

	public static function textToTextProvider() : array
	{
		return [
			["Plain text", "Plain text"],
			["New\nline text", "New\nline text"],
			['Special -> characters <- & stuff', 'Special -> characters <- & stuff'],
			['<b>Contains HTML</b>', '<b>Contains HTML</b>'],      // HTML is text too
			['HTML<br />newline', "HTML<br />newline"],            // HTML is text too
			["Multi-line:\n1.  First line\n -> Second\n", "Multi-line:\n1.  First line\n -> Second\n"],
		];
	}

	/**
	 * With no parsing into an HTML file, we expect the same
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('textToHTMLProvider')]
	public function testTextToHtml($testText, $expectedText)
	{
		$this->markTestSkipped("Something goes wrong with GitHub Actions but not locally.  Skipping for now.");
		$errors = [];
		$this->merge->setReplacements(['$$replacement$$' => $testText]);
		$result = $this->merge->merge_string(self::SIMPLE_TARGET, [1], $errors, "text/html");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals($expectedText, $result);
	}

	public static function textToHtmlProvider() : array
	{
		return [
			["Plain text", "Plain text"],
			["New\nline text", "New<br/>line text"],    // Newlines get parsed anyway
			['Special -> characters <- & stuff', 'Special -> characters '],
			// strip_tags() is not smart.  This could be improved
			['<b>Contains<br /> HTML</b>', '<b>Contains<br/> HTML</b>'],      // Some tags are allowed
			['<q>Contains HTML that will be stripped</q>', 'Contains HTML that will be stripped'],
			["Multi-line:\n1.  First line\n -> Second\n", "Multi-line:<br/>1.  First line<br/> -> Second<br/>"],
		];
	}

	/**
	 * Word / LibreOffice spell-check or autocorrect can wrap part of a placeholder in a
	 * formatting tag (eg. <text:span>) whose opening or closing half lands just outside
	 * the {{...}} markers, eg. "{{ts<text:span ...>_end}}</text:span>".  merge_string()
	 * has to reunite the split placeholder and drop the orphaned tag half, instead of
	 * leaving unbalanced markup behind that a target application then refuses to open.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('splitPlaceholderTagProvider')]
	public function testSplitPlaceholderTag($target, $expected)
	{
		$errors = [];
		$this->merge->setReplacements(['$$ts_end$$' => 'VALUE']);
		$result = $this->merge->merge_string($target, [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals($expected, $result);
	}

	public static function splitPlaceholderTagProvider() : array
	{
		return [
			// opening tag inside the markers, closing tag just outside - the real-world bug
			['{{ts<text:span text:style-name="T1">_end}}</text:span>', 'VALUE'],
			// symmetric case: closing tag inside, opening tag just outside
			['<text:span text:style-name="T1">{{ts_</text:span>end}}', 'VALUE'],
			// an unrelated tag right after the placeholder must NOT be swallowed
			['{{ts<text:span>_end}}</text:span><text:p>next</text:p>', 'VALUE<text:p>next</text:p>'],
			// tag fully inside the markers already worked, must keep working
			['{{<text:span>ts_end</text:span>}}', 'VALUE'],
			// tag fully outside the markers - already balanced, must stay untouched
			['<text:span>{{ts_end}}</text:span>', '<text:span>VALUE</text:span>'],
		];
	}
}