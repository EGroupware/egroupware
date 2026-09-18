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
	 * Column filters a client may send.
	 *
	 * '#...' custom-field columns are allowed too, and re-checked against the app's actually
	 * defined custom fields in History::get_rows().
	 */
	protected static $allowed_col_filters = array('status', 'owner', 'user_ts');

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
					if (in_array($column, self::$allowed_col_filters, true) || $column[0] === '#')
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
		// Which record's history this is, taken from the server's own content rather than from
		// whatever the client sent - see self::$server_authoritative.  Their presence in the
		// client's filters is what distinguishes a row request from a form submit, so a submit
		// (which sends neither) still contributes nothing.
		// Not self::get_array(self::$request->content, ...) directly: its first parameter is
		// by-reference, so a null request would be auto-vivified and fatal rather than simply
		// yielding nothing.
		$request_content = is_object(self::$request) ? (array)self::$request->content : array();
		$content_value = self::get_array($request_content, $form_name);
		foreach(self::$server_authoritative as $filter_key => $content_key)
		{
			if (isset($value[$filter_key]))
			{
				$filters[$filter_key] = is_array($content_value) ? ($content_value[$content_key] ?? null) : null;
			}
		}
		// Apps are expected to set 'app' (see Api\Storage\Tracking's documented content shape) and
		// every in-tree one does, but nothing enforces it - and History::get_rows() scopes every
		// query by appname, so a missing one silently returns an empty history rather than failing.
		// Fall back to the current app, which is what History's own constructor does for the same
		// reason, and is the app whose dialog this history log is sitting in.
		// array_key_exists, not isset: the value we just wrote is null when the content had no
		// 'app', and isset() is false for null - which is exactly the case this is here to catch.
		if (array_key_exists('appname', $filters) && (string)$filters['appname'] === '')
		{
			$filters['appname'] = $GLOBALS['egw_info']['flags']['currentapp'] ?? null;
		}
		if ($filters)
		{
			$valid =& self::get_array($validated, $form_name, true);
			$valid = $filters;
		}
		return true;
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\HistoryLog', array('et2-historylog', 'historylog'));