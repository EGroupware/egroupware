<?php
/**
 * EGroupware - eTemplate serverside implementation of the nextmatch widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Widget;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * eTemplate serverside implementation of the nextmatch widget
 *
 * $content[$id] = array(	// I = value set by the app, 0 = value on return / output
 * 	'get_rows'       =>		// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
 * 	'cat_id_label'   =>		// I  label for category  (optional)
 * 	'filter_label'   =>		// I  label for filter    (optional)
 * 	'filter2_label'   =>	// I  label for filter2   (optional)
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
 * 							//  O col_filter[$parent_id] row_id of parent to query children for hierachical display
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
 * 	'columnselection_pref' => // I  name of the preference (plus 'nextmatch-' prefix), default = template-name
 * 	'default_cols'   => 	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
 * 	'options-selectcols' => // I  array with name/label pairs for the column-selection, this gets autodetected by default. A name => false suppresses a column completly.
 *	'return'         =>     // IO allows to return something from the get_rows function if $query is a var-param!
 *	'csv_fields'     =>		// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames or preferred importexport definition,
 * 		array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
 *		or name of import/export definition
 *  'row_id'         =>     // I  key into row content to set it's value as row-id, eg. 'id'
 *  'row_modified'   =>		// I  key into row content for modification date or state of a row, to not query it again
 *  'parent_id'      =>		// I  key into row content of children linking them to their parent, also used as col_filter to query children
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
class Nextmatch extends Etemplate\Widget
{
	/**
	 * Path where the icons are stored (relative to webserver_url)
	 */
	const ICON_PATH = '/api/images';

	public function __construct($xml='')
	{
		if($xml) {
			parent::__construct($xml);
		}
	}

	/**
	 * Legacy options
	 */
	protected $legacy_options = 'template';

	/**
	 * Number of rows to send initially
	 */
	const INITIAL_ROWS = 50;

	/**
	 * Set up what we know on the server side.
	 *
	 * Sending a first chunk of rows
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array(self::$request->content, $form_name, true);

		$value['start'] = 0;
		if(!array_key_exists('num_rows',$value))
		{
			$value['num_rows'] = self::INITIAL_ROWS;
		}

		$value['rows'] = array();

		$send_value = $value;

		list($app) = explode('.',$value['get_rows']);
		if(!$GLOBALS['egw_info']['apps'][$app])
		{
			list($app) = explode('.',$this->attrs['template']);
		}

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
			// Old type
			if($favorite && $favorite['filter'])
			{
				$favorite['state'] = $favorite['filter'];
			}
			if($favorite && $favorite['state'])
			{
				$send_value = array_merge($value, $favorite['state']);

				// Ajax call can handle the saved sort here, but this can't
				if($favorite['state']['sort'])
				{
					unset($send_value['sort']);
					$send_value['order'] = $favorite['state']['sort']['id'];
					$send_value['sort'] = $favorite['state']['sort']['asc'] ? 'ASC' : 'DESC';
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
		if($value['num_rows'] != 0)
		{
			$total = self::call_get_rows($send_value, $send_value['rows'], self::$request->readonlys, null, null, $this);
		}
		if (true) $value =& self::get_array(self::$request->content, $form_name, true);

		// Add favorite here so app doesn't save it in the session
		if($_GET['favorite'])
		{
			$send_value['favorite'] = $safe_name;
		}
		if (true) $value = $send_value;
		$value['total'] = $total;

		// Send categories
		if(!$value['no_cat'] && !$value['cat_is_select'])
		{
			$cat_app = $value['cat_app'] ? $value['cat_app'] : $GLOBALS['egw_info']['flags']['current_app'];
			$value['options-cat_id'] = self::$request->sel_options['cat_id'] ? self::$request->sel_options['cat_id'] : array();

			// Add 'All', if not already there
			if(!$value['options-cat_id'][''] && !$value['options-cat_id'][0])
			{
				$value['options-cat_id'][''] = lang('All categories');
			}
			$value['options-cat_id'] += Select::typeOptions('select-cat', ',,'.$cat_app,$no_lang=true,false,$value['cat_id']);
			Select::fix_encoded_options($value['options-cat_id']);
		}

		// Favorite group for admins
		if($GLOBALS['egw_info']['apps']['admin'] && $value['favorites'])
		{
			self::$request->sel_options[$form_name]['favorite']['group'] = array('all' => lang('All users')) +
				Select::typeOptions('select-account',',groups');
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
				Select::fix_encoded_options($_value, TRUE);
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
	 * @param string $form_name ='nm' full id of widget incl. all namespaces
	 * @param array $knownUids =null uid's know to client
	 * @param int $lastModified =null date $knowUids last checked
	 * @todo for $queriedRange[refresh] first check if there's any modification since $lastModified, return $result[order]===null
	 * @return array with values for keys 'total', 'rows', 'readonlys', 'order', 'data' and 'lastModification'
	 */
	static public function ajax_get_rows($exec_id, array $queriedRange, array $filters = array(), $form_name='nm',
		array $knownUids=null, $lastModified=null)
	{
		self::$request = Etemplate\Request::read($exec_id);
		// fix for somehow empty etemplate request content
		if (!is_array(self::$request->content))
		{
			self::$request->content = array($form_name => array());
		}
		self::$response = Api\Json\Response::get();

		$value = self::get_array(self::$request->content, $form_name, true);
		if(!is_array($value))
		{
			$value = ($value) ? array($value) : array();
		}

		// Validate filters
		if (($template = Template::instance(self::$request->template['name'], self::$request->template['template_set'],
			self::$request->template['version'], self::$request->template['load_via'])))
		{
			$template = $template->getElementById($form_name, strpos($form_name, 'history') === 0 ? 'historylog' : 'nextmatch');
			$expand = array(
				'cont' => array($form_name => $filters),
			);
			$valid_filters = array();

			if($template)
			{
				$template->run('validate', array('', $expand, $expand['cont'], &$valid_filters), false);	// $respect_disabled=false: as client may disable things, here we validate everything and leave it to the get_rows to interpret
				$filters = $valid_filters[$form_name];
			}
			// Avoid empty arrays, they cause problems with db filtering
			foreach($filters['col_filter'] as $col => &$val)
			{
				if(is_array($val) && count($val) == 0)
				{
					unset($filters['col_filter'][$col]);
				}
			}
			//error_log($this . " Valid filters: " . array2string($filters));
		}

		if (true) $value = $value_in = array_merge($value, $filters);

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
		if($value['num_rows'] == 0) $value['num_rows'] = self::INITIAL_ROWS;
		// if app supports parent_id / hierarchy ($value['parent_id'] not empty), set parent_id as filter
		if (($parent_id = $value['parent_id']))
		{
			// Infolog at least wants 'parent_id' instead of $parent_id
			$value['col_filter'][$parent_id] = $queriedRange['parent_id'];
			if ($queriedRange['parent_id']) $value['csv_export'] = 'children';
		}

		// Set current app for get_rows
		list($app) = explode('.',self::$request->method);
		if(!$app) list($app) = explode('::',self::$request->method);
		if($app)
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = $app;
			Api\Translation::add_app($app);
		}
		// If specific data requested, just do that
		if (($row_id = $value['row_id']) && $queriedRange['refresh'])
		{
			$value['col_filter'][$row_id] = $queriedRange['refresh'];
			$value['csv_export'] = 'refresh';
		}
		$rows = $result['data'] = $result['order'] = array();
		$result['total'] = self::call_get_rows($value, $rows, $result['readonlys'], null, null, $template);
		$result['lastModification'] = Api\DateTime::to('now', 'ts')-1;

		if (isset($GLOBALS['egw_info']['flags']['app_header']) && self::$request->app_header != $GLOBALS['egw_info']['flags']['app_header'])
		{
			self::$request->app_header = $GLOBALS['egw_info']['flags']['app_header'];
			Api\Json\Response::get()->apply('egw_app_header', array($GLOBALS['egw_info']['flags']['app_header']));
		}

		$row_id = isset($value['row_id']) ? $value['row_id'] : 'id';
		$row_modified = $value['row_modified'];

		foreach($rows as $n => $row)
		{
			$kUkey = false;
			if (is_int($n) && $row)
			{
				if (!isset($row[$row_id])) unset($row_id);	// unset default row_id of 'id', if not used
				if (!isset($row[$row_modified])) unset($row_modified);

				$id = $row_id ? $row[$row_id] : $n;
				$result['order'][] = $id;

				$modified = $row[$row_modified];
				if (isset($modified) && !(is_int($modified) || is_string($modified) && is_numeric($modified)))
				{
					$modified = Api\DateTime::to(str_replace('Z', '', $modified), 'ts');
				}

				// check if we need to send the data
				//error_log("$id Known: " . (array_search($id, $knownUids) !== false ? 'Yes' : 'No') . ' Modified: ' . Api\DateTime::to($row[$row_modified]) . ' > ' . Api\DateTime::to($lastModified).'? ' . ($row[$row_modified] > $lastModified ? 'Yes' : 'No'));
				if (!$row_id || !$knownUids || ($kUkey = array_search($id, $knownUids)) === false ||
					!$lastModified || !isset($modified) || $modified > $lastModified ||
					$queriedRange['refresh'] && $id == $queriedRange['refresh']
				)
				{
					$result['data'][$id] = $row;
				}

				if ($kUkey !== false) unset($knownUids[$kUkey]);
			}
			else	// non-row data set by get_rows method
			{
				// Encode all select options and re-index to avoid Firefox's problem with
				// '' => 'All'
				if($n == 'sel_options')
				{
					foreach($row as &$options)
					{
						Select::fix_encoded_options($options,true);
					}
				}
				$result['rows'][$n] = $row;
			}
		}
		// check knowUids outside of range for modification - includes deleted
		/*
		if ($knownUids)
		{
			// row_id not set for nextmatch --> just skip them, we can't identify the rows
			if (!$row_id)
			{
				foreach($knownUids as $uid)
				{
					// Just don't send it back for now
					unset($result['data'][$uid]);
					//$result['data'][$uid] = null;
				}
			}
			else
			{
				error_log(__METHOD__."() knowUids left to check ".array2string($knownUids));
				// check if they are up to date: we create a query similar to csv-export without any filters
				$uid_query = $value;
				$uid_query['csv_export'] = 'knownUids';	// do not store $value in session
				$uid_query['filter'] = $uid_query['filter2'] = $uid_query['cat_id'] = $uid_query['search'] = '';
				$uid_query['col_filter'] = array($row_id => $knownUids);
				// if we know name of modification column and have a last-modified date
				if ($row_modified && $lastModified)	// --> set filter to return only modified entries
				{
					$uid_query['col_filter'][] = $row_modified.' > '.(int)$lastModified;
				}
				$uid_query['start'] = 0;
				$uid_query['num_rows'] = count($knownUids);
				$rows = array();
				try
				{
					if (self::call_get_rows($uid_query, $rows))
					{
						foreach($rows as $n => $row)
						{
							if (!is_int($n)) continue;	// ignore non-row data set by get_rows method

							if (!$row_modified || !isset($row[$row_modified]) ||
								!isset($lastModified) || $row[$row_modified] > $lastModified)
							{
								$result['data'][$row[$row_id]] = $row;
								$kUkey = array_search($id, $knownUids);
								if ($kUkey !== false) unset($knownUids[$kUkey]);
							}
						}
					}
				}
				catch (Exception $e)
				{
					unset($value['row_modified']);
					error_log("Error trying to find changed rows with {$value['get_rows']}, falling back to all rows. ");
					error_log($e);
				}

				// Remove any remaining knownUIDs from the grid
				foreach($knownUids as $uid)
				{
					$result['data'][$uid] = null;
				}
			}
		}
		 */

		// Check for anything changed in the query
		// Tell the client about the changes
		$request_value =& self::get_array(self::$request->content, $form_name,true);
		$changes = $no_rows = false;

		foreach($value_in as $key => $original_value)
		{
			// These keys are ignored
			if(in_array($key, array('col_filter','start','num_rows','total','order','sort')))
			{
				continue;
			}
			if($original_value == $value[$key]) continue;

			// These keys we don't send row data back, as they cause a partial reload
			if(in_array($key, array('template'))) $no_rows = true;

			// Actions still need extra handling
			if($key == 'actions' && !isset($value['actions'][0]))
			{
				$value['action_links'] = array();
				$template_name = isset($value['template']) ? $value['template'] : '';
				if (!is_array($value['action_links'])) $value['action_links'] = array();
				$value['actions'] = self::egw_actions($value['actions'], $template_name, '', $value['action_links']);
			}

			$changes = true;
			$request_value[$key] = $value[$key];

			Api\Json\Response::get()->generic('assign', array(
				'etemplate_exec_id' => $exec_id,
				'id' => $form_name,
				'key' => $key,
				'value' => $value[$key],
			));
		}
		// Request doesn't handle changing by reference, so force it
		if($changes)
		{
			$content = self::$request->content;
			self::$request->content = array();
			self::$request->content = $content;
		}

		// Send back data
		//foreach($result as $name => $value) if ($name != 'readonlys') error_log(__METHOD__."() result['$name']=".array2string($name == 'data' ? array_keys($value) : $value));
		Api\Json\Response::get()->data($result);

		// If etemplate_exec_id has changed, update the client side
		if (($new_id = self::$request->id()) != $exec_id)
		{
			Api\Json\Response::get()->generic('assign', array(
				'etemplate_exec_id' => $exec_id,
				'id' => '',
				'key' => 'etemplate_exec_id',
				'value' => $new_id,
			));
		}
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
	 * @param array &$readonlys =null
	 * @param object $obj =null (internal)
	 * @param string|array $method =null (internal)
	 * @param Etemplate\Widget $widget =null instanciated nextmatch widget to let it's widgets transform each row
	 * @return int|boolean total items found of false on error ($value['get_rows'] not callable)
	 */
	private static function call_get_rows(array &$value,array &$rows,array &$readonlys=null,$obj=null,$method=null, Etemplate\Widget $widget=null)
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
		$raw_rows = array();
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
		// if we have a nextmatch widget, find the repeating row
		if ($widget && $widget->attrs['template'])
		{
			$row_template = $widget->getElementById($widget->attrs['template']);
			if(!$row_template)
			{
				$row_template = Template::instance($widget->attrs['template']);
			}

			// Try to find just the repeating part
			$repeating_row = null;
			// First child should be a grid, we want last row
			foreach($row_template->children[0]->children[1]->children as $child)
			{
				if($child->type == 'row') $repeating_row = $child;
			}
		}
		// otherwise we might get stoped by max_excutiontime
		if ($total > 200) @set_time_limit(0);

		$is_parent = $value['is_parent'];
		$is_parent_value = $value['is_parent_value'];
		$parent_id = $value['parent_id'];

		// remove empty rows required by old etemplate to compensate for header rows
		$first = $total ? null : 0;
		foreach($raw_rows as $n => $row)
		{
			// skip empty rows inserted for each header-line in old etemplate
			if (is_int($n) && is_array($rows))
			{
				if (is_null($first)) $first = $n;

				if ($row[$is_parent])	// if app supports parent_id / hierarchy, set parent_id and is_parent
				{
					$row['is_parent'] = isset($is_parent_value) ?
						$row[$is_parent] == $is_parent_value : (boolean)$row[$is_parent];
					$row['parent_id'] = $row[$parent_id];	// seems NOT used on client!
				}
				// run beforeSendToClient methods of widgets in row on row-data
				if($repeating_row)
				{
					// Change anything by widget for each row ($row set to 1)
					$_row = array(1 => &$row);
					$repeating_row->run('set_row_value', array('',array('row' => 1), &$_row), true);
				}
				else if (!$widget || get_class($widget) != __NAMESPACE__.'\\HistoryLog')
				{
					// Fallback based on widget names
					//error_log(self::$request->template['name'] . ' had to fallback to run_beforeSendToClient() because it could not find the row');
					$row = self::run_beforeSendToClient($row);
				}
				$rows[$n-$first+$value['start']] = $row;
			}
			elseif(!is_numeric($n))	// rows with string-keys, after numeric rows
			{
				if($n == 'sel_options')
				{
					foreach($row as $name => &$options)
					{
						// remember newly set options for validation of nextmatch filters
						self::$request->sel_options[$name] = $options;

						Select::fix_encoded_options($options, true);
					}
				}
				$rows[$n] = $row;
			}
		}

		//error_log($value['get_rows'].'() returning '.array2string($total).', method = '.array2string($method).', value = '.array2string($value));
		return $total;
	}

	/**
	 * Run beforeSendToClient methods of widgets in row over row-data
	 *
	 * This is currently only a hack to convert everything looking like a timestamp to a 'Y-m-d\TH:i:s\Z' string, fix timezone problems!
	 *
	 * @todo instanciate row of template and run it's beforeSendToClient
	 * @param array $row
	 * @return array
	 */
	private static function run_beforeSendToClient(array $row)
	{
		$timestamps = self::get_timestamps();

		foreach($row as $name => &$value)
		{
			if ($name[0] != '#' && in_array($name, $timestamps) && $value &&
				(is_int($value) || is_string($value) && is_numeric($value)) &&
				($value > 21000000 || $value < 19000000))
			{
				$value = Api\DateTime::to($value, 'Y-m-d\TH:i:s\Z');
			}
		}
		return $row;
	}

	/**
	 * Get all timestamp columns incl. names with removed prefixes like cal_ or contact_
	 *
	 * @return array
	 */
	private static function get_timestamps()
	{
		return Api\Cache::getTree(__CLASS__, 'timestamps', function()
		{
			$timestamps = array();
			foreach(scandir(EGW_SERVER_ROOT) as $app)
			{
				$dir = EGW_SERVER_ROOT.'/'.$app;
				if (is_dir($dir) && file_exists($dir.'/setup/tables_current.inc.php') &&
					($tables_defs = $GLOBALS['egw']->db->get_table_definitions($app)))
				{
					foreach($tables_defs as $defintion)
					{
						foreach($defintion['fd'] as $col => $data)
						{
							if ($data['type'] == 'timestamp' || $data['meta'] == 'timestamp')
							{
								$timestamps[] = $col;
								// some apps remove a prefix --> add prefix-less version too
								$matches = null;
								if (preg_match('/^(tz|acl|async|cal|contact|lock|history|link|cf|cat|et)_(.+)$/', $col, $matches))
								{
									$timestamps[] = $matches[2];
								}
							}
						}
					}
				}
			}
			//error_log(__METHOD__."() returning ".array2string($timestamps));
			return $timestamps;
		}, array(), 86400);	// cache for 1 day
	}

	/**
	 * Default maximum length for context submenus, longer menus are put as a "More" submenu
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
	 * - string 'hint' tooltip on menu item
	 *
	 * @param array $actions id indexed array of actions / array with valus for keys: 'iconUrl', 'caption', 'onExecute', ...
	 * @param string $template_name ='' name of the template, used as default for app name of images
	 * @param string $prefix ='' prefix for ids
	 * @param array &$action_links =array() on return all first-level actions plus the ones with enabled='javaScript:...'
	 * @param int $max_length =self::DEFAULT_MAX_MENU_LENGTH automatic pagination, not for first menu level!
	 * @param array $default_attrs =null default attributes
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
		$group = false;

		foreach((array)$actions as $id => $action)
		{
			if (!empty($action['hideOnMobile']) && Api\Header\UserAgent::mobile())
			{
				continue;	// no need to send this action to client, specially document actions can be huge
			}
			// in case it's only selectbox  id => label pairs
			if (!is_array($action)) $action = array('caption' => $action);
			if ($default_attrs) $action += $default_attrs;

			// Add 'Select All' after first group
			if ($first_level && $group !== false && $action['group'] != $group && !$egw_actions[$prefix.'select_all'])
			{

				$egw_actions[$prefix.'select_all'] = array(
					'caption' => 'Select all',
					//'checkbox' => true,
					'hint' => 'Select all entries',
					'enabled' => true,
					'shortcut' => array(
						'keyCode'	=>	65, // A
						'ctrl'		=>	true,
						'caption'	=> lang('Ctrl').'+A'
					),
					'group' => $action['group'],
				);
				$action_links[] = $prefix.'select_all';
			}
			$group = $action['group'];

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
				
				// sets the default attributes to every children dataset 
				if (is_array($action['children'])) {
					foreach ($action['children'] as $key => $children) {
						// checks if children is a valid array and if the "$default_attrs" variable exists
 						if (is_array($action['children'][$key]) && $default_attrs) {
							$action['children'][$key] += $default_attrs;
						}
					}
				}	
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
				$inherit_keys = array_flip($inherit_attrs);
				$action['children'] = self::egw_actions($action['children'], $template_name, $action['prefix'], $action_links, $max_length,
					array_intersect_key($action, $inherit_keys));

				unset($action['prefix']);

				// Allow default actions to keep their onExecute
				if($action['default']) unset($inherit_keys['onExecute']);
				$action = array_diff_key($action, $inherit_keys);
			}

			// link or popup action
			if ($action['url'])
			{
				$action['url'] = Api\Framework::link('/index.php',str_replace('$action',$id,$action['url']));
				if ($action['popup'])
				{
					list($action['data']['width'],$action['data']['height']) = explode('x',$action['popup']);
					unset($action['popup']);
					$action['data']['nm_action'] = 'popup';
				}
				else
				{
					$action['data']['nm_action'] = 'location';
					if(!$action['target'] && strpos($action['url'],'menuaction') > 0)
					{
						// It would be better if app set target, but we'll auto-detect if not
						list(,$menuaction) = explode('=',$action['url']);
						list($app) = explode('.',$menuaction);
						$action['data']['target'] = $app;
					}
				}
			}
			if ($action['egw_open'])
			{
				$action['data']['nm_action'] = 'egw_open';
			}

			$egw_actions[$prefix.$id] = $action;

			if (!$first_level && $n++ == $max_length) break;
		}

		// Make sure select all is in a group by itself
		foreach($egw_actions as $id => &$_action)
		{
			if($id == $prefix . 'select_all') continue;
			if($_action['group'] >= $egw_actions[$prefix.'select_all']['group'] )
			{
				$egw_actions[$id]['group']+=1;
			}
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
	 * @param int $group =0 see self::egw_actions
	 * @param string $caption ='Change category'
	 * @param string $prefix ='cat_' prefix category id to get action id
	 * @param boolean $globals =true application global categories too
	 * @param int $parent_id =0 only returns cats of a certain parent
	 * @param int $max_cats_flat =self::DEFAULT_MAX_MENU_LENGTH use hierarchical display if more cats
	 * @return array like self::egw_actions
	 */
	public static function category_action($app, $group=0, $caption='Change category',
		$prefix='cat_', $globals=true, $parent_id=0, $max_cats_flat=self::DEFAULT_MAX_MENU_LENGTH)
	{
		$cat = new Api\Categories(null,$app);
		$cats = $cat->return_sorted_array($start=0, false, '', 'ASC', 'cat_name', $globals, $parent_id, true);

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

				$cat_actions[$cat['id']] = array(
					'caption' => $name,
					'no_lang' => true,
				);
				// add category icon
				if (is_array($cat['data']) && $cat['data']['icon'] && file_exists(EGW_SERVER_ROOT.self::ICON_PATH.'/'.basename($cat['data']['icon'])))
				{
					$cat_actions[$cat['id']]['iconUrl'] = $GLOBALS['egw_info']['server']['webserver_url'].self::ICON_PATH.'/'.$cat['data']['icon'];
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
	 * @param array $cats =null all cats if already read
	 * @param string $prefix ='cat_' prefix category id to get action id
	 * @param int $parent_id =0 only returns cats of a certain parent
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

				$cat_actions[$cat['id']] = array(
					'caption' => $name,
					'no_lang' => true,
					'prefix' => $prefix,
				);
				// add category icon
				if ($cat['data']['icon'] && file_exists(EGW_SERVER_ROOT.self::ICON_PATH.'/'.basename($cat['data']['icon'])))
				{
					$cat_actions[$cat['id']]['iconUrl'] = $GLOBALS['egw_info']['server']['webserver_url'].self::ICON_PATH.'/'.$cat['data']['icon'];
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
	 * @param array &$validated =array() validated content
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);
		$value = self::get_array($content, $form_name);

		// Some (most) nextmatch settings are set in its value, not attributes, which aren't in
		// $content.  Fetch them from the request, so we actually have them.
		$content_value = self::get_array(self::$request->content, $form_name);

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
		if($GLOBALS['egw_info']['user']['apps']['admin'] && $app && $value['selectcols'])
		{
			$pref_name = 'nextmatch-' . (isset($content_value['columnselection_pref']) ? $content_value['columnselection_pref'] : $this->attrs['template']);
			$refresh_pref_name = $pref_name.'-autorefresh';
			switch($value['nm_col_preference']) {
				case 'force':
					$pref_level = 'forced';
					break;
				case 'reset':
				case 'default':
					$pref_level = 'default';
					break;
				default:
					$pref_level = 'user';
			}

			// Clear forced pref before setting default
			if($pref_level != 'forced')
			{
				$GLOBALS['egw']->preferences->delete($app,$pref_name,'forced');
				$GLOBALS['egw']->preferences->delete($app,$refresh_pref_name,'forced');
				$GLOBALS['egw']->preferences->delete($app,$pref_name.'-size','forced');
				$GLOBALS['egw']->preferences->delete($app,$pref_name.'-lettersearch','forced');
				$GLOBALS['egw']->preferences->save_repository(true,'forced');
			}

			// Set columns + refresh as default for all users
			// Columns included in submit, preference might not be updated yet
			$cols = $value['selectcols'];
			$GLOBALS['egw']->preferences->read_repository(true);
			$GLOBALS['egw']->preferences->add($app,$pref_name,is_array($cols) ? implode(',',$cols) : $cols, $pref_level);

			// Autorefresh
			$refresh = $value['nm_autorefresh'];
			$GLOBALS['egw']->preferences->add($app,$refresh_pref_name,(int)$refresh,$pref_level);

			// Lettersearch
			$lettersearch = is_array($cols) && in_array('lettersearch', $cols);
			$GLOBALS['egw']->preferences->add($app,$pref_name.'-lettersearch',(int)$lettersearch,$pref_level);

			$GLOBALS['egw']->preferences->save_repository(true,$pref_level);
			$GLOBALS['egw']->preferences->read(true);

			if($value['nm_col_preference'] == 'reset')
			{
				// Clear column + refresh preference so users go back to default
				$GLOBALS['egw']->preferences->delete_preference($app,$pref_name);
				$GLOBALS['egw']->preferences->delete_preference($app,$pref_name.'-size');
				$GLOBALS['egw']->preferences->delete_preference($app,$pref_name.'-lettersearch');
				$GLOBALS['egw']->preferences->delete_preference($app,$refresh_pref_name);
			}
		}
		unset($value['nm_col_preference']);

		$validated[$form_name] = $value;
	}

	/**
	 * Run a given method on all children
	 *
	 * Reimplemented to add namespace, and make sure row template gets included
	 *
	 * @param string $method_name
	 * @param array $params =array('') parameter(s) first parameter has to be cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
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

			// Run on all the sub-templates
			foreach(array('template', 'header_left', 'header_right', 'header_row') as $sub_template)
			{
				if($this->attrs[$sub_template])
				{
					$row_template = Template::instance($this->attrs[$sub_template]);
					$row_template->run($method_name, $params, $respect_disabled);
				}
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
	 * @param string $type ='edit' "edit" (default), "delete" or "add"
	 */
	public function refresh($row_ids, $type='edit')
	{
		unset($row_ids, $type);	// not used, but required by function signature

		throw new Api\Exception('Not yet implemented');
	}
}

// Registration needs to go here, otherwise customfields won't be loaded until some other cf shows up
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\Customfields', array('nextmatch-customfields'));
