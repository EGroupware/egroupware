<?php
/**
 * eGroupWare EditableTemplates - Business Objects
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-10 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @version $Id$
 */

/**
 * Business Object for eTemplates, extending the Storage Object
 */
class boetemplate extends soetemplate
{
	/**
	 * Widget types implemented directly by etemplate (no extensions)
	 *
	 * @var array intern name => label
	 */
	static public $types = array(
		'label'	=> 'Label',			// Label $cell['label'] is (to be translated) textual content
		'text'	=> 'Text',			// Textfield 1 Line (size = [length][,maxlength])
		'int'	=> 'Integer',		// like text, but only numbers (size = [min][,max])
		'float'	=> 'Floating Point', // --------------- " --------------------------
		'textarea'=> 'Textarea',	// Multiline Text Input (size = [rows][,cols])
		'htmlarea' => 'Formatted Text (HTML)',
		'checkbox'=> 'Checkbox',
		'radio'	=> 'Radiobutton',	// Radiobutton (size = value if checked)
		'button'=> 'Submitbutton',
		'buttononly' => 'Button',	// input type="button"
		'hrule'	=> 'Horizontal Rule',
		'template' => 'Template',	// $cell['name'] contains template-name, $cell['size'] index into $content,$cname,$readonlys
		'image'	=> 'Image',			// label = url, name=link or method, help=alt or title
		'date'	=> '', 				// Datefield, size='' timestamp or size=format like 'm/d/Y'
		'select'=>	'Selectbox',	// Selectbox ($sel_options[$name] or $content[options-$name] is array with options)
									// if size > 1 then multiple selections, size lines showed
		'html'	=> 'Html',			// Raw html in $content[$cell['name']]
		'file'	=> 'FileUpload',	// show an input type='file', set the local name as ${name}_path
		'vbox'	=> 'VBox',			// a (vertical) box to contain widgets in rows, size = # of rows
		'hbox'	=> 'HBox',			// a (horizontal) box to contain widgets in cols, size = # of cols
		'groupbox' => 'GroupBox',	// a box with a label containing other elements to group them (html: fieldset)
		'box'	=> 'Box',			// just a container for widgets (html: div)
		'grid'	=> 'Grid',			// tabular widget containing rows with columns of widgets
		'deck'	=> 'Deck',			// a container of elements where only one is visible, size = # of elem.
		'passwd' => 'Password',		// a text of type password
		'colorpicker' => 'Colorpicker',	// input for a color (eg. #123456)
		'hidden'=> 'Hidden input',	// a hidden input eg. to submit javascript computed values back
	);

	/**
	 * Flag if form validation requires to loop
	 *
	 * @var boolean
	 */
	static public $loop = false;

	/**
	 * Request object of the currecntly created request
	 *
	 * It's a static variable as etemplates can contain further etemplates (rendered by a different object)
	 *
	 * @var etemplate_request
	 */
	static public $request;

	/**
	 * constructor of class
	 *
	 * Calls the constructor of soetemplate
	 *
	 * @param string|array $name name of etemplate or array with name and other keys
	 * @param string|array $load_via name or array with keys of other etemplate to load in order to get $name
	 */
	function __construct($name='',$load_via='')
	{
		parent::__construct();

		$tname = &$name;
		if (is_array($name))
		{
			$tname = &$name['name'];
		}
		$tname = (strpos($tname,'.') === false && !empty($tname) ?
			(is_array($load_via) ? $load_via['name'] : $load_via).'.':'').$tname;

		if (empty($tname) || !$this->read($name,'','',0,'',$load_via))
		{
			$this->init($name);
		}
	}

	/**
	 * checks if a grid row or column is disabled
	 *
	 * Expression: [!][@]val[=[@]check]
	 * Parts in square brackets are optional, a ! negates the expression, @val evaluates to $content['val']
	 * if no =check is given all set non-empty and non-zero strings are true (standard php behavior)
	 *
	 * @param string $disabled expression to check, eg. "!@var" for !$content['var']
	 * @param array $content the content-array in the context of the grid
	 * @param int $row=null to be able to use $row or $row_content in value of checks
	 * @param int $c=null to be able to use $row or $row_content in value of checks
	 * @return boolean true if the row/col is disabled or false if not
	 */
	protected function check_disabled($disabled,$content,$row=null,$c=null)
	{
		if ($this->onclick_handler && !$this->no_onclick)
		{
			return false;	// we have an onclick handler
		}
		if ($not = $disabled[0] == '!')
		{
			$disabled = substr($disabled,1);
		}
		list($val,$check_val) = $vals = explode('=',$disabled);

		// use expand_name to be able to use @ or $
		$val = $this->expand_name($val,$c,$row,'','',$content);
		$check_val = $this->expand_name($check_val,$c,$row,'','',$content);
		$result = count($vals) == 1 ? $val != '' : ($check_val[0] == '/' ? preg_match($check_val,$val) : $val == $check_val);
		if ($not) $result = !$result;
		//echo "<p>check_disabled: '".($not?'!':'')."$disabled' = '$val' ".(count($vals) == 1 ? '' : ($not?'!':'=')."= '$check_val'")." = ".($result?'True':'False')."</p>\n";
		return $result;
	}

