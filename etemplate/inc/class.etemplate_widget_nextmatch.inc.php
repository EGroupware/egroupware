<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate serverside implementation of the nextmatch widget
 *
 * $content[$id] = array(	// I = value set by the app, 0 = value on return / output
 * 	'get_rows'       =>		// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
 * 	'filter_label'   =>		// I  label for filter    (optional)
 * 	'filter_help'    =>		// I  help-msg for filter (optional)
 * 	'no_filter'      => True// I  disable the 1. filter
 * 	'no_filter2'     => True// I  disable the 2. filter (params are the same as for filter)
 * 	'no_cat'         => True// I  disable the cat-selectbox
 *	'cat_app'        =>     // I  application the cat's should be from, default app in get_rows
 *	'cat_is_select'  =>     // I  true||'no_lang' use selectbox instead of category selection, default null
 * 	'template'       =>		// I  template to use for the rows, if not set via options
 * 	'header_left'    =>		// I  template to show left of the range-value, left-aligned (optional)
 * 	'header_right'   =>		// I  template to show right of the range-value, right-aligned (optional)
 * 	'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
 *	'never_hide'     => True// I  never hide the nextmatch-line if less then maxmatch entries
 *	'lettersearch'   => True// I  show a lettersearch
 *	'searchletter'   =>     // IO active letter of the lettersearch or false for [all]
 * 	'start'          =>		// IO position in list
 *	'num_rows'       =>     // IO number of rows to show, defaults to maxmatches from the general prefs
 * 	'cat_id'         =>		// IO category, if not 'no_cat' => True
 * 	'search'         =>		// IO search pattern
 * 	'order'          =>		// IO name of the column to sort after (optional for the sortheaders)
 * 	'sort'           =>		// IO direction of the sort: 'ASC' or 'DESC'
 * 	'col_filter'     =>		// IO array of column-name value pairs (optional for the filterheaders)
 * 							// grid requires implementation of folowing filters in get_rows, even if not used as regular filters!
 * 							//  O col_filter[$row_id]   to query certain rows only
 * 							//  O col_filter[parent_id] row_id of parent to query children for hierachical display
 * 	'filter'         =>		// IO filter, if not 'no_filter' => True
 * 	'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
 *	'filter_onchange'=> 'this.form.submit();' // I onChange action for filter, default: this.form.submit();
 * 	'filter2'        =>		// IO filter2, if not 'no_filter2' => True
 * 	'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
 *	'filter2_onchange'=> 'this.form.submit();' // I onChange action for filter2, default: this.form.submit();
 * 	'rows'           =>		//  O content set by callback
 * 	'total'          =>		//  O the total number of entries
 * 	'sel_options'    =>		//  O additional or changed sel_options set by the callback and merged into $tmpl->sel_options
 * 	'no_columnselection' => // I  turns off the columnselection completly, turned on by default
 * 	'columnselection-pref' => // I  name of the preference (plus 'nextmatch-' prefix), default = template-name
 * 	'default_cols'   => 	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
 * 	'options-selectcols' => // I  array with name/label pairs for the column-selection, this gets autodetected by default. A name => false suppresses a column completly.
 *	'return'         =>     // IO allows to return something from the get_rows function if $query is a var-param!
 *	'csv_fields'     =>		// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames or preferred importexport definition,
 * 		array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
 *		or name of import/export definition
 *  'row_id'         =>     // I  key into row content to set it's value as row-id, eg. 'id'
 *  'row_modified'   =>		// I  key into row content for modification date or state of a row, to not query it again
 *  'parent_id'      =>		// I  key into row content of children linking them to their parent
 *  'is_parent'      =>		// I  key into row content to mark a row to have children
 *  'is_parent_value'=>     // I  if set value of is_parent, otherwise is_parent is evaluated as boolean
 *  'dataStorePrefix'	=>	// I Optional prefix for client side cache to prevent collisions in applications that have more than one data set, such as ProjectManager / Project elements.  Defaults to appname if not set.
 *  'actions'        =>     // I  array with actions, see nextmatch_widget::egw_actions
 *  'action_links'   =>     // I  array with enabled actions or ones which should be checked if they are enabled
 *                                optional, default id of all first level actions plus the ones with enabled='javaScript:...'
 *  'action_var'     => 'action'	// I name of var to return choosen action, default 'action'
 *  'action'         =>     //  O string selected action
 *  'selected'       =>     //  O array with selected id's
 *  'checkboxes'     =>     //  O array with checkbox id as key and boolean checked value
 *  'select_all'     =>     //  O boolean value of select_all checkbox, reference to above value for key 'select_all'
 *  'favorites'      =>     //  I boolean|array True to enable favorites, or an array of additional, app specific settings to include
 *					in the saved filters (eg: pm_id)
 *  'placeholder'    =>     //  I String Optional text to display in the empty row placeholder.  If not provided, it's "No matches found."
 *  'placeholder_actions' =>     //  I Array Optional list of actions allowed on the placeholder.  If not provided, it's ["add"].
 */
class etemplate_widget_nextmatch extends etemplate_widget
{
	public function __construct($xml='')
	{
		if($xml) {
			parent::__construct($xml);

			// TODO: probably a better way to do this
			egw_framework::includeCSS('/phpgwapi/js/egw_action/test/skins/dhtmlxmenu_egw.css');
		}
	}

	/**
	 * Legacy options
	 */
	protected $legacy_options = 'template';

	/**
	 * Number of rows to send initially
	 */
	const INITIAL_ROWS = 25;

	/**
	 * Set up what we know on the server side.
	 *
	 * Sending a first chunk of rows
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand)
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array(self::$request->content, $form_name, true);

		$value['start'] = 0;
		$value['num_rows'] = self::INITIAL_ROWS;
		$value['rows'] = array();

		$send_value = $value;

		list($app) = explode('.',$value['get_rows']);

		// Check for a favorite in URL
		if($_GET['favorite'] && $value['favorites'])
		{
			$safe_name = preg_replace('/[^A-Za-z0-9-_]/','_',strip_tags($_GET['favorite']));
			$pref_name = "favorite_" .$safe_name;

			// Do some easy applying of filters server side
			$favorite = $GLOBALS['egw_info']['user']['preferences'][$app][$pref_name];
			if(!$favorite && $_GET['favorite'] == 'blank')
			{
				// Have to go through each of these
				foreach(array('search','cat_id','filter','filter2') as $filter)
				{
					$send_value[$filter] = '';
				}
				unset($send_value['col_filter']);
			}
			if($favorite && $favorite['filter'])
			{
				$send_value = array_merge($value, $favorite['filter']);

				// Ajax call can handle the saved sort here, but this can't
				if($favorite['filter']['sort'])
				{
					unset($send_value['sort']);
					$send_value['order'] = $favorite['filter']['sort']['id'];
					$send_value['sort'] = $favorite['filter']['sort']['asc'] ? 'ASC' : 'DESC';
				}
			}
		}
		// Make sure it's not set
		unset($send_value['favorite']);

		// Parse sort into something that get_rows functions are expecting: db_field in order, ASC/DESC in sort
		if(is_array($send_value['sort']))
		{
			$send_value['order'] = $send_value['sort']['id'];
			$send_value['sort'] = $send_value['sort']['asc'] ? 'ASC' : 'DESC';
		}
		$total = self::call_get_rows($send_value, $send_value['rows'], self::$request->readonlys);
		$value =& self::get_array(self::$request->content, $form_name, true);

		// Add favorite here so app doesn't save it in the session
		if($_GET['favorite'])
		{
			$send_value['favorite'] = $safe_name;
		}
		$value = $send_value;
		$value['total'] = $total;

		// Send categories
		if(!$value['no_cat'] && !$value['cat_is_select'])
		{
			$cat_app = $value['cat_app'] ? $value['cat_app'] : $GLOBALS['egw_info']['flags']['current_app'];
			$value['options-cat_id'] = array('' => lang('all')) + etemplate_widget_menupopup::typeOptions('select-cat', ',,'.$cat_app,$no_lang,false,$value['cat_id']);
			// Prevent double encoding - widget does this on its own, but we're just grabbing the options
			foreach($value['options-cat_id'] as &$label)
			{
				if(!is_array($label))
				{
					$label = html_entity_decode($label, ENT_NOQUOTES,'utf-8');
				}
				elseif($label['label'])
				{
					$label['label'] = html_entity_decode($label['label'], ENT_NOQUOTES,'utf-8');
				}
			}
		}

		// Favorite group for admins
		if($GLOBALS['egw_info']['apps']['admin'] && $value['favorites'])
		{
			self::$request->sel_options[$form_name]['favorite']['group'] = array('all' => lang('All users')) +
				etemplate_widget_menupopup::typeOptions('select-account',',groups');
		}
		foreach($value as $name => &$_value)
		{
			if(strpos($name, 'options-') !== false && $_value)
			{
				$select = substr($name, 8);
				if(!self::$request->sel_options[$select])
				{
					self::$request->sel_options[$select] = array();
				}
				foreach($_value as &$label)
				{
					if(!is_array($label))
					{
						$label = html_entity_decode($label, ENT_NOQUOTES,'utf-8');
					}
					elseif($label['label'])
					{
						$label['label'] = html_entity_decode($label['label'], ENT_NOQUOTES,'utf-8');
					}
				}
				self::$request->sel_options[$select] += $_value;
				// The client doesn't need them in content, but we can't unset them because
				// some apps don't send them on re-load, pulling them from the session
				//unset($value[$name]);
			}
		}
		if($value['rows']['sel_options'])
		{
			self::$request->sel_options = array_merge(self::$request->sel_options,$value['rows']['sel_options']);
			unset($value['rows']['sel_options']);
		}

		// If column selection preference is forced, set a flag to turn off UI
		$pref_name = 'nextmatch-' . (isset($value['columnselection_pref']) ? $value['columnselection_pref'] : $this->attrs['template']);
		$value['no_columnselection'] = $value['no_columnselection'] || (
			$GLOBALS['egw']->preferences->forced[$app][$pref_name] &&
			// Need to check admin too, or it will be impossible to turn off
			!$GLOBALS['egw_info']['user']['apps']['admin']
		);
		// Use this flag to indicate to the admin that columns are forced (and that's why they can't change)
		$value['columns_forced'] = (boolean)$GLOBALS['egw']->preferences->forced[$app][$pref_name];

		// todo: no need to store rows in request, it's enought to send them to client

		//error_log(__METHOD__."() $this: total=$value[total]");
		//foreach($value['rows'] as $n => $row) error_log("$n: ".array2string($row));

		// set up actions, but only if they are defined AND not already set up (run throught self::egw_actions())
		if (isset($value['actions']) && !isset($value['actions'][0]))
		{
			$value['action_links'] = array();
			$template_name = isset($value['template']) ? $value['template'] : $this->attrs['options'];
			if (!is_array($value['action_links'])) $value['action_links'] = array();
			$value['actions'] = self::egw_actions($value['actions'], $template_name, '', $value['action_links']);
		}
	}

	/**
	 * Callback to fetch more rows
	 *
	 * Callback uses existing get_rows callback, but requires now 'row_id' to be set.
	 * If no 'row_modified' is set, rows cant checked for modification and therefore
	 * are always returned to client if in range or deleted if outside range.
	 *
	 * @param string $exec_id identifys the etemplate request
	 * @param array $queriedRange array with values for keys "start", "num_rows" and optional "refresh", "parent_id"
	 * @param array $filters Search and filter parameters, passed to data source
	 * @param string $form_name='nm' full id of widget incl. all namespaces
	 * @param array $knownUids=null uid's know to client
	 * @param int $lastModified=null date $knowUids last checked
	 * @todo for $queriedRange[refresh] first check if there's any modification since $lastModified, return $result[order]===null
	 * @return array with values for keys 'total', 'rows', 'readonlys', 'order', 'data' and 'lastModification'
	 */
	static public function ajax_get_rows($exec_id, array $queriedRange, array $filters = array(), $form_name='nm',
		array $knownUids=null, $lastModified=null)
	{
		self::$request = etemplate_request::read($exec_id);
		$value = self::get_array(self::$request->content, $form_name, true);
		if(!is_array($value))
		{
			$value = ($value) ? array($value) : array();
		}
		$value = array_merge($value, $filters);
		//error_log(__METHOD__."('".substr($exec_id,0,10)."...', range=".array2string($queriedRange).', filters='.array2string($filters).", '$form_name', knownUids=".array2string($knownUids).", lastModified=$lastModified) parent_id=$value[parent_id], is_parent=$value[is_parent]");

		$result = array();

		// Parse sort into something that get_rows functions are expecting: db_field in order, ASC/DESC in sort
		if(is_array($value['sort']))
		{
			$value['order'] = $value['sort']['id'];
			$value['sort'] = $value['sort']['asc'] ? 'ASC' : 'DESC';
		}

		$value['start'] = (int)$queriedRange['start'];
		$value['num_rows'] = (int)$queriedRange['num_rows'];
		if($value['num_rows'] == 0) $value['num_rows'] = 20;
		// if app supports parent_id / hierarchy ($value['parent_id'] not empty), set parent_id as filter
		if (($parent_id = $value['parent_id']))
		{
			// Infolog at least wants 'parent_id' instead of $parent_id
			$value['col_filter']['parent_id'] = $queriedRange['parent_id'];
		}

		// Set current app for get_rows
		list($app) = explode('.',self::$request->method);
		if(!$app) list($app) = explode('::',self::$request->method);
		if($app)
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = $app;
			translation::add_app($app);
		}
		// If specific data requested, just do that
		if (($row_id = $value['row_id']) && $queriedRange['refresh'])
		{
			$value['col_filter'][$row_id] = $queriedRange['refresh'];
			$value['csv_export'] = 'refresh';
		}
		$rows = $result['data'] = $result['order'] = array();
		$result['total'] = self::call_get_rows($value, $rows, $result['readonlys']);
		$result['lastModification'] = egw_time::to('now', 'ts')-1;

		if (isset($GLOBALS['egw_info']['flags']['app_header']) && self::$request->app_header != $GLOBALS['egw_info']['flags']['app_header'])
		{
			self::$request->app_header = $GLOBALS['egw_info']['flags']['app_header'];
			egw_json_response::get()->apply('egw_app_header', array($GLOBALS['egw_info']['flags']['app_header']));
		}

		$row_id = isset($value['row_id']) ? $value['row_id'] : 'id';
		$row_modified = $value['row_modified'];
		$is_parent = $value['is_parent'];
		$is_parent_value = $value['is_parent_value'];

		foreach($rows as $n => $row)
		{
			$kUkey = false;
			if (is_int($n) && $row)
			{
				if (!isset($row[$row_id])) unset($row_id);	// unset default row_id of 'id', if not used
				if (!isset($row[$row_modified])) unset($row_modified);

				$id = $row_id ? $row[$row_id] : $n;
				$result['order'][] = $id;

				// check if we need to send the data
				if (!$row_id || !$knownUids || ($kUkey = array_search($id, $knownUids)) === false ||
					!$lastModified || !isset($row[$row_modified]) || $row[$row_modified] > $lastModified)
				{
					if ($parent_id)	// if app supports parent_id / hierarchy, set parent_id and is_parent
					{
						$row['is_parent'] = isset($is_parent_value) ?
							$row[$is_parent] == $is_parent_value : (boolean)$row[$is_parent];
						$row['parent_id'] = $row[$parent_id];	// seems NOT used on client!
					}
					$result['data'][$id] = $row;
				}
				if ($kUkey !== false) unset($knownUids[$kUkey]);
			}
			else	// non-row data set by get_rows method
			{
				$result['rows'][$n] = $row;
			}
		}
		// check knowUids outside of range for modification
		if ($knownUids)
		{
			// commenting out trying to validate knowUids not returned in current list,
			// as this generates a second db search and they might not be visible anyway
			// --> for now we tell the grid to purge them
			//if (!$row_id)	// row_id not set by nextmatch user --> tell client to delete data, as we cant identify rows
			{
				foreach($knownUids as $uid)
				{
					// Just don't send it back for now
					unset($result['data'][$uid]);
					//$result['data'][$uid] = null;
				}
			}
			/*else
			{
				//error_log(__METHOD__."() knowUids left to check ".array2string($knownUids));
				// check if they are up to date: we create a query similar to csv-export without any filters
				$value['csv_export'] = 'knownUids';	// do not store $value in session
				$value['filter'] = $value['filter2'] = $value['cat_id'] = $value['search'] = '';
				$value['col_filter'] = array($row_id => $knownUids);
				// if we know name of modification column and have a last-modified date
				if ($row_modified && $lastModified)	// --> set filter to return only modified entries
				{
					$value['col_filter'][] = $row_modified.' > '.(int)$lastModified;
				}
				$value['start'] = 0;
				$value['num_rows'] = count($knownUids);
				$rows = array();
				if (self::call_get_rows($value, $rows))
				{
					foreach($rows as $n => $row)
					{
						if (!is_int($n)) continue;	// ignore non-row data set by get_rows method

						if (!$row_modified || !isset($row[$row_modified]) ||
							!isset($lastModified) || $row[$row_modified] > $lastModified)
						{
							$result['data'][$row[$row_id]] = $row;
						}
					}
				}
			}*/
		}

		//foreach($result as $name => $value) if ($name != 'readonlys') error_log(__METHOD__."() result['$name']=".array2string($name == 'data' ? array_keys($value) : $value));
		egw_json_response::get()->data($result);
	}

	/**
	 * Calling our callback
	 *
	 * Signature of get_rows callback is either:
	 * a) int get_rows($query,&$rows,&$readonlys)
	 * b) int get_rows(&$query,&$rows,&$readonlys)
	 *
	 * If get_rows is called static (and php >= 5.2.3), it is always b) independent on how it's defined!
	 *
	 * @param array &$value
	 * @param array &$rows on return: rows are indexed by their row-number: $value[start], ..., $value[start]+$value[num_rows]-1
	 * @param array &$readonlys=null
	 * @param object $obj=null (internal)
	 * @param string|array $method=null (internal)
	 * @return int|boolean total items found of false on error ($value['get_rows'] not callable)
	 */
	private static function call_get_rows(array &$value,array &$rows,array &$readonlys=null,$obj=null,$method=null)
	{
		if (is_null($method)) $method = $value['get_rows'];

		if (is_null($obj))
		{
			// allow static callbacks
			if(strpos($method,'::') !== false)
			{
				list($class,$method) = explode('::',$method);

				//  workaround for php < 5.2.3: do NOT call it static, but allow application code to specify static callbacks
				if (version_compare(PHP_VERSION,'5.2.3','>='))
				{
					$method = array($class,$method);
					unset($class);
				}
			}
			else
			{
				list($app,$class,$method) = explode('.',$value['get_rows']);
			}
			if ($class)
			{
				if (!$app && !is_object($GLOBALS[$class]))
				{
					$GLOBALS[$class] = new $class();
				}
				if (is_object($GLOBALS[$class]))	// use existing instance (put there by a previous CreateObject)
				{
					$obj = $GLOBALS[$class];
				}
				else
				{
					$obj = CreateObject($app.'.'.$class);
				}
			}
		}
		if (!is_array($raw_rows)) $raw_rows = array();
		if (!is_array($readonlys)) $readonlys = array();
		if(is_callable($method))	// php5.2.3+ static call (value is always a var param!)
		{
			$total = call_user_func_array($method,array(&$value,&$raw_rows,&$readonlys));
		}
		elseif(is_object($obj) && method_exists($obj,$method))
		{
			$total = $obj->$method($value,$raw_rows,$readonlys);
		}
		else
		{
			$total = false;	// method not callable
		}
		/* no automatic fallback to start=0
		if ($method && $total && $value['start'] >= $total)
		{
			$value['start'] = 0;
			$total = self::call_get_rows($value,$rows,$readonlys,$obj,$method);
		}
		*/
		// otherwise we might get stoped by max_excutiontime
		if ($total > 200) @set_time_limit(0);

		// remove empty rows required by old etemplate to compensate for header rows
		$first = $total ? null : 0;
		foreach($raw_rows as $n => $row)
		{
			// skip empty rows inserted for each header-line in old etemplate
			if (is_int($n) && is_array($rows))
			{
				if (is_null($first)) $first = $n;
				$rows[$n-$first+$value['start']] = $row;
			}
			elseif(!is_numeric($n))	// rows with string-keys, after numeric rows
			{
				if($n == 'sel_options')
				{
					foreach($row as $name => &$options)
					{
						foreach($options as $key => &$label)
						{
							$label = html_entity_decode($label, ENT_NOQUOTES,'utf-8');
						}
					}
				}
				$rows[$n] = $row;
			}
		}

		//error_log($value['get_rows'].'() returning '.array2string($total).', method = '.array2string($method).', value = '.array2string($value));
		return $total;
	}
	/**
	 * Default maximum lenght for context submenus, longer menus are put as a "More" submenu
	 */
	const DEFAULT_MAX_MENU_LENGTH = 14;

	/**
	 * Return egw_actions
	 *
	 * The following attributes are understood for actions on eTemplate/PHP side:
	 * - string 'id' id of the action (set as key not attribute!)
	 * - string 'caption' name/label or action, get's automatic translated
	 * - boolean 'no_lang' do NOT translate caption, default false
	 * - string 'icon' icon, eg. 'edit' or 'infolog/task', if no app given app of template or API is used
	 * - string 'iconUrl' full url of icon, better use 'icon'
	 * - boolean|string 'allowOnMultiple' should action be shown if multiple lines are marked, or string 'only', default true!
	 * - boolean|string 'enabled' is action available, or string with javascript function to call, default true!
	 * - string 'disableClass' class name to check if action should be disabled (if presend, enabled if not)
	 *   (add that css class in get_rows(), if row lacks rights for an action)
	 * - string 'enableClass' class name to check if action should be enabled (if present, disabled if not)
	 * - string 'enableId' regular expression row-id has to match to enable action
	 * - boolean 'hideOnDisabled' hide disabled actions, default false
	 * - string 'type' type of action, default 'popup' for contenxt menus, 'drag' or 'drop'
	 * - boolean 'default' is that action the default action, default false
	 * - array  'children' array with actions of submenu
	 * - int    'group' to group items, default all actions are in one group
	 * - string 'onExecute' javascript to run, default 'javaScript:nm_action' or eg. 'javaScript:app.myapp.someMethod'
	 *   which runs action specified in nm_action attribute:
	 * - string 'nm_action'
	 *   + 'alert'  debug action, shows alert with action caption, id and id's of selected rows
	 *   + 'submit' default action, sets nm[action], nm[selected] and nm[select_all]
	 *   + 'location' redirects / set location.href to 'url' attribute
	 *   + 'popup'  opens popup with url given in 'url' attribute
	 * - string 'url' url for location or popup
	 * - string 'target' target for location or popup
	 * - string 'width' for popup
	 * - string 'height' for popup
	 * - string 'confirm' confirmation message
	 * - string 'confirm_multiple' confirmation message for multiple selected, defaults to 'confirm'
	 * - boolean 'postSubmit' eg. downloads need a submit via POST request not our regular Ajax submit, only works with nm_action=submit!
	 *
	 * @param array $actions id indexed array of actions / array with valus for keys: 'iconUrl', 'caption', 'onExecute', ...
	 * @param string $template_name='' name of the template, used as default for app name of images
	 * @param string $prefix='' prefix for ids
	 * @param array &$action_links=array() on return all first-level actions plus the ones with enabled='javaScript:...'
	 * @param int $max_length=self::DEFAULT_MAX_MENU_LENGTH automatic pagination, not for first menu level!
	 * @param array $default_attrs=null default attributes
	 * @return array
	 */
	public static function egw_actions(array $actions=null, $template_name='', $prefix='', array &$action_links=array(),
		$max_length=self::DEFAULT_MAX_MENU_LENGTH, array $default_attrs=null)
	{
		//echo "<p>".__METHOD__."(\$actions, '$template_name', '$prefix', \$action_links, $max_length) \$actions="; _debug_array($actions);
		$first_level = !$action_links;	// add all first level actions

		//echo "actions="; _debug_array($actions);
		$egw_actions = array();
		$n = 1;
		foreach((array)$actions as $id => $action)
		{
			// in case it's only selectbox  id => label pairs
			if (!is_array($action)) $action = array('caption' => $action);
			if ($default_attrs) $action += $default_attrs;

			if (!$first_level && $n == $max_length && count($actions) > $max_length)
			{
				$id = 'more_'.count($actions);	// we need a new unique id
				$action = array(
					'caption' => 'More',
					'prefix' => $prefix,
					// display rest of actions incl. current one as children
					'children' => array_slice($actions, $max_length-1, count($actions)-$max_length+1, true),
				);
				//echo "*** Inserting id=$prefix$id"; _debug_array($action);
				// we break at end of foreach loop, as rest of actions is already dealt with
				// by putting them as children
			}

			// add all first level popup actions plus ones with enabled = 'javaScript:...' to action_links
			if ((!isset($action['type']) || in_array($action['type'],array('popup','drag','drop'))) &&	// popup is the default
				($first_level || substr($action['enabled'],0,11) == 'javaScript:'))
			{
				$action_links[] = $prefix.$id;
			}

			// add sub-menues
			if ($action['children'])
			{
				static $inherit_attrs = array('url','popup','nm_action','onExecute','type','egw_open','allowOnMultiple','confirm','confirm_multiple');
				$action['children'] = self::egw_actions($action['children'], $template_name, $action['prefix'], $action_links, $max_length,
					array_intersect_key($action, array_flip($inherit_attrs)));

				unset($action['prefix']);
				$action = array_diff_key($action, array_flip($inherit_attrs));
			}

			// link or popup action
			if ($action['url'])
			{
				$action['url'] = egw::link('/index.php',str_replace('$action',$id,$action['url']));
				if ($action['popup'])
				{
					list($action['data']['width'],$action['data']['height']) = explode('x',$action['popup']);
					unset($action['popup']);
					$action['data']['nm_action'] = 'popup';
				}
				else
				{
					$action['data']['nm_action'] = 'location';
				}
			}
			if ($action['egw_open'])
			{
				$action['data']['nm_action'] = 'egw_open';
			}

			$egw_actions[$prefix.$id] = $action;

			if (!$first_level && $n++ == $max_length) break;
		}
		//echo "egw_actions="; _debug_array($egw_actions);
		return $egw_actions;
	}

	/**
	 * Action with submenu for categories
	 *
	 * Automatic switch to hierarchical display, if more then $max_cats_flat=14 cats found.
	 *
	 * @param string $app
	 * @param int $group=0 see self::egw_actions
	 * @param string $caption='Change category'
	 * @param string $prefix='cat_' prefix category id to get action id
	 * @param boolean $globals=true application global categories too
	 * @param int $parent_id=0 only returns cats of a certain parent
	 * @param int $max_cats_flat=self::DEFAULT_MAX_MENU_LENGTH use hierarchical display if more cats
	 * @return array like self::egw_actions
	 */
	public static function category_action($app, $group=0, $caption='Change category',
		$prefix='cat_', $globals=true, $parent_id=0, $max_cats_flat=self::DEFAULT_MAX_MENU_LENGTH)
	{
		$cat = new categories(null,$app);
		$cats = $cat->return_sorted_array($start=0, $limit=false, $query='', $sort='ASC', $order='cat_name', $globals, $parent_id, $unserialize_data=true);

		// if more then max_length cats, switch automatically to hierarchical display
		if (count($cats) > $max_cats_flat)
		{
			$cat_actions = self::category_hierarchy($cats, $prefix, $parent_id);
		}
		else	// flat, indented categories
		{
			$cat_actions = array();
			foreach((array)$cats as $cat)
			{
				$name = str_repeat('&nbsp;',2*$cat['level']) . stripslashes($cat['name']);
				if (categories::is_global($cat)) $name .= ' &#9830;';

				$cat_actions[$cat['id']] = array(
					'caption' => $name,
					'no_lang' => true,
				);
				// add category icon
				if ($cat['data']['icon'] && file_exists(EGW_SERVER_ROOT.'/phpgwapi/images/'.basename($cat['data']['icon'])))
				{
					$cat_actions[$cat['id']]['iconUrl'] = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/images/'.$cat['data']['icon'];
				}
			}
		}
		return array(
			'caption' => $caption,
			'children' => $cat_actions,
			'enabled' => (boolean)$cat_actions,
			'group' => $group,
			'prefix' => $prefix,
		);
	}

	/**
	 * Return one level of the category hierarchy
	 *
	 * @param array $cats=null all cats if already read
	 * @param string $prefix='cat_' prefix category id to get action id
	 * @param int $parent_id=0 only returns cats of a certain parent
	 * @return array
	 */
	private static function category_hierarchy(array $cats, $prefix, $parent_id=0)
	{
		$cat_actions = array();
		foreach($cats as $key => $cat)
		{
			// current hierarchy level
			if ($cat['parent'] == $parent_id)
			{
				$name = stripslashes($cat['name']);
				if (categories::is_global($cat)) $name .= ' &#9830;';

				$cat_actions[$cat['id']] = array(
					'caption' => $name,
					'no_lang' => true,
					'prefix' => $prefix,
				);
				// add category icon
				if ($cat['data']['icon'] && file_exists(EGW_SERVER_ROOT.'/phpgwapi/images/'.basename($cat['data']['icon'])))
				{
					$cat_actions[$cat['id']]['iconUrl'] = $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/images/'.$cat['data']['icon'];
				}
				unset($cats[$key]);
			}
			// direct children
			elseif(isset($cat_actions[$cat['parent']]))
			{
				$cat_actions['sub_'.$cat['parent']] = $cat_actions[$cat['parent']];
				// have to add category itself to children, to be able to select it!
				$cat_actions[$cat['parent']]['group'] = -1;	// own group on top
				$cat_actions['sub_'.$cat['parent']]['children'] = array(
					$cat['parent'] => $cat_actions[$cat['parent']],
				)+self::category_hierarchy($cats, $prefix, $cat['parent']);
				unset($cat_actions[$cat['parent']]);
			}
		}
		return $cat_actions;
	}


	/**
	 * Validate input
	 *
	 * Following attributes get checked:
	 * - needed: value must NOT be empty
	 * - min, max: int and float widget only
	 * - maxlength: maximum length of string (longer strings get truncated to allowed size)
	 * - preg: perl regular expression incl. delimiters (set by default for int, float and colorpicker)
	 * - int and float get casted to their type
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array($content, $form_name);
		list($app) = explode('.',$this->attrs['template']);

		unset($value['favorite']);

		// On client, rows does not get its own namespace, but all apps are expecting it
		$value['rows'] = $value;

		// Legacy support - action popups were not properly namespaced
		$preserve = self::get_array(self::$request->preserv, $form_name);
		if($value[$preserve['action_var']] && $content[$value[$preserve['action_var']].'_popup'])
		{
			$validated += $content[$value[$preserve['action_var']].'_popup'];
		}


		// Save current column settings as default, clear, or force (admins only)
		if($GLOBALS['egw_info']['user']['apps']['admin'] && $app)
		{
			$pref_name = 'nextmatch-' . (isset($value['columnselection_pref']) ? $value['columnselection_pref'] : $this->attrs['template']);
			$refresh_pref_name = $pref_name.'-autorefresh';
			$pref_level = $value['nm_col_preference'] == 'force' ? 'forced' : 'default';

			// Clear forced pref before setting default
			if($pref_level != 'forced')
			{
				$GLOBALS['egw']->preferences->delete($app,$pref_name,'forced');
				$GLOBALS['egw']->preferences->delete($app,$refresh_pref_name,'forced');
				$GLOBALS['egw']->preferences->save_repository(true,'forced');
			}

			// Set columns + refresh as default for all users

			// Columns already saved to current user's preferences, use from there
			$prefs = $GLOBALS['egw']->preferences->read();
			$cols = $prefs[$app][$pref_name];
			$GLOBALS['egw']->preferences->add($app,$pref_name,is_array($cols) ? implode(',',$cols) : $cols, $pref_level);

			// Autorefresh
			$refresh = $value['nm_autorefresh'];
			$GLOBALS['egw']->preferences->add($app,$refresh_pref_name,(int)$refresh,$pref_level);

			$GLOBALS['egw']->preferences->save_repository(true,$pref_level);
			$prefs = $GLOBALS['egw']->preferences->read(true);

			if($value['nm_col_preference'] == 'reset')
			{
				// Clear column + refresh preference so users go back to default
				$GLOBALS['egw']->preferences->delete_preference($app,$pref_name);
				$GLOBALS['egw']->preferences->delete_preference($app,$pref_name.'-size');
				$GLOBALS['egw']->preferences->delete_preference($app,$refresh_pref_name);
			}
		}
		unset($value['nm_col_preference']);

		$validated[$form_name] = $value;
	}


	/**
	 * Include favorites when generating the page server-side
	 *
	 * Use this function in your sidebox (or anywhere else, I suppose) to
	 * get the favorite list when a nextmatch is _not_ on the page.  If
	 * a nextmatch is on the page, it will update / replace this list.
	 *
	 * @param $app String Current application, needed to find preferences
	 * @param $default String Preference name for default favorite
	 *
	 * @return String HTML fragment for the list
	 */
	public static function favorite_list($app, $default)
	{
		if(!$app) return '';
		$target = 'favorite_sidebox_'.$app;
		$pref_prefix = 'favorite_';
		$filters = array(
			'blank' => array(
				'name' => lang('No filters'),
				'filters' => array(),
				'group' => true
			)
		);
		$default_filter = $GLOBALS['egw_info']['user']['preferences'][$app][$default];
		if(!$default_filter) $default_filter = "blank";

		$html = "<span id='$target' class='ui-helper-clearfix sidebox-favorites'><ul class='ui-menu ui-widget-content ui-corner-all favorites' role='listbox'>\n";
		foreach($GLOBALS['egw_info']['user']['preferences'][$app] as $pref_name => $pref)
		{
			if(strpos($pref_name, $pref_prefix) === 0)
			{
				if(!is_array($pref))
				{
					$GLOBALS['egw']->preferences->delete($app,$pref_name);
					$GLOBALS['egw']->preferences->save_repository(false);
					continue;
				}
				$filters[substr($pref_name,strlen($pref_prefix))] = $pref;
			}
		}
		foreach($filters as $name => $filter)
		{
			$href = egw::link('/index.php', (array)egw_link::get_registry($app,'list') + array('favorite'=>$name),$app);
			$html .= "<li id='$name' class='ui-menu-item' role='menuitem'>\n";
			$html .= "<a href=\"$href\" class='ui-corner-all' tabindex='-1'>";
			$html .= "<div class='" . ($name == $default_filter ? 'ui-icon ui-icon-heart' : 'sideboxstar') . "'></div>".
				$filter['name'] .($filter['group'] != false ? " ♦" :"");
			$html .= "</a></li>\n";

		}

		$html .= '</ul></span>';
		return $html;
	}

	/**
	 * Create or delete a favorite for multiple users
	 *
	 * Need to be an admin or it will just do nothing quietly
	 *
	 * @param $app Current application, needed to save preference
	 * @param $name String Name of the favorite
	 * @param $action String add or delete
	 * @param $group int|String ID of the group to create the favorite for, or All for all users
	 * @param $filters Array of key => value pairs for the filter
	 *
	 * @return boolean Success
	 */
	public static function ajax_set_favorite($app, $name, $action, $group, $filters = array())
	{
		// Only use alphanumeric for preference name, so it can be used directly as DOM ID
		$name = strip_tags($name);
		$pref_name = "favorite_".preg_replace('/[^A-Za-z0-9-_]/','_',$name);

		if($group && $GLOBALS['egw_info']['apps']['admin'])
		{
			$prefs = new preferences(is_numeric($group) ? $group: $GLOBALS['egw_info']['user']['account_id']);
		}
		else
		{
			$prefs = $GLOBALS['egw']->preferences;
			$type = 'user';
		}
		$prefs->read_repository();
		$type = $group == "all" ? "default" : "user";
		if($action == "add")
		{
			$filters = array(
				// This is the name as user entered it, minus tags
				'name' => $name,
				'group' => $group ? $group : false,
				'filter' => $filters
			);
			$result = $prefs->add($app,$pref_name,$filters,$type);
			$prefs->save_repository(false,$type);

			// Update preferences client side, or it could disappear
			$pref = $GLOBALS['egw']->preferences->read_repository(false);
			$pref = $pref[$app];
                        if(!$pref) $pref = Array();
                        egw_json_response::get()->script('window.egw.set_preferences('.json_encode($pref).', "'.$app.'");');

			egw_json_response::get()->data(isset($result[$app][$pref_name]));
			return isset($result[$app][$pref_name]);
		}
		else if ($action == "delete")
		{
			$result = $prefs->delete($app,$pref_name, $type);
			$prefs->save_repository(false,$type);

			// Update preferences client side, or it could come back
			$pref = $GLOBALS['egw']->preferences->read_repository(false);
			$pref = $pref[$app];
			if(!$pref) $pref = Array();
			egw_json_response::get()->script('window.egw.set_preferences('.json_encode($pref).', "'.$app.'");');

			egw_json_response::get()->data(!isset($result[$app][$pref_name]));
			return !isset($result[$app][$pref_name]);
		}
	}

	/**
	 * Run a given method on all children
	 *
	 * Reimplemented to add namespace, and make sure row template gets included
	 *
	 * @param string $method_name
	 * @param array $params=array('') parameter(s) first parameter has to be cname, second $expand!
	 * @param boolean $respect_disabled=false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$old_param0 = $params[0];
		$cname =& $params[0];
		// Need this check or the headers will get involved too
		if($this->type == 'nextmatch')
		{
			parent::run($method_name, $params, $respect_disabled);
			if ($this->id) $cname = self::form_name($cname, $this->id, $params[1]);

			if ($this->attrs['template'])
			{
				$row_template = etemplate_widget_template::instance($this->attrs['template']);
				$row_template->run($method_name, $params, $respect_disabled);
			}
		}
		$params[0] = $old_param0;

		// Prevent troublesome keys from breaking the nextmatch
		// TODO: Figure out where these come from
		foreach(array('$row','${row}', '$', '0','1','2') as $key)
		{
			if(is_array(self::$request->content[$cname])) unset(self::$request->content[$cname][$key]);
			if(is_array(self::$request->preserve[$cname])) unset(self::$request->preserve[$cname][$key]);
		}
	}

	/**
	 * Refresh given rows for specified change
	 *
	 * Change type parameters allows for quicker refresh then complete server side reload:
	 * - edit: send just modified data from given rows
	 * - delete: just send null for given rows to clientside (no backend call neccessary)
	 * - add: requires full reload
	 *
	 * @param array|string $row_ids rows to refresh
	 * @param string $type='edit' "edit" (default), "delete" or "add"
	 */
	public function refresh($row_ids, $type='edit')
	{
		throw new Exception('Not yet implemented');
	}
}

