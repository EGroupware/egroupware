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
require_once realpath(__DIR__.'/../../test/LoggedInTest.php');

// Store request in the session, file access probably won't work due to permissions
\EGroupware\Api\Etemplate\Request::$request_class = 'EGroupware\Api\Etemplate\Request\Session';

/**
 * Base class for all widget tests doing needed setup so the tests can run, and
 * providing common utilities.
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

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		// Call Etemplate constructor once to make sure etemplate::$request is set,
		// otherwise some tests will fail.
		// In normal usage, this is not needed as Etemplate constructor does it.
		new \EGroupware\Api\Etemplate();
	}

	public function setUp()
	{
		// Mock AJAX response
		$this->ajax_response = $this->mock_ajax_response();
	}
	public function tearDown()
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
}
