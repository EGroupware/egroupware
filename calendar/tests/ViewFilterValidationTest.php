<?php
/**
 * Test that the filters of calendar's day, week, month and planner views are checked
 *
 * @link https://www.egroupware.org
 * @package calendar
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\calendar;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

require_once realpath(__DIR__.'/../../api/tests/Etemplate/WidgetBaseTest.php');

/**
 * The views other than the list have no nextmatch: app.ts fetches their events from
 * calendar_uiviews::ajax_get_rows(), which builds the query for calendar_uilist::get_rows() from what
 * the views send, checking each part (calendar_uiviews::viewsQuery()).
 *
 * SETUP
 * viewsQuery() is called directly to see what would reach get_rows; one test calls ajax_get_rows()
 * as the client does, with the real get_rows.  Nothing is written to the database.
 *
 * PASS CRITERIA
 * What the views send with valid values passes unchanged and is not logged.  An invalid value of each
 * filter, and anything the views do not send, is logged as "would drop" and sent as warning to the
 * browser, but passed on, unless validation is enforced: then only what is valid is passed on.
 */
class ViewFilterValidationTest extends Etemplate\WidgetBaseTest
{
	protected function tearDown() : void
	{
		unset($GLOBALS['egw_info']['server']['nm_filter_validation']);
		self::useFilterTemplate('calendar.filter');
		parent::tearDown();
	}

	/**
	 * Use another template for the filters of the views, eg. a customised calendar.filter
	 *
	 * @param string $template name of the template, or path of a .xet file
	 */
	private static function useFilterTemplate(string $template) : void
	{
		(new \ReflectionProperty(\calendar_uiviews::class, 'views_filter_template'))->setValue(null, $template);
		foreach(['views_filter_widgets', 'views_filter_options'] as $cache)
		{
			(new \ReflectionProperty(\calendar_uiviews::class, $cache))->setValue(null, null);
		}
	}

	/**
	 * Query as calendar's app.ts _fetch_data() sends it
	 *
	 * @param array $filters replacing the defaults
	 * @return array
	 */
	private static function query(array $filters=[]) : array
	{
		return array_merge([
			'row_id'     => 'row_id',
			'startdate'  => '2026-11-22T00:00:00.000Z',
			'enddate'    => '2026-11-28T23:59:59.000Z',
			'col_filter' => [
				'participant'        => [(string)$GLOBALS['egw_info']['user']['account_id']],
				'include_videocalls' => false,
			],
			'filter'          => 'custom',
			'status_filter'   => 'default',
			'cat_id'          => false,
			'csv_export'      => false,
			'selected_owners' => [(string)$GLOBALS['egw_info']['user']['account_id']],
		], $filters);
	}

	/**
	 * Call calendar_uiviews::viewsQuery()
	 *
	 * @param array $filters as sent by the client
	 * @param mixed $mode value of the nm_filter_validation server setting, null to leave it unset
	 * @param array $range queried range
	 * @param ?string &$lines on return the lines logged about the filters
	 * @return array|null query for get_rows
	 */
	private function viewsQuery(array $filters, $mode=null, array $range=['start' => 0, 'num_rows' => 400], ?string &$lines=null) : ?array
	{
		if ($mode !== null) $GLOBALS['egw_info']['server']['nm_filter_validation'] = $mode;
		$this->ajax_response->initResponseArray();

		$log = tempnam(sys_get_temp_dir(), 'cal_filters');
		$old_log = ini_set('error_log', $log);
		try
		{
			$query = (new \ReflectionMethod(\calendar_uiviews::class, 'viewsQuery'))->invoke(null, $filters, $range);
		}
		finally
		{
			ini_set('error_log', (string)$old_log);
			$lines = implode("\n", preg_grep('/Nextmatch filter validation/', file($log, FILE_IGNORE_NEW_LINES) ?: []));
			unlink($log);
		}
		return $query;
	}

