<?php
/**
 * EGroupware - Calendar's views and widgets
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2004-16 by RalfBecker-At-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;
use EGroupware\Api\Image;
use EGroupware\Api\Link;
use EGroupware\Api\Framework;
use EGroupware\Api\Egw;
use EGroupware\Api\Etemplate;

/**
 * Class to generate the calendar views and the necesary widgets
 *
 * The listview is in a separate class calendar_uilist!
 *
 * The new UI, BO and SO classes have a strikt definition, in which time-zone they operate:
 *  UI only operates in user-time, so there have to be no conversation at all !!!
 *  BO's functions take and return user-time only (!), they convert internaly everything to servertime, because
 *  SO operates only on server-time
 *
 * The state of the UI elements is managed in the uical class, which all UI classes extend.
 *
 * All permanent debug messages of the calendar-code should done via the debug-message method of the calendar_bo class !!!
 */
class calendar_uiviews extends calendar_ui
{
	var $public_functions = array(
		'index' => True,
	);

	/**
	 * integer level or string function- or widget-name
	 *
	 * @var mixed
	 */
	var $debug=false;

	/**
	 * extra rows above and below the workday
	 *
	 * @var int
	 */
	var $extraRows = 2;

	/**
	 * removes n extra rows below the workday
	 *
	 * @var int
	 */
	var $remBotExtraRows = 0;

	/**
	 * extra rows original (save original value even if it gets changed in the class)
	 *
	 * @var int
	 */
	var $extraRowsOriginal;

	var $timeRow_width = 40;

	/**
	 * how many rows per day get displayed, gets set by the timeGridWidget
	 *
	 * @var int
	 */
	var $rowsToDisplay;

	/**
	 * height in percent of one row, gets set by the timeGridWidget
	 *
	 * @var int
	 */
	var $rowHeight;

	/**
	 * standard params for calling bocal::search for all views, set by the constructor
	 *
	 * @var array
	 */
	var $search_params;

	/**
	 * should we use a time grid, can be set for week- and month-view to false by the cal_pref no_time_grid
	 *
	 * @var boolean
	 */
	var $use_time_grid=true;

	/**
	 * Pref value of use_time_grid preference
	 * @var string
	 */
	var $use_time_grid_pref = '';

	/**
	 * Can we display the whole day in a timeGrid of the size of the workday and just scroll to workday start
	 *
	 * @var boolean
	 */
	var $scroll_to_wdstart=false;

	/**
	 * counter for the current whole day event of a single day
	 *
	 * @var int
	 */
	var $wholeDayPosCounter=1;

	/**
	 * Switch to disable private data and possibility to view and edit events
	 * in case of a public view (sitemgr)
	 */
	var $allowEdit = true;

	/**
	 * Constructor
	 *
	 * @param array $set_states = null to manualy set / change one of the states, default NULL = use $_REQUEST
	 */
	function __construct($set_states=null)
	{
		parent::__construct(false,$set_states);	// call the parent's constructor
		$this->extraRowsOriginal = $this->extraRows; //save original extraRows value

		$GLOBALS['egw_info']['flags']['nonavbar'] = False;

		// Check for GET message (from merge)
		if($_GET['msg'])
		{
			Framework::message($_GET['msg']);
			unset($_GET['msg']);
		}
		// standard params for calling bocal::search for all views
		$this->owner = str_replace('%2C',',',$this->owner);
		$this->search_params = array(
			'start'   => $this->date,
			'cat_id'  => $this->cat_id ? (is_array($this->cat_id)?$this->cat_id:explode(',',$this->cat_id)) : 0,
			'users'   => explode(',',$this->owner),
			'owner'   => explode(',',$this->owner),
			'filter'  => $this->filter,
			'daywise' => True,
			'use_so_events' => $this->test === 'true',
		);
//		$this->holidays = $this->bo->read_holidays($this->year);

		$this->check_owners_access();

		//ATM: Forces use_time_grid preference to use all views by ignoring the preference value
		//@TODO: the whole use_time_grid preference should be removed (including dependent vars)
		// after we decided that is not neccessary to have it at all
		$this->use_time_grid_pref = 'all'; //$this->cal_prefs['use_time_grid'];
	}


	/**
	 * Calculate iso8601 week-number, which is defined for Monday as first day of week only
	 *
	 * We addjust the day, if user prefs want a different week-start-day
	 *
	 * @param int|string|DateTime $time
	 * @return string
	 */
	public function week_number($time)
	{
		if (!is_a($time,'DateTime')) $time = new Api\DateTime($time);

		// if week does not start Monday and $time is Sunday --> add one day
		if ($this->cal_prefs['weekdaystarts'] != 'Monday' && !($wday = $time->format('w')))
		{
			$time->modify('+1day');
		}
		// if week does start Saturday and $time is Saturday --> add two days
		elseif ($this->cal_prefs['weekdaystarts'] == 'Saturday' && $wday == 6)
		{
			$time->modify('+2days');
		}
		return $time->format('W');
	}

	/**
	 * Load all views used by calendar, client side switches between them as needed
	 */
	function index($content=array())
	{
		// handle views in other files
		if (!isset($this->public_functions[$this->view]) && $this->view !== 'listview')
		{
			$this->view = 'week';
		}
		// get manual to load the right page
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualCalendar'.ucfirst($this->view));

		// Sidebox & iframe for old views
		if(in_array($this->view,array('year')) && $_GET['view'])
		{
			$GLOBALS['egw_info']['flags']['nonavbar'] = true;
			$this->manage_states($_GET);
			$this->{$this->view}();
			return;
		}

		// Toolbar
		$tmpl = new Etemplate('calendar.toolbar');
		$tmpl->setElementAttribute('toolbar', 'actions', $this->getToolbarActions($content));
		// Adjust toolbar for mobile
		if(Api\Header\UserAgent::mobile()){
			$tmpl->setElementAttribute('toolbar','class', 'et2_head_toolbar');
			$tmpl->setElementAttribute('toolbar','view_range', '3');
		}
		$tmpl->exec('calendar_uiviews::index',array());

		// Load the different views once, we'll switch between them on the client side
		$todo = new Etemplate('calendar.todo');
		$label = '';
		$todo->exec('calendar_uiviews::index',array('todos'=>'', 'label' => $label));

		// Actually, this takes care of most of it...
		$this->week();

		$planner = new Etemplate('calendar.planner');
		// Get the actions
		$planner->setElementAttribute('planner','actions',$this->get_actions());
		$planner->exec('calendar_uiviews::index',array());

		// List view in a separate file
		$list_ui = new calendar_uilist();
		$list_ui->listview();
	}

