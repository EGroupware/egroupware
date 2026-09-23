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
	 * csv_export is not a value, it is a per-request instruction to get_rows(): "do not store this
	 * query in the session".  ajax_get_rows() sets it for a single-row refresh, and its own
	 * change-detection loop then wrote every changed key back into the stored request content - so
	 * the flag survived into every later query, which all then claimed to be refreshes.
	 *
	 * The damage is invisible in the list itself: get_rows() keeps returning the right rows, it
	 * just stops caching the query.  Everything that reads that cache afterwards - a "select all"
	 * expansion, addressbook's delete_list - silently used whichever filters were in force when
	 * the page was opened.  Since it takes one single-row refresh to arm, and ajax context-menu
	 * actions refresh exactly one row, the first action on a list poisoned every action after it.
	 */
	public function testCsvExportFlagDoesNotSurviveIntoTheNextQuery()
	{
		$exec_id = $this->templateRequest(array('sub_nm' => array(
			'get_rows' => __CLASS__.'::mock_get_rows',
			'row_id'   => 'id',
			'num_rows' => 0,
		)));

		// a single-row refresh, as an ajax context-menu action triggers
		self::$mock_get_rows_params = null;
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10, 'refresh' => '1'),
			array(), 'sub_nm');
		$this->assertSame('refresh', self::$mock_get_rows_params['csv_export'] ?? null,
			'a single-row refresh should still tell get_rows not to store the query');

		// ...and now an ordinary paged query, which must be storable again
		$this->ajax_response->initResponseArray();
		self::$mock_get_rows_params = null;
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), 'sub_nm');
		$this->assertArrayNotHasKey('csv_export', (array)self::$mock_get_rows_params,
			'the refresh flag must not be carried over into the next query');

		$stored = Etemplate\Request::read($exec_id, false);
		$this->assertArrayNotHasKey('csv_export', (array)($stored->content['sub_nm'] ?? array()),
			'csv_export must never be written into the stored request content');
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
	 * Apps are expected to put 'app' in the history log's content, and every in-tree one does, but
	 * nothing enforces it.  History::get_rows() scopes every query by appname, so a missing one
	 * would silently return an empty history rather than failing visibly - fall back to the
	 * current app, as History's own constructor does.
	 *
	 * The test template's content sets app=api, so this drops it to prove the fallback.
	 */
	public function testHistoryValidateFallsBackToCurrentAppWithoutAppInContent()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');

		// Drop 'app' from the stored request content, as an app that never set it would leave it
		$stored = Etemplate\Request::read($exec_id, false);
		$content = $stored->content;
		unset($content['history_custom']['app']);
		$stored->content = $content;
		unset($stored);

		$previous_app = $GLOBALS['egw_info']['flags']['currentapp'] ?? null;
		$GLOBALS['egw_info']['flags']['currentapp'] = 'infolog';
		self::$mock_get_rows_params = null;
		try
		{
			Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
				'record_id' => $record_id,
				'appname'   => 'addressbook',   // the client's value must still be ignored
			), 'history_custom');
		}
		finally
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = $previous_app;
		}

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertNotSame('addressbook', $query['appname'] ?? null,
			"the client's appname must never be honoured, even when the content has none");
		$this->assertNotSame('', (string)($query['appname'] ?? ''),
			'an empty appname would make get_rows() return an empty history with no indication why');
	}

	// --- HistoryLog::validate() filter allow-list ---

	/**
	 * The history log's validate() is what sanitizes client filters for ajax_get_rows(), which
	 * then *replaces* the client's filters with whatever comes back.  Only allow-listed keys may
	 * survive: anything else would be handed to History::get_rows() as part of its query, where a
	 * stray key becomes a WHERE clause (and 'order'/'sort' would reach an ORDER BY).
	 */
	public function testHistoryValidateDropsNonAllowlistedFilters()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id' => $record_id,
			'appname'   => 'api',
			// calendar sets a raw SQL fragment under this key server-side; a client must never be
			// able to supply one
			'filter'    => "1=1 OR history_status LIKE '%'",
			'order'     => 'history_owner',
			'sort'      => 'ASC',
			'csv_export'=> 'children',
			'row_id'    => 'id',
		), 'history_custom');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertArrayNotHasKey('filter', $query,
			"a client-supplied raw SQL 'filter' must never reach get_rows()");
		$this->assertArrayNotHasKey('csv_export', $query,
			'an un-allow-listed filter key must be dropped');
		// 'order'/'sort' are set unconditionally by ajax_get_rows() itself from $value['sort'],
		// so assert the client's values specifically did not survive
		$this->assertNotSame('history_owner', $query['order'] ?? null,
			"a client-supplied 'order' must not reach get_rows() - the history sort is fixed");
	}

	/**
	 * `filter` is a raw SQL fragment, and since the history log now *honours* it (calendar uses it
	 * to scope participant changes to one recurrence) the allow-list is the only thing standing
	 * between a client and arbitrary SQL in the WHERE clause.
	 *
	 * This is the companion to testGetRowsAppliesServerSideFilterFragment in HistoryTest: that one
	 * proves a server-set fragment works, this one proves a client-set one never arrives.
	 */
	public function testHistoryValidateNeverAcceptsAClientSqlFilter()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id' => $record_id,
			'appname'   => 'api',
			'filter'    => array("1=1 OR history_appname LIKE '%'"),
		), 'history_custom');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertArrayNotHasKey('filter', $query,
			'a client-supplied SQL fragment must never reach get_rows() - it is trusted input, '.
			'settable only from the app\'s own server-side content');
	}

	/**
	 * The allow-listed filters must actually get through, or filtering would silently do nothing.
	 */
	public function testHistoryValidateKeepsAllowedFilters()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id'  => $record_id,
			'appname'    => 'api',
			'search'     => 'needle',
			'col_filter' => array(
				'status'         => array('E', '~file~'),
				'owner'          => 5,
				'user_ts'        => array('from' => '2020-01-01', 'to' => '2020-12-31'),
				'not_a_column'   => 'dropped',
			),
		), 'history_custom');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertSame('needle', $query['search'] ?? null, 'search must survive validate()');
		$this->assertSame(array('E', '~file~'), $query['col_filter']['status'] ?? null,
			'col_filter[status] must survive validate()');
		$this->assertSame(5, $query['col_filter']['owner'] ?? null,
			'col_filter[owner] must survive validate()');
		$this->assertSame(array('from' => '2020-01-01', 'to' => '2020-12-31'), $query['col_filter']['user_ts'] ?? null,
			'col_filter[user_ts] must survive validate()');
		$this->assertArrayNotHasKey('not_a_column', $query['col_filter'],
			'an un-allow-listed col_filter column must be dropped');
	}

	/**
	 * Which record's history is returned is the server's decision, not the client's.
	 *
	 * History::get_rows() does no permission check, so honouring a client-supplied
	 * record_id/appname would let anyone read any entry's history in any app.  The client still
	 * sends them (that is what marks a row request), but the values must be overwritten from the
	 * server's own content.
	 */
	public function testHistoryValidateIgnoresClientSuppliedRecord()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->historyRequest('history_custom', $record_id, __CLASS__.'::mock_get_rows');
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id' => 'somebody-elses-entry',
			'appname'   => 'addressbook',
		), 'history_custom');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertSame($record_id, $query['record_id'] ?? null,
			"get_rows() must receive the server's record_id, not the client's");
		$this->assertSame('api', $query['appname'] ?? null,
			"get_rows() must receive the server's appname, not the client's");
	}

	/**
	 * The history log is a display widget and returns no value, so validate() must contribute
	 * nothing to a real form submit - not even an empty/null entry.
	 *
	 * Called the way Etemplate's submit walk calls it (no filter keys at all), rather than
	 * through ajax_get_rows().
	 */
	public function testHistoryValidateWritesNothingOnSubmit()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$this->historyRequest('history', $record_id, Api\Storage\History::class.'::get_rows');

		$template = Etemplate\Widget\Template::instance(self::TEST_TEMPLATE, 'test');
		$this->assertNotFalse($template, 'could not re-read the test template');
		$widget = $template->getElementById('history', 'historylog')
			?? $template->getElementById('history', 'et2-historylog');
		$this->assertNotNull($widget, 'could not find the historylog widget in the test template');

		// What a submit looks like for a widget that is not an input: nothing under its id
		$validated = array();
		$widget->validate('', array('cont' => array()), array(), $validated);
		$this->assertSame(array(), $validated,
			'a form submit must not produce any validated content for the history log');

		// Even if something stray arrives under its id, only allow-listed keys may come out -
		// and a value with none of them must still produce nothing
		$validated = array();
		$widget->validate('', array('cont' => array()), array('history' => array('id' => 5, 'app' => 'infolog')), $validated);
		$this->assertSame(array(), $validated,
			'content without any allow-listed filter key must still produce nothing');
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
