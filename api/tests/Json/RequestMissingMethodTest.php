<?php
/**
 * EGroupware Api: JSON dispatcher reporting of menuactions naming a method that does not exist
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage json
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Json;

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api\LoggedInTest;

/**
 * A menuaction may name a method the class does not have - a typo, a client calling an endpoint
 * that was renamed, or one that never existed (the report this covers was
 * "addressbook.addressbook_ui.ajax_search", which no EGroupware code ever generates).
 *
 * That used to reach call_user_func_array() unchecked and raise a TypeError, so the user got a
 * bare "An error happened!" and the log got a stack trace whose frames all point into
 * Request.php - saying nothing about which menuaction was actually wrong. handleRequest() now
 * rejects it up front with the menuaction, class and method named in the message - as a plain
 * \InvalidArgumentException, so the user is not told "Permission denied!" for what is a typo.
 *
 * Like RequestSecurityTest this extends LoggedInTest rather than plain TestCase: Request.php runs
 * Widget::scanForWidgets() at file scope, so autoloading the class at all needs a live DB.
 */
class RequestMissingMethodTest extends LoggedInTest
{
	/**
	 * A menuaction whose method does not exist must be reported as an invalid menuaction,
	 * not blow up inside call_user_func_array().
	 */
	public function testMissingMethodIsReportedAsInvalidMenuaction()
	{
		$this->assertInvalidMenuaction(
			'api.'.ajax_dispatcher_test_helper::class.'.ajax_no_such_method', 'ajax_no_such_method');
	}

	/**
	 * The guard must not get in the way of a menuaction that IS valid - otherwise it would just
	 * be breaking every ajax call instead of describing the broken ones.
	 */
	public function testExistingMethodStillDispatches()
	{
		ajax_dispatcher_test_helper::$called = null;

		(new Request())->handleRequest(
			'api.'.ajax_dispatcher_test_helper::class.'.ajax_ping', ['pong']);

		$this->assertSame(['pong'], ajax_dispatcher_test_helper::$called);
	}

	/**
	 * Same again for the Class::method menuaction form (eg. the searchUrl of every et2-select-account,
	 * "EGroupware\Api\Etemplate\Widget\Select::ajax_search"), which resolves down a separate branch:
	 * it reflects on the method to decide whether it can skip instantiating the class. Reflecting on
	 * a method that is not there threw a ReflectionException, so this form needs its own coverage
	 * that the misspelling is reported and the well-spelled one still runs - statically, without the
	 * class being instantiated.
	 */
	public function testStaticMissingMethodIsReportedAsInvalidMenuaction()
	{
		$this->assertInvalidMenuaction(
			ajax_dispatcher_test_helper::class.'::ajax_no_such_static', 'ajax_no_such_static');
	}

	public function testStaticMethodStillDispatches()
	{
		ajax_dispatcher_test_helper::$called = null;

		(new Request())->handleRequest(
			ajax_dispatcher_test_helper::class.'::ajax_static_ping', ['pong']);

		$this->assertSame(['static', 'pong'], ajax_dispatcher_test_helper::$called);
	}

	/**
	 * Pass criteria for a menuaction that names no callable method, asserted for both forms:
	 *
	 * 1. \InvalidArgumentException code 996 - the type json.php answers with a plain HTTP 400 and
	 *    only the message. Any other throwable falls through to ajax_exception_handler(), which
	 *    hands the client the server-side file path and line it came from instead.
	 * 2. the message names the method, so the reader can see what to correct.
	 * 3. the class was never constructed. Rejecting the menuaction has to happen before that, or
	 *    a caller could still set off a constructor's side effects with a menuaction they are not
	 *    allowed to actually call.
	 * 4. a line reached the error log. json.php's InvalidArgumentException catch does not log, so
	 *    without the dispatcher logging it itself nothing about the failed call would survive on
	 *    the server. error_log is redirected to a temp file for the duration, both to read it back
	 *    and to keep the line out of the test run's own output.
	 *
	 * @param string $menuaction to dispatch
	 * @param string $method expected to be named in the message and the log line
	 */
	protected function assertInvalidMenuaction(string $menuaction, string $method)
	{
		ajax_dispatcher_test_helper::$constructed = 0;

		$log = tempnam(sys_get_temp_dir(), 'egw-invalid-menuaction-');
		$error_log = ini_get('error_log') ?: '';
		ini_set('error_log', $log);

		try
		{
			(new Request())->handleRequest($menuaction, []);
			$this->fail('Expected \InvalidArgumentException for menuaction '.$menuaction);
		}
		catch(\InvalidArgumentException $e)
		{
			$this->assertSame(996, $e->getCode(), 'wrong code, json.php answers 400 only for its own codes');
			$this->assertStringContainsString($method, $e->getMessage());
		}
		finally	// also on the fail() above, so a failing test does not leave error_log redirected
		{
			$logged = file_get_contents($log);
			ini_set('error_log', $error_log);
			unlink($log);
		}

		$this->assertStringContainsString($method, $logged, 'nothing logged server-side');
		$this->assertSame(0, ajax_dispatcher_test_helper::$constructed,
			'class was constructed for a menuaction that was rejected');
	}
}

/**
 * Instantiable stand-in for an app's ajax class: cheap to construct, and named so it passes the
 * dispatcher's "class- or function-name must start with ajax" security check.
 *
 * Lives in EGroupware\Api\Json so checkMenuAction() resolves its owning app to "api", which is
 * implicitly allowed for every user - no app rights needed to run this test.
 */
class ajax_dispatcher_test_helper
{
	/**
	 * Parameters of the last ajax_ping() call, or null if it was not called
	 *
	 * @var array|null
	 */
	public static $called = null;

	/**
	 * How often this class has been constructed, to prove a rejected menuaction never builds it
	 *
	 * @var int
	 */
	public static $constructed = 0;

	public function __construct()
	{
		self::$constructed++;
	}

	public function ajax_ping(...$parameters)
	{
		self::$called = $parameters;
	}

	public static function ajax_static_ping(...$parameters)
	{
		self::$called = ['static', ...$parameters];
	}
}