	/**
	 * Fetch the events of the day, week, month and planner views
	 *
	 * The views show their events without a nextmatch: CalendarApp._fetchViewRows() answers their
	 * egw.dataFetch() with this.  The client has to name the eTemplate request of a page of ours it
	 * shows, any part of it, as json.php has no CSRF protection of its own.
	 *
	 * The query is built from what the views send, each part checked, see self::viewsQuery().  The
	 * rows are processed as for a nextmatch: get_rows runs between the etemplate2_before_get_rows and
	 * etemplate2_after_get_rows hooks, by which other apps change the query (eg. the project filter of
	 * the ProjectManager integration), and dates are sent in the format the client expects.
	 *
	 * @param string $exec_id eTemplate request of the page showing the views, eg. of the sidebox
	 * @param array $queriedRange values for keys "start" and "num_rows", or "refresh" with row ids to fetch
	 * @param array $filters query of the views, see CalendarApp._fetch_data()
	 */
	public static function ajax_get_rows($exec_id, array $queriedRange, array $filters)
	{
		$response = Api\Json\Response::get();
		if (!Etemplate\Widget\Nextmatch::validateExecId($exec_id) ||
			($query = self::viewsQuery($filters, $queriedRange)) === null)
		{
			$response->data(['order' => [], 'data' => [], 'total' => 0]);
			return;
		}
		Api\Translation::add_app('calendar');

		$get_rows = 'calendar.calendar_uilist.get_rows';
		$rows = $raw_rows = $readonlys = [];
		$total = null;
		Api\Hooks::process([
			'hook_location' => 'etemplate2_before_get_rows',
			'get_rows'      => $get_rows,
			'value'         => &$query,
			'rows'          => &$rows,
			'readonlys'     => &$readonlys,
			'total'         => &$total,
		], [], true);

		// get_rows() keeps the query in the session, from which the list is rendered the next
		// time: it has to name the callback the list fetches its rows with
		$query['get_rows'] = $get_rows;
		$total = (new calendar_uilist())->get_rows($query, $raw_rows, $readonlys);

		Api\Hooks::process([
			'hook_location' => 'etemplate2_after_get_rows',
			'get_rows'      => $get_rows,
			'value'         => &$query,
			'rows'          => &$rows,
			'readonlys'     => &$readonlys,
			'total'         => &$total,
		], [], true);

		$result = [
			'total'            => $total,
			'order'            => [],
			'data'             => [],
			'readonlys'        => $readonlys,
			'lastModification' => Api\DateTime::to('now', 'ts')-1,
		];
		// as for a nextmatch: rows are numbered from the queried start, and once a row has no row_id
		// (eg. the empty row for a calendar the user has no rights for), rows are identified by their number
		$first = $total ? null : 0;
		$row_id = 'row_id';
		foreach($raw_rows as $n => $row)
		{
			if (is_int($n))
			{
				if (is_null($first)) $first = $n;
				$n = $n - $first + $query['start'];
				if (!$row) continue;

				$row = Etemplate\Widget\Nextmatch::run_beforeSendToClient($row);
				if ($row_id && !isset($row[$row_id])) $row_id = null;
				$id = $row_id ? $row[$row_id] : $n;
				$result['order'][] = $id;
				$result['data'][$id] = $row;
			}
			elseif (!is_int($n))	// not a row, eg. options of the sidebox's owner selection
			{
				if ($n === 'sel_options')
				{
					foreach($row as &$options)
					{
						Etemplate\Widget\Select::fix_encoded_options($options, true);
					}
					unset($options);
				}
				$result['rows'][$n] = $row;
			}
		}
		$response->data($result);
	}

