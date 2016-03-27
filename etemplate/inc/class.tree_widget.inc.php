<?php
/**
 * eGroupWare  eTemplate Extension - Tree Widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * eTemplate Extension: tree widgets with predefined eGW specific content
 */
class tree_widget
{
	/**
	 * exported methods of this class
	 * @var array
	 */
	var $public_functions = array(
		'pre_process' => True,
		'post_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 * @var array
	 */
	var $human_name = array(
		'tree' => 'Tree',
		'tree-cat' => 'Category tree',
	);

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	function __construct($ui)
	{
		$this->ui = $ui;
	}

	/**
	 * pre-processing of the extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		list($rows,$type,$type2,$type3) = explode(',',$cell['size']);

		$extension_data['type'] = $cell['type'];
		$extension_data['multiple'] = $rows;

		$readonly = $cell['readonly'] || $readonlys;
		switch ($cell['type'])
		{
			case 'tree-cat':	// !$type == globals cats too, $type2: not used, $type3: application, if not current-app
				if ($readonly)	// for readonly we dont need to fetch all cat's, nor do we need to indent them by level
				{
					$cell['no_lang'] = True;
					foreach(is_array($value) ? $value : (strpos($value,',') !== false ? explode(',',$value) : array($value)) as $id)
					{
						if ($id) $cell['sel_options'][$id] = stripslashes($GLOBALS['egw']->categories->id2name($id));
					}
					break;
				}
				if (!$type3 || $type3 === $GLOBALS['egw']->categories->app_name)
				{
					$categories =& $GLOBALS['egw']->categories;
				}
				else	// we need to instanciate a new cat object for the correct application
				{
					$categories = new categories('',$type3);
				}
				$cat2path=array();
				foreach((array)$categories->return_sorted_array(0,False,'','','',!$type) as $cat)
				{
					$s = stripslashes($cat['name']);

					if ($cat['app_name'] == 'phpgw' || $cat['owner'] == '-1')
					{
						$s .= ' &#9830;';
					}
					$cat2path[$cat['id']] = $path = ($cat['parent'] ? $cat2path[$cat['parent']].'/' : '').(string)$cat['id'];
					$cell['sel_options'][$path] = $s;
				}
				// change cat-ids to pathes and preserv unavailible cats (eg. private user-cats)
				if ($value)
				{
					$pathes = $extension_data['unavailable'] = array();
					foreach(is_array($value) ? $value : explode(',',$value) as $cat)
					{
						if (isset($cat2path[$cat]))
						{
							$pathes[] = $cat2path[$cat];
						}
						else
						{
							$extension_data['unavailable'][] = $cat;
						}
					}
					$value = $rows ? $pathes : $pathes[0];
				}
				$cell['size'] = $rows.($type2 ? ','.$type2 : '');
				$cell['no_lang'] = True;
				break;
		}
		// creating a div-id and var-name for the tree-object by replacing brackets with underscores and removing exec or evtl. cont parts
		$tree_id = str_replace(array('exec[cont][','exec[','[',']'),array('','','_',''),$name);
		$onNodeSelect = 'onNodeSelect_'.$tree_id;
		$script = html::input_hidden($name,$value,false)."<script type='text/javascript'>";
		if (($onclick = $cell['onclick']))
		{
			if (strpos($onclick,'$') !== false || $onclick{0} == '@')
			{
				$onclick = $tmpl->expand_name($onclick,$c,$r,$content['.c'],$content['.row'],$content);
			}
			$onclick = $tmpl->js_pseudo_funcs($onclick,$tmpl->name_vars);
		}
		if ($rows >= 1)	// multiselction with checkboxes --> use onNodeSelect to check the item
		{
			unset($cell['sel_options']['']);
			$onCheck = 'onCheck_'.$tree_id;
			$script .= "
	function $onCheck(id) {
		document.getElementsByName('$name')[0].value=$tree_id.getAllChecked();
		$onclick;
	}
	function $onNodeSelect(id) {
		$tree_id.setCheck(id,$tree_id.isItemChecked(id) ? 0 : 1);
		$onCheck(id);
	}
";
		}
		else	// single selection
		{
			$script .= "
	function $onNodeSelect(id) {
		document.getElementsByName('$name')[0].value=id;
		$onclick;
	}
";
		}
		$script .= "</script>\n";

		list(,$class) = explode(',',$cell['span']);
		$value = $script.html::tree($tmpl->_sel_options($cell,$name),$value,false,$onNodeSelect,$tree_id,$class,'',$onCheck);

		$cell = boetemplate::empty_cell('html',$cell['name']);

		return True;	// extra Label Ok
	}

	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * Only used by select-dow so far
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param object &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		//echo "value_in"; _debug_array($value_in);

		if (!preg_match('/^[0-9\\/'.($extension_data['multiple']?',':'').']*$/',$value_in)) return false;	// guard against xss and other malious content

		$value = $extension_data['multiple'] ? explode(',',$value_in) : $value_in;

		switch ($extension_data['type'])
		{
			case 'tree-cat':
				if ($extension_data['multiple'])
				{
					foreach($value as $k => $path)
					{
						$parts = explode('/',$path);
						$value[$k] = array_pop($parts);
					}
					if ($extension_data['unavailable'])
					{
						$value += $extension_data['unavailable'];
					}
				}
				else
				{
					$parts = explode('/',$value);
					$value = array_pop($parts);
				}
		}
		//echo "value"; _debug_array($value);
		return true;
	}
}