	/**
	 * Regular expression matching a PHP variable in a string, eg.
	 *
	 *	"replies[$row][reply_message]" should only match $row
	 *	"delete[$row_cont[path]]" should match $row_cont[path]
	 */
	const PHP_VAR_PREG = '\$[A-Za-z0-9_]+(\[[A-Za-z0-9_]+\])*';

	/**
	 * allows a few variables (eg. row-number) to be used in field-names
	 *
	 * This is mainly used for autorepeat, but other use is possible.
	 * You need to be aware of the rules PHP uses to expand vars in strings, a name
	 * of "Row$row[length]" will expand to 'Row' as $row is scalar, you need to use
	 * "Row${row}[length]" instead. Only one indirection is allowd in a string by php !!!
	 * Out of that reason we have now the variable $row_cont, which is $cont[$row] too.
	 * Attention !!!
	 * Using only number as index in field-names causes a lot trouble, as depending
	 * on the variable type (which php determines itself) you used filling and later
	 * accessing the array it can by the index or the key of an array element.
	 * To make it short and clear, use "Row$row" or "$col$row" not "$row" or "$row$col" !!!
	 *
	 * @param sring $name the name to expand
	 * @param int $c is the column index starting with 0 (if you have row-headers, data-cells start at 1)
	 * @param int $row is the row number starting with 0 (if you have col-headers, data-cells start at 1)
	 * @param int $c_ is the value of the previous template-inclusion,
	 * 	eg. the column-headers in the eTemplate-editor are templates itself,
	 * 	to show the column-name in the header you can not use $col as it will
	 * 	be constant as it is always the same col in the header-template,
	 * 	what you want is the value of the previous template-inclusion.
	 * @param int $row_ is the value of the previous template-inclusion,
	 * @param array $cont content of the template, you might use it to generate button-names with id values in it:
	 * 	"del[$cont[id]]" expands to "del[123]" if $cont = array('id' => 123)
	 * @return string the expanded name
	 */
	static function expand_name($name,$c,$row,$c_='',$row_='',$cont='')
	{
		$is_index_in_content = $name[0] == '@';
		if (($pos_var=strpos($name,'$')) !== false)
		{
			if (!$cont)
			{
				$cont = array();
			}
			if (!is_numeric($c)) $c = boetemplate::chrs2num($c);
			$col = boetemplate::num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
			$col_ = boetemplate::num2chrs($c_-1);
			$row_cont = $cont[$row];
			$col_row_cont = $cont[$col.$row];

			// check if name is enclosed in single quotes as argument eg. to an event handler or
			// variable name is contained in quotes and curly brackets, eg. "'{$cont[nm][path]}'" or
			// used as name for a button like "delete[$row_cont[something]]" --> quote contained quotes (' or ")
			if (in_array($name[$pos_var-1],array('[',"'",'{')) && preg_match('/[\'\[]{?('.self::PHP_VAR_PREG.')}?[\'\]]+/',$name,$matches))
			{
				eval('$value = '.$matches[1].';');
				if (is_array($value) && $name[$pos_var-1] == "'")	// arrays are only supported for '
				{
					foreach($value as &$val)
					{
						$val = "'".str_replace(array("'",'"'),array('\\\'','&quot;'),$val)."'";
					}
					$value = '[ '.implode(', ',$value).' ]';
					$name = str_replace("'".$matches[1]."'",$value,$name);
				}
				else
				{
					$value = str_replace(array("'",'"'),array('\\\'','&quot;'),$value);
					$name = str_replace(array('{'.$matches[1].'}',$matches[1]),$value,$name);
				}
			}
			// check if name is assigned in an url --> urlendcode contained & as %26, as egw::link explodes it on &
			if ($name[$pos_var-1] == '=' && preg_match('/[&?]([A-Za-z0-9_]+(\[[A-Za-z0-9_]+\])*)=('.self::PHP_VAR_PREG.')/',$name,$matches))
			{
				eval('$value = '.$matches[3].';');
				if (is_array($value))	// works only reasonable, if get-parameter uses array notation, eg. &file[]=$cont[filenames]
				{
					foreach($value as &$val)
					{
						$val = str_replace('&',urlencode('&'),$val);
					}
					$name = str_replace($matches[3],implode('&'.$matches[1].'=',$value),$name);
				}
				else
				{
					$value = str_replace('&',urlencode('&'),$value);
					$name = str_replace($matches[3],$value,$name);
				}
			}
			eval('$name = "'.str_replace('"','\\"',$name).'";');
		}
		if ($is_index_in_content)
		{
			if ($name[1] == '@')
			{
				$name = self::get_array(self::$request->content,substr($name,2));
			}
			else
			{
				$name = self::get_array($cont,substr($name,1));
			}
		}
		return $name;
	}

