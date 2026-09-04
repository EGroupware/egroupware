<?php

/**
 * Test for templates
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @subpackage etemplate
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

/**
 * Description of TemplateTest
 *
 * @author nathan
 */
class TemplateTest extends \EGroupware\Api\Etemplate\WidgetBaseTest {

	/**
	 * Test instanciation of template from a file
	 */
	public function testSimpleInstance()
	{
		static $name = 'api.prompt';

		$template = Template::instance($name);
		$this->assertInstanceOf('EGroupware\Api\Etemplate\Widget\Template', $template);
	}

	/**
	 * Test instanciating nested template
	 *
	 */
	public function testNestedInstanciation()
	{
		static $template = 'api.nested';

		$template = Template::instance($template, 'test');
		$this->assertInstanceOf('EGroupware\Api\Etemplate\Widget\Template', $template);

		// Check for the sub-child to see if the nested template was loaded
		$this->assertInstanceOf('EGroupware\Api\Etemplate\Widget', $template->getElementById('sub_child'));

		// Check that it's not just making things up
		$this->assertNull($template->getElementById('not_existing'));
	}


	/**
	 * Test that we can instanciate a sub-template from a file, once the file
	 * is in the cache
	 *
	 */
	#[\PHPUnit\Framework\Attributes\Depends('testNestedInstanciation')]
	public function testSubTemplate()
	{
		// No file matches this, but it was loaded and cached in the previous test
		static $template = 'api.nested.sub_template';
		$template = Template::instance($template, 'test');
		$this->assertInstanceOf('EGroupware\Api\Etemplate\Widget\Template', $template);

		// Check for the sub-child to see if the template was loaded
		$this->assertInstanceOf('EGroupware\Api\Etemplate\Widget', $template->getElementById('sub_child'));

		// Check that it's not just making things up
		$this->assertNull($template->getElementById('not_existing'));
	}

	/**
	 * Run $callback with error_log() redirected to a temporary file and return what was written.
	 *
	 * @param callable $callback
	 * @return string everything error_log()ed while $callback ran
	 */
	protected function captureErrorLog(callable $callback) : string
	{
		$log = tempnam(sys_get_temp_dir(), 'egw-template-test-');
		$old = ini_get('error_log');
		ini_set('error_log', $log);
		try
		{
			$callback();
		}
		finally
		{
			ini_set('error_log', $old);
		}
		$captured = file_get_contents($log);
		unlink($log);

		return $captured;
	}

	/**
	 * A template that is genuinely referenced but missing has to keep being logged:
	 * that is a real error somebody needs to see.
	 *
	 * Passes if the "template NOT found" line naming the template shows up in error_log().
	 */
	public function testMissingTemplateIsLogged()
	{
		$name = 'api.no_such_template_'.__LINE__;

		$logged = $this->captureErrorLog(static function() use ($name)
		{
			Template::instance($name);
		});

		$this->assertStringContainsString('template NOT found', $logged);
		$this->assertStringContainsString($name, $logged);
	}

	/**
	 * Callers deliberately probing whether an optional template exists pass quiet=true, because a
	 * missing template is the expected case for them (eg. Nextmatch checking for an app-supplied
	 * filter-template before generating one). They must not write an error on every request.
	 *
	 * Passes if nothing at all is written to error_log() and the probe still reports "not found".
	 */
	public function testQuietProbeOfMissingTemplateIsNotLogged()
	{
		$name = 'api.no_such_template_'.__LINE__;

		$logged = $this->captureErrorLog(static function() use ($name)
		{
			self::assertFalse(Template::instance($name, quiet: true));
		});

		$this->assertSame('', $logged);
	}

	/**
	 * A name can resolve to a file that exists but does not define it, which is reported by a second,
	 * separate error_log() - eg. the probe for "filemanager.filter" reads filter.xet, which only defines
	 * "filemanager.index.filter". For a probe that is still just "no such template", so quiet has to
	 * cover that message too.
	 *
	 * $load_via resolves the path from api.nested (api/templates/test/nested.xet) while we ask for a
	 * name that file does not define, which is the cheapest way to reach that branch.
	 *
	 * Passes if nothing is written to error_log() while the same lookup without quiet does log.
	 */
	public function testQuietProbeOfTemplateMissingFromItsFileIsNotLogged()
	{
		$name = 'api.not_defined_in_nested';
		$load_via = 'api.nested';
		$this->assertNotNull(Template::relPath($name, 'test', '', $load_via), 'test needs an existing file');

		$noisy = $this->captureErrorLog(static function() use ($name, $load_via)
		{
			self::assertFalse(Template::instance($name, 'test', '', $load_via));
		});
		$this->assertStringContainsString('template NOT found in file', $noisy);

		$quiet = $this->captureErrorLog(static function() use ($name, $load_via)
		{
			self::assertFalse(Template::instance($name, 'test', '', $load_via, quiet: true));
		});
		$this->assertSame('', $quiet);
	}

	/**
	 * A template name that is a content-reference ("@name") is resolved from content, and gets
	 * (re-)resolved client-side if the server had nothing to expand it with. It can never name a
	 * file, so reporting it as a missing template is a false alarm.
	 *
	 * Passes if nothing is written to error_log() for the unexpanded reference.
	 */
	public function testUnexpandedContentReferenceIsNotLogged()
	{
		$logged = $this->captureErrorLog(static function()
		{
			self::assertFalse(Template::instance('@no_such_key_in_content'));
		});

		$this->assertSame('', $logged);
	}
}
