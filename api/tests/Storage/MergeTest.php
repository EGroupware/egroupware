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
	 * @dataProvider textToTextProvider
	 */
	public function testTextToText($testText, $expectedText)
	{
		$errors = [];
		$this->merge->setReplacements(['$$replacement$$' => $testText]);
		$result = $this->merge->merge_string(self::SIMPLE_TARGET, [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals($expectedText, $result);
	}

	public function textToTextProvider() : array
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
	 * @dataProvider textToHTMLProvider
	 */
	public function testTextToHtml($testText, $expectedText)
	{
		$errors = [];
		$this->merge->setReplacements(['$$replacement$$' => $testText]);
		$result = $this->merge->merge_string(self::SIMPLE_TARGET, [1], $errors, "text/html");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals($expectedText, $result);
	}

	public function textToHtmlProvider() : array
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
}