	/**
	 * Query for calendar_uilist::get_rows() from what the views sent
	 *
	 * The views send the filters of calendar.filter - startdate, enddate, status_filter and cat_id -,
	 * the shown calendars as col_filter[participant] and selected_owners, col_filter[include_videocalls]
	 * and the order of the planner. Each is checked:
	 * - startdate and enddate have to be dates
	 * - status_filter an option of the status filter
	 * - cat_id categories the category filter offers, or "0" for none
	 * - participants and selected owners plain ids: calendar_bo::search() only shows calendars the user has rights for
	 * - include_videocalls a single value
	 * - order "participants" or "categories"
	 * - filter "custom", row_id "row_id" and csv_export empty, which is what the views send
	 * - anything else as the widget of calendar.filter for it, see self::templateFilterCheck()
	 *
	 * An invalid value, and anything else the client sent, is logged as "would drop" and sent as warning to
	 * the console of the browser.  Depending on $GLOBALS['egw_info']['server']['nm_filter_validation']:
	 * - "report" (default): everything is passed on, as the client sent it
	 * - "enforce": only what is valid is passed on, an invalid date gives no events at all
	 * - false: nothing is checked, everything is passed on
	 * get_rows, start and num_rows never come from the filters, a search only as a string, and order only if
	 * it is a column name, as for a nextmatch.
	 *
	 * @param array $filters as sent by the client
	 * @param array $queriedRange values for keys "start" and "num_rows", or "refresh" with row ids to fetch
	 * @return array|null null if a date is invalid and validation is enforced
	 */
	protected static function viewsQuery(array $filters, array $queriedRange) : ?array
	{
		$mode = $GLOBALS['egw_info']['server']['nm_filter_validation'] ?? 'report';
		$enforce = $mode === 'enforce';

		// never from the client, as for a nextmatch
		unset($filters['get_rows']);
		// as for a nextmatch: a search has to be a string
		if (isset($filters['search']) && !is_string($filters['search']))
		{
			unset($filters['search']);
		}
		// integer keys of col_filter would be used as SQL fragments
		if (isset($filters['col_filter']) && is_array($filters['col_filter']))
		{
			$filters['col_filter'] = array_filter($filters['col_filter'], static fn($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
		}
		$valid = $invalid = [];
		if ($mode)
		{
			$is_ids = static fn($ids) => is_scalar($ids) || is_array($ids) && count(array_filter($ids, 'is_scalar')) === count($ids);
			foreach($filters as $name => $value)
			{
				$reason = null;
				switch($name)
				{
					case 'startdate':
					case 'enddate':
						if (!is_scalar($value))
						{
							$reason = 'not a date';
						}
						elseif ((string)$value !== '')
						{
							try {
								new Api\DateTime($value);
							}
							catch (\Exception $e) {
								$reason = 'not a date';
							}
						}
						break;
					case 'status_filter':
						if (!is_scalar($value) || (string)$value !== '' &&
							!in_array((string)$value, array_column(calendar_ui::status_filter_options(), 'value'), true))
						{
							$reason = 'not an option of the status filter';
						}
						break;
					case 'cat_id':
						if (!empty($value)) $reason = self::invalidCategories($value);
						break;
					case 'col_filter':
						foreach(is_array($value) ? $value : ['' => $value] as $col => $val)
						{
							if ($col === 'participant')
							{
								$col_reason = $is_ids($val) ? null : 'not a list of ids';
							}
							elseif ($col === 'include_videocalls')
							{
								$col_reason = is_scalar($val) ? null : 'not a single value';
							}
							else
							{
								$col_reason = self::templateFilterCheck("col_filter[$col]", $val);
							}
							if ($col_reason)
							{
								$invalid["col_filter[$col]"] = [$val, $col_reason];
							}
							else
							{
								$valid['col_filter'][$col] = $val;
							}
						}
						continue 2;
					case 'selected_owners':
						if (!$is_ids($value)) $reason = 'not a list of ids';
						break;
					case 'order':
						if (!in_array($value, ['', 'participants', 'categories'], true)) $reason = 'not an order of the views';
						break;
					case 'filter':
						if ($value !== 'custom') $reason = 'the views only use "custom"';
						break;
					case 'row_id':
						if ($value !== 'row_id') $reason = 'the views only use "row_id"';
						break;
					case 'csv_export':
						if (!empty($value)) $reason = 'the views do not export';
						break;
					default:
						// another filter of calendar.filter, eg. one added to a customised template
						$reason = self::templateFilterCheck($name, $value);
						break;
				}
				if ($reason)
				{
					$invalid[$name] = [$value, $reason];
				}
				else
				{
					$valid[$name] = $value;
				}
			}
			self::logInvalidFilters($invalid, $enforce);

			if ($enforce && (isset($invalid['startdate']) || isset($invalid['enddate'])))
			{
				return null;
			}
		}
		$query = $enforce ? $valid : $filters;
		$query['filter'] = $query['filter'] ?? 'custom';
		$query['row_id'] = $query['row_id'] ?? 'row_id';

		// Avoid empty arrays, they cause problems with db filtering
		foreach((array)($query['col_filter'] ?? []) as $col => $val)
		{
			if (is_array($val) && !$val) unset($query['col_filter'][$col]);
		}
		// as for a nextmatch: order has to be a column name
		if (!preg_match('/^(#[a-z0-9 _]+|[a-z0-9_]+)$/i', (string)($query['order'] ?? '')))
		{
			$query['order'] = '';
		}
		// no direction: the planner's "participants" and "categories" are no columns, and are only
		// recognised as the order to sort by with nothing appended to them
		$query['sort'] = '';
		$query['start'] = (int)($queriedRange['start'] ?? 0);
		$query['num_rows'] = (int)($queriedRange['num_rows'] ?? 0) ?: Etemplate\Widget\Nextmatch::INITIAL_ROWS;

		// If specific rows are requested, just fetch them
		if (!empty($queriedRange['refresh']))
		{
			$query['col_filter'][$query['row_id']] = $queriedRange['refresh'];
			$query['csv_export'] = 'refresh';
		}
		return $query;
	}

	/**
	 * Template with the filters of the views: its name, or the path of a .xet file whose first template is used
	 *
	 * @var string
	 */
	protected static $views_filter_template = 'calendar.filter';

	/**
	 * Filters of the template, once read: id => ['type' => tag of the widget, 'attrs' => its attributes, 'options' => values of its <option>s]
	 *
	 * @var array[]|null
	 */
	protected static $views_filter_widgets;

	/**
	 * Options of the filters of the views, once read, see calendar_ui::filter_settings()
	 *
	 * @var array|null
	 */
	protected static $views_filter_options;

	/**
	 * Why a value is not one the widget of calendar.filter for that filter accepts
	 *
	 * For filters without a check of their own in self::viewsQuery(), eg. one added to a customised
	 * calendar.filter. Checked by the type of the widget:
	 * - select: one of its options, from the template or calendar_ui::filter_settings(), or of its type, eg. select-country
	 * - select-cat: a category the category filter offers
	 * - select-account: an existing account
	 * - date: a date
	 * - number, integer or float: a number
	 * - searchbox, textbox, checkbox or switch: a single value
	 * A filter of any other type is reported, as there is no check for it.
	 *
	 * @param string $name eg. "search" or "col_filter[priority]"
	 * @param mixed $value as sent by the client
	 * @return string|null null if it is valid
	 */
	protected static function templateFilterCheck(string $name, $value) : ?string
	{
		if (!($widget = self::viewsFilterWidgets()[$name] ?? null))
		{
			return 'not a filter of the views';
		}
		$tag = is_string($widget['attrs']['type'] ?? null) ? $widget['attrs']['type'] : $widget['type'];
		$type = preg_replace('/^et2-/', '', $tag);
		$values = is_array($value) ? $value : [$value];
		if (count(array_filter($values, static fn($v) => is_scalar($v) || is_null($v))) !== count($values))
		{
			return "not a value of $tag";
		}
		$values = array_filter($values, static fn($v) => (string)$v !== '');
		switch(true)
		{
			case $type === 'select-cat':
				return $values ? self::invalidCategories(array_values($values)) : null;

			case $type === 'select-account':
				foreach($values as $v)
				{
					if (!$GLOBALS['egw']->accounts->exists($v)) return "account $v does not exist";
				}
				return null;

			case str_starts_with($type, 'select'):
				$options = self::viewsFilterOptions();
				$offered = array_merge($widget['options'], Etemplate\Widget\Select::optionValues((array)($options[$name] ??
					Etemplate\Widget\Select::get_array($options, $name) ?? [])));
				if ($type !== 'select')
				{
					try {
						$offered = array_merge($offered, Etemplate\Widget\Select::optionValues(
							(array)Etemplate\Widget\Select::typeOptions($type, (string)($widget['attrs']['options'] ?? ''))));
					}
					catch (\Throwable $e) {
						return "options of $tag: ".$e->getMessage();
					}
				}
				$offered = array_map('strval', $offered);
				foreach($values as $v)
				{
					if (!in_array((string)$v, $offered, true)) return "not an option of $tag";
				}
				return null;

			case str_starts_with($type, 'date'):
				foreach($values as $v)
				{
					try {
						new Api\DateTime($v);
					}
					catch (\Exception $e) {
						return 'not a date';
					}
				}
				return null;

			case in_array($type, ['number', 'integer', 'int', 'float'], true):
				foreach($values as $v)
				{
					if (!is_numeric($v)) return 'not a number';
				}
				return null;

			case in_array($type, ['searchbox', 'textbox', 'checkbox', 'switch'], true):
				return is_array($value) ? "not a single value of $tag" : null;
		}
		return "no check for a filter of type $tag";
	}

	/**
	 * Filters of the template with the filters of the views, by their id
	 *
	 * The template is the one Template::instance() resolves, so a customised one is used, and it is read
	 * from its file: running it needs an eTemplate request, which a request of the views does not have.
	 * Every element with an id is a filter, but layout and display ones, and so is every one of a
	 * template it includes.  An id with "$" or "@" is expanded by content, those belong to other apps'
	 * integration and set no filter of the views.
	 *
	 * @return array[] id => ['type' => tag of the widget, 'attrs' => its attributes, 'options' => values of its <option>s]
	 */
	protected static function viewsFilterWidgets() : array
	{
		if (!isset(self::$views_filter_widgets))
		{
			self::$views_filter_widgets = [];
			if (str_ends_with(self::$views_filter_template, '.xet'))
			{
				self::readFilterTemplate(self::$views_filter_template, null);
			}
			elseif (($template = Etemplate\Widget\Template::instance(self::$views_filter_template, null, '', '', true)))
			{
				self::readFilterTemplate(Etemplate\Widget\Template::rel2path($template->rel_path), $template->id);
			}
		}
		return self::$views_filter_widgets;
	}

	/**
	 * Add the filters of a template from a .xet file to self::$views_filter_widgets
	 *
	 * @param string $path of the .xet file
	 * @param ?string $name of the template in it, null for the first one
	 * @param int $depth of included templates
	 */
	protected static function readFilterTemplate(string $path, ?string $name, int $depth=0) : void
	{
		$reader = new \XMLReader();
		if ($depth > 5 || !$reader->open($path)) return;

		$in_template = false;
		$in_template_depth = $select = null;
		while($reader->read())
		{
			if ($reader->nodeType === \XMLReader::END_ELEMENT && $in_template && $reader->depth === $in_template_depth)
			{
				break;
			}
			if ($reader->nodeType !== \XMLReader::ELEMENT) continue;

			$tag = $reader->name;
			$id = $reader->getAttribute('id');
			if (!$in_template)
			{
				if (in_array($tag, ['template', 'et2-template'], true) && (!isset($name) || $id === $name))
				{
					$in_template = true;
					$in_template_depth = $reader->depth;
				}
				continue;
			}
			if ($tag === 'option')
			{
				if ($select) self::$views_filter_widgets[$select]['options'][] = (string)$reader->getAttribute('value');
				continue;
			}
			$select = null;
			if (!$id || preg_match('/[$@]/', $id)) continue;

			// an included template
			if (in_array($tag, ['template', 'et2-template'], true))
			{
				if (($included = Etemplate\Widget\Template::instance($reader->getAttribute('template') ?: $id, null, '', '', true)))
				{
					self::readFilterTemplate(Etemplate\Widget\Template::rel2path($included->rel_path), $included->id, $depth+1);
				}
				continue;
			}
			// layout and display widgets are no filters
			if (preg_match('/^(et2-)?(box|vbox|hbox|grid|columns|column|rows|row|iframe|description|label|button.*|image|details|tabbox|tabs|tab|tabpanels|tabpanel|groupbox|caption|hrule|styles|split)$/', $tag))
			{
				continue;
			}
			$attrs = [];
			while($reader->moveToNextAttribute()) $attrs[$reader->name] = $reader->value;
			$reader->moveToElement();
			self::$views_filter_widgets[$id] = ['type' => $tag, 'attrs' => $attrs, 'options' => []];
			if (str_contains($tag, 'select')) $select = $id;
		}
		$reader->close();
	}

	/**
	 * Options of the filters of the views, as calendar.filter is rendered with them
	 *
	 * @return array
	 */
	protected static function viewsFilterOptions() : array
	{
		if (!isset(self::$views_filter_options))
		{
			$ui = new calendar_uilist();
			$nm = [];
			self::$views_filter_options = ['filter' => $ui->date_filters];
			$ui->filter_settings($nm, self::$views_filter_options);
		}
		return self::$views_filter_options;
	}

	/**
	 * Why categories are not ones the category filter of the views offers
	 *
	 * @param mixed $cat_id one or more ids, an array or comma-separated, "0" for no category
	 * @return string|null null if they are valid
	 */
	protected static function invalidCategories($cat_id) : ?string
	{
		$ids = is_array($cat_id) ? $cat_id : (is_scalar($cat_id) ? explode(',', (string)$cat_id) : null);
		if (!isset($ids) || count(array_filter($ids, 'is_scalar')) !== count($ids))
		{
			return 'not a list of category ids';
		}
		// the options the et2-select-cat of calendar.filter gets
		$offered = Etemplate\Widget\Select::optionValues(Etemplate\Widget\Select::typeOptions('select-cat', ',,calendar'));
		foreach($ids as $id)
		{
			if ((string)$id !== '0' && !in_array((string)$id, $offered, true))
			{
				return "category $id is not offered by the category filter";
			}
		}
		return null;
	}

	/**
	 * Log filters of the views, which are invalid, to the PHP error-log and as warning to the console of the browser
	 *
	 * Same lines as for the filters of a nextmatch, so both can be found the same way.
	 *
	 * @param array[] $invalid name => [value as sent, why it is invalid]
	 * @param bool $enforce true: they are dropped, false: they are passed on nevertheless
	 */
	protected static function logInvalidFilters(array $invalid, bool $enforce) : void
	{
		foreach($invalid as $name => [$value, $reason])
		{
			$line = 'Nextmatch filter validation '.($enforce ? 'dropped' : 'would drop').": calendar.filter calendar $name=".
				substr(json_encode($value), 0, 100).": $reason";
			error_log($line);
			Api\Json\Response::get()->apply('egw.debug', ['warn', $line]);
		}
	}

	/**
	 * Generate the calendar toolbar actions
	 */
	protected function getToolbarActions()
	{
		$group = 0;
		$actions = array(
			'day_view' => array(
				'caption' => 'Dayview',
				'icon'	=> '1_day_view',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Dayview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'day'))
			),
			'4day_view' => array(
				'caption' => 'Four days view',
				'icon'	=> '4_day_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Four days view',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'day4'))
			),
			'week_view' => array(
				'caption' => 'Weekview',
				'icon'	=> 'week_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Weekview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'week'))
			),
			'weekN_view' => array(
				'caption' => 'Multiple week view',
				'icon'	=> 'multiweek_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Multiple week view',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'weekN'))
			),
			'month_view' => array(
				'caption' => 'Monthview',
				'icon'	=> 'month_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Monthview',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'month'))
			),
			'planner_category' => array(
				'caption' => 'Planner by category',
				'icon'	=> 'planner_category_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Planner by category',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'category')),
			),
			'planner_user' => array(
				'caption' => 'Planner by user',
				'icon'	=> 'planner_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Planner by user',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'user')),
			),
			'planner_month' => array(
				'caption' => 'Yearly planner',
				'icon'	=> 'year_view',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Yearly planner',
				'toolbarDefault' => false,
				'data' => array('state' => array('view' => 'planner', 'sortby' => 'month')),
			),
			'list' => array(
				'caption' => 'Listview',
				'icon'	=> 'list',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Listview',
				'toolbarDefault' => true,
				'data' => array('state' => array('view' => 'listview')),
			),
			'weekend' => array(
				'caption' => 'Weekend',
				'checkbox'	=> true,
				'checked' => is_array($this->cal_prefs['saved_states']) ? $this->cal_prefs['saved_states']['weekend']:false,
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Toggle weekend',
				'toolbarDefault' => false,
				'data' => array('onIcon' => '7-square', 'offIcon' => '5-square'),
			),
			'previous' => array(
				'caption' => 'Previous',
				'icon'	=> 'previous',
				'group' => ++$group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Previous',
				'toolbarDefault' => true,
			),
			'today' => array(
				'caption' => 'Today',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Today',
				'toolbarDefault' => true,
				'icon' => Api\Header\UserAgent::mobile() ? 'today' : ''
			),
			'next' => array(
				'caption' => 'Next',
				'icon'	=> 'next',
				'group' => $group,
				'onExecute' => 'javaScript:app.calendar.toolbar_action',
				'hint' => 'Next',
				'toolbarDefault' => true,
			),
		);

		// Don't show videoconference action if videoconference is disabled or BBB is not configured
		if (calendar_hooks::isVCRecordingSupported()
			&& !calendar_hooks::isVideoconferenceDisabled())
		{
			// Add toggle for video calls
			$status_config = Api\Config::read("status");
			if($status_config["status_cat_videocall"])
			{
				$actions['video_toggle'] = array(
					'caption' => 'Video call',
					'iconUrl' => Image::find('status', 'videoconference_call'),
					'checkbox' => true,
					'hint' => lang("video call"),
					'group' => 'integration',
					'onExecute' => 'javaScript:app.calendar.toolbar_videocall_toggle_action',
					'checked' => in_array('video_toggle', (array)$this->cal_prefs['integration_toggle']),
					'data' => array('toggle_off' => '0', 'toggle_on' => '1')
				);
			}
		}

		// Add integrated app options
		$integration_data = Api\Hooks::process(array('location' => 'calendar_search_union'));
		foreach($integration_data as $app => $app_hooks)
		{
			foreach ($app_hooks as $data)
			{
				// App might have multiple hooks, let it specify something else
				$app = $data['selects']['app'] ?: $app;

				// Don't add if no access or app already added
				if (!array_key_exists($app, $GLOBALS['egw_info']['user']['apps']) ||
						(is_array($actions['integration']['children']) && array_key_exists($app, $actions['integration']['children']))
				)
				{
					continue;
				}
				// Don't show infolog if there are no configured types
				if($app == 'infolog' && empty($GLOBALS['egw_info']['user']['preferences']['infolog']['calendar_integration']))
				{
					continue;
				}
				$img = self::integration_get_icons($app, null, [])[0];
				preg_match('/<img src=\"(.*?)\".*\/>/', $img, $results);
				$actions['integration_'.$app] = array(
					'caption'   => $data['selects']['caption'] ?? $app,
					'iconUrl'   => $results[1] ?: "$app\navbar",
					'checkbox'  => true,
					'hint'      => lang("show %1 from %2", lang(Link::get_registry($app, 'entries') ?: 'entries'), lang(Link::get_registry($app, 'name'))),
					'group'     => 'integration',
					'onExecute' => 'javaScript:app.calendar.toolbar_integration_action',
					'checked'   => in_array($app, (array)$this->cal_prefs['integration_toggle']),
					'data'      => array('toggle_off' => '0', 'toggle_on' => '1')
				);

			}
		}
		if (Api\CalDAV\Sync::listSubscriptions())
		{
			$actions['subscriptions'] = [
				'caption'   => 'Subscriptions',
				'iconUrl'   => Image::find('api', 'arrow-repeat'),
				'checkbox'  => true,
				'hint'      => lang('Show subscribed calendars'),
				'group'     => 'integration',
				'onExecute' => 'javaScript:app.calendar.toolbar_subscriptions_toggle_action',
				'checked'   => in_array('subscriptions', (array)$this->cal_prefs['integration_toggle']),
				'data'      => array('toggle_off' => '0', 'toggle_on' => '1'),
				'toolbarDefault' => true,
			];
		}
		if (Api\Header\UserAgent::mobile())
		{
			foreach (array_keys($actions) as $key)
			{
				if (!in_array($key, array('next', 'today','previous'))) {
					$actions[$key]['toolbarDefault'] = false;
				}
				else {
					$actions[$key]['toolbarDefault'] = true;
				}
			}
			unset($actions['pgp']);
		}
		return $actions;
	}

	/**
	 * Displays the planner view
	 *
	 * @param boolean|Etemplate $home = false if etemplate return content suitable for home-page
	 */
	function &planner($content = array(), $home=false)
	{
		if ($this->sortby == 'month')	// yearly planner with month rows
		{
			$this->first = $this->bo->date2array($this->date);
			$this->first['day'] = 1;
			unset($this->first['raw']);
			$this->last = $this->first;
			$this->last['year']++;
			$this->last = $this->bo->date2ts($this->last)-1;
		}
		elseif (!$this->planner_view || $this->planner_view == 'month')	// planner monthview
		{
			if ($this->day < 15)	// show one complete month
			{
				$this->_week_align_month($this->first,$this->last);
			}
			else	// show 2 half month
			{
				$this->_week_align_month($this->first,$this->last,15);
			}
		}
		elseif ($this->planner_view == 'week' || $this->planner_view == 'weekN')	// weeekview
		{
			$start = new Api\DateTime($this->date);
			$start->setWeekstart();
			$this->first = $start->format('ts');
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += ($this->planner_view == 'week' ? 7 : 7 * $this->cal_prefs['multiple_weeks'])-1;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
		}
		else // dayview
		{
			$this->first = $this->bo->date2ts($this->date);
			$this->last = $this->bo->date2array($this->first);
			$this->last['day'] += 0;
			$this->last['hour'] = 23; $this->last['minute'] = $this->last['sec'] = 59;
			unset($this->last['raw']);
			$this->last = $this->bo->date2ts($this->last);
		}

		$search_params = $this->search_params;
		$search_params['daywise'] = false;
		$search_params['start'] = $this->first;
		$search_params['end'] = $this->last;
		$search_params['enum_groups'] = $this->sortby == 'user';
		$content['planner'] = $this->bo->search($search_params);
		foreach($content['planner'] as &$event)
		{
			$this->to_client($event);
		}

		if ($this->debug > 0) $this->bo->debug_message('uiviews::planner() date=%1: first=%2, last=%3',False,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		$tmpl = $home ? $home : new Etemplate('calendar.planner');

		$tmpl->setElementAttribute('planner','start_date', Api\DateTime::to($this->first, Api\DateTime::ET2));
		$tmpl->setElementAttribute('planner','end_date', Api\DateTime::to($this->last, Api\DateTime::ET2));
		$tmpl->setElementAttribute('planner','owner', $search_params['users']);
		$tmpl->setElementAttribute('planner','group_by', $this->sortby);
		// Get the actions
		$tmpl->setElementAttribute('planner','actions',$this->get_actions());

		$tmpl->exec(__METHOD__, $content);
	}

	/**
	 * Displays the monthview or a multiple week-view
	 *
	 * Used for home app
	 *
	 * @param int $weeks = 0 number of weeks to show, if 0 (default) all weeks of one month are shown
	 * @param boolean|etemplate2 $home = false if not false return content suitable for home-page
	 */
	function &month($weeks=0,$home=false)
	{
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(weeks=%1) date=%2',True,$weeks,$this->date);

		if (!$home)
		{
			trigger_error(__METHOD__ .' only used by home app', E_USER_DEPRECATED);
			return;
		}

		$this->use_time_grid = !$this->use_time_grid_pref || $this->use_time_grid_pref == 'all';	// all views
		$granularity = 0;
		if($weeks)
		{
			$granularity = ($this->cal_prefs['interval'] ? (int)$this->cal_prefs['interval'] : 30);

			$list = $this->cal_prefs['use_time_grid'];
			if(!is_array($list))
			{
				$list = explode(',',$list);
			}
			if(is_array($list))
			{
				$granularity = in_array('weekN',$list) ? 0 : $granularity;
			}
		}
		$content = array('view' => array());

		if ($weeks)
		{
			$start = new Api\DateTime($this->date);
			$start->setWeekstart();
			$this->first = $start->format('ts');
			$end = clone $start;
			$end->add(new DateInterval("P{$weeks}W"));
			$end->modify('-1 second');
			$this->last = $end->format('ts');
			$weekNavH = "$weeks weeks";
			$navHeader = lang('Week').' '.$this->week_number($this->first).' - '.$this->week_number($this->last).': '.
				$this->bo->long_date($this->first,$this->last);
		}
		else
		{
			$this->_week_align_month($this->first,$this->last);
			$weekNavH = "1 month";
			$navHeader = lang(date('F',$this->bo->date2ts($this->date))).' '.$this->year;
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::month(%1) date=%2: first=%3, last=%4',False,$weeks,$this->date,$this->bo->date2string($this->first),$this->bo->date2string($this->last));

		// Loop through, using Api\DateTime to handle DST
		$week = 0;
		$week_start = new EGroupware\Api\DateTime($this->first);
		$week_start->setTime(0,0,0);
		$week_end = new Api\DateTime($week_start);
		$week_end->add(new DateInterval('P6DT23H59M59S'));
		$last = new EGroupware\Api\DateTime($this->last);
		for ($week_start; $week_start < $last; $week_start->add('1 week'), $week_end->add('1 week'))
		{
			$search_params = $this->search_params;
			$search_params['start'] = $week_start->format('ts');
			$search_params['end'] = $week_end->format('ts');

			$content['view'][] = (array)$this->tagWholeDayOnTop($this->bo->search($search_params)) +
			array(
				'id' => $week_start->format('Ymd')
			);
			$home->setElementAttribute("view[$week]",'onchange',false);
			$home->setElementAttribute("view[$week]",'granularity',$granularity);
			$home->setElementAttribute("view[$week]",'show_weekend', $this->search_params['weekend']);
			$week++;
		}

		// Get the actions
		$home->setElementAttribute('view','actions',$this->get_actions());

		$home->exec(__METHOD__, $content);
	}

	/**
	 * get start and end of a month aligned to full weeks
	 *
	 * @param int &$first timestamp 0h of first day of week containing the first of the current month
	 * @param int &$last timestamp 23:59:59 of last day of week containg the last day of the current month
	 * @param int $day = 1 should the alignment be based on the 1. of the month or an other date, eg. the 15.
	 */
	function _week_align_month(&$first,&$last,$day=1)
	{
		$start = new Api\DateTime($this->date);
		$start->setDate($this->year,$this->month,$this->day=$day);
		$start->setWeekstart();
		$first = $start->format('ts');
		$start->setDate($this->year,$this->month+1,$day);
		if ($day == 1) $start->add('-1day');
		$start->setWeekstart();
		// now we need to calculate the end of the last day of that week
		// as simple $last += WEEK_s - 1; does NOT work, if daylight saving changes in that week!!!
		$arr = $start->format('array');
		$arr['day'] += 6;
		$arr['hour'] = 23;
		$arr['min'] = $arr['sec'] = 59;
		unset($arr['raw']);	// otherwise date2ts does not calc raw new, but uses it
		$last = $this->bo->date2ts($arr);
	}

	/**
	 * Displays the weekview, with 5 or 7 days
	 *
	 * @param int $days = 0 number of days to show, if 0 (default) the value from the URL or the prefs is used
	 * @param boolean|etemplate2 $home = false if not false return content suitable for home-page
	 */
	function week($days=0,$home=false)
	{
		if (!$days)
		{
			$days = isset($_GET['days']) ? $_GET['days'] : $this->cal_prefs['days_in_weekview'];
			if ($days != 5) $days = 7;
			if ($days != $this->cal_prefs['days_in_weekview'])	// save the preference
			{
				$GLOBALS['egw']->preferences->add('calendar','days_in_weekview',$days);
				$GLOBALS['egw']->preferences->save_repository();
				$this->cal_prefs['days_in_weekview'] = $days;
			}
		}
		if ($this->debug > 0) $this->bo->debug_message('uiviews::week(days=%1) date=%2',True,$days,$this->date);

		if ($days <= 4)		// next 4 days view
		{
			$this->first = new Api\DateTime($this->date);
			$this->last = clone $this->first;
			$this->last->add(new DateInterval("P{$days}D"));
			$this->last->modify('-1 second');
			$view = $days == 1 ? 'day' : 'day4';
		}
		else
		{
			$this->first = new Api\DateTime($this->date);
			$this->first->setWeekstart();
			if ($days <= 5)		// no weekend-days
			{
				switch($this->cal_prefs['weekdaystarts'])
				{
					case 'Saturday':
						$this->first->modify('+2 days');
						break;
					case 'Sunday':
						$this->first->modify('+1 day');
						break;
				}
			}
			$this->last = clone $this->first;
			$this->last->add(new DateInterval("P{$days}D"));
			$this->last->modify('-1 second');
			$view = 'week';
		}

		$granularity = ($this->cal_prefs['interval'] ? (int)$this->cal_prefs['interval'] : 30);

		$list = $this->cal_prefs['use_time_grid'];
		if(!is_array($list))
		{
			$list = explode(',',$list);
		}
		if(is_array($list))
		{
			$granularity = in_array($view,$list) ? 0 : $granularity;
		}

		$search_params = array(
				'start'   => $this->first,
				'end'     => $this->last,
			) + $this->search_params;

		$users = $this->search_params['users'];
		if (!is_array($users)) $users = array($users);

		$content = array('view' => array());

		if(!$home)
		{
			// Fill with the minimum needed 'weeks'
			$min = max(
				6, // Some months need 6 weeks for full display
				$this->cal_prefs['multiple_weeks'],  // WeekN view
				$this->cal_prefs['week_consolidate'] // We collapse after this many users
			);
			for($i = 0; $i < $min; $i++)
			{
				$content['view'][] = array();
			}
		}
		else
		{
			// Always do 7 days for a week so scrolling works properly
			if($days != 4)
			{
				$end = new Api\DateTime($this->first, Api\DateTime::$user_timezone);
				$end->add(new DateInterval("P{$days}D"));
				$end->modify('-1 second');
				$this->last = $search_params['end'] = $end;
			}
			if (count($users) == 1 || count($users) >= $this->cal_prefs['week_consolidate']	||// for more then X users, show all in one row
				$days == 1 // Showing just 1 day
			)
			{
				$content['view'][] = $this->tagWholeDayOnTop($this->bo->search($search_params)) +
					array('owner' => $users);
			}
			else
			{
				foreach($users as $uid)
				{
					$search_params['users'] = $uid;
					$content['view'][] = $this->tagWholeDayOnTop($this->bo->search($search_params))
						 + array('owner' => $uid);
				}
			}
		}
		$tmpl = $home ? $home :new Etemplate('calendar.view');
		foreach(array_keys($content['view']) as $index)
		{
			$tmpl->setElementAttribute("view[$index]",'granularity',$granularity);
			$tmpl->setElementAttribute("view[$index]",'show_weekend',$this->search_params['weekend']);
		}

		// Get the actions
		$tmpl->setElementAttribute('view','actions',$this->get_actions());

		$tmpl->exec(__METHOD__, $content);
	}

	/**
	 * Get todos via AJAX
	 */
	public static function ajax_get_todos($_date, $owner)
	{
		$date = Api\DateTime::to($_date, 'array');
		$ui = new calendar_uiviews();
		$ui->year = $date['year'];
		$ui->month = $date['month'];
		$ui->day = $date['day'];
		$ui->owner = (int)$owner;

		$label = '';
		$todos = $ui->get_todos($label);
		Api\Json\Response::get()->data(array(
			'label' => $label,
			'todos' => $todos
		));
	}

	/**
	 * Query the open ToDo's via a hook from InfoLog or any other 'calendar_include_todos' provider
	 *
	 * @param array/string $todo_label label for the todo-box or array with 2 values: the label and a boolean show_all
	 *	On return $todo_label contains the label for the todo-box
	 * @return string/boolean Api\Html with a table of open todo's or false if no hook availible
	 */
	function get_todos(&$todo_label)
	{
		$todos_from_hook = Api\Hooks::process(array(
			'location'  => 'calendar_include_todos',
			'year'      => $this->year,
			'month'     => $this->month,
			'day'       => $this->day,
			'owner'     => $this->owner	// num. id of the user, not necessary current user
		));

		if(is_array($todo_label))
		{
			list($label,$showall)=$todo_label;
		}
		else
		{
			$label=$todo_label;
			$showall=true;
		}
		$maxshow = (int)$GLOBALS['egw_info']['user']['preferences']['infolog']['mainscreen_maxshow'];
		if($maxshow <= 0)
		{
			$maxshow=10;
		}
		//print_debug("get_todos(): label=$label; showall=$showall; max=$maxshow");

		$todo_label = '';
		$todo_list = array();
		if (is_array($todos_from_hook) && count($todos_from_hook))
		{
			foreach($todos_from_hook as $todos)
			{
				$i = 0;
				if (is_array($todos))
				{
					$todo_label = !empty($label) ? $label : lang("open ToDo's:");

					foreach($todos as &$todo)
					{
						if(!$showall && ($i++ > $maxshow))
						{
							break;
						}
						$icons = '';
						foreach($todo['icons'] as $name => $alt)
						{
							$icons .= ($icons?' ':'').Api\Html::image('infolog',$name,lang($alt),'border="0" width="15" height="15"');
						}
						$todo['icons'] = $icons;
						if($todo['edit']) {
							$todo['edit_size'] = $todo['edit']['size'];
							unset($todo['edit']['size']);
							$edit_icon_href = Api\Html::a_href( $icons, $todo['edit'],'',' data-todo="app|750x590" ');
							$edit_href = Api\Html::a_href( $todo['title'], $todo['edit'],'',' data-todo="app|750x590" ');
							$todo['edit'] = Framework::link('/index.php',$todo['edit'],true);
						}
						$todo_list[] = $todo;
					}
				}
			}
		}
		return $todo_list;
	}

	/**
	 * Get onclick attribute to open integration item for edit
	 *
	 * Name of the attribute is 'edit_link' and it should be an array with values for keys:
	 * - 'edit'    => array('menuaction' => 'app.class.method')
	 * - 'edit_id' => 'app_id'
	 * - 'edit_popup' => '400x300' (optional)
	 *
	 * @param string $app
	 * @param int|string $id
	 * @return string
	 */
	public static function integration_get_popup($app,$id)
	{
		$app_data = calendar_bo::integration_get_data($app,'edit_link');

		if (is_array($app_data) && isset($app_data['edit']))
		{
			$popup_size = $app_data['edit_popup'];
			$edit = $app_data['edit'];
			$edit[$app_data['edit_id']] = $id;
		}
		else
		{
			$edit = Link::edit($app,$id,$popup_size);
		}
		if ($edit)
		{
			if ($popup_size)
			{
				$popup = ' data-app="app|'.$popup_size.'"';
			}
			else
			{
				$popup = ' data-app="app|'.$app.'|'.'"';
			}
		}
		return $popup;
	}

	/**
	 * Get icons for an integration event
	 *
	 * Attribute 'icons' is either null (--> navbar icon), false (--> no icon)
	 * or a callback with parameters $id,$event
	 *
	 * Icons specified in $events['icons'] are always displayed!
	 *
	 * @param string $app
	 * @param int|string $id
	 * @param array $event
	 * @return array
	 */
	static function integration_get_icons($app,$id,$event)
	{
		$icons = array();
		if ($event['icons'])
		{
			foreach(explode(',',$event['icons']) as $icon)
			{
				list($icon_app,$icon) = explode(':',$icon);
				if (Api\Image::find($icon_app,$icon))
				{
					$icons[] = Api\Html::image($icon_app,$icon);
				}
			}
		}
		$app_data = calendar_bo::integration_get_data($app,'icons');
		if (is_null($app_data))
		{
			if (($icon = Api\Link::get_registry($app,'icon')) &&
				($icon = explode('/', $icon)))
			{

				$icons[] = Api\Html::image($icon[0], $icon[1]);	// use icon from link registry
			}
			else
			{
				$icons[] = Api\Html::image($app,'navbar');	// use navbar icon
			}
		}
		elseif ($app_data)
		{
			$icons += (array)ExecMethod2($app_data,$id,$event);
		}
		return $icons;
	}

	/**
	 * Get the actions for the non-list views
	 *
	 * We use the actions from the list as a base, and only change what we have to
	 * to get it to work outside of a nextmatch.
	 *
	 * @return Array
	 */
	protected static function get_actions()
	{
		// Just copy from the list, but change to match our needs
		$ui = new calendar_uilist();
		$actions = $ui->get_actions();

		unset($actions['no_notifications']);
		unset($actions['select_all']);

		// This disables the event actions for the grid rows (calendar weeks/owners)
		foreach($actions as $id => &$action)
		{
			if($id=='add') continue;
			if(!$action['enabled'])
			{
				$action['enabled'] = 'javaScript:app.calendar.is_event';
			}
		}
		$actions['add']['open'] = '{"app":"calendar","type":"add"}';
		$actions['add']['onExecute'] =  'javaScript:app.calendar.action_open';
		$actions['copy']['open'] = '{"app": "calendar", "type": "edit", "extra": "cal_id=$id&action=copy"}';
		$actions['copy']['onExecute'] = 'javaScript:app.calendar.action_open';
		$actions['print']['open'] = '{"app": "calendar", "type": "edit", "extra": "cal_id=$id&print=1"}';
		$actions['print']['onExecute'] = 'javaScript:app.calendar.action_open';
		$actions['notifications']['onExecute'] = 'javaScript:app.calendar.action_open';

		foreach($actions['status']['children'] as $id => &$status)
		{
			$status = array(
				'id' => $id,
				'caption' => $status,
                'icon' => $ui->bo->status_images[$id],
				'onExecute' => 'javaScript:app.calendar.status'
			);
		}

		if ($actions['filemanager'])
		{
			$actions['filemanager']['url'] = '/index.php?'. $actions['filemanager']['url'];
			$actions['filemanager']['onExecute'] = 'javaScript:app.calendar.action_open';
		}
		$actions['ical']['onExecute'] = 'javaScript:app.calendar.ical';

		$actions['delete']['onExecute'] = 'javaScript:app.calendar.delete';

		// allow other apps to add actions
		foreach(Api\Hooks::process(array(
			'location' => 'add_calendar_non_list_actions',
			'template_name' => 'calendar_non_list',
		), array('policy'), true) as $app => $data)
		{
			// todo: place new items based on group
			if ($data) $actions = EGroupware\Api\Etemplate\Widget\Nextmatch::merge_actions_by_group((array)$actions, $data);
		}

		return $actions;
	}

	/**
	 * Marks whole day events for later usage and increments extraRows
	 *
	 * @param array $dayEvents
	 * @return array $dayEvents
	 */
	function tagWholeDayOnTop($dayEvents)
	{
		$this->extraRows = $this->extraRowsOriginal;
		$this->remBotExtraRows = 0;

		if (is_array($dayEvents))
		{
			foreach ($dayEvents as $day=>$oneDayEvents)
			{
				$extraRowsToAdd = 0;
				foreach ($oneDayEvents as $num => $event)
				{
					if((int)$event['start']->format('H') === 0 && (int)$event['start']->format('i') === 0 &&
						(int)$event['end']->format('H') === 23 && (int)$event['end']->format('i') === 59)
					{
						if($event['non_blocking'])
						{
							$dayEvents[$day][$num]['whole_day_on_top']=true;
							$this->whole_day_positions[$num]=($this->rowHeight*($num+2));
							$extraRowsToAdd++;
						}
						else
						{
							$dayEvents[$day][$num]['whole_day']=true;
						}
					}
				}
				// check after every day if we have to increase $this->extraRows
				if(($this->extraRowsOriginal+$extraRowsToAdd) > $this->extraRows)
				{
					$this->remBotExtraRows = $extraRowsToAdd;
					$this->extraRows = ($this->extraRowsOriginal+$extraRowsToAdd);
				}
			}

			// Run to_client() on all events after, since to_client() modifies the event
			// and we need the original event for the whole day check.  Multi-day events
			// are in the array as references.
			foreach($dayEvents as $day => $oneDayEvents)
			{
				foreach($oneDayEvents as $num => $event)
				{
					$this->to_client($dayEvents[$day][$num]);
				}
			}
		}
		else
		{
			$dayEvents = [];    // search returns false or null for nothing found!
		}
		return $dayEvents;
 	}
}