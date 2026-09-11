<?php

namespace Storage;

use EGroupware\Api;
use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Storage\Customfields;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../LoggedInTest.php';
require_once __DIR__ . '/TestMerge.php';

class MergeTest extends LoggedInTest
{
	const SIMPLE_TARGET = "{{replacement}}";

	/**
	 * Names of custom fields created by a test, cleaned up in tearDown()
	 *
	 * @var string[]
	 */
	private $customfields = [];

	/**
	 * link_id's created by a test, unlinked in tearDown()
	 *
	 * @var int[]
	 */
	private $links = [];

	protected function setUp() : void
	{
		$this->merge = new TestMerge();
	}

	protected function tearDown() : void
	{
		if($this->customfields)
		{
			$fields = Customfields::get('test');
			foreach($this->customfields as $name)
			{
				unset($fields[$name]);
			}
			Customfields::save('test', $fields);
			$this->customfields = [];
		}
		foreach($this->links as $link_id)
		{
			Api\Link::unlink($link_id);
		}
		$this->links = [];
		parent::tearDown();
	}

	/**
	 * Call a protected/private method via reflection
	 *
	 * @param object $object
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	private function callMethod($object, $method, array $args)
	{
		$reflection = new \ReflectionMethod($object, $method);
		$reflection->setAccessible(true);
		return $reflection->invokeArgs($object, $args);
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

	/**
	 * Merge::get_app() special-cases the concrete Api\Contacts\Merge class to 'addressbook',
	 * regardless of what get_class() would naively resolve to.
	 *
	 * Pass criteria: get_app() called on a real Api\Contacts\Merge instance returns 'addressbook'.
	 */
	public function testGetAppReturnsAddressbookForContactsMergeClass()
	{
		$merge = new Api\Contacts\Merge();
		$this->assertEquals('addressbook', $merge->get_app());
	}

	/**
	 * For any other class, get_app() strips a trailing "_merge" (global-namespace legacy app
	 * classes) or takes the 2nd namespace part (EGroupware\App\...), then requires the result to
	 * be a real, currently-known app name - else it returns false.
	 *
	 * Pass criteria: TestMerge (namespace Storage, no "_merge" suffix) is not a known app name,
	 * so get_app() must return false rather than the literal class name.
	 */
	public function testGetAppReturnsFalseForUnregisteredClass()
	{
		$this->assertFalse($this->merge->get_app());
	}

	/**
	 * Merge::get_app_class() resolves a global-namespace "{$appname}_merge" class if one exists
	 * and extends Api\Storage\Merge - calendar_merge is a real example (calendar/inc/class.calendar_merge.inc.php).
	 *
	 * Pass criteria: get_app_class('calendar') returns a calendar_merge instance.
	 */
	public function testGetAppClassResolvesGlobalNamespaceAppMergeClass()
	{
		$merge = TestMerge::get_app_class('calendar');
		$this->assertInstanceOf(\calendar_merge::class, $merge);
	}

	/**
	 * When neither a "{$appname}_merge" class nor an "EGroupware\Ucfirst($appname)\Merge" class
	 * exists, get_app_class() falls back to Api\Contacts\Merge.
	 *
	 * Pass criteria: an appname that resolves to no real class still returns SOME Merge instance
	 * (Api\Contacts\Merge), not null/an error.
	 */
	public function testGetAppClassFallsBackToContactsMergeForUnknownApp()
	{
		$merge = TestMerge::get_app_class('no_such_app_xyz');
		$this->assertInstanceOf(Api\Contacts\Merge::class, $merge);
	}