	/**
	 * @return array[] parameters of the warnings sent to the console of the browser
	 */
	private function warnings() : array
	{
		return array_column(array_column(array_filter($this->ajax_response->returnResult(), static fn($command) =>
			$command['type'] === 'apply' && $command['data']['func'] === 'egw.debug'), 'data'), 'parms');
	}

	/**
	 * What the views send with valid values reaches get_rows unchanged, plus what is set server-side,
	 * and nothing is logged - also a range in the past or future, the planner's orders, the
	 * videoconference toggle and "no category".
	 */
	public function testValidFiltersOfTheViewsPassUnchanged()
	{
		foreach([
			self::query(),
			self::query(['startdate' => '2020-03-01T00:00:00Z', 'enddate' => '2020-03-31T23:59:59Z', 'order' => 'participants']),
			self::query(['startdate' => '2031-06-01T00:00:00.000Z', 'enddate' => '2031-06-30T23:59:59.000Z', 'order' => 'categories',
				'col_filter' => ['participant' => ['0', 'r1'], 'include_videocalls' => true], 'cat_id' => ['0']]),
		] as $filters)
		{
			$query = $this->viewsQuery($filters, null, ['start' => 0, 'num_rows' => 400], $lines);
			$this->assertSame('', $lines);
			$this->assertSame([], $this->warnings());
			foreach($filters as $name => $value)
			{
				$this->assertSame($value, $query[$name] ?? null, $name);
			}
			$this->assertSame(0, $query['start']);
			$this->assertSame(400, $query['num_rows']);
		}
	}

	/**
	 * Every option of the status filter is accepted.
	 */
	public function testEveryStatusFilterIsAccepted()
	{
		$values = array_column(\calendar_ui::status_filter_options(), 'value');
		$this->assertContains('deleted', $values);
		foreach($values as $value)
		{
			$this->viewsQuery(self::query(['status_filter' => $value]), null, ['start' => 0], $lines);
			$this->assertSame('', $lines, $value);
		}
	}

	/**
	 * A category the category filter offers is accepted, alone, as one of several, and with "0" for no category.
	 */
	public function testOfferedCategoryIsAccepted()
	{
		$offered = Etemplate\Widget\Select::optionValues(Etemplate\Widget\Select::typeOptions('select-cat', ',,calendar'));
		if (!$offered)
		{
			$this->markTestSkipped('no calendar category the test user can read');
		}
		foreach([[$offered[0]], array_slice($offered, 0, 2), ['0', $offered[0]], implode(',', array_slice($offered, 0, 2))] as $cat_id)
		{
			$this->viewsQuery(self::query(['cat_id' => $cat_id]), null, ['start' => 0], $lines);
			$this->assertSame('', $lines, json_encode($cat_id));
		}
	}

	/**
	 * An invalid value of each filter, and anything the views do not send, is logged as "would drop",
	 * sent as warning to the browser and, as filters are only reported for now, still passed on.
	 */
	public function testInvalidFiltersAreReportedButPassedOn()
	{
		$filters = self::query([
			'status_filter' => 'bogus',
			'filter'        => 'bogus',
			'startdate'     => 'not-a-date',
			'cat_id'        => ['999999999'],
			'col_filter'    => ['participant' => [['nested']], 'cal_owner' => '5'],
			'selected_owners' => [['nested']],
			'order'         => 'cal_title',
			'row_id'        => 'cal_owner',
			'session_for'   => 'session_data',
		]);
		$query = $this->viewsQuery($filters, null, ['start' => 0], $lines);

		foreach(['status_filter="bogus"', 'filter="bogus"', 'startdate="not-a-date"', 'cat_id=["999999999"]',
			'col_filter[participant]=[["nested"]]', 'col_filter[cal_owner]="5"', 'selected_owners=[["nested"]]',
			'order="cal_title"', 'row_id="cal_owner"', 'session_for="session_data"'] as $filter)
		{
			$this->assertMatchesRegularExpression('/would drop: calendar\.filter calendar '.preg_quote($filter, '/').': /', $lines, $filter);
		}
		$this->assertCount(10, $this->warnings(), 'each invalid filter is a warning in the console of the browser');

		foreach(['status_filter', 'filter', 'startdate', 'cat_id', 'col_filter', 'selected_owners', 'order', 'row_id', 'session_for'] as $name)
		{
			$this->assertSame($filters[$name], $query[$name] ?? null, "$name must still be passed on");
		}
	}

