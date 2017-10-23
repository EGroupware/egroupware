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
	 * @depends testNestedInstanciation
	 */
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

}
