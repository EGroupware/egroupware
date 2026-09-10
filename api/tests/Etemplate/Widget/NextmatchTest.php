<?php

/**
 * Test file for Nextmatch::ajax_get_rows()
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

/**
 * ajax_get_rows() requires $form_name to resolve to a real nextmatch/historylog
 * widget in the current template before trusting any of the filters sent with it -
 * unless $form_name is registered in Nextmatch::$raw_form_names, which some apps use
 * for views that fetch rows via egw.dataFetch() without a backing widget (eg.
 * calendar's day/week/month/planner views, which pass the app name as $form_name).
 * In both cases, the actual get_rows callback that gets called must always come from
 * the server side (either the widget's own content, or this allowlist) - a client-
 * supplied 'get_rows' filter must never be able to pick the callback.
 */
class NextmatchTest extends Etemplate\WidgetBaseTest
{
	const TEST_TEMPLATE = 'api.nextmatch_test';

	/**
	 * @var array|null backup of Nextmatch::$raw_form_names, restored in tearDown
	 */
	private $raw_form_names_backup;

	/**
	 * @var array|null last $query received by mock_get_rows()
	 */
	private static $mock_get_rows_params;

	/**
	 * @var bool whether the client-supplied callback was called
	 */
	private static $client_get_rows_called = false;

	protected function tearDown(): void
	{
		if ($this->raw_form_names_backup !== null)
		{
			$ref = new \ReflectionProperty(Nextmatch::class, 'raw_form_names');
			$ref->setAccessible(true);
			$ref->setValue(null, $this->raw_form_names_backup);
			$this->raw_form_names_backup = null;
		}
		parent::tearDown();
	}

