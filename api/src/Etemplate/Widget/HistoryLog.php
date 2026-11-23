<?php
/**
 * EGroupware eTemplate2 - History log server-side
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2012 Nathan Gray
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * eTemplate history log widget displays a list of changes to the current record.
 * The widget is encapsulated, and only needs the record's ID, and a map of
 * fields:widgets for display
 */
class HistoryLog extends Etemplate\Widget
{

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * Historylog need to use $this->id as namespace, otherwise we overwrite
	 * sel_options of regular widgets with more general data for historylog!
	 *
	 * eg. owner in addressbook.edit contains only accounts user has rights to,
	 * while it uses select-account for owner in historylog (containing all users).
	 *
	 * @param string $cname
	*/
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);

		// Historylog is a reusable, self-configuring widget: unlike nextmatch,
		// whose callers each set content[form_name]['get_rows'] themselves,
		// no app sets one for us. Seed the trusted default/override here, server-side, so
		// Nextmatch::ajax_get_rows()'s generic content-vs-client-filters merge picks
		// it up without needing to know about this widget type.
		// $this->attrs['get_rows'] is safe to trust: attrs are populated only from
		// the .xet template XML parsed server-side (see Widget::__construct()),
		// never from request/session data a client could influence.
		$value =& self::get_array(self::$request->content, $form_name, true);
		$value['get_rows'] = $this->attrs['get_rows'] ?? Api\Storage\History::class.'::get_rows';

		// Statuses every history log can show, whatever the app declared, plus the app's custom
		// fields.  They go into sel_options rather than onto the client's row template because
		// Et2Select resolves its options from the sel_options array manager by id, which wins over
		// anything the client sets on the element - so an attribute set client-side is silently
		// replaced during row hydration, and attachment rows end up with a blank "Changed" cell.
		//
		// The same list is mirrored to the filter's own path, so the "Changed" filter offers
		// exactly what the column can display - which is what makes '~file~' (attachments) and
		// '~link~' filterable without inventing separate filter values for them.
		$this->addStatusOptions($form_name);

		if(is_array(self::$request->content[$form_name]['status-widgets']))
		{
			foreach(self::$request->content[$form_name]['status-widgets'] as $key => $type)
			{
				if (is_array($type) && count($type) === 1 && isset($type[0]))
				{
					$type = $type[0];
				}
				if(!is_array($type))
				{
					list($basetype) = explode('-',$type);
					list($tag) = explode(':', $type);	// xml tags must not include undeclared namespaces like: <link-entry:infolog
					$widget = @self::factory($basetype, '<?xml version="1.0"?><'.$tag.' type="'.$type.'"/>', $key);
					$widget->id = $key;
					$widget->attrs['type'] = $type;
					$widget->type = $type;

					if(method_exists($widget, 'beforeSendToClient'))
					{
						// need to use $form_name as $cname see comment in header
						$widget->beforeSendToClient($form_name, array());
					}
				}
				else
				{
					// need to use self::form_name($form_name, $key) as index into sel_options see comment in header
					$options =& self::get_array(self::$request->sel_options, self::form_name($form_name, $key), true);
					if (!is_array($options)) $options = array();
					$options += $type;
				}

			}
		}
	}

	/**
	 * Add the built-in and custom-field statuses to the "Changed" column's options
	 *
	 * @param string $form_name namespace the history log's sel_options live under
	 */
	protected function addStatusOptions($form_name)
	{
		$appname = self::$request->content[$form_name]['app'] ?? null;
		// The "Changed" column is normally called 'status', but an app whose dialog already has a
		// widget of that name renames it - calendar uses 'history_status', via the legacy
		// `options="history_status"`.  Server-side we parse the raw .xet, where that is still the
		// legacy `options` attribute (api/etemplate.php only rewrites the copy sent to the client),
		// so accept every spelling.  Getting this wrong is silent: the options land under a name
		// nothing reads, and the column falls back to showing raw field codes.
		$status_id = $this->attrs['status_id'] ?? $this->attrs['statusId'] ?? $this->attrs['options'] ?? 'status';

		$extra = array(
			'~link~'            => lang('Link'),
			'~file~'            => lang('File'),
			'user_agent_action' => lang('User-agent & action'),
		);
		// Custom fields are recorded as '#<name>' and are not in the app's status-widgets map
		foreach($appname ? (array)Api\Storage\Customfields::get($appname) : array() as $name => $cf)
		{
			$extra['#'.$name] = $cf['label'] ?: $name;
		}

		// Apps put their field labels in $sel_options['status'] at the *root*, not under the history
		// log's namespace, and the client's array manager finds them by falling back to the root
		// when the namespaced key is absent.  So writing a namespaced list does not merge with
		// theirs - it SHADOWS it, and the column loses every app label it used to show.  Seed from
		// the root list first so the one list at our own path is the complete one.
		$app_labels = (array)(self::$request->sel_options[$status_id] ?? array());
		// Apps commonly map a field they have no label for to null - infolog does exactly that for
		// every custom field (`$tracking->field2label[$field] ?? null`).  A null must not win over
		// the real customfield label added below, or the column shows the raw field code ("#chrgs2"
		// instead of "2-Charges"), so drop the empty ones before merging.
		$app_labels = array_filter($app_labels, static function($label) { return (string)$label !== ''; });
		$options =& self::get_array(self::$request->sel_options, self::form_name($form_name, $status_id), true);
		if (!is_array($options)) $options = array();
		// += so an existing label for the same key wins - the app knows its fields better than we do
		$options += $app_labels;
		$options += $extra;

		// Same list for the filter, whose widget id is col_filter[status]
		$filter_options =& self::get_array(self::$request->sel_options, self::form_name($form_name, 'col_filter[status]'), true);
		if (!is_array($filter_options)) $filter_options = array();
		$filter_options += $options;
	}

	/**
	 * Filter keys a client may send, and how to check each one.
	 *
	 * Everything else is dropped.  This is an allow-list rather than a deny-list because the
	 * result is handed straight to Api\Storage\History::get_rows() as its query: a key that got
	 * through would become a WHERE clause.  In particular 'order'/'sort' are NOT here - the
	 * history log's sort is fixed (newest first), so no client input reaches an ORDER BY at all -
	 * and neither are 'start'/'num_rows', which Nextmatch::ajax_get_rows() takes from the queried
	 * range after this runs, never from the filters.
	 */
	protected static $allowed_filters = array(
		'search'     => 'is_string',
		'col_filter' => null,       // checked per-column below
	);

	/**
	 * Which record's history is being requested is decided by the server, not the client.
	 *
	 * These two are what scope every history query, and History::get_rows() does no permission
	 * check of its own - so taking them from the client would let anyone read any entry's history
	 * in any app by sending a different pair.  The client still has to *send* them (that is what
	 * marks the request as a row request rather than a form submit), but the values are replaced
	 * with the server's own, from the content the app put in $content[<widget id>].
	 *
	 * Same reasoning as the 'get_rows' callback, which beforeSendToClient() already seeds
	 * server-side for exactly this reason.
	 *
	 * @var array client filter key => server content key
	 */
	protected static $server_authoritative = array(
		'record_id' => 'id',
		'appname'   => 'app',
	);

	/**
	 * Column filters a client may send, and the method checking the value of each.
	 *
	 * '#...' custom-field columns are allowed too, see self::checkCustomfieldFilter().
	 */
	protected static $allowed_col_filters = array(
		'status'  => 'checkStatusFilter',
		'owner'   => 'checkOwnerFilter',
		'user_ts' => 'checkDateFilter',
	);

	/**
	 * Statuses every history log can show, whatever the app declared, see addStatusOptions()
	 */
	const BUILTIN_STATUSES = array('~link~', '~file~', 'user_agent_action');

	/**
	 * Validate input
	 *
	 * This widget has two callers, and has to behave differently for each without being told
	 * which one it is:
	 *
	 *  - A real form submit walking the template.  The history log is a display widget and
	 *    returns no value, so it must contribute nothing to $validated.
	 *  - Nextmatch::ajax_get_rows(), which deliberately runs validate() as its filter sanitizer
	 *    and then *replaces* the client's filters with what comes back.
	 *
	 * The allow-list below settles both: build the permitted subset, and write $validated only if
	 * it is non-empty.  On a submit the client sends no filter keys (this is not an input widget),
	 * so nothing is written; on a row request it is the filters.  Deny-by-default falls out, and
	 * an unknown key is simply dropped rather than passed to the database.
	 *
	 * The value of each permitted filter is checked as well, by what it means:
	 *
	 *  - search: a string
	 *  - col_filter[status]: one or more of the statuses this history log offers in its "Changed"
	 *    filter, a key of its status-widgets, a built-in status or one of the app's custom fields
	 *  - col_filter[owner]: one or more ids of existing accounts
	 *  - col_filter[user_ts]: a date, or a from/to pair of dates either of which may be empty
	 *  - col_filter[#<name>]: <name> is a custom field of the app the user can see, the value a scalar
	 *  - record_id, appname: always replaced by the entry the history log was rendered for
	 *  - column filters the app set in the content, other than the ones above: always kept as set
	 *
	 * Like the filters of a nextmatch (see Nextmatch::validate()), what happens to a value
	 * failing its check depends on $GLOBALS['egw_info']['server']['nm_filter_validation']:
	 * 'report' (default) passes it on and logs it as "would drop", 'enforce' drops and logs it,
	 * false checks nothing.  Keys which are not allowed at all are always dropped.
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array($content, $form_name);
		if (!is_array($value))
		{
			return true;
		}
		// Not self::get_array(self::$request->content, ...) directly: its first parameter is
		// by-reference, so a null request would be auto-vivified and fatal rather than simply
		// yielding nothing.
		$request_content = is_object(self::$request) ? (array)self::$request->content : array();
		$content_value = self::get_array($request_content, $form_name);
		if (!is_array($content_value)) $content_value = array();

		$mode = $GLOBALS['egw_info']['server']['nm_filter_validation'] ?? 'report';
		$enforce = $mode === 'enforce';

		// Which record's history this is, taken from the server's own content rather than from
		// whatever the client sent - see self::$server_authoritative.  Their presence in the
		// client's filters is what distinguishes a row request from a form submit, so a submit
		// (which sends neither) still contributes nothing.
		$scope = array();
		foreach(self::$server_authoritative as $filter_key => $content_key)
		{
			if (isset($value[$filter_key]))
			{
				$scope[$filter_key] = $content_value[$content_key] ?? null;
			}
		}
		// Apps are expected to set 'app' (see Api\Storage\Tracking's documented content shape) and
		// every in-tree one does, but nothing enforces it - and History::get_rows() scopes every
		// query by appname, so a missing one silently returns an empty history rather than failing.
		// Fall back to the current app, which is what History's own constructor does for the same
		// reason, and is the app whose dialog this history log is sitting in.
		// array_key_exists, not isset: the value we just wrote is null when the content had no
		// 'app', and isset() is false for null - which is exactly the case this is here to catch.
		if (array_key_exists('appname', $scope) && (string)$scope['appname'] === '')
		{
			$scope['appname'] = $GLOBALS['egw_info']['flags']['currentapp'] ?? null;
		}
		// The client sends what it was rendered with, so anything else is somebody trying
		// another entry - or content the app changed without rendering the history log again.
		if ($mode)
		{
			foreach($scope as $filter_key => $server_value)
			{
				if ((string)$value[$filter_key] !== (string)$server_value)
				{
					self::logRejected($form_name, $filter_key, $value[$filter_key],
						'replaced by '.json_encode($server_value).', the entry the history log was rendered for', true);
				}
			}
		}
		$app = $scope['appname'] ?? (($content_value['app'] ?? '') ?: ($GLOBALS['egw_info']['flags']['currentapp'] ?? null));

		$filters = array();
		foreach(self::$allowed_filters as $key => $check)
		{
			if (!isset($value[$key]) || $value[$key] === '' || $value[$key] === array())
			{
				continue;
			}
			if ($key === 'col_filter')
			{
				if (!is_array($value[$key])) continue;
				$col_filter = array();
				foreach($value[$key] as $column => $val)
				{
					// Numeric keys would be raw SQL fragments
					if (is_int($column) || $val === '' || $val === null || $val === array())
					{
						continue;
					}
					if (isset(self::$allowed_col_filters[$column]))
					{
						$reason = $mode ? $this->{self::$allowed_col_filters[$column]}($val, $form_name, $content_value, $app) : null;
					}
					elseif ($column[0] === '#')
					{
						$reason = $mode ? self::checkCustomfieldFilter($column, $val, $app) : null;
					}
					else
					{
						if ($mode)
						{
							self::logRejected($form_name, "col_filter[$column]", $val, 'not a column of the history log', true);
						}
						continue;
					}
					if (isset($reason))
					{
						self::logRejected($form_name, "col_filter[$column]", $val, $reason, $enforce);
						if ($enforce) continue;
					}
					$col_filter[$column] = $val;
				}
				// Nextmatch::ajax_get_rows() replaces the whole col_filter of the content with
				// ours, so column filters the app set server-side, which a client can not set,
				// have to be kept here, or a client filter would remove what scopes the history
				// (eg. Policy scopes it to the commands of one addressbook by cmd_account).
				foreach((array)($content_value['col_filter'] ?? []) as $column => $val)
				{
					if (is_int($column) || !isset(self::$allowed_col_filters[$column]) && $column[0] !== '#')
					{
						$col_filter[$column] = $val;
					}
				}
				if ($col_filter) $filters['col_filter'] = $col_filter;
				continue;
			}
			if ($check && !$check($value[$key]))
			{
				continue;
			}
			$filters[$key] = $value[$key];
		}
		$filters += $scope;
		if ($filters)
		{
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $filters;
		}
		return true;
	}

	/**
	 * Check the value of the "Changed" filter
	 *
	 * Valid are the statuses the filter offers (the options beforeSendToClient() put at its path
	 * of the stored sel_options), the keys of the app's status-widgets, the built-in statuses and
	 * the app's custom fields.  The client may offer some of those without a label, so they are
	 * all accepted, not only the options.
	 *
	 * @param mixed $val one status or a list of them
	 * @param string $form_name full name of the history log
	 * @param array $content_value content the history log was rendered with
	 * @param ?string $app app whose history it is
	 * @return ?string null if valid, otherwise why not
	 */
	protected function checkStatusFilter($val, string $form_name, array $content_value, ?string $app) : ?string
	{
		$sel_options = is_object(self::$request) ? (array)self::$request->sel_options : array();
		$options = self::get_array($sel_options, self::form_name($form_name, 'col_filter[status]'));
		$statuses = array();
		foreach(is_array($options) ? $options : array() as $key => $label)
		{
			$statuses[] = (string)(is_array($label) && isset($label['value']) ? $label['value'] : $key);
		}
		$statuses = array_merge($statuses, array_map('strval', array_keys((array)($content_value['status-widgets'] ?? array()))),
			self::BUILTIN_STATUSES);
		foreach($app ? array_keys((array)Api\Storage\Customfields::get($app)) : array() as $name)
		{
			$statuses[] = '#'.$name;
		}
		foreach((array)$val as $status)
		{
			if (!is_scalar($status))
			{
				return 'not a status';
			}
			if (!in_array((string)$status, $statuses, true))
			{
				return 'not a status of this history log';
			}
		}
		return null;
	}

	/**
	 * Check the value of the "User" filter: one or more ids of existing accounts
	 *
	 * @param mixed $val one account id or a list of them
	 * @return ?string null if valid, otherwise why not
	 */
	protected function checkOwnerFilter($val) : ?string
	{
		foreach((array)$val as $account_id)
		{
			if (!(is_int($account_id) || is_string($account_id) && preg_match('/^-?\d+$/', $account_id)) ||
				!$GLOBALS['egw']->accounts->exists((int)$account_id))
			{
				return 'not an account';
			}
		}
		return null;
	}

	/**
	 * Check the value of the "Date" filter: a date, or the from/to pair Et2DateRange sends, either
	 * of which may be empty
	 *
	 * @param mixed $val
	 * @return ?string null if valid, otherwise why not
	 */
	protected function checkDateFilter($val) : ?string
	{
		if (is_array($val) && array_diff(array_keys($val), array('from', 'to')))
		{
			return 'not a date range';
		}
		foreach(is_array($val) ? $val : array($val) as $date)
		{
			if ($date === null || $date === '')
			{
				continue;
			}
			if (!is_string($date) && !is_int($date))
			{
				return 'not a date';
			}
			try
			{
				new Api\DateTime($date);
			}
			catch(\Exception $e)
			{
				return 'not a date';
			}
		}
		return null;
	}

	/**
	 * Check a custom-field filter: it has to name a custom field of the app the user can see
	 *
	 * Only the name decides what History::get_rows() returns, the value is not used, so any
	 * scalar is fine.
	 *
	 * @param string $column '#' followed by the name of the custom field
	 * @param mixed $val
	 * @param ?string $app app whose history it is
	 * @return ?string null if valid, otherwise why not
	 */
	protected static function checkCustomfieldFilter(string $column, $val, ?string $app) : ?string
	{
		if (!$app || !isset(Api\Storage\Customfields::get($app)[substr($column, 1)]))
		{
			return 'not a custom field of '.$app;
		}
		return is_scalar($val) ? null : 'not a scalar';
	}

	/**
	 * Log a filter value failing its check
	 *
	 * Same wording, log destination and browser-console warning as
	 * Nextmatch::logUnvalidatedFilters(), so the findings of nextmatches and history logs can be
	 * searched for together.  Each line is logged once per request.
	 *
	 * @param string $form_name full name of the history log
	 * @param string $filter name of the filter relative to the history log, eg. "col_filter[owner]"
	 * @param mixed $val value the client sent
	 * @param string $reason why it is not valid
	 * @param bool $dropped true: it is not passed on, false: it is passed on nevertheless
	 */
	protected static function logRejected(string $form_name, string $filter, $val, string $reason, bool $dropped) : void
	{
		static $logged = [];
		$log_file = $GLOBALS['egw_info']['server']['nm_filter_validation_report'] ?? null;
		$template = is_object(self::$request) ? (self::$request->template['name'] ?? '') : '';
		$line = 'Nextmatch filter validation '.($dropped ? 'dropped' : 'would drop').": $template $form_name $filter=".
			substr(json_encode($val), 0, 100).": $reason";
		if (isset($logged[$line]))
		{
			return;
		}
		$logged[$line] = true;
		is_string($log_file) ? error_log(date('c ').$line."\n", 3, $log_file) : error_log($line);

		if (Api\Json\Response::isJSONResponse())
		{
			Api\Json\Response::get()->apply('egw.debug', ['warn', $line]);
		}
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\HistoryLog', array('et2-historylog', 'historylog'));
