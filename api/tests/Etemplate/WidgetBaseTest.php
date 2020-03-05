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

use Egroupware\Api\Etemplate;

// test base providing Egw environment, since we need the DB
require_once realpath(__DIR__.'/../LoggedInTest.php');

// Store request in the session, file access probably won't work due to permissions
\EGroupware\Api\Etemplate\Request::$request_class = 'EGroupware\Api\Etemplate\Request\Session';

/**
 * Base class for all widget tests doing needed setup so the tests can run, and
 * providing common utilities to make testing a little easier.
 *
 * Widget scans the apps for widgets, which needs the app list, pulled from the
 * database, so we need to log in.
 */
abstract class WidgetBaseTest extends \EGroupware\Api\LoggedInTest {

	/**
	 * We use our own callback for results of executing, here we store the results
	 * until returned
	 *
	 * @var Array
	 */
	protected static $mocked_exec_result = array();

	protected $ajax_response = null;

	public static function setUpBeforeClass() : void
	{
		parent::setUpBeforeClass();

		// Call Etemplate constructor once to make sure etemplate::$request is set,
		// otherwise some tests will fail.
		// In normal usage, this is not needed as Etemplate constructor does it.
		new \EGroupware\Api\Etemplate();
	}

	protected function setUp() : void
	{
		// Mock AJAX response
		$this->ajax_response = $this->mock_ajax_response();
	}
	protected function tearDown() : void
	{
		// Clean up AJAX response
		$this->ajax_response->initResponseArray();
		$ref = new \ReflectionProperty('\\EGroupware\\Api\\Json\\Response', 'response');
		$ref->setAccessible(true);
		$ref->setValue(null, null);
	}

	/**
	 * Use a known, public callback so we can hook into it, if needed
	 */
	public static function public_callback($content)
	{
		static::$mocked_exec_result = $content;
	}

	/**
	 * Mocks what is needed to fake a call to exec, and catch its output.
	 * The resulting array of information, which would normally be sent to the
	 * client as JSON, is returned for evaluation.
	 *
	 * @param Etemplate $etemplate
	 * @param array $content
	 * @param array $sel_options
	 * @param array $readonlys
	 * @param array $preserv
	 */
	protected function mockedExec(Etemplate $etemplate, array $content,array $sel_options=null,array $readonlys=null,array $preserv=null)
	{
		ob_start();

		// Exec the template
		$etemplate->exec(__CLASS__ . '::public_callback', $content, $sel_options, $readonlys, $preserv, 4);
		$result = $this->ajax_response->returnResult();

		// Store & clean the request
		//$etemplate->destroy_request();

		ob_end_clean();

		return $result;
	}

	/**
	 * Mocks what is needed to fake a call to Etemplate->exec(), and catch its output.
	 * The resulting array of information, which would normally be sent to the
	 * client as JSON, is then processed and validated by the server as if it had
	 * been sent from the client.
	 *
	 * @param \EGroupware\Api\Etemplate $etemplate
	 * @param array $content
	 * @param array $sel_options
	 * @param array $readonlys
	 * @param array $preserv
	 * @return type
	 */
	protected function mockedRoundTrip(\EGroupware\Api\Etemplate $etemplate, array $content,array $sel_options=null,array $readonlys=null,array $preserv=null)
	{

		$result = $this->mockedExec($etemplate, $content, $sel_options, $readonlys, $preserv);

		// Check for the load
		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		return $content;
	}

	/**
	 * Mock the ajax response and override it so it doesn't try to send headers,
	 * which generates errors since PHPUnit has already done that
	 */
	protected function mock_ajax_response()
	{
		$response = $this->getMockBuilder('\\EGroupware\\Api\\Json\\Response')
			->disableOriginalConstructor()
			->setMethods(['get'/*,'generic'*/])
			->getMock();
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
		return $response;
	}

	/**
	 * Exec the template with the provided content, change the values according to
	 * $set_values to simulate client side changes by the user, then validate
	 * against $expected_values.  Optionally, it can check that validation errors
	 * are created by particular widgets.
	 *
	 *
	 * @param \EGroupware\Api\Etemplate $etemplate
	 * @param array $content
	 * @param array $set_values
	 * @param array $expected_values
	 * @param array $validation_errors Indexed by widget ID, we just check that an error
	 *	was found, not what that error was.
	 */
	protected function validateRoundTrip(\EGroupware\Api\Etemplate $etemplate, Array $content, Array $set_values, Array $expected_values = null, Array $validation_errors = array())
	{
		if(is_null($expected_values))
		{
			$expected_values = $set_values;
		}
		$result = $this->mockedExec($etemplate, $content, array(), array(), array());

		// Check for the load
		$data = array();
		foreach($result as $command)
		{
			if($command['type'] == 'et2_load')
			{
				$data = $command['data'];
				break;
			}
		}

		// 'Edit' the data client side
		$data['data']['content'] = $set_values;

		// Let it validate
		Etemplate::ajax_process_content($data['data']['etemplate_exec_id'], $data['data']['content'], false);

		$content = static::$mocked_exec_result;
		static::$mocked_exec_result = array();

		return $this->validateTest($content, $expected_values, $validation_errors);
	}


	/**
	 * Test that the content matches expected_values, and any widgets listed in
	 * $validation_errors actually did raise a validation error.
	 *
	 * Note that in most (all?) cases, a validation error will clear the value.
	 *
	 * @param array $content
	 * @param array $expected_values
	 * @param array $validation_errors
	 */
	protected function validateTest(Array $content, Array $expected_values, Array $validation_errors = array())
	{
		// Make validation errors accessible
		$ref = new \ReflectionProperty('\\EGroupware\\Api\\Etemplate\\Widget', 'validation_errors');
		$ref->setAccessible(true);
		$errors = $ref->getValue();

		// Test values
		foreach($expected_values as $widget_id => $value)
		{
			$this->assertEquals($value, $content[$widget_id], 'Widget "' . $widget_id . '" did not get expected value');
		}

		// Check validation errors
		foreach($validation_errors as $widget_id => $errored)
		{
			$this->assertTrue(array_key_exists($widget_id, $errors), "Widget $widget_id did not cause a validation error");
		}
		$ref->setValue(array());
	}
}