	/**
	 * A filter of calendar.filter without a check of its own in calendar_uiviews is checked by the type
	 * of its widget: the search the template has is accepted as a string.  As for a nextmatch, a search
	 * which is no string is never passed on.
	 */
	public function testOtherFilterOfTheTemplateIsCheckedByItsWidget()
	{
		$query = $this->viewsQuery(self::query(['search' => 'meeting']), null, ['start' => 0], $lines);
		$this->assertSame('', $lines);
		$this->assertSame('meeting', $query['search']);

		$query = $this->viewsQuery(self::query(['search' => ['meeting']]), null, ['start' => 0], $lines);
		$this->assertArrayNotHasKey('search', $query);
	}

	/**
	 * In a request to calendar_uiviews::ajax_get_rows() no eTemplate is running, so the template of the
	 * filters is read without a request: that must work, and leave none behind.
	 */
	public function testFilterTemplateIsReadWithoutARequest()
	{
		$request = new \ReflectionProperty(Etemplate\Widget::class, 'request');
		$before = $request->getValue();
		$request->setValue(null, null);
		try
		{
			$this->viewsQuery(self::query(['search' => 'meeting', 'col_filter' => ['participant' => ['5'], 'nowhere' => 'x']]),
				null, ['start' => 0], $lines);
			$this->assertNull($request->getValue(), 'no request must be left behind');
		}
		finally
		{
			$request->setValue(null, $before);
		}
		$this->assertStringNotContainsString('search=', $lines);
		$this->assertStringContainsString('col_filter[nowhere]="x": not a filter of the views', $lines);
	}

	/**
	 * A filter added to a customised calendar.filter is checked by its widget, without any change to
	 * calendar_uiviews, and a filter of a type there is no check for is reported.
	 */
	public function testFiltersOfACustomisedTemplateAreChecked()
	{
		self::useFilterTemplate(__DIR__.'/fixtures/views_filter.xet');
		$offered = Etemplate\Widget\Select::optionValues(Etemplate\Widget\Select::typeOptions('select-cat', ',,calendar'));

		$valid = [
			'search'     => 'x',
			'country'    => 'DE',
			'limit'      => '10',
			'only_mine'  => '1',
			'col_filter' => array_filter([
				'participant' => ['5'],
				'priority'    => '2',
				'cat2'        => $offered ? [$offered[0]] : null,
				'creator'     => (string)$GLOBALS['egw_info']['user']['account_id'],
				'due'         => '2026-12-24',
			]),
		];
		$query = $this->viewsQuery(self::query($valid), null, ['start' => 0], $lines);
		$this->assertSame('', $lines);
		$this->assertSame($valid['col_filter'], $query['col_filter']);

		$query = $this->viewsQuery(self::query([
			'country'    => 'XX',
			'limit'      => 'ten',
			'only_mine'  => ['1'],
			'iframe'     => 'x',
			'col_filter' => [
				'participant' => ['5'],
				'priority'    => '9',
				'cat2'        => ['999999999'],
				'creator'     => '999999',
				'due'         => 'not-a-date',
				'link'        => 'addressbook:1',
			],
		]), 'enforce', ['start' => 0], $lines);
		foreach([
			'country="XX": not an option of et2-select-country',
			'limit="ten": not a number',
			'only_mine=["1"]: not a single value of et2-checkbox',
			'iframe="x": not a filter of the views',
			'col_filter[priority]="9": not an option of et2-select',
			'col_filter[cat2]=["999999999"]: category 999999999 is not offered by the category filter',
			'col_filter[creator]="999999": account 999999 does not exist',
			'col_filter[due]="not-a-date": not a date',
			'col_filter[link]="addressbook:1": no check for a filter of type et2-link-entry',
		] as $line)
		{
			$this->assertStringContainsString('dropped: calendar.filter calendar '.$line, $lines);
		}
		$this->assertSame(['participant' => ['5']], $query['col_filter']);
		foreach(['country', 'limit', 'only_mine', 'iframe'] as $name)
		{
			$this->assertArrayNotHasKey($name, $query);
		}
	}

