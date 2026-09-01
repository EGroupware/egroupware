<?php
/**
 * EGroupware Api: api.queue job isolation regression test
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
 * A menuaction called via a static ajax_* method, used as the queue's "always throws" job.
 *
 * Namespace EGroupware\Api\Json puts this in appName 'api' (see the appName-derivation for
 * '::'-syntax menuactions in Request::handleRequest()), which is exempt from the normal
 * app-membership check - same reasoning RequestSecurityTest.php documents for its own payloads.
 */
class QueueIsolationTestFailingAjax
{
	public static function ajax_fail()
	{
		throw new \Exception('queue isolation test: this job always fails');
	}
}

/**
 * The queue's "always succeeds" job, so the test can prove a sibling job's own response is
 * unaffected by another job throwing in the same api.queue batch.
 */
class QueueIsolationTestOkAjax
{
	public static function ajax_ok()
	{
		Response::get()->data('ok');
	}
}

/**
 * Regression coverage for Api\Json\Request::parseRequest()'s 'api.queue' handling: before this
 * fix, ANY queued job throwing aborted the whole batch (uncaught exception propagates out of
 * parseRequest(), through json.php's global exception handler, which sends one generic error
 * and exit()s) - so every OTHER queued job's own client-side promise (see Jsonq.jsonqSend())
 * was left permanently unresolved, never mind rejected. That defeats the whole point of
 * batching several independent calls: one denied/failing item must not sink the rest.
 *
 * Setup strategy mirrors RequestSecurityTest.php: extends LoggedInTest because Request.php has
 * a load-time side effect (Widget::scanForWidgets(), bottom of the file) that needs a live DB
 * connection just to autoload the class - a bare TestCase fails before any test body runs.
 */
class QueueIsolationTest extends LoggedInTest
{
	/**
	 * A failing job must not prevent a sibling job in the same batch from completing normally,
	 * and must not throw out of parseRequest() itself.
	 */
	public function testFailingJobDoesNotAbortSiblingJobs()
	{
		// reset any response state a previous test in this process may have left behind -
		// Response is a process-wide singleton (Response::get()), see Response::$response
		Response::get()->initResponseArray();

		$queue = [
			'fails' => [
				'menuaction' => QueueIsolationTestFailingAjax::class.'::ajax_fail',
				'parameters' => [],
			],
			'succeeds' => [
				'menuaction' => QueueIsolationTestOkAjax::class.'::ajax_ok',
				'parameters' => [],
			],
		];
		$input = json_encode(['request' => ['parameters' => [$queue]]]);

		$request = new Request();
		// must NOT throw - that's the regression this test guards against
		$request->parseRequest('api.queue', $input);

		$result = Response::returnResult();
		$this->assertSame('data', $result[0]['type']);
		$responses = $result[0]['data'];

		// the failing job is reported as its own isolated error, not a fatal batch abort
		$this->assertNotEmpty($responses['fails'], 'failing job should still get a response entry');
		$this->assertSame('error', $responses['fails'][0]['type']);
		$this->assertStringContainsString('queue isolation test: this job always fails', $responses['fails'][0]['data']);

		// the sibling job completed completely normally, unaffected by the other job's failure
		$this->assertNotEmpty($responses['succeeds'], 'sibling job should still get its normal response');
		$this->assertSame('data', $responses['succeeds'][0]['type']);
		$this->assertSame('ok', $responses['succeeds'][0]['data']);
	}
}
