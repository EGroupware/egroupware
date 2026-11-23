<?php

/**
 * Test file for the history log widget's server-side validation of its filters
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
 * A history log fetches its rows through Nextmatch::ajax_get_rows(), which runs
 * HistoryLog::validate() on the filters the client sent and passes only what comes back on to
 * the history log's get_rows.  So validate() decides which filters reach the query, with which
 * values, and for which entry.
 */
class HistoryLogTest extends Etemplate\WidgetBaseTest
{
	const TEST_TEMPLATE = 'api.historylog_test';

	/**
	 * @var array|null last $query received by mock_get_rows()
	 */
	private static $mock_get_rows_params;

	/**
	 * Stand-in get_rows callback of "history_custom" in the test template
	 */
	public static function mock_get_rows(&$query, &$rows, &$readonlys)
	{
		self::$mock_get_rows_params = $query;
		$rows = array(array('id' => 1));
		return 1;
	}

	/**
	 * Render the test template to create the server-side request cache used by
	 * ajax_get_rows(), and return its exec id.
	 *
	 * The request is stored in a real PHP session, which a previous ajax_get_rows() closed
	 * (its commit_session()), and which is not reopened implicitly between tests sharing this
	 * process - without reopening it, Etemplate\Request::read() would find nothing and exit().
	 *
	 * @param array $content
	 * @param ?array $sel_options
	 * @return string etemplate_exec_id
	 */
	private function templateRequest(array $content, ?array $sel_options=null)
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();

		Etemplate::reset_request();
		$etemplate = new Etemplate();
		$this->assertTrue($etemplate->read(self::TEST_TEMPLATE, 'test'),
			'could not load historylog test template');
		$result = $this->mockedExec($etemplate, $content, $sel_options);

		$exec_id = null;
		foreach ($result as $command)
		{
			if ($command['type'] === 'et2_load')
			{
				$exec_id = $command['data']['data']['etemplate_exec_id'] ?? null;
				break;
			}
		}
		$this->assertNotEmpty($exec_id, 'historylog test template did not create an exec id');

