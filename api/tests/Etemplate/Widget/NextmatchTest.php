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
 * widget in the current template before trusting any of the filters sent with it.
 * The get_rows callback that gets called must always come from the widget's own
 * server-side content - a client-supplied 'get_rows' filter must never be able to
 * pick the callback.
 */
class NextmatchTest extends Etemplate\WidgetBaseTest
{
	const TEST_TEMPLATE = 'api.nextmatch_test';

	/**
	 * Content of "tab_nm" for the extraTabs tests: a server-side get_rows, no initial rows
	 */
	const TAB_NM_CONTENT = array(
		'get_rows' => __CLASS__.'::mock_get_rows',
		'num_rows' => 0,	// lazy: no rows shipped with the page itself
	);

	/**
	 * @var array|null last $query received by mock_get_rows()
	 */
	private static $mock_get_rows_params;

	/**
	 * @var bool whether the client-supplied callback was called
	 */
	private static $client_get_rows_called = false;

	/**
	 * A form_name that does not resolve to a widget must be rejected outright, eg. "calendar",
	 * which calendar's day/week/month/planner views used before they got an endpoint of their own.
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
	 * A nextmatch in a tab the server adds via a tabbox's extraTabs (as hooks do for
	 * Preferences' "Security & Password" popup) is only attached to the template while it
	 * runs, so getElementById() alone can not see it - every fetch after the rows shipped
	 * with the page (sorting, paging, refresh) failed with "Unknown nextmatch".
	 *
	 * Setup: api.nextmatch_test.extra_tab (holding "tab_nm") is added to the "tabs" tabbox
	 * as an extra tab, and the page is rendered with a server-side get_rows for it.
	 * Pass criteria: no InvalidArgumentException, the server-side get_rows ran (total=1) and
	 * the get_rows the client sent along was ignored.
	 */
	public function testAjaxGetRowsResolvesNextmatchInExtraTab()
	{
		$exec_id = $this->extraTabRequest(array('tab_nm' => self::TAB_NM_CONTENT), array(
			'label'    => 'Extra',
			'template' => 'api.nextmatch_test.extra_tab',
		));
		self::$mock_get_rows_params = null;
		self::$client_get_rows_called = false;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'get_rows' => __CLASS__.'::client_get_rows',
		), 'tab_nm');

		$data = $this->responseData();
		$this->assertSame(1, $data['total'] ?? null,
			'nextmatch inside an extraTabs tab did not resolve to its server-side get_rows');
		$this->assertFalse(self::$client_get_rows_called,
			'client-supplied get_rows callback must never be called');
	}

	/**
	 * Only the extraTabs THIS request's server side added may be searched: a template that
	 * exists on disk, and holds a nextmatch of the requested id, but was not added as a tab of
	 * this page must not make the form_name resolve.
	 *
	 * Setup: same page and content as testAjaxGetRowsResolvesNextmatchInExtraTab(), so a
	 * server-side get_rows for "tab_nm" is in the request - but no extraTabs are set.
	 * Pass criteria: InvalidArgumentException, as for any other unknown form_name.
	 */
	public function testAjaxGetRowsRejectsNextmatchInTabNotAddedByRequest()
	{
		$exec_id = $this->extraTabRequest(array('tab_nm' => self::TAB_NM_CONTENT));

		$this->expectException(\InvalidArgumentException::class);
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), 'tab_nm');
	}

	/**
	 * A nextmatch in an extraTabs tab must be requested by its full name, namespaces included:
	 * a tab given its own namespace (the tab's "content") holds "ns[tab_nm]", and its bare id
	 * "tab_nm" must not resolve to it.
	 *
	 * Setup: the extra tab is added with content="ns"; the request has a server-side get_rows
	 * both at "ns[tab_nm]" and at a top-level "tab_nm", so only the widget resolution can tell
	 * the two names apart.
	 * Pass criteria: "ns[tab_nm]" resolves (total=1), "tab_nm" throws InvalidArgumentException.
	 */
	public function testAjaxGetRowsExtraTabRequiresFullNamespace()
	{
		$exec_id = $this->extraTabRequest(array(
			'ns'     => array('tab_nm' => self::TAB_NM_CONTENT),
			'tab_nm' => self::TAB_NM_CONTENT,
		), array(
			'label'    => 'Extra',
			'template' => 'api.nextmatch_test.extra_tab',
			'content'  => 'ns',
		));
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), 'ns[tab_nm]');
		$this->assertSame(1, $this->responseData()['total'] ?? null,
			'namespaced nextmatch inside an extraTabs tab did not resolve by its full name');

		$this->expectException(\InvalidArgumentException::class);
		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(), 'tab_nm');
	}

	/**
	 * Render the test template, optionally adding $tab to its "tabs" tabbox via extraTabs
	 *
	 * Rendering attaches an extra tab to the cached template objects themselves, which a real
	 * ajax_get_rows() request - a new PHP process - starts without, so the cache is reset
	 * afterwards: otherwise getElementById() finds the nextmatch and the extraTabs lookup is
	 * never exercised.
	 *
	 * @param array $content
	 * @param ?array $tab extraTabs entry, null to add none
	 * @return string etemplate_exec_id
	 */
	private function extraTabRequest(array $content, ?array $tab=null)
	{
		$exec_id = $this->templateRequest($content, $tab ? static function(Etemplate $etemplate) use ($tab)
		{
			$etemplate->setElementAttribute('tabs', 'add_tabs', true);
			$etemplate->setElementAttribute('tabs', 'extraTabs', array($tab));
		} : null);

		$cache = new \ReflectionProperty(Template::class, 'cache');
		$cache->setValue(null, array());

		return $exec_id;
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
	 * @param ?callable $prepare called with the Etemplate before exec(), eg. to set element attributes
	 * @return string etemplate_exec_id
	 */
	private function templateRequest(array $content, ?callable $prepare=null)
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();

		Etemplate::reset_request();
		$etemplate = new Etemplate();
		$this->assertTrue($etemplate->read(self::TEST_TEMPLATE, 'test'),
			'could not load nextmatch test template');
		if ($prepare) $prepare($etemplate);
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

	// --- ajax_get_rows(): 'order' allow-list, independent of 'sort' being an array ---

	/**
	 * 'order' is normally re-derived from the allow-list regex applied to $value['sort']['id'],
	 * but only when 'sort' arrives as an array (the shape a column-header click sends). A plain,
	 * non-array 'sort' must not let whatever raw 'order' the client sent bypass that same regex -
	 * a column name/`#customfield` token is all 'order' is ever supposed to carry, and some
	 * storage layers splice it straight into a SQL fragment uninspected.
	 */
	public function testAjaxGetRowsDropsOrderNotMatchingAllowlistWithoutSortArray()
	{
		$exec_id = $this->templateRequest(array('sub_nm' => array(
			'get_rows' => __CLASS__.'::mock_get_rows',
			'num_rows' => 0,
		)));
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'sort'  => 'DESC',    // a plain string, not the ['id' => ..., 'asc' => ...] shape
			'order' => "#mycf OR 1=1-- -",
		), 'sub_nm');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertSame('', $query['order'] ?? null,
			"an 'order' not matching the allow-list regex must be dropped, even when 'sort' is not an array");
	}

	/**
	 * The allow-list must still let ordinary column-name and "#customfield" sort/lettersearch
	 * values through with a non-array 'sort', or every plain single-click sort would break.
	 */
	public function testAjaxGetRowsKeepsAllowlistedOrderWithoutSortArray()
	{
		$exec_id = $this->templateRequest(array('sub_nm' => array(
			'get_rows' => __CLASS__.'::mock_get_rows',
			'num_rows' => 0,
		)));
		self::$mock_get_rows_params = null;

		Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), array(
			'sort'  => 'ASC',
			'order' => '#my cf',
		), 'sub_nm');

		$query = self::$mock_get_rows_params;
		$this->assertNotNull($query, 'get_rows callback did not run');
		$this->assertSame('#my cf', $query['order'] ?? null,
			'a legitimate lettersearch order token must survive validation');
	}

	// --- filters are validated by the widgets of the nextmatch ---

	/**
	 * Fetch rows of "filter_nm", and return what was logged about its filters
	 *
	 * @param array $col_filter col_filter the client sends
	 * @param array $filters further filters the client sends
	 * @param mixed $mode value of the nm_filter_validation server setting: 'enforce', 'report' or false, null to leave it unset
	 * @param bool $report value of the nm_filter_validation_report server setting
	 * @param array $rendered col_filter the nextmatch was rendered with
	 * @return string[] lines logged about the filters
	 */
	private function filterLog(array $col_filter, array $filters=array(), $mode=null, bool $report=false, array $rendered=array()) : array
	{
		$exec_id = $this->templateRequest(array('filter_nm' => array(
			'get_rows' => __CLASS__.'::mock_get_rows',
			'num_rows' => 0,
		)+($rendered ? array('col_filter' => $rendered) : array())));
		self::$mock_get_rows_params = null;

		$log = tempnam(sys_get_temp_dir(), 'nm_report');
		$old_log = ini_set('error_log', $log);
		if ($mode !== null) $GLOBALS['egw_info']['server']['nm_filter_validation'] = $mode;
		if ($report) $GLOBALS['egw_info']['server']['nm_filter_validation_report'] = true;
		try
		{
			Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10),
				array('col_filter' => $col_filter)+$filters, 'filter_nm');
		}
		finally
		{
			unset($GLOBALS['egw_info']['server']['nm_filter_validation'], $GLOBALS['egw_info']['server']['nm_filter_validation_report']);
			ini_set('error_log', (string)$old_log);
			$lines = file($log, FILE_IGNORE_NEW_LINES) ?: array();
			unlink($log);
		}
		return array_values(array_filter($lines, static fn($line) => str_contains($line, 'Nextmatch filter validation')));
	}

	/**
	 * Values a filter-header, the widget a custom filter-header stands for, or a widget of the
	 * filter-template accepts reach get_rows, and nothing is logged.
	 */
	public function testFiltersAWidgetAcceptsArePassedOn()
	{
		$col_filter = array(
			'status'  => 'open',	// option of the et2-nextmatch-header-filter
			'country' => 'DE',		// et2-nextmatch-header-custom with widgetType="select-country"
			'type'    => 'note',	// option of the select in the filter-template
		);
		$lines = $this->filterLog($col_filter, array(), null, true);

		$this->assertSame(array(), $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * When enforcing, a value the filter's widget does not offer, and a filter without any widget,
	 * do not reach get_rows and are logged. No validation error is left behind for the client.
	 */
	public function testFiltersNoWidgetAcceptsAreDropped()
	{
		$lines = implode("\n", $this->filterLog(array(
			'status'  => 'bogus',
			'country' => 'not-a-country',
			'type'    => 'note',
			'nowhere' => 'x',
		), array(), 'enforce'));

		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[status\]="bogus": rejected by et2-nextmatch-header-filter/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[country\]="not-a-country": rejected by et2-select-country/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[nowhere\]="x": no widget validates it/', $lines);
		$this->assertStringNotContainsString('col_filter[type]', $lines);

		$this->assertSame(array('type' => 'note'), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertFalse(Etemplate\Widget::validation_errors(),
			'validating the filters must not leave validation errors behind');
	}

	/**
	 * A filter no widget stands for is passed on, if it is the value the server rendered the
	 * nextmatch with - but not with another value.
	 */
	public function testFilterTheNextmatchWasRenderedWithIsPassedOn()
	{
		$this->filterLog(array('nowhere' => '5'), array(), 'enforce', false, array('nowhere' => 5));
		$this->assertSame(array('nowhere' => '5'), self::$mock_get_rows_params['col_filter'] ?? null);

		$this->filterLog(array('nowhere' => '6'), array(), 'enforce', false, array('nowhere' => 5));
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * A filter only this nextmatch has, outside of col_filter, is passed on if a widget of the
	 * filter-template accepts it.
	 */
	public function testOtherFilterAWidgetAcceptsIsPassedOn()
	{
		$this->filterLog(array(), array('state' => 'archived'));
		$this->assertSame('archived', self::$mock_get_rows_params['state'] ?? null);

		$this->filterLog(array(), array('state' => 'bogus'));
		$this->assertArrayNotHasKey('state', self::$mock_get_rows_params);
	}

	/**
	 * Widgets inside the nextmatch, which it shows in its header or footer slot, validate its
	 * filters too: a template referenced there with the standard filter, and a widget itself.
	 */
	public function testFiltersSlottedIntoTheNextmatchAreValidated()
	{
		$this->filterLog(array('footer_choice' => 'two'), array('filter' => 'all'), 'enforce');
		$this->assertSame('all', self::$mock_get_rows_params['filter'] ?? null);
		$this->assertSame(array('footer_choice' => 'two'), self::$mock_get_rows_params['col_filter'] ?? null);

		$lines = implode("\n", $this->filterLog(array('footer_choice' => 'three'), array('filter' => 'bogus'), 'enforce'));
		$this->assertArrayNotHasKey('filter', self::$mock_get_rows_params);
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertMatchesRegularExpression('/dropped: .* filter="bogus": rejected by et2-select/', $lines);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[footer_choice\]="three": rejected by et2-select/', $lines);
	}

	/**
	 * An entry-search accepts whatever it is given, so the nextmatch checks itself that the
	 * value is an entry the user can read.
	 */
	public function testEntryFilterHasToBeAReadableEntry()
	{
		$contact_id = $GLOBALS['egw_info']['user']['person_id'] ?? null;
		if (!$contact_id || (string)Api\Link::title('addressbook', $contact_id) === '')
		{
			$this->markTestSkipped('test user has no contact of its own to filter by');
		}
		$this->filterLog(array('contact' => (string)$contact_id), array(), 'enforce');
		$this->assertSame(array('contact' => (string)$contact_id), self::$mock_get_rows_params['col_filter'] ?? null);

		$lines = implode("\n", $this->filterLog(array('contact' => '999999999'), array(), 'enforce'));
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[contact\]="999999999": et2-link-entry: no entry the user can read/', $lines);

		// not an id at all
		$this->filterLog(array('contact' => array('id' => array('nested' => array('x')))), array(), 'enforce');
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
	}

	/**
	 * A custom field accepts as filter what its own widget accepts: an option of a select, a
	 * date, a number. It leaves neither a validation error nor its options behind.
	 */
	public function testCustomFieldAcceptsOnlyValuesOfItsType()
	{
		$request = new \ReflectionProperty(Etemplate\Widget::class, 'request');
		$request_before = $request->getValue();
		$request->setValue(null, Etemplate\Request::read());
		$sel_options_before = $request->getValue()->sel_options;
		$cf = new Etemplate\Widget\Customfields('<et2-customfields/>');

		$select = array('type' => 'select', 'needed' => true, 'rows' => 1, 'values' => array('a' => 'A', 'b' => 'B'));
		$this->assertTrue($cf->acceptsValue('choice', $select, 'b'));
		$this->assertFalse($cf->acceptsValue('choice', $select, 'c'));

		$date = array('type' => 'date', 'needed' => false, 'values' => array());
		$this->assertTrue($cf->acceptsValue('when', $date, '2026-11-20'));
		$this->assertFalse($cf->acceptsValue('when', $date, 'not-a-date'));

		$int = array('type' => 'int', 'needed' => false, 'values' => array());
		$this->assertTrue($cf->acceptsValue('count', $int, '12'));
		$this->assertFalse($cf->acceptsValue('count', $int, 'abc'));

		$this->assertFalse(Etemplate\Widget::validation_errors(), 'no validation error must be left behind');
		$sel_options_after = $request->getValue()->sel_options;
		$request->setValue(null, $request_before);
		$this->assertSame($sel_options_before, $sel_options_after, 'the options of the request must be unchanged');
	}

	/**
	 * Fetch rows of a nextmatch of the test template, and return what was logged about its filters
	 *
	 * @param string $nm id of the nextmatch
	 * @param array $filters filters the client sends
	 * @return string lines logged about the filters
	 */
	private function enforcedFilters(string $nm, array $filters) : string
	{
		$exec_id = $this->templateRequest(array($nm => array('get_rows' => __CLASS__.'::mock_get_rows', 'num_rows' => 0)));
		self::$mock_get_rows_params = null;
		$log = tempnam(sys_get_temp_dir(), 'nm_report');
		$old_log = ini_set('error_log', $log);
		$GLOBALS['egw_info']['server']['nm_filter_validation'] = 'enforce';
		try
		{
			Nextmatch::ajax_get_rows($exec_id, array('start' => 0, 'num_rows' => 10), $filters, $nm);
		}
		finally
		{
			unset($GLOBALS['egw_info']['server']['nm_filter_validation']);
			ini_set('error_log', (string)$old_log);
			$lines = (string)file_get_contents($log);
			unlink($log);
		}
		return $lines;
	}

	/**
	 * A nextmatch without filter-template of its own is validated with the widgets of the template
	 * generated for it, the very one the client shows: a widget for each filter-header of the columns,
	 * and the standard filters with the widget they are given.
	 */
	public function testFiltersAreValidatedByTheGeneratedFilterTemplate()
	{
		$url = '/egroupware/api/filter-template.php/api/templates/test/nextmatch_test.xet?1&template=api.nextmatch_test.rows'.
			'&filter=Filter&filter2=Lists&filter2_widget=et2-tree-dropdown&cat_id=Category';
		$template = Nextmatch\FilterTemplate::widgets($url);
		$this->assertNotNull($template, 'no template generated for '.$url);
		// a template name with an underscore is not accepted as "template", so all of the file is used
		$this->assertSame('api.filter', $template->id);

		$widgets = array();
		$template->run(static function($cname, $expand, $widget) use (&$widgets)
		{
			if ($widget->id) $widgets[$widget->id] = $widget->type;
		}, array('', array()));
		foreach(array(
			'filter'              => 'et2-select',
			'filter2'             => 'tree-dropdown',
			'cat_id'              => 'et2-select-cat',
			'col_filter[status]'  => 'et2-select',
			'col_filter[country]' => 'et2-select-country',
			'col_filter[contact]' => 'et2-link-entry',
		) as $id => $type)
		{
			$this->assertSame($type, $widgets[$id] ?? null, "widget for $id");
		}
		// generated for the call, not kept with the templates read from files
		$this->assertFalse(Etemplate\Widget\Template::instance('api.filter', quiet: true));
		$this->assertNull(Nextmatch\FilterTemplate::widgets('/egroupware/api/filter-template.php/api/templates/test/no_such_file.xet?1'));
		$this->assertNull(Nextmatch\FilterTemplate::widgets('/egroupware/api/filter-template.php/api/templates/../../header.inc.xet'));

		// the nextmatch uses them
		$lines = $this->enforcedFilters('gen_nm', array('col_filter' => array('country' => 'DE', 'nowhere' => 'x')));
		$this->assertSame(array('country' => 'DE'), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[nowhere\]="x": no widget validates it/', $lines);

		$lines = $this->enforcedFilters('gen_nm', array('col_filter' => array('country' => 'not-a-country')));
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[country\]="not-a-country": rejected by et2-select-country/', $lines);
	}

	/**
	 * An application with a filter-template of its own is validated with that one, however the
	 * nextmatch gets to it: named by the nextmatch, or found by the name of the rows-template.
	 */
	public function testFiltersAreValidatedByTheFilterTemplateOfTheApplication()
	{
		// named by the filterTemplate attribute
		$this->enforcedFilters('filter_nm', array('col_filter' => array('type' => 'note')));
		$this->assertSame(array('type' => 'note'), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->enforcedFilters('filter_nm', array('col_filter' => array('type' => 'bogus')));
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);

		// "api.nextmatch_conv.filter" for the rows-template "api.nextmatch_conv.rows"
		$this->enforcedFilters('conv_nm', array('col_filter' => array('kind' => 'small')));
		$this->assertSame(array('kind' => 'small'), self::$mock_get_rows_params['col_filter'] ?? null);
		$lines = $this->enforcedFilters('conv_nm', array('col_filter' => array('kind' => 'huge')));
		$this->assertSame(array(), self::$mock_get_rows_params['col_filter'] ?? null);
		$this->assertMatchesRegularExpression('/dropped: .* col_filter\[kind\]="huge": rejected by et2-select/', $lines);
	}

	/**
	 * A filter shown as tree-dropdown offers its options nested, eg. distribution lists below a
	 * node which itself can not be selected. The tree widget accepts the options of all levels,
	 * and nothing else as long as it has options.
	 */
	public function testTreeDropdownValidatesAgainstItsNestedOptions()
	{
		$options = array(
			array('value' => '', 'label' => 'No distribution list'),
			array('value' => 'lists', 'label' => 'Distribution lists', 'unselectable' => true, 'children' => array(
				array('value' => '12', 'label' => 'Friends'),
				array('value' => 14, 'label' => 'Family', 'children' => array(array('value' => '15', 'label' => 'Kids'))),
			)),
			array('id' => '-408', 'text' => 'A group', 'item' => array(array('id' => '-2', 'text' => 'Admins'))),
			'plain' => 'Plain option',
		);
		$this->assertSame(array('', '12', '14', '15', '-408', '-2', 'plain'), Etemplate\Widget\Tree::optionValues($options));

		$request = new \ReflectionProperty(Etemplate\Widget::class, 'request');
		$request_before = $request->getValue();
		$request->setValue(null, Etemplate\Request::read());
		try
		{
			$accepts = static function($value, array $sel_options) use ($request)
			{
				$request->getValue()->sel_options = $sel_options;
				$tree = Etemplate\Widget::factory('et2-tree-dropdown', '<et2-tree-dropdown id="filter2"/>', 'filter2');
				$validated = array();
				$tree->validate('nm', array(), array('nm' => array('filter2' => $value)), $validated);
				$errors = Etemplate\Widget::validation_errors();
				// forget the error for the next call
				(new \ReflectionProperty(Etemplate\Widget::class, 'validation_errors'))->setValue(null, array());
				return !$errors && ($validated['nm']['filter2'] ?? null) == $value;
			};
			$this->assertTrue($accepts('12', array('filter2' => $options)), 'a nested option');
			$this->assertTrue($accepts('15', array('filter2' => $options)), 'an option two levels down');
			$this->assertTrue($accepts('-2', array('filter2' => $options)));
			$this->assertFalse($accepts('99999', array('filter2' => $options)), 'no option');
			$this->assertFalse($accepts('lists', array('filter2' => $options)), 'a node which can not be selected');
			$this->assertTrue($accepts('anything', array()), 'without options there is nothing to check against');
		}
		finally
		{
			$request->setValue(null, $request_before);
		}
	}

	/**
	 * With the report switched on, filters outside of col_filter which get_rows never receives
	 * are logged too: a date no widget of the filter-template stands for, and a filter of the
	 * application. A date its widget accepts, and the parts of the value which are no filter,
	 * are not.
	 */
	public function testFilterReportNamesOtherFiltersNoWidgetAccepts()
	{
		$lines = implode("\n", $this->filterLog(array(), array(
			'startdate'     => '2026-11-20T00:00:00Z',	// et2-date of the filter-template
			'enddate'       => '2026-11-21T00:00:00Z',	// no widget
			'status_filter' => 'deleted',				// no widget, and not registered as extra attribute
			'sort'          => 'ASC',
			'order'         => 'title',
			'search'        => 'something',
		), null, true));

		$this->assertMatchesRegularExpression('/ enddate="2026-11-21T00:00:00Z": no widget validates it$/m', $lines);
		$this->assertMatchesRegularExpression('/ status_filter="deleted": no widget validates it, and it is not passed on to get_rows$/m', $lines);
		foreach(array('startdate', 'sort', 'order', 'search') as $name)
		{
			$this->assertStringNotContainsString(" $name=", $lines);
		}
		$this->assertArrayNotHasKey('status_filter', self::$mock_get_rows_params);
	}

	/**
	 * By default nothing is dropped: what enforcing would drop is passed on, logged as "would
	 * drop" and sent as warning to the console of the browser.
	 */
	public function testFilterValidationOnlyReportsByDefault()
	{
		$col_filter = array('status' => 'bogus', 'nowhere' => 'x', 'type' => 'note');
		$lines = implode("\n", $this->filterLog($col_filter));

		$this->assertMatchesRegularExpression('/would drop: .* col_filter\[status\]="bogus": rejected by et2-nextmatch-header-filter/', $lines);
		$this->assertMatchesRegularExpression('/would drop: .* col_filter\[nowhere\]="x": no widget validates it/', $lines);
		$this->assertStringNotContainsString('col_filter[type]', $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);

		$warnings = array_filter($this->ajax_response->returnResult(), static fn($command) =>
			$command['type'] === 'apply' && $command['data']['func'] === 'egw.debug');
		$this->assertCount(2, $warnings, 'each filter which would be dropped is a warning in the console of the browser');
		$this->assertStringContainsString('would drop', json_encode($warnings));
	}

	/**
	 * nm_filter_validation = false switches the check off: everything is passed on, nothing logged.
	 */
	public function testFilterValidationCanBeSwitchedOff()
	{
		$col_filter = array('status' => 'bogus', 'nowhere' => 'x');
		$lines = $this->filterLog($col_filter, array(), false);

		$this->assertSame(array(), $lines);
		$this->assertSame($col_filter, self::$mock_get_rows_params['col_filter'] ?? null);
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
