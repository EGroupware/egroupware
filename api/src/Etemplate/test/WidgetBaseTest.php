<?php

/**
 * Base test file for all widget tests
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @package api
 * @copyright (c) 2017  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate;

// test base providing Egw environment, since we need the DB
require_once realpath(__DIR__.'/../../test/LoggedInTest.php');

/**
 * Base class for all widget tests doing needed setup so the tests can run, and
 * providing common utilities.
 *
 * Widget scans the apps for widgets, which needs the app list, pulled from the
 * database, so we need to log in.
 */
abstract class WidgetBaseTest extends \EGroupware\Api\LoggedInTest {

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		// Call Etemplate constructor once to make sure etemplate::$request is set,
		// otherwise some tests will fail.
		// In normal usage, this is not needed as Etemplate constructor does it.
		new \EGroupware\Api\Etemplate();
	}

	/**
	 * Mocks what is needed to fake a call to exec, and catch its output.
	 * The resulting array of information, which would normally be sent to the
	 * client as JSON, is returned for evaluation.
	 *
	 * @param String $method
	 * @param array $content
	 * @param array $sel_options
	 * @param array $readonlys
	 * @param array $preserv
	 */
	protected function mockedExec(\EGroupware\Api\Etemplate $etemplate, $method,array $content,array $sel_options=null,array $readonlys=null,array $preserv=null)
	{
		$response = $this->getMockBuilder('\\EGroupware\\Api\\Json\\Response')
			->disableOriginalConstructor()
			->setMethods(['get'/*,'generic'*/])
			->getMock($etemplate);
		// Replace protected self reference with mock object
		$ref = new \ReflectionProperty('\\EGroupware\\Api\\Json\\Response', 'response');
		$ref->setAccessible(true);
		$ref->setValue(null, $response);

		$response
			->method('get')
			->with(function() {
				// Don't send headers, like the real one does
				return self::$response;
			});

		ob_start();

		// Exec the template
		$etemplate->exec($method, $content, $sel_options, $readonlys, $preserv, 4);
		$result = $response->returnResult();

		// Clean json response
		$response->initResponseArray();
		$ref->setValue(null, null);
		
		ob_end_clean();

		return $result;
	}
}
