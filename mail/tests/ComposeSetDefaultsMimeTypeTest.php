<?php
/**
 * EGroupware Mail: regression test for Compose::setDefaults()'s mimeType type
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Mail\Compose;

/**
 * Found live 2026-09-14 (ralf, verifying the mail-body-loss fix): a genuinely blank new compose
 * always opened in HTML mode regardless of the "New message should be composed as" preference
 * (composeOptions='text' for plain-text) - ralf: "the preference says plain-text not html, wired".
 *
 * Root cause: setDefaults() (called by Compose::ajax_getComposeToolbarData(), the source of the
 * mimeType WIDGET's own initial content for a blank compose) used to assign the STRING 'html' or
 * 'plain' to $content['mimeType']. compose.xet's <et2-checkbox id="mimeType"> has no
 * selectedValue/unselectedValue of its own (defaults to boolean true/false) - Et2Checkbox's value
 * setter (Et2Checkbox.ts) only recognizes a real boolean, or a value loosely-equal to its
 * selectedValue/unselectedValue; neither 'html' nor 'plain' matches either, so both fall through
 * to `this.checked = !!new_value` - true for ANY non-empty string, always rendering checked
 * (HTML) regardless of which string was sent. ajax_saveAsDraft()'s own pre-existing code already
 * documented the expected type ("checkbox has only true|false value") - setDefaults() was the one
 * place not honoring it. Reply/forward are unaffected: they set this widget via their own
 * client-side set_value(isHtml) with a real boolean already (mail/js/compose.ts).
 *
 * Doesn't call the real constructor (initMailAccount() needs live IMAP connectivity this
 * environment's PHPUnit CLI context doesn't have - same reasoning SendRefactorTest.php's own
 * newInstanceWithoutConstructor() pattern documents) - passes a non-empty 'mailidentity' so
 * setDefaults() skips its own $this->mail_bo-dependent identity lookup entirely, isolating just
 * the mimeType assignment this fix is about.
 */
class ComposeSetDefaultsMimeTypeTest extends Api\LoggedInTest
{
	private function composeWithPreferences(array $mailPreferences) : Compose
	{
		$compose = (new ReflectionClass(Compose::class))->newInstanceWithoutConstructor();
		$compose->mailPreferences = $mailPreferences;
		return $compose;
	}

	public function testPlainTextPreferenceResolvesToRealBooleanFalse()
	{
		$compose = $this->composeWithPreferences(['composeOptions' => 'text']);

		$content = $compose->setDefaults(['mailidentity' => 'skip-lookup']);

		$this->assertSame(false, $content['mimeType'],
			"A string 'plain' is truthy to Et2Checkbox's own default value setter (no ".
			'selectedValue/unselectedValue on <et2-checkbox id="mimeType">) - it must be a real boolean');
	}

	public function testHtmlPreferenceResolvesToRealBooleanTrue()
	{
		$compose = $this->composeWithPreferences(['composeOptions' => 'html']);

		$content = $compose->setDefaults(['mailidentity' => 'skip-lookup']);

		$this->assertSame(true, $content['mimeType']);
	}

	public function testMissingPreferenceDefaultsToRealBooleanTrue()
	{
		$compose = $this->composeWithPreferences([]);

		$content = $compose->setDefaults(['mailidentity' => 'skip-lookup']);

		$this->assertSame(true, $content['mimeType'],
			'No composeOptions preference set at all should still default to HTML, same as before this fix');
	}
}