	/**
	 * A form_name that resolves neither to a widget nor to a registered raw
	 * form-name must still be rejected outright.
	 */
	public function testAjaxGetRowsRejectsUnresolvableFormName()
	{
		$request = Etemplate\Request::read();
		$exec_id = $request->id();
		$form_name = 'phpunit_unresolvable_'.bin2hex(random_bytes(4));
		$request->content = array($form_name => array());
		unset($request);

		$this->expectException(\InvalidArgumentException::class);
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), $form_name);
	}

	/**
	 * A registered "raw" (non-widget) form_name must dispatch to its server-side
	 * registered get_rows callback, even if the client sends its own 'get_rows'
	 * filter alongside it - the client-supplied value must be ignored.
	 */
	public function testAjaxGetRowsUsesRegisteredCallbackForRawFormName()
	{
		$form_name = 'phpunit_raw_'.bin2hex(random_bytes(4));

		$ref = new \ReflectionProperty(Nextmatch::class, 'raw_form_names');
		$ref->setAccessible(true);
		$this->raw_form_names_backup = $ref->getValue();
		$ref->setValue(null, $this->raw_form_names_backup + array(
			$form_name => __CLASS__.'::mock_get_rows',
		));

		$request = Etemplate\Request::read();
		$exec_id = $request->id();
		$request->content = array($form_name => array());
		unset($request);

		$filters = array(
			// must be ignored - the registered callback above must run instead
			'get_rows' => '.EGroupware\\Api\\Accounts.save',
		);

		self::$mock_get_rows_params = null;
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), $filters, $form_name);

		$this->assertNotNull(self::$mock_get_rows_params, 'registered raw get_rows callback was not called');
		$this->assertArrayNotHasKey('account_id', self::$mock_get_rows_params ?? array(),
			'client-supplied filter data leaked into the registered callback beyond what it should see');

		$response = $this->ajax_response->returnResult();
		$data = null;
		foreach ($response as $command)
		{
			if ($command['type'] == 'data')
			{
				$data = $command['data'];
				break;
			}
		}
		$this->assertEquals(1, $data['total'] ?? null);
	}

	/**
	 * Historylog's default row source is defined client-side, so the server must
	 * restore its own trusted default after discarding the client callback.
	 *
	 * The random record id ensures the real history callback returns integer 0;
	 * the test fails if dispatch is skipped (false) or the client callback runs.
	 */
	public function testAjaxGetRowsUsesDefaultHistoryCallback()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history', $record_id, Api\Storage\History::class.'::get_rows');
		self::$client_get_rows_called = false;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id' => $record_id,
			'appname' => 'api',
			'get_rows' => __CLASS__.'::client_get_rows',
		), 'history');

		$data = $this->responseData();
		$this->assertSame(0, $data['total'] ?? null,
			'default history callback should return integer zero for an unknown record');
		$this->assertFalse(self::$client_get_rows_called,
			'client-supplied history callback must never be called');
	}

	/**
	 * A historylog may explicitly configure a different callback in its server-side
	 * template.  That trusted override must win over a client-supplied callback.
	 */
	public function testAjaxGetRowsUsesHistoryTemplateCallback()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');
		self::$mock_get_rows_params = null;
		self::$client_get_rows_called = false;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id' => $record_id,
			'appname' => 'api',
			'get_rows' => __CLASS__.'::client_get_rows',
		), 'history_custom');

		$data = $this->responseData();
		$this->assertSame(1, $data['total'] ?? null,
			'server-side history callback override was not called');
		$this->assertNotNull(self::$mock_get_rows_params,
			'server-side history callback did not receive the query');
		$this->assertFalse(self::$client_get_rows_called,
			'client-supplied history callback must never be called');
	}

	/**
	 * A nextmatch living inside a bare <et2-template> reference tag (as any lazy-loaded
	 * tab-panel does, eg. tracker's "Comments" tab) must resolve just as well as one in the
	 * template itself: such a reference is NOT expanded while the referencing template gets
	 * parsed, so searching only its (non-existing) children rejected the form_name outright
	 * ("Unknown nextmatch/historylog widget 'replies'!" -> 400 on opening the tab).
	 *
	 * Setup: the test template references api.nextmatch_test.sub_template (which holds the
	 * "sub_nm" nextmatch) as a bare tag, and is rendered with a server-side get_rows for it.
	 * Pass criteria: no InvalidArgumentException, the server-side get_rows ran (total=1) and
	 * the get_rows the client sent along was ignored.
	 */
	public function testAjaxGetRowsResolvesNextmatchInReferencedTemplate()
	{
		$exec_id = $this->templateRequest(array('sub_nm' => array(
			'get_rows' => __CLASS__.'::mock_get_rows',
			'num_rows' => 0,	// lazy: no rows shipped with the page itself
		)));
		self::$mock_get_rows_params = null;
		self::$client_get_rows_called = false;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'get_rows' => __CLASS__.'::client_get_rows',
		), 'sub_nm');

		$data = $this->responseData();
		$this->assertSame(1, $data['total'] ?? null,
			'nextmatch inside a bare <et2-template> reference did not resolve to its server-side get_rows');
		$this->assertFalse(self::$client_get_rows_called,
			'client-supplied get_rows callback must never be called');
	}

	/**
	 * Render the test template to create the server-side request cache used by
	 * ajax_get_rows() (see templateRequest()).  Returning an exec id proves the
	 * history widget resolved.
	 *
	 * Also asserts that HistoryLog::beforeSendToClient() has already seeded the
	 * trusted 'get_rows' into the request content at render time - this is what
	 * lets Nextmatch::ajax_get_rows() stay completely generic about historylog,
	 * so a regression here should fail at this layer, not just show up as a
	 * wrong 'total' from ajax_get_rows().
	 *
	 * @param string $expected_get_rows the get_rows callback beforeSendToClient()
	 *	should have stored server-side for $form_name
	 */
	private function historyRequest($form_name, $record_id, $expected_get_rows)
	{
		$exec_id = $this->templateRequest(array(
			$form_name => array(
				'id' => $record_id,
				'app' => 'api',
				'status-widgets' => array(),
			),
		));

		// $handle_not_found=false: never let a "not found" fall through to
		// Etemplate\Request::read()'s web-request-only redirect/exit() fallback
		$stored = Etemplate\Request::read($exec_id, false);
		$this->assertSame($expected_get_rows, $stored->content[$form_name]['get_rows'] ?? null,
			'HistoryLog::beforeSendToClient() did not seed the trusted get_rows into request content');

		return $exec_id;
	}

	/**
	 * Render the test template with the given content to create the server-side
	 * request cache used by ajax_get_rows(), and return its exec id.
	 *
	 * The request created here is stored server-side via a real PHP session
	 * (WidgetBaseTest sets Request::$request_class to Request\Session). An
	 * earlier test's successful Nextmatch::ajax_get_rows() dispatch closes that
	 * session (its normal, correct end-of-request commit_session() call) -
	 * harmless for a real one-request-per-process web call, but fatal here:
	 * every test in this file shares one long-running PHP process, so a closed
	 * session is never implicitly reopened between tests. Reopen it explicitly
	 * before relying on it. Without this, a later Etemplate\Request::read()
	 * would find nothing (Cache::setSession() silently no-ops on a closed
	 * session instead of writing $_SESSION), hit its own "session expired"
	 * fallback, and - since that fallback assumes a real web request - call
	 * exit(), killing the whole PHPUnit process instead of just failing a test.
	 *
	 * @param array $content
	 * @return string etemplate_exec_id
	 */
	private function templateRequest(array $content)
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();

		Etemplate::reset_request();
		$etemplate = new Etemplate();
		$this->assertTrue($etemplate->read(self::TEST_TEMPLATE, 'test'),
			'could not load nextmatch test template');
		$result = $this->mockedExec($etemplate, $content);

		$exec_id = null;
		foreach ($result as $command)
		{
			if ($command['type'] === 'et2_load')
			{
				$exec_id = $command['data']['data']['etemplate_exec_id'] ?? null;
				break;
			}
		}
		$this->assertNotEmpty($exec_id, 'nextmatch test template did not create an exec id');

		$this->ajax_response->initResponseArray();
		return $exec_id;
	}

	/**
	 * Return the data command generated by ajax_get_rows().
	 */
	private function responseData()
	{
		foreach ($this->ajax_response->returnResult() as $command)
		{
			if ($command['type'] === 'data')
			{
				return $command['data'];
			}
		}
		$this->fail('ajax_get_rows() did not return a data command');
	}

	/**
	 * Stand-in get_rows callback, used as the registered target in
	 * testAjaxGetRowsUsesRegisteredCallbackForRawFormName()
	 */
	public static function mock_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$mock_get_rows_params = $query;
		$rows = array(array('id' => 1));
		return 1;
	}

	/**
	 * Callback supplied as untrusted client input.  No test may dispatch here.
	 */
	public static function client_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$client_get_rows_called = true;
		return 99;
	}
}