		$this->ajax_response->initResponseArray();
		return $exec_id;
	}

	/**
	 * Render a history log for $record_id of app "api", and check HistoryLog::beforeSendToClient()
	 * stored the trusted get_rows callback in the request content.
	 *
	 * @param string $form_name id of the history log in the test template
	 * @param string $record_id
	 * @param string $expected_get_rows the get_rows callback beforeSendToClient() should have stored
	 * @return string etemplate_exec_id
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
				'status'         => array('~link~', '~file~'),
				'owner'          => 5,
				'user_ts'        => array('from' => '2020-01-01', 'to' => '2020-12-31'),
				'not_a_column'   => 'dropped',
			),
		), 'history_custom');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertSame('needle', $query['search'] ?? null, 'search must survive validate()');
		$this->assertSame(array('~link~', '~file~'), $query['col_filter']['status'] ?? null,
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

	// --- HistoryLog::validate() checks the values of the filters ---

	/**
	 * Fetch rows of "history_custom", and return what was logged about its filters
	 *
	 * The history log is rendered the way apps do it: their field labels at the root
	 * sel_options['status'], the widgets of some fields in status-widgets.
	 *
	 * @param array $filters filters the client sends, record_id and appname are added
	 * @param mixed $mode value of the nm_filter_validation server setting: 'enforce', 'report' or false, null to leave it unset
	 * @param ?string $app app of the entry the history log is rendered for
	 * @return string[] lines logged about the filters
	 */
	private function historyFilterLog(array $filters, $mode=null, string $app='api') : array
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->templateRequest(array(
			'history_custom' => array(
				'id'  => $record_id,
				'app' => $app,
				'status-widgets' => array('Co' => 'select-percent'),
			),
		), array(
			'status' => array('De' => 'Description', 'Co' => 'Completed'),
		));
		self::$mock_get_rows_params = null;

		$log = tempnam(sys_get_temp_dir(), 'history_report');
		$old_log = ini_set('error_log', $log);
		if ($mode !== null) $GLOBALS['egw_info']['server']['nm_filter_validation'] = $mode;
		try
		{
			Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10),
				$filters+array('record_id' => $record_id, 'appname' => $app), 'history_custom');
		}
		finally
		{
			unset($GLOBALS['egw_info']['server']['nm_filter_validation']);
			ini_set('error_log', (string)$old_log);
			$lines = file($log, FILE_IGNORE_NEW_LINES) ?: array();
			unlink($log);
		}
		$this->assertNotNull(self::$mock_get_rows_params, 'get_rows callback did not run');
		return array_values(array_filter($lines, static fn($line) => str_contains($line, 'Nextmatch filter validation')));
	}

	/**
	 * Values the history log's own filters offer reach get_rows, and nothing is logged.
	 */
	public function testHistoryFiltersWithValidValuesArePassedOn()
	{
		$col_filter = array(
			// app label from the root sel_options, a status-widgets key, and a built-in status
			'status'  => array('De', 'Co', '~file~', 'user_agent_action'),
			'owner'   => (string)$GLOBALS['egw_info']['user']['account_id'],
			'user_ts' => array('from' => '2026-01-01T00:00:00Z', 'to' => ''),
		);
		$lines = $this->historyFilterLog(array('search' => 'needle', 'col_filter' => $col_filter), 'enforce');

		$this->assertSame(array(), $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertSame('needle', self::$mock_get_rows_params['search'] ?? null);
	}

	/**
	 * Phase 1 of the rollout: a value failing its check is still passed on, and logged as "would
	 * drop" in the same wording as the filters of a nextmatch.
	 */
	public function testHistoryFiltersWithInvalidValuesAreReportedButPassedOn()
	{
		$col_filter = array(
			'status'  => array('De', 'bogus'),
			'owner'   => 'x',
			'user_ts' => array('from' => 'not a date'),
		);
		$lines = implode("\n", $this->historyFilterLog(array('col_filter' => $col_filter)));

		$this->assertMatchesRegularExpression('/would drop: api\.historylog_test history_custom col_filter\[status\]=\["De","bogus"\]: not a status of this history log/', $lines);
		$this->assertMatchesRegularExpression('/would drop: .* col_filter\[owner\]="x": not an account/', $lines);
		$this->assertMatchesRegularExpression('/would drop: .* col_filter\[user_ts\]=\{"from":"not a date"\}: not a date/', $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * When enforcing, values failing their check do not reach get_rows, valid ones do.
	 */
	public function testHistoryFiltersWithInvalidValuesAreDroppedWhenEnforcing()
	{
		$lines = implode("\n", $this->historyFilterLog(array('col_filter' => array(
			'status'  => array(array('De')),
			'owner'   => array((string)$GLOBALS['egw_info']['user']['account_id'], '2147483647'),
			'user_ts' => array('from' => '2026-01-01', 'evil' => '2026-01-02'),
			'#phpunit_no_such_field' => '1',
		)), 'enforce'));

		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[status\]=\[\["De"\]\]: not a status/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[owner\]=.*: not an account/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[user_ts\]=.*: not a date range/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[#phpunit_no_such_field\]="1": not a custom field of api/', $lines);
		$this->assertArrayNotHasKey('col_filter', self::$mock_get_rows_params);
	}

	/**
	 * A custom field of the app is a valid filter and status, an unknown one is not.
	 */
	public function testHistoryCustomfieldFilters()
	{
		$app = null;
		foreach(array('infolog', 'addressbook', 'calendar', 'tracker') as $candidate)
		{
			if (($cfs = Api\Storage\Customfields::get($candidate)))
			{
				$app = $candidate;
				$name = array_key_first($cfs);
				break;
			}
		}
		if (!$app)
		{
			$this->markTestSkipped('no app with custom fields to test with');
		}
		$lines = $this->historyFilterLog(array('col_filter' => array(
			'#'.$name => '1',
			'status'  => '#'.$name,
		)), 'enforce', $app);

		$this->assertSame(array(), $lines);
		$this->assertSame(array('#'.$name => '1', 'status' => '#'.$name), self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * Columns the history log does not have, and another entry than the rendered one, are dropped
	 * whatever the mode - and logged, so it shows up next to the values that would be dropped.
	 */
	public function testHistoryDroppedFiltersAreLogged()
	{
		$lines = implode("\n", $this->historyFilterLog(array(
			'record_id'  => 'somebody-elses-entry',
			'col_filter' => array('history_appname' => 'addressbook'),
		)));

		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[history_appname\]="addressbook": not a column of the history log/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* history_custom record_id="somebody-elses-entry": replaced by "phpunit-[0-9a-f]+", the entry the history log was rendered for/', $lines);
		$this->assertArrayNotHasKey('col_filter', self::$mock_get_rows_params);
	}

	/**
	 * Column filters the app set server-side scope the history (Policy scopes it to one
	 * addressbook by cmd_account), so a client column filter must not remove or change them.
	 */
	public function testHistoryKeepsServerSetColumnFilters()
	{
		$record_id = 'phpunit-'.bin2hex(random_bytes(8));
		$exec_id = $this->templateRequest(array(
			'history_custom' => array(
				'id'  => $record_id,
				'app' => 'api',
				'col_filter' => array('cmd_parent IS NULL', 'cmd_account' => -5),
			),
		));
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'record_id'  => $record_id,
			'appname'    => 'api',
			'col_filter' => array('status' => array('~file~'), 'cmd_account' => 7),
		), 'history_custom');

		$this->assertSame(array('status' => array('~file~'), 0 => 'cmd_parent IS NULL', 'cmd_account' => -5),
			self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * nm_filter_validation = false switches the value checks off, as for nextmatch filters.
	 */
	public function testHistoryFilterValidationCanBeSwitchedOff()
	{
		$col_filter = array('status' => 'bogus', 'owner' => 'x');
		$lines = $this->historyFilterLog(array('col_filter' => $col_filter), false);

		$this->assertSame(array(), $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);
	}
}