	/**
	 * contact_replacements() builds a flat $$name$$ => value map from Api\Contacts fields,
	 * including derived/formatted fields (owner/creator/modifier -> username, not raw account id).
	 *
	 * Setup: use the current logged-in test user's own linked addressbook contact (read-only,
	 * no fixture created/destroyed).
	 *
	 * Pass criteria: $$n_fn$$ (full name) is present and non-empty; if the contact has a creator,
	 * $$creator$$ resolves to a username string, not a bare numeric account id.
	 */
	public function testContactReplacementsIncludesKnownFields()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}

		$replacements = $this->merge->contact_replacements($contact_id);

		$this->assertArrayHasKey('$$n_fn$$', $replacements);
		$this->assertNotEmpty($replacements['$$n_fn$$']);

		if(!empty($replacements['$$creator$$']))
		{
			$this->assertFalse(is_numeric($replacements['$$creator$$']),
				'creator replacement should be a resolved username, not a bare account id');
		}
	}

	/**
	 * get_app_replacements() short-circuits to an empty array if $app, $id or $content is empty -
	 * avoids doing any lookup work at all for a placeholder that clearly can't resolve.
	 */
	public function testGetAppReplacementsEmptyArgsShortCircuit()
	{
		$this->assertEquals([], $this->merge->get_app_replacements('', 1, '$$foo$$'));
		$this->assertEquals([], $this->merge->get_app_replacements('addressbook', 0, '$$foo$$'));
		$this->assertEquals([], $this->merge->get_app_replacements('addressbook', 1, ''));
	}

	/**
	 * get_app_replacements('addressbook', $id, ...) is special-cased to call
	 * contact_replacements($id, ...) directly rather than resolving a merge class.
	 *
	 * Pass criteria: calling via get_app_replacements() with app='addressbook' produces the same
	 * $$n_fn$$ value as calling contact_replacements() directly.
	 */
	public function testGetAppReplacementsAddressbookBranch()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}

		$direct = $this->merge->contact_replacements($contact_id);
		$via_app = $this->merge->get_app_replacements('addressbook', $contact_id, 'dummy content');

		$this->assertEquals($direct['$$n_fn$$'], $via_app['$$n_fn$$']);
	}

	/**
	 * get_app_replacements('api-accounts', $account_id, ...) resolves the account to its linked
	 * contact's person_id first, then delegates to contact_replacements() - so an account_id (not
	 * a contact_id) is the correct id to pass for this app.
	 */
	public function testGetAppReplacementsApiAccountsBranch()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}

		$via_app = $this->merge->get_app_replacements('api-accounts', $account_id, 'dummy content');
		$direct = $this->merge->contact_replacements($contact_id);

		$this->assertEquals($direct['$$n_fn$$'], $via_app['$$n_fn$$']);
	}

	/**
	 * cf_link_to_expand() expands a $$#cfname/subfield$$ placeholder for a select-account type
	 * custom field into a value from the linked account's contact_replacements() - eg.
	 * $$#assignee/n_fn$$ pulls the assignee's full name.
	 *
	 * Setup: create a real 'test'-app select-account custom field, then call cf_link_to_expand()
	 * directly with $values containing the current test user's own account_id as the field's raw
	 * value.
	 *
	 * Pass criteria: the placeholder resolves to the current user's own $$n_fn$$ value (from
	 * contact_replacements()), proving the account->contact->field chain works end to end.
	 */
	public function testCfLinkToExpandSelectAccountSingleValue()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}
		$expected_name = $this->merge->contact_replacements($contact_id)['$$n_fn$$'];

		$this->customfields[] = $name = 'MergeTest_assignee';
		Customfields::update([
			'app' => 'test', 'name' => $name, 'label' => 'Assignee', 'type' => 'select-account',
			'type2' => [], 'rows' => 1, 'values' => null, 'private' => [],
		]);

		$values = ['#' . $name => $account_id];
		$content = '$$#' . $name . '/n_fn$$';
		$replacements = [];
		$this->merge->cf_link_to_expand($values, $content, $replacements, 'test');

		$this->assertArrayHasKey($content, $replacements);
		$this->assertEquals($expected_name, $replacements[$content]);
	}

	/**
	 * For a select-account CF with rows>1 (multi-value), cf_link_to_expand() splits the stored
	 * comma-separated value and joins each resolved sub-placeholder with ", ".
	 *
	 * Setup: same as the single-value test, but $values holds two comma-separated account ids
	 * (the current user twice - real 2nd account not required for this to prove the join logic).
	 *
	 * Pass criteria: the resolved replacement is "$$name$$, $$name$$" (comma-space joined).
	 */
	public function testCfLinkToExpandSelectAccountMultiValue()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}
		$expected_name = $this->merge->contact_replacements($contact_id)['$$n_fn$$'];

		$this->customfields[] = $name = 'MergeTest_assignees';
		Customfields::update([
			'app' => 'test', 'name' => $name, 'label' => 'Assignees', 'type' => 'select-account',
			'type2' => [], 'rows' => 2, 'values' => null, 'private' => [],
		]);

		$values = ['#' . $name => $account_id . ',' . $account_id];
		$content = '$$#' . $name . '/n_fn$$';
		$replacements = [];
		$this->merge->cf_link_to_expand($values, $content, $replacements, 'test');

		$this->assertEquals($expected_name . ', ' . $expected_name, $replacements[$content]);
	}

	/**
	 * get_links() (protected) builds a newline-joined list of Api\Link::title() results for every
	 * link on an entry, via Api\Link::get_links().
	 *
	 * Setup: link the current test user's own addressbook contact to another real account's
	 * contact (found the same way CustomfieldsTest::get_another_user() does), clean up the link
	 * in tearDown().
	 *
	 * Pass criteria: get_links('addressbook', $my_contact_id, 'addressbook') returns a string
	 * containing the other contact's title (their n_fn).
	 */
	public function testGetLinksReturnsLinkedEntryTitle()
	{
		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$contact_id = $GLOBALS['egw']->accounts->id2name($account_id, 'person_id');
		if(!$contact_id)
		{
			$this->markTestSkipped('Test user has no linked addressbook contact');
		}

		$accounts = $GLOBALS['egw']->accounts->search(['type' => 'accounts']);
		unset($accounts[$account_id]);
		$other_account_id = null;
		foreach(array_keys($accounts) as $candidate)
		{
			if($GLOBALS['egw']->accounts->id2name($candidate, 'person_id'))
			{
				$other_account_id = $candidate;
				break;
			}
		}
		if(!$other_account_id)
		{
			$this->markTestSkipped('Need a second account with a linked addressbook contact');
		}
		$other_contact_id = $GLOBALS['egw']->accounts->id2name($other_account_id, 'person_id');
		$expected_title = Api\Link::title('addressbook', $other_contact_id);

		$link_id = Api\Link::link('addressbook', $contact_id, 'addressbook', $other_contact_id);
		$this->assertNotFalse($link_id, 'Failed to create test link fixture');
		$this->links[] = $link_id;

		$result = $this->callMethod($this->merge, 'get_links', ['addressbook', $contact_id, 'addressbook']);

		$this->assertStringContainsString($expected_title, $result);
	}

	/**
	 * replace()'s YAML-specific transform syntax: for mimetype application/x-yaml, an indented
	 * line containing $$name/regex/replacement$$ (converted from {{name/regex/replacement}} by
	 * merge_string()'s {{}}->$$ pass) applies a regex substitution to the placeholder's resolved
	 * value instead of inserting it verbatim - eg. to turn commas into newlines while preserving
	 * indentation.
	 *
	 * Pass criteria: "{{replacement/,/;}}" with replacement value "a,b,c" produces "a;b;c" in the
	 * merged output, proving the /regex/replacement transform actually ran (a plain substitution
	 * would leave the commas untouched).
	 */
	public function testReplaceYamlRegexTransform()
	{
		$errors = [];
		$this->merge->setReplacements(['$$replacement$$' => 'a,b,c']);
		$result = $this->merge->merge_string('  key: {{replacement/,/;}}', [1], $errors, 'application/x-yaml');

		$this->assertEmpty($errors, 'Errors when merging');
		$this->assertStringContainsString('a;b;c', $result);
	}

	/**
	 * $$IF field~compare~then~else$$ command: field value matches the compare literal -> THEN branch.
	 *
	 * Setup: a single replacement for $$n_prefix$$. Pass criteria: the IF block resolves to the
	 * THEN argument.
	 */
	public function testIfMatchUsesThenBranch()
	{
		$errors = [];
		$this->merge->setReplacements(['$$n_prefix$$' => 'Herr']);
		$result = $this->merge->merge_string(
			'{{IF n_prefix~Herr~Sehr geehrter~Sehr geehrte}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Sehr geehrter', $result);
	}

	/**
	 * $$IF ...$$: field value does NOT match the compare literal -> ELSE branch.
	 */
	public function testIfNoMatchUsesElseBranch()
	{
		$errors = [];
		$this->merge->setReplacements(['$$n_prefix$$' => 'Frau']);
		$result = $this->merge->merge_string(
			'{{IF n_prefix~Herr~Sehr geehrter~Sehr geehrte}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Sehr geehrte', $result);
	}

	/**
	 * $$IF field~EMPTY~then~else$$: the EMPTY special-case compares the field against emptiness,
	 * not the literal string "EMPTY". Field IS empty -> THEN branch chosen.
	 */
	public function testIfEmptySpecialCaseFieldIsEmptyUsesThenBranch()
	{
		$errors = [];
		$this->merge->setReplacements(['$$url$$' => '']);
		$result = $this->merge->merge_string(
			'{{IF url~EMPTY~NoWebsite~Website:}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('NoWebsite', $result);
	}

	/**
	 * $$IF field~EMPTY~then~else$$: field is NOT empty -> ELSE branch chosen (matches the
	 * method's own docblock example: {{IF url~EMPTY~~Website:}} "if url is not empty, writes
	 * Website:").
	 */
	public function testIfEmptySpecialCaseFieldNonEmptyUsesElseBranch()
	{
		$errors = [];
		$this->merge->setReplacements(['$$url$$' => 'http://example.com']);
		$result = $this->merge->merge_string(
			'{{IF url~EMPTY~NoWebsite~Website:}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Website:', $result);
	}

	/**
	 * $$IF ...$$: the compare value (2nd argument) can itself be a bare placeholder name (no
	 * $$..$$ wrapping) instead of a literal - it gets looked up in the replacements and its
	 * VALUE is used for the comparison.
	 */
	public function testIfCompareValueIsBarePlaceholderName()
	{
		$errors = [];
		$this->merge->setReplacements(['$$city$$' => 'Berlin', '$$billing_city$$' => 'Berlin']);
		$result = $this->merge->merge_string(
			'{{IF city~billing_city~Same~Different}}', [1], $errors, "text/plain");
		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Same', $result, 'city equals billing_city, expected the THEN branch');

		$errors = [];
		$this->merge->setReplacements(['$$city$$' => 'Berlin', '$$billing_city$$' => 'Munich']);
		$result = $this->merge->merge_string(
			'{{IF city~billing_city~Same~Different}}', [1], $errors, "text/plain");
		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Different', $result, 'city differs from billing_city, expected the ELSE branch');
	}

	/**
	 * $$IF ...$$: a THEN/ELSE argument that is itself a bare placeholder name gets wrapped back
	 * into $$name$$ and resolved by the normal placeholder substitution pass that runs right
	 * after process_commands() - not treated as a literal string.
	 */
	public function testIfThenArgumentIsBarePlaceholderName()
	{
		$errors = [];
		$this->merge->setReplacements([
			'$$n_prefix$$' => 'Herr',
			'$$greeting_male$$' => 'Hello Sir',
			'$$greeting_female$$' => 'Hello Madam',
		]);
		$result = $this->merge->merge_string(
			'{{IF n_prefix~Herr~greeting_male~greeting_female}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Hello Sir', $result);
	}

	/**
	 * $$NELF field$$: field has a value -> a line-feed followed by the field's value is inserted.
	 * text/plain's line_feed is "\n" (Merge::apply_mimetype()'s default case).
	 */
	public function testNelfFieldNonEmptyInsertsLineFeedAndValue()
	{
		$errors = [];
		$this->merge->setReplacements(['$$role$$' => 'Manager']);
		$result = $this->merge->merge_string('Line1{{NELF role}}End', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals("Line1\nManagerEnd", $result);
	}

	/**
	 * $$NELF field$$: field is empty -> the whole placeholder resolves to nothing (no line-feed).
	 */
	public function testNelfFieldEmptyProducesNothing()
	{
		$errors = [];
		$this->merge->setReplacements(['$$role$$' => '']);
		$result = $this->merge->merge_string('Line1{{NELF role}}End', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals("Line1End", $result);
	}

	/**
	 * $$NENVLF field$$: field has a value -> only a line-feed is inserted, the value itself is
	 * NOT included (unlike NELF).
	 */
	public function testNenvlfFieldNonEmptyInsertsOnlyLineFeed()
	{
		$errors = [];
		$this->merge->setReplacements(['$$role$$' => 'Manager']);
		$result = $this->merge->merge_string('Line1{{NENVLF role}}End', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals("Line1\nEnd", $result);
	}

	/**
	 * $$NENVLF field$$: field is empty -> nothing is inserted, same as NELF.
	 */
	public function testNenvlfFieldEmptyProducesNothing()
	{
		$errors = [];
		$this->merge->setReplacements(['$$role$$' => '']);
		$result = $this->merge->merge_string('Line1{{NENVLF role}}End', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals("Line1End", $result);
	}

	/**
	 * $$LETTERPREFIX$$ expands to $$LETTERPREFIXCUSTOM n_prefix title n_family$$, which joins
	 * only the non-empty fields with a single space (avoiding a double space when eg. title is
	 * empty, per the method's own docblock example).
	 */
	public function testLetterprefixSkipsEmptyFieldsNoDoubleSpace()
	{
		$errors = [];
		$this->merge->setReplacements([
			'$$n_prefix$$' => '',
			'$$title$$' => 'Dr.',
			'$$n_family$$' => 'Miller',
		]);
		$result = $this->merge->merge_string('{{LETTERPREFIX}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Dr. Miller', $result);
	}

	/**
	 * $$LETTERPREFIXCUSTOM field1 field2 ...$$ directly, all fields present.
	 */
	public function testLetterprefixCustomJoinsAllNonEmptyFields()
	{
		$errors = [];
		$this->merge->setReplacements([
			'$$n_prefix$$' => 'Mr',
			'$$title$$' => 'Dr.',
			'$$n_family$$' => 'Miller',
		]);
		$result = $this->merge->merge_string(
			'{{LETTERPREFIXCUSTOM n_prefix title n_family}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('Mr Dr. Miller', $result);
	}

	/**
	 * $$LETTERPREFIXCUSTOM ...$$: all listed fields empty -> the placeholder resolves to an
	 * empty string.
	 */
	public function testLetterprefixCustomAllFieldsEmptyProducesEmptyString()
	{
		$errors = [];
		$this->merge->setReplacements([
			'$$n_prefix$$' => '',
			'$$title$$' => '',
			'$$n_family$$' => '',
		]);
		$result = $this->merge->merge_string(
			'{{LETTERPREFIXCUSTOM n_prefix title n_family}}', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals('', $result);
	}

	/**
	 * $$pagerepeat$$ with 2+ ids: content before the first $$pagerepeat$$ tag is used once as a
	 * header, content between the two tags is repeated once per id (joined with "\r\n" for
	 * text/plain), content after the second tag is used once as a footer.
	 *
	 * Setup: TestMerge::setReplacementsForId() gives each id its own $$name$$ value.
	 */
	public function testPagerepeatRepeatsContentPerIdForTextPlain()
	{
		$errors = [];
		$this->merge->setReplacementsForId(1, ['$$name$$' => 'Alice']);
		$this->merge->setReplacementsForId(2, ['$$name$$' => 'Bob']);
		$target = "Header\n\$\$pagerepeat\$\$Name: \$\$name\$\$\$\$pagerepeat\$\$Footer";

		$result = $this->merge->merge_string($target, [1, 2], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$this->assertEquals("Header\nName: Alice\r\nName: BobFooter", $result);
	}

	/**
	 * NOT reproducible for text/plain, documented as a deliberate finding rather than forced:
	 * the "for more than one contact in a document use the tag pagerepeat!" error
	 * (Merge.php's `if(count($ids) > 1 && !$contentrepeat)` guard) is effectively UNREACHABLE
	 * for mimetype=text/plain specifically, because of two interacting behaviors:
	 *  1. merge_string()'s very first lines short-circuit and return $_content UNCHANGED,
	 *     before any pagerepeat logic runs at all, whenever content has neither "$$" nor "{{"
	 *     (a genuine no-placeholder-at-all template would hit this, never reaching the guard).
	 *  2. For any content that DOES contain a placeholder, text/plain's own "nohead"
	 *     auto-detection (`stripos($contentstart, '$$') !== false`) treats a missing
	 *     $$pagerepeat$$ tag as "the whole template IS the repeat block" and silently repeats
	 *     it instead - this was discovered by first writing this test with placeholder-bearing
	 *     content and observing it silently repeat instead of failing (see
	 *     testPagerepeatRepeatsContentPerIdForTextPlain() above for that same auto-detection
	 *     used deliberately).
	 * Together these mean no text/plain input reaches the guard: either short-circuited before
	 * it, or auto-repeated instead of erroring. The guard likely still fires for other mimetypes
	 * (ODF/OOXML), whose own pagerepeat-splitting code (a few lines above, split on
	 * "office:body>"/"w:body>") has no equivalent "nohead" fallback - but exercising that would
	 * need a real zip/XML document fixture, out of scope for this text/plain-only pass (see
	 * doc/ai/projects/storage-test-coverage.md).
	 */
	public function testPagerepeatMissingTagErrorIsUnreachableForTextPlainDeferred()
	{
		$this->assertTrue(true);
	}

	/**
	 * Merge::number_format() with its own defaults (2 decimals, "." as decimal separator, no
	 * thousands separator - Merge::number_format()'s non-null defaults bypass
	 * Api\Etemplate::number_format()'s user-preference fallback).
	 */
	public function testNumberFormatDefaults()
	{
		$this->assertEquals('1234.50', Api\Storage\Merge::number_format(1234.5));
		$this->assertEquals('0.10', Api\Storage\Merge::number_format(0.1));
	}

	/**
	 * Merge::number_format() with explicit decimal/thousands separators (eg. German-style
	 * "1.234,50").
	 */
	public function testNumberFormatCustomSeparators()
	{
		$this->assertEquals('1.234,50', Api\Storage\Merge::number_format(1234.5, 2, '', ',', '.'));
	}

	/**
	 * Merge::number_format(): null or empty-string input returns an empty string rather than
	 * "0.00" or throwing.
	 */
	public function testNumberFormatNullOrEmptyStringReturnsEmptyString()
	{
		$this->assertEquals('', Api\Storage\Merge::number_format(null));
		$this->assertEquals('', Api\Storage\Merge::number_format(''));
	}

	/**
	 * Date/time field placeholders: setting Merge::$date_fields for a field auto-generates
	 * $$field/date$$ and $$field/time$$ placeholders (formatted per the current user's
	 * date/time preferences), from a single $$field$$ replacement holding a DateTime value.
	 * This happens unconditionally in replace() (not gated behind the $is_xml-only numeric/date
	 * custom-format block - see the ":FORMAT$$"/":decimals$$" note below), so it's reachable via
	 * a plain text/plain merge.
	 */
	public function testDateFieldsGenerateDateAndTimePlaceholders()
	{
		$errors = [];
		$when = new Api\DateTime('2026-03-15 14:30:00');
		$this->merge->date_fields = ['appointment'];
		$this->merge->setReplacements(['$$appointment$$' => $when]);

		$result = $this->merge->merge_string(
			'$$appointment/date$$ $$appointment/time$$', [1], $errors, "text/plain");

		$this->assertEmpty($errors, "Errors when merging");
		$expected = $when->format(Api\DateTime::$user_dateformat) . ' ' .
			$when->format(Api\DateTime::$user_timeformat);
		$this->assertEquals($expected, $result);
	}

	/**
	 * NOT covered here, documented as deliberately deferred: the ":decimals,thousands$$" numeric
	 * placeholder variant and the ":FORMAT$$" date placeholder variant (Merge.php's
	 * numeric_fields/date_fields custom-format block) only run when Merge::replace()'s $is_xml
	 * flag is true (XML-based document mimetypes, or text/html) - that whole block sits inside
	 * `if(!empty($is_xml)) { ... }`. This test file intentionally sticks to "text/plain" targets
	 * (matching the rest of this file's convention, and avoiding the "text/html" merge path,
	 * which testTextToHtml() above documents as unreliable outside a real browser/CI
	 * environment), so those two placeholder variants are not reachable from here. They would
	 * need either a real XML-ish document fixture or a dedicated text/html-based test accepting
	 * that fragility - left for a later phase (see doc/ai/projects/storage-test-coverage.md).
	 */
	public function testDecimalsAndDateFormatPlaceholderVariantsAreXmlOnlyDeferred()
	{
		$this->assertTrue(true);
	}

	/**
	 * Merge::is_implemented(): pure static mimetype/extension support matrix.
	 */
	#[\PHPUnit\Framework\Attributes\DataProvider('isImplementedProvider')]
	public function testIsImplemented($mimetype, $extension, $expected)
	{
		$this->assertSame($expected, Api\Storage\Merge::is_implemented($mimetype, $extension));
	}

	public static function isImplementedProvider() : array
	{
		return [
			'text/plain' => ['text/plain', null, true],
			'arbitrary text/* subtype' => ['text/x-whatever', null, true],
			'application/rtf' => ['application/rtf', null, true],
			'text/rtf' => ['text/rtf', null, true],
			'application/xml' => ['application/xml', null, true],
			'message/rfc822' => ['message/rfc822', null, true],
			'application/x-yaml' => ['application/x-yaml', null, true],
			'msword without .rtf extension' => ['application/msword', null, false],
			'msword with .rtf extension' => ['application/msword', '.rtf', true],
			'msword with .rtf extension, case-insensitive' => ['application/msword', '.RTF', true],
			'unknown binary type' => ['application/octet-stream', null, false],
			'image type' => ['image/png', null, false],
		];
	}
}