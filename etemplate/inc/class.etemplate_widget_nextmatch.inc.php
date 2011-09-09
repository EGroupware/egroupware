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
 *  'cat_app'        =>     // I  application the cat's should be from, default app in get_rows
 *  'cat_is_select'  =>     // I  true||'no_lang' use selectbox instead of category selection, default null
 * 	'template'       =>		// I  template to use for the rows, if not set via options
 * 	'header_left'    =>		// I  template to show left of the range-value, left-aligned (optional)
 * 	'header_right'   =>		// I  template to show right of the range-value, right-aligned (optional)
 * 	'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
 *	'never_hide'     => True// I  never hide the nextmatch-line if less then maxmatch entries
 *  'lettersearch'   => True// I  show a lettersearch
 *  'searchletter'   =>     // I0 active letter of the lettersearch or false for [all]
 * 	'start'          =>		// IO position in list
 *	'num_rows'       =>     // IO number of rows to show, defaults to maxmatches from the general prefs
 * 	'cat_id'         =>		// IO category, if not 'no_cat' => True
 * 	'search'         =>		// IO search pattern
 * 	'order'          =>		// IO name of the column to sort after (optional for the sortheaders)
 * 	'sort'           =>		// IO direction of the sort: 'ASC' or 'DESC'
 * 	'col_filter'     =>		// IO array of column-name value pairs (optional for the filterheaders)
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
 *  'return'         =>     // IO allows to return something from the get_rows function if $query is a var-param!
 *  'csv_fields'     =>		// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames or preferred importexport definition,
 * 		array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
 *		or name of import/export definition
 *  'row_id'         =>     // I  key into row content to set it's value as tr id, eg. 'id'
 *  'actions'        =>     // I  array with actions, see nextmatch_widget::egw_actions
 *  'action_links'   =>     // I  array with enabled actions or ones which should be checked if they are enabled
 *                                optional, default id of all first level actions plus the ones with enabled='javaScript:...'
 *  'action_var'     => 'action'	// I name of var to return choosen action, default 'action'
 *  'action'         =>     //  O string selected action
 *  'selected'       =>     //  O array with selected id's
 *  'checkboxes'     =>     //  O array with checkbox id as key and boolean checked value
 *  'select_all'     =>     //  O boolean value of select_all checkbox, reference to above value for key 'select_all'
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
	 * Number of rows to send initially
	 */
	const INITIAL_ROWS = 25;

	/**
	 * Set up what we know on the server side.
	 *
	 * Sending a first chunk of rows
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id);
		$value =& self::get_array(self::$request->content, $form_name, true);

		$value['start'] = 0;
		$value['num_rows'] = self::INITIAL_ROWS;
		$value['total'] = self::call_get_rows($value, $value['rows'], self::$request->readonlys);
		// todo: no need to store rows in request, it's enought to send them to client

		error_log(__METHOD__."() $this: total=$value[total]");
		foreach($value['rows'] as $n => $row) error_log("$n: ".array2string($row));
	}

	/**
	 * Callback to fetch more rows
	 *
	 * @param string $exec_id identifys the etemplate request
	 * @param array $fetchList array of array with values for keys "startIdx" and "count"
	 * @param string full id of widget incl. all namespaces
	 * @return array with values for keys 'total', 'rows', 'readonlys'
	 */
	static public function ajax_get_rows($exec_id, $fetchList, $form_name='nm')
	{
		error_log(__METHOD__."($exec_id,".array2string($fetchList).",$form_name)");

		// Force the array to be associative
		self::$request = etemplate_request::read($exec_id);
		$value = self::get_array(self::$request->content, $form_name, true);
		$result = array('rows' => array());

		foreach ($fetchList as $entry)
		{
			$value['start'] = $entry['startIdx'];
			$value['num_rows'] = $entry['count'];
			$rows = array();

			$result['total'] = self::call_get_rows($value, $rows, $result['readonlys']);

			foreach($rows as $n => $row)
			{
				$result['rows'][$entry['startIdx']+$n] = $row;
			}
		}

		$response = egw_json_response::get();
		$response->data($result);
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
	 * @param array &$rows=null
	 * @param array &$readonlys=null
	 * @param object $obj=null (internal)
	 * @param string|array $method=null (internal)
	 * @return int|boolean total items found of false on error ($value['get_rows'] not callable)
	 */
	private static function call_get_rows(array &$value,array &$rows=null,array &$readonlys=null,$obj=null,$method=null)
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
		if(is_callable($method))	// php5.2.3+ static call (value is always a var param!)
		{
			$total = call_user_func_array($method,array(&$value,&$rows,&$readonlys));
		}
		elseif(is_object($obj) && method_exists($obj,$method))
		{
			if (!is_array($readonlys)) $readonlys = array();
			$total = $obj->$method($value,$rows,$readonlys);
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
		// otherwise we get stoped by max_excutiontime
		if ($total > 200) @set_time_limit(0);
		//error_log($value['get_rows'].'() returning '.array2string($total).', method = '.array2string($method).', value = '.array2string($value));
		return $total;
	}
}