	/**
	 * When enforcing, only what is valid reaches get_rows, and an invalid date gives no events at all.
	 */
	public function testEnforcingPassesOnlyValidFilters()
	{
		$query = $this->viewsQuery(self::query([
			'status_filter' => 'bogus',
			'cat_id'        => ['999999999'],
			'col_filter'    => ['participant' => ['5'], 'cal_owner' => '5'],
			'row_id'        => 'cal_owner',
			'session_for'   => 'session_data',
		]), 'enforce', ['start' => 0], $lines);

		$this->assertMatchesRegularExpression('/dropped: calendar\.filter calendar status_filter="bogus"/', $lines);
		foreach(['status_filter', 'cat_id', 'session_for'] as $name)
		{
			$this->assertArrayNotHasKey($name, $query, $name);
		}
		$this->assertSame(['participant' => ['5']], $query['col_filter']);
		$this->assertSame('row_id', $query['row_id'], 'row_id is set server-side');
		$this->assertSame('custom', $query['filter']);

		$this->assertNull($this->viewsQuery(self::query(['enddate' => 'not-a-date']), 'enforce'));
	}

	/**
	 * nm_filter_validation = false switches the check off: everything is passed on, nothing logged.
	 */
	public function testValidationCanBeSwitchedOff()
	{
		$query = $this->viewsQuery(self::query(['status_filter' => 'bogus', 'session_for' => 'x']), false, ['start' => 0], $lines);
		$this->assertSame('', $lines);
		$this->assertSame('bogus', $query['status_filter']);
		$this->assertSame('x', $query['session_for']);
	}

	/**
	 * Whatever the mode: get_rows, start and num_rows never come from the filters, integer keys of
	 * col_filter are dropped, order has to be a column name, and a refresh filters by the row ids.
	 */
	public function testClientCanNotChooseCallbackRangeOrSqlFragments()
	{
		$query = $this->viewsQuery(self::query([
			'get_rows'   => 'EGroupware\\Api\\Accounts::save',
			'start'      => 999,
			'col_filter' => ['participant' => ['5'], 0 => '1=1'],
			'order'      => 'cal_start; DROP TABLE x',
		]), null, ['start' => 10, 'num_rows' => 20]);
		$this->assertArrayNotHasKey('get_rows', $query);
		$this->assertSame(10, $query['start']);
		$this->assertSame(20, $query['num_rows']);
		$this->assertSame(['participant' => ['5']], $query['col_filter']);
		$this->assertSame('', $query['order']);

		$query = $this->viewsQuery(self::query(), null, ['refresh' => ['8319:1795370400']]);
		$this->assertSame(['8319:1795370400'], $query['col_filter']['row_id']);
		$this->assertSame('refresh', $query['csv_export']);
	}