	/**
	 * Checks if we have an row- or column autorepeat and sets the indexes for $content, etc.
	 *
	 * Autorepeat is important to allow a variable numer of rows or cols, eg. for a list.
	 * The eTemplate has only one (have to be the last) row or column, which gets
	 * automaticaly repeated as long as content is availible. To check this the content
	 * has to be in an sub-array of content. The index / subscript into content is
	 * determined by the content of size for templates or name for regular fields.
	 * An autorepeat is defined by an index which contains variables to expand.
	 * (vor variable expansion in names see expand_names). Usually I use the keys
	 * $row: 0, 1, 2, 3, ... for only rows, $col: '@', 'A', 'B', 'C', ... for only cols or
	 * $col$row: '@0','A0',... '@1','A1','B1',... '@2','A2','B2',... for both rows and cells.
	 * In general everything expand_names can generate is ok - see there.
	 * As you usually have col- and row-headers, data-cells start with '1' or 'A' !!!
	 *
	 * @param array $cell with data of cell: name, type, size, ...
	 * @param int $c,$r col/row index starting from 0
	 * @param string &$idx returns the index in $content and $readonlys (NOT $sel_options !!!)
	 * @param string &$idx_cname returns the basename for the form-name: is $idx if only one value
	 *		(no ',') is given in size (name (not template-fields) are always only one value)
	 * @param boolean $check_col to check for col- or row-autorepeat
	 * @return boolean true if cell is autorepeat (has index with vars / '$') or false otherwise
	 */
	function autorepeat_idx($cell,$c,$r,&$idx,&$idx_cname,$check_col=False,$cont=null)
	{
		$org_idx = $idx = $cell[ $cell['type'] == 'template' ? 'size' : 'name' ];

		$idx = $this->expand_name($idx,$c,$r,'','',$cont);
		if (!($komma = strpos($idx,',')))
		{
			$idx_cname = $idx;
		}
		else
		{
			$idx_cname = substr($idx,1+$komma);
			$idx = substr($idx,0,$komma);
		}
		$Ok = False;
		$pat = $org_idx;
		while (!$Ok && ($pat = @strstr($pat,'$')))
		{
			$pat = substr($pat,$pat[1] == '{' ? 2 : 1);

			if ($check_col)
			{
				$Ok = $pat[0] == 'c' && !(substr($pat,0,4) == 'cont' ||
						substr($pat,0,2) == 'c_' || substr($pat,0,4) == 'col_');
			}
			else
			{
				$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' ||
					substr($pat,0,4) == 'row_');
			}
		}
		if ($this->name && $this->name == $this->debug)
		{
			echo "$this->name ".($check_col ? 'col' : 'row')."-check: c=$c, r=$r, idx='$org_idx'='$idx' idx_cname='$idx_cname' ==> ".($Ok?'True':'False')."<p>\n";
		}
		return $Ok;
	}

	/**
	 * gets an attribute in a named cell
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @return mixed the attribute or False if named cell not found
	 */
	function &get_cell_attribute($name,$attr)
	{
		return $this->set_cell_attribute($name,$attr,NULL);
	}

	/**
	 * set an attribute in a named cell if val is not NULL else return the attribute
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @param mixed $val if not NULL sets attribute else returns it
	 * @return mixed number of changed cells or False, if none changed
	 */
	function &set_cell_attribute($name,$attr,$val)
	{
		//echo "<p>set_cell_attribute(tpl->name=$this->name, name='$name', attr='$attr',val='$val')</p>\n";

		$extra = array(false,$name,$attr,$val);
		$result =& $this->widget_tree_walk('set_cell_attribute_helper',$extra);

		if (is_null($val))
		{
			return $result;
		}
		return $extra[0];
	}

	/**
	 *  disables all cells with name == $name
	 *
	 * @param sting $name cell-name
	 * @param boolean $disabled=true disable or enable a cell, default true=disable
	 * @return mixed number of changed cells or False, if none changed
	 */
	function disable_cells($name,$disabled=True)
	{
		return $this->set_cell_attribute($name,'disabled',$disabled);
	}

	/**
	 * set one or more attibutes for row $n
	 *
	 * @param int $n numerical row-number starting with 1 (!)
	 * @param string $height percent or pixel or '' for no height
	 * @param string $class name of css class (without the leading '.') or '' for no class
	 * @param string $valign alignment (top,middle,bottom) or '' for none
	 * @param boolean $disabled True or expression or False to disable or enable the row (Only the number 0 means dont change the attribute !!!)
	 * @param string $path='/0' default is the first widget in the tree of children
	 * @return false if $path is no grid or array(height,class,valign,disabled) otherwise
	 */
	function set_row_attributes($n,$height=0,$class=0,$valign=0,$disabled=0,$path='/0')
	{
		$grid =& $this->get_widget_by_path($path);
		if (is_null($grid) || $grid['type'] != 'grid') return false;
		$grid_attr =& $grid['data'][0];

		list($old_height,$old_disabled) = explode(',',$grid_attr["h$n"]);
		$disabled = $disabled !== 0 ? $disabled : $old_disabled;
		$grid_attr["h$n"] = ($height !== 0 ? $height : $old_height).
			($disabled ? ','.$disabled : '');
		list($old_class,$old_valign) = explode(',',$grid_attr["c$n"]);
		$valign = $valign !== 0 ? $valign : $old_valign;
		$grid_attr["c$n"] = ($class !== 0 ? $class : $old_class).
			($valign ? ','.$valign : '');

		list($height,$disabled) = explode(',',$grid_attr["h$n"]);
		list($class,$valign) = explode(',',$grid_attr["c$n"]);
		return array($height,$class,$valign,$disabled);
	}

	/**
	 * disables row $n
	 *
	 * @param int $n numerical row-number starting with 1 (!)
	 * @param boolean $enable=false can be used to re-enable a row if set to True
	 * @param string $path='/0' default is the first widget in the tree of children
	 */
	function disable_row($n,$enable=False,$path='/0')
	{
		$this->set_row_attributes($n,0,0,0,!$enable,$path);
	}

	/**
	 * set one or more attibutes for column $c
	 *
	 * @param int|string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
	 * @param string $width percent or pixel or '' for no height
	 * @param mixed $disabled=0 True or expression or False to disable or enable the column (Only the number 0 means dont change the attribute !!!)
	 * @param string $path='/0' default is the first widget in the tree of children
	 * @return false if $path specifies no grid or array(width,disabled) otherwise
	 */
	function set_column_attributes($c,$width=0,$disabled=0,$path='/0')
	{
		if (is_numeric($c))
		{
			$c = $this->num2chrs($c);
		}
		$grid =& $this->get_widget_by_path($path);
		if (is_null($grid) || $grid['type'] != 'grid') return false;
		$grid_attr =& $grid['data'][0];

		list($old_width,$old_disabled) = explode(',',$grid_attr[$c]);
		$disabled = $disabled !== 0 ? $disabled : $old_disabled;
		$grid_attr[$c] = ($width !== 0 ? $width : $old_width).
			($disabled ? ','.$disabled : '');

		//echo "set_column_attributes('$c',,'$path'): ".$grid_attr[$c]."</p>\n"; _debug_array($grid_attr);
		return explode(',',$grid_attr[$c]);
	}

	/**
	 * disables column $c
	 *
	 * @param int|string $c numerical column-number starting with 0 (!), or the char-code starting with 'A'
	 * @param boolean $enable can be used to re-enable a column if set to True
	 * @param string $path='/0' default is the first widget in the tree of children
	 */
	function disable_column($c,$enable=False,$path='/0')
	{
		$this->set_column_attributes($c,0,!$enable,$path);
	}

	/**
	 * Cache for extension objects
	 *
	 * @var array
	 */
	static private $extensions = array();

	/**
	 * trys to load the Extension / Widget-class from the app or etemplate
	 *
	 * @param string $name name of the extension, the classname should be class.${name}_widget.inc.php
	 *	the $name might be "$name.$app" to give a app-name (default is the current app,or template-name)
	 * @return string|boolean human readable name or false if not found/loadable
	 */
	function loadExtension($type)
	{
		list($class,$app) = explode('.',$type);
		$class .= '_widget';

		if (!$app) $app = $GLOBALS['egw_info']['flags']['current_app'];

		if (!file_exists(EGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
		{
			list($app) = explode('_',$type);
		}
		if (!file_exists(EGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
		{
			list($app) = explode('.',$this->name);
		}
		if (!file_exists(EGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
		{
			$app = 'etemplate';
		}
		if (!file_exists(EGW_SERVER_ROOT."/$app/inc/class.$class.inc.php"))
		{
			//echo "<p>boetemplate::loadExtension($type) extension not found</p>\n";
			return self::$extensions[$type] = False;
		}
		self::$extensions[$type] =& CreateObject($app.'.'.$class,$ui='html');

		//echo "<p>boetemplate::loadExtension($type) extension found in App. $app</p>\n";
		return self::$extensions[$type]->human_name;
	}

	/**
	 * checks if extension is loaded (load it if it isnt) and optional checks if it has a given method
	 *
	 * @param string $name name of the extension, the classname should be class.${name}_widget.inc.php
	 *	the $name might be "$name.$app" to give a app-name (default is the current app,or template-name)
	 * @param string $function 'pre_process', 'post_process' or 'render'
	 * @return boolean true if the extension (incl. method) exists, else false
	 */
	protected function haveExtension($type,$function='')
	{
		return (self::$extensions[$type] || $this->loadExtension($type,$ui)) &&
						($function == '' || self::$extensions[$type]->public_functions[$function]);
	}

	/**
	 * Check if we have a widget of type $type
	 *
	 * @param string $type
	 * @return boolean true widget exists, false unknow widget type
	 */
	public function widgetExists($type)
	{
		return isset(self::$types[$type]) || $this->haveExtension($type);
	}

	/**
	 * executes the pre_process-function of the extension $cell[type]
	 *
	 * @param string $type type of the extension
	 * @param string &$name form-name of this widget/field (used as a unique index into extension_data)
	 * @param mixed  &$value value of the extensions content(-array)
	 * @param array &$cell table-cell on which the extension operates
	 * @param array &$readonlys value of the extensions readonly-setting(-array)
	 * @return mixed the return-value of the extensions preprocess function
	 */
	protected function extensionPreProcess($type,&$name,&$value,&$cell,&$readonlys)
	{
		if (!$this->haveExtension($type))
		{
			return False;
		}
		// only supply extension data for non-readonly widgets or if it's already set
		// otherwise lists store >10k unnecessary data in each etemplate-session
		if (!($cell['readonly'] || $readonlys && !is_array($readonlys)) || isset(self::$request->extension_data[$name]))
		{
			$extension_data =& self::$request->extension_data[$name];
		}
		return self::$extensions[$type]->pre_process($name,$value,$cell,$readonlys,$extension_data,$this);
	}

	/**
	 * executes the post_process-function of the extension $cell[type]
	 *
	 * @param string $type name of the extension
	 * @param string $name form-name of this widget/field (used as a unique index into extension_data)
	 * @param mixed &$value returns the value of the extensions content(-array)
	 * @param mixed $value_in unprocessed value, eg. as posted by the browser
	 * @return boolean True if a value should be returned (default for no postprocess fkt.), else False
	 */
	protected function extensionPostProcess($type,$name,&$value,$value_in)
	{
		if (!$this->haveExtension($type,'post_process'))
		{
			return True;
		}
		return self::$extensions[$type]->post_process($name,$value,
			self::$request->extension_data[$name],
			self::$loop,$this,$value_in);
	}

	/**
	 * executes the render-function of the extension $cell[type]
	 *
	 * @param string $type name of the extension
	 * @param string $name form-name of this widget/field (used as a unique index into extension_data)
	 * @param mixed &$value value of the extensions content(-array)
	 * @param array &$cell table-cell on which the extension operates
	 * @param array &$readonlys value of the extensions readonly-setting(-array)
	 * @return mixed return-value of the render function
	 */
	protected function extensionRender($type,$name,&$value,&$cell,$readonly)
	{
		if (!$this->haveExtension($type,'render'))
		{
			return False;
		}
		return self::$extensions[$type]->render($cell,$name,$value,$readonly,
			self::$request->extension_data[$name],$this);
	}

	/**
	 * checks if $idx is set in array $arr
	 *
	 * for one level of subindes identical to isset($arr[$idx])
	 *
	 * @param array $arr array to check
	 * @param string $idx may contain multiple subindex (eg.'x[y][z]')
	 * @return boolean true if set, else false
	 */
	static function isset_array($arr,$idx)
	{
		$idxs = explode('[',str_replace(']','',$idx));
		$last_idx = array_pop($idxs);
		$pos = &$arr;
		foreach($idxs as $idx)
		{
			if (!is_array($pos))
			{
				return False;
			}
			$pos = &$pos[$idx];
		}
		// was return isset($pos[$last_idx]);
		// array_key_exists also returns true for keys with value null, which fixes some problems with autorepeating rows
		return is_array($pos) && array_key_exists($last_idx,$pos);
	}

	/**
	 * sets $arr[$idx] = $val
	 *
	 * This works for non-trival indexes like 'a[b][c]' too: $arr['a']['b']['c'] = $val;
	 *
	 * @param array &$arr the array to search
	 * @param string $idx the index, may contain sub-indices like a[b], see example below
	 * @param mixed $val value to set
	 */
	static function set_array(&$arr,$idx,$val)
	{
		if (!is_array($arr))
		{
			throw new egw_exception_assertion_failed(__METHOD__."(\$arr,'$idx','$val') \$arr is no array!");
		}
		$idxs = explode('[',str_replace(']','',$idx));
		$pos = &$arr;
		foreach($idxs as $idx)
		{
			$pos = &$pos[$idx];
		}
		$pos = $val;
	}

	/**
	 * return a reference to $arr[$idx]
	 *
	 * This works for non-trival indexes like 'a[b][c]' too: it returns &$arr[a][b][c]
	 * $sub = get_array($arr,'a[b]'); $sub = 'c'; is equivalent to $arr['a']['b'] = 'c';
	 *
	 * @param array $arr the array to search, referenz as a referenz gets returned
	 * @param string $idx the index, may contain sub-indices like a[b], see example below
	 * @param boolean $reference_into default False, if True none-existing sub-arrays/-indices get created to be returned as referenz, else False is returned
	 * @param bool $skip_empty returns false if $idx is not present in $arr
	 * @return mixed reference to $arr[$idx] or false if $idx is not set and not $reference_into
	 */
	static function &get_array(&$arr,$idx,$reference_into=False,$skip_empty=False)
	{
		if (!is_array($arr))
		{
			throw new egw_exception_assertion_failed(__METHOD__."(\$arr,'$idx',$reference_into,$skip_empty) \$arr is no array!");
		}
		if (is_object($idx)) return false;	// given an error in php5.2

		$idxs = explode('[',str_replace(']','',$idx));
		$pos = &$arr;
		foreach($idxs as $idx)
		{
			if (!is_array($pos) && !$reference_into)
			{
				return False;
			}
			if($skip_empty && (!is_array($pos) || !isset($pos[$idx]))) return false;
			$pos = &$pos[$idx];
		}
		return $pos;
	}

	/**
	 * unsets $arr[$idx]
	 *
	 * This works for non-trival indexes like 'a[b][c]' too
	 * unset_array($arr,'a[b]'); is equivalent to unset($arr['a']['b']);
	 *
	 * @param array $arr the array to search, referenz as a referenz gets returned
	 * @param string $idx the index, may contain sub-indices like a[b], see example below
	 */
	static function unset_array(&$arr,$idx)
	{
		if (!is_array($arr))
		{
			throw new egw_exception_assertion_failed(__METHOD__."(\$arr,'$idx') \$arr is no array!");
		}
		$idxs = explode('[',str_replace(']','',$idx));
		$last_idx = array_pop($idxs);
		$pos = &$arr;
		foreach($idxs as $idx)
		{
			$pos = &$pos[$idx];
		}
		unset($pos[$last_idx]);
	}

	/**
	 * merges $old and $new, content of $new has precedence over $old
	 *
	 * THIS IS NOT THE SAME AS PHP's functions:
	 * - array_merge, as it calls itself recursive for values which are arrays.
	 * - array_merge_recursive accumulates values with the same index and $new does NOT overwrite $old
	 *
	 * @param array $old
	 * @param array $new
	 * @return array the merged array
	 */
	static function complete_array_merge($old,$new)
	{
		if (is_array($new))
		{
			if (!is_array($old)) $old = (array) $old;

			foreach($new as $k => $v)
			{
				if (!is_array($v) || !isset($old[$k]) || 	// no array or a new array
					isset($v[0]) && !is_array($v[0]) && isset($v[count($v)-1]))	// or no associative array, eg. selecting multiple accounts
				{
					$old[$k] = $v;
				}
				else
				{
					$old[$k] = self::complete_array_merge($old[$k],$v);
				}
			}
		}
		return $old;
	}

	/**
	 * returns a reference to a widget in the widget's children tree spezified by a path
	 *
	 * The path get's generated by the widget_tree_walk() methode and consists of the keys of the children arrays.
	 * For the 3. Column in the 2. row of a grid which is the only widget in the children-tree it is eg.: "/0/2C"
	 *
	 * @param string $path path in the widget tree
	 * @param int $ancestor=0 0: widget itself, 1: parent, 2: grand-parent, ...
	 * @return array referenz to the widget spezified or null, if it's not found
	 */
	function &get_widget_by_path($path,$ancestor=0)
	{
		//echo "<p>boetemplate::get_widget_by_path('$path',$ancestor)</p>\n";
		$path_parts = explode('/',$path);
		while($ancestor-- > 0)
		{
			if (array_pop($path_parts) === '') return null;
		}
		$path = implode('/',$path_parts);
		if ($path == '/' || $path === '') return $this->children;
		if (count($path_parts) == 2) return $this->children[$path_parts[1]];

		return $this->widget_tree_walk('get_widget_by_path_helper',$path);
	}

	/**
	 * returns a reference to a widget in the widget's children tree spezified it's name
	 *
	 * It returns the first match!
	 *
	 * @param string $name name of the widget
	 * @return array referenz to the widget spezified or null, if it's not found
	 */
	function &get_widget_by_name($name)
	{
		return $this->widget_tree_walk('get_widget_by_name_helper',$name);
	}

	/**
	 * returns an array of references to widgets of the specified type
	 *
	 * @param type String
	 * @return array
	 */
	function &get_widgets_by_type($type) {
		$extra = array(
			'type'		=>	$type,
			'widgets'	=>	array()
		);
		$this->widget_tree_walk('get_widgets_by_type_helper', $extra);
		return $extra['widgets'];
	}

	/**
	 * Split a $delimiter-separated options string, which can contain parts with delimiters enclosed in $enclosure
	 *
	 * Examples:
	 * - csv_split('"1,2,3",2,3') === array('1,2,3','2','3')
	 * - csv_split('1,2,3',2) === array('1','2,3')
	 * - csv_split('"1,2,3",2,3',2) === array('1,2,3','2,3')
	 * - csv_split('"a""b,c",d') === array('a"b,c','d')	// to escape enclosures double them!
	 *
	 * @param string $str
	 * @param int $num=null in how many parts to split maximal, parts over this number end up (unseparated) in the last part
	 * @param string $delimiter=','
	 * @param string $enclosure='"'
	 * @return array
	 */
	static function csv_split($str,$num=null,$delimiter=',',$enclosure='"')
	{
		if (strpos($str,$enclosure) === false)
		{
			return is_null($num) ? explode($delimiter,$str) : explode($delimiter,$str,$num);	// no need to run this more expensive code
		}
		$parts = explode($delimiter,$str);
		for($n = 0; isset($parts[$n]); ++$n)
		{
			$part =& $parts[$n];
			if ($part[0] === $enclosure)
			{
				while (isset($parts[$n+1]) && substr($part,-1) !== $enclosure)
				{
					$part .= $delimiter.$parts[++$n];
					unset($parts[$n]);
				}
				$part = substr(str_replace($enclosure.$enclosure,$enclosure,$part),1,-1);
			}
		}
		$parts = array_values($parts);	// renumber the parts (in case we had to concat them)

		if ($num > 0 && count($parts) > $num)
		{
			$parts[$num-1] = implode($delimiter,array_slice($parts,$num-1,count($parts)-$num+1));
			$parts = array_slice($parts,0,$num);
		}
		return $parts;
	}

	/**
	 * generated a file-name from an eTemplates, name, template(-set) and lang
	 *
	 * @param string|array $name name of template or array('name'=>$name,'template'=>$template,'lang'=>$lang)
	 * @param string $template template-set
	 * @param string $lang language to use
	 * @return string
	 */
	private /* static */ function cache_name($name='',$template='default',$lang='default')
	{
		if (empty($name))
		{
			$name     = $this->name;
			$template = $this->template;
			$lang     = $this->lang;
		}
		elseif (is_array($name))
		{
			$template = $name['template'];
			$lang     = $name['lang'];
			$name     = $name['name'];
		}
		if (empty($template))
		{
			$template = 'default';
		}
		$cname = $template . '/' . $name . (!empty($lang) && $lang != 'default' ? '.' . $lang : '');
		//echo "<p>".__METHOD__."('$name','$template','$lang') = '$cname'</p>";

		return $cname;
	}

	/**
	 * stores the etemplate in the cache in egw_info
	 *
	 * @param boetemplate $tpl=null required parameter for static use!
	 */
	public /*static*/ function store_in_cache(boetemplate $tpl=null)
	{
		if (is_null($tpl)) $tpl = $this;

		$cname = $tpl->cache_name();
		$old = egw_cache::getInstance('etemplate',$cname);

		// only cache newest versions (currently cached one is older or same version)
		if (is_null($old) || version_compare($old['version'],$tpl->version,'<='))
		{
			//echo "<p>".__METHOD__."('$tpl->name','$tpl->template','$tpl->lang','$tpl->version') modified=$tpl->modified, time()=".time()."</p>\n";
			egw_cache::setInstance('etemplate',$cname,$tpl->as_array(1));
		}
	}

	/**
	* deletes the etemplate in the cache
	*/
	private function delete_in_cache()
	{
		//echo "<p>".__METHOD__."('$this->name','$this->template','$this->lang','$this->version')</p>\n";
		egw_cache::unsetInstance('etemplate',$this->cache_name());
	}

	static private $import_tested;

	/**
	 * Test if new template-import necessary for app and does the import
	 *
	 * Get called on every read of a eTemplate, caches the result.
	 * The timestamp of the last import for app gets written into the db.
	 *
	 * @param string|array $app app- or template-name
	 * @return string translated message with number of templates imported
	 */
	static function test_import($app)	// should be done from the setup-App
	{
		if (is_array($app)) $app = $app['name'];
		list($app) = explode('.',$app);

		if (is_null(self::$import_tested))
		{
			self::$import_tested =& egw_cache::getSession('etemplate','import_tested');
			if (is_null(self::$import_tested)) self::$import_tested = array();
		}
		if (!$app || self::$import_tested[$app])
		{
			return '';	// ensure test is done only once per session and app
		}
		self::$import_tested[$app] = True;	// need to be done before new ...

		$path = EGW_SERVER_ROOT."/$app/setup/etemplates.inc.php";

		if (($time = @filemtime($path)))
		{
			$templ = new boetemplate(array('name' => '.'.$app,'lang' => '##'));
			if ($templ->lang != '##' || $templ->modified < $time) // need to import
			{
				//echo "<p>".__METHOD__."($app) import necessary, as app-modified=$templ->modified < filemtime($path)=$time test-tpl=".array2string($templ->as_array(1))."</p>\n";
				$ret = self::import_dump($app);
				$templ->modified = $time;
				$templ->save('.'.$app,'','##');
			}
		}
		return $ret;
	}

	/**
	 * Imports the dump-file /$app/setup/etempplates.inc.php unconditional (!)
	 *
	 * @param string $app app name
	 * @return string translated message with number of templates imported
	 */
	static function import_dump($app)
	{
		$templ_version=0;

		include($path = EGW_SERVER_ROOT."/$app/setup/etemplates.inc.php");
		$templ = new boetemplate($app);

		foreach($templ_data as $data)
		{
			if ((int)$templ_version < 1)	// we need to stripslashes
			{
				$data['data'] = stripslashes($data['data']);
			}
			$templ->init($data);

			if (!$templ->modified)
			{
				$templ->modified = filemtime($path);
			}
			$templ->save();

			$n++;
		}
		return lang("%1 new eTemplates imported for Application '%2'",$n,$app);
	}

	/**
	 * reads the content of an eTemplate from the cache into the current object
	 *
	 * same as read but only via the cache
	 *
	 * @param string|array $name name of template or array('name'=>$name,'template'=>$template,'lang'=>$lang)
	 * @param string $template template-set
	 * @param string $lang language to use
	 * @param int $group to use the template for
	 * @param string $version of the template
	 * @return boolean true if the eTemplate was found in the cache
	 */
	private function read_from_cache($name,$template='default',$lang='default',$group=0,$version='')
	{
		// check if new import necessary, currently on every request
		$msg = self::test_import($name);
		//if ($msg) echo "<p>".__METHOD__."($name,$template,$lang,$group,$version) self::test_import($name) returning $msg</p>\n";

		if (is_array($name))
		{
			$version = $name['version'];
			$lang = $name['lang'];
			$template = $name['template'];
			$name = $name['name'];
		}
		// templates starting with . (as the import timestamp) never use the cache
		if ($name[0] == '.') return false;

		$data = egw_cache::getInstance('etemplate',$cname=self::cache_name($name,$template,$lang));
		//echo "<p>".__METHOD__.'('.array2string($name).",$template,$lang,$group,$version) egw_cache::getInstance('etemplate','$cname')=".array2string(array('name'=>$data['name'],'version'=>$data['version'],'modified'=>$data['modified']))."</p>\n";

		if (!is_null($data) && (empty($version) || $data['version'] == $version))
		{
			$this->init($data);

			return true;
		}
		return false;
	}

	/**
	 * reads an eTemplate from the cache or database / filesystem (and updates the cache)
	 *
	 * reimplementation of soetemplate::read to use and/or update the cache
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the  default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param mixed $load_via name/array of keys of etemplate to load in order to get $name (only as second try!)
	 * @return boolean True if a fitting template is found, else False
	 */
	function read($name,$template='default',$lang='default',$group=0,$version='',$load_via='')
	{
		if (is_array($name))
		{
			$pname = &$name['name'];
		}
		else
		{
			$pname = &$name;
		}
		if (empty($pname))
		{
			return False;
		}
		$parent = is_array($load_via) ? $load_via['name'] : $load_via;

		if (strstr($pname,'.') === False && !empty($parent))
		{
			$pname = $parent . '.' . $pname;
		}
		if (!$this->read_from_cache($name,$template,$lang,$group,$version))
		{
			if (!parent::read($name,$template,$lang,$group,$version))
			{
				if ($load_via && (is_string($load_via) ||
					!isset($load_via['tpls_in_file']) || $load_via['tpls_in_file'] > 1))
				{
					parent::read($load_via);
					return $this->read_from_cache($name,$template,$lang,$group,$version);
				}
				return False;
			}
			$this->store_in_cache();
		}
		return True;
	}

	/**
	 * saves eTemplate-object to db and update the cache
	 *
	 * reimplementation of soetemplate::save to update the cache
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set or '' for the default one
	 * @param string $lang language or '' for the default one
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @return the number of affected rows, 1 should be ok, 0 somethings wrong
	 */
	function save($name='',$template='.',$lang='.',$group=0,$version='.')
	{
		if ($result = parent::save($name,$template,$lang,$group,$version))
		{
			$this->store_in_cache();
		}
		return $result;
	}

	/**
	 * Deletes the eTemplate from the db, object itself is unchanged
	 *
	 * reimplementation of soetemplate::delete to update the cache
	 *
	 * @return int number of affected rows, 1 should be ok, 0 somethings wrong
	 */
	function delete()
	{
		$this->delete_in_cache();

		return parent::delete();
	}

	/**
	 * initialise our static vars
	 */
	static function _init_static()
	{
	}
}
boetemplate::_init_static();

if (!function_exists('set_cell_attribute_helper'))
{
	function &set_cell_attribute_helper(&$widget,&$extra)
	{
		// extra = array(0=>n,1=>name,2=>attr,3=>value)
		if ($widget['name'] == $extra[1])
		{
			if (is_null($extra[3]))
			{
				$extra['__RETURN_NOW__'] = true;	// wouldnt work otherwise, if attr is not yet set == null
				return $widget[$extra[2]];
			}
			$widget[$extra[2]] = $extra[3];
			++$extra[0];
		}
	}

	function &get_widget_by_name_helper(&$widget,$extra)
	{
		if ($widget['name'] == $extra) return $widget;
	}

	function &get_widget_by_path_helper(&$widget,$extra,$path)
	{
		//echo "<p>path_searched='$extra', widget-path($widget[type]:$widget[name])='$path'</p>\n";
		if ($path == $extra) return $widget;
	}

	function &get_widgets_by_type_helper(&$widget, &$extra)
	{
		//echo '<br />get_widgets_by_type_helper(' . $widget['name'] . ',' . $extra['type'] . ')<br />';
		if($widget['type'] == $extra['type'])
		{
			$extra['widgets'][] =& $widget;
		}
	}
}
// just in case someone still uses the old var
$GLOBALS['egw_info']['flags']['etemplate']['loop'] =& boetemplate::$loop;