// Registration needs to go here, otherwise customfields won't be loaded until some other cf shows up
etemplate_widget::registerWidget('etemplate_widget_customfields', array('nextmatch-customfields'));

/**
 * Extend selectbox so select options get parsed properly before being sent to client
 */
class etemplate_widget_nextmatch_filterheader extends etemplate_widget_menupopup
{
}

/**
 * Extend selectbox and change type so proper users / groups get loaded, according to preferences
 */
class etemplate_widget_nextmatch_accountfilter extends etemplate_widget_menupopup
{
	public function set_attrs($xml)
	{
		parent::set_attrs($xml);
		$this->attrs['type'] = 'select-account';
	}
}

/**
 * A filter widget that fakes another (select) widget and turns it into a nextmatch filter widget.
 */
class etemplate_widget_nextmatch_customfilter extends etemplate_widget_transformer
{

	protected $legacy_options = 'type,widget_options';

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		switch($this->attrs['type'])
		{
			case "link-entry":
				self::$transformation['type'] = $this->attrs['type'] = 'nextmatch-entryheader';
				break;
			default:
				list($type, $subtype) = explode('-',$this->attrs['type'],2);
				if($type == 'select')
				{
					$this->attrs['type'] = 'nextmatch-filterheader';
				}
				self::$transformation['type'] = $this->attrs['type'];
		}
		$form_name = self::form_name($cname, $this->id, $expand);

		// Don't need simple onchanges, it's ajax
		if($this->attrs['onchange'] == 1)
		{
			$this->setElementAttribute($form_name, 'onchange', false);
		}

		$this->setElementAttribute($form_name, 'options', trim($this->attrs['widget_options']) != '' ? $this->attrs['widget_options'] : '');

		parent::beforeSendToClient($cname);
		$this->setElementAttribute($form_name, 'type', $this->attrs['type']);

	}
}