	/**
	 * Request as the sidebox's, whose exec_id the views use
	 *
	 * @return string exec_id
	 */
	private static function sideboxRequest() : string
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		Etemplate::reset_request();
		$request = (new \ReflectionProperty(Etemplate\Widget::class, 'request'))->getValue();
		$request->method = 'calendar.calendar_ui.sidebox_etemplate';
		$exec_id = $request->id();
		// the request is stored, when the last reference to it is gone, as after Etemplate::exec()
		(new \ReflectionProperty(Etemplate\Widget::class, 'request'))->setValue(null, null);
		unset($request);
		return $exec_id;
	}

	/**
	 * ajax_get_rows() answers the views' fetch with calendar's get_rows: every row with its data, keyed
	 * by row_id, dates as for a nextmatch - and an unknown request with no events and a message to reload.
	 */
	public function testAjaxGetRowsAnswersTheViews()
	{
		$this->ajax_response->initResponseArray();
		\calendar_uiviews::ajax_get_rows(self::sideboxRequest(), ['start' => 0, 'num_rows' => 5],
			self::query(['startdate' => '2000-01-01T00:00:00Z', 'enddate' => '2040-12-31T23:59:59Z', 'status_filter' => 'all']));
		$data = array_values(array_filter($this->ajax_response->returnResult(), static fn($command) => $command['type'] === 'data'));
		$this->assertCount(1, $data);
		$result = $data[0]['data'];
		$this->assertIsInt($result['total'] ?? null);
		// a numeric row_id becomes an integer as key
		$this->assertSame(array_map('strval', array_keys($result['data'])), array_map('strval', $result['order']));
		foreach($result['data'] as $row)
		{
			$this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT/', (string)$row['start'], 'dates are sent as for a nextmatch');
		}

		// a calendar the user has no rights for gives get_rows' empty row without row_id: as for a
		// nextmatch it is identified by its number, not by a missing row_id
		$this->ajax_response->initResponseArray();
		\calendar_uiviews::ajax_get_rows(self::sideboxRequest(), ['start' => 0, 'num_rows' => 5],
			self::query(['col_filter' => ['participant' => ['999999']], 'selected_owners' => ['999999']]));
		$data = array_values(array_filter($this->ajax_response->returnResult(), static fn($command) => $command['type'] === 'data'));
		$this->assertSame([0], $data[0]['data']['order'] ?? null);
		$this->assertArrayHasKey(0, $data[0]['data']['data'] ?? []);

		$this->ajax_response->initResponseArray();
		\calendar_uiviews::ajax_get_rows('calendar_unknown_'.bin2hex(random_bytes(8)), ['start' => 0, 'num_rows' => 5], self::query());
		$response = $this->ajax_response->returnResult();
		$data = array_values(array_filter($response, static fn($command) => $command['type'] === 'data'));
		$this->assertSame(['order' => [], 'data' => [], 'total' => 0], $data[0]['data'] ?? null);
		$this->assertNotEmpty(array_filter($response, static fn($command) => $command['type'] === 'apply' && $command['data']['func'] === 'egw.message'));
	}

	/**
	 * get_rows keeps the query of the views in the session, from which the list is rendered the next
	 * time it is loaded: the query has to name the callback, or the list has nothing to fetch its rows with.
	 */
	public function testViewsFetchKeepsTheCallbackOfTheList()
	{
		Api\Cache::unsetSession('calendar', 'calendar_list');
		$this->ajax_response->initResponseArray();
		\calendar_uiviews::ajax_get_rows(self::sideboxRequest(), ['start' => 0, 'num_rows' => 5], self::query());

		$stored = Api\Cache::getSession('calendar', 'calendar_list');
		$this->assertIsArray($stored, 'the views store their query for the list');
		$this->assertSame('calendar.calendar_uilist.get_rows', $stored['get_rows'] ?? null);
	}

	/**
	 * The planner asks for its rows ordered by "participants" or "categories", which are no columns:
	 * they are only recognised with no sort direction appended.
	 */
	public function testPlannerOrdersByParticipantsAndCategories()
	{
		foreach(['participants', 'categories'] as $order)
		{
			$this->ajax_response->initResponseArray();
			\calendar_uiviews::ajax_get_rows(self::sideboxRequest(), ['start' => 0, 'num_rows' => 5],
				self::query(['startdate' => '2000-01-01T00:00:00Z', 'enddate' => '2040-12-31T23:59:59Z', 'order' => $order]));
			$data = array_values(array_filter($this->ajax_response->returnResult(), static fn($command) => $command['type'] === 'data'));
			$this->assertIsInt($data[0]['data']['total'] ?? null, "rows ordered by $order");
		}
	}
}
