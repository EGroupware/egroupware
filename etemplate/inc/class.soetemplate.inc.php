<?php
/**
 * eGroupWare EditableTemplates - Storage Objects
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-9 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @version $Id$
 */

/**
 * Storage Objects: Everything to store and retrive and eTemplate.
 *
 * eTemplates are stored in the db in table 'phpgw_etemplate' and gets distributed
 * through the file 'etemplates.inc.php' in the setup dir of each app. That file gets
 * automatically imported in the db, whenever you show a eTemplate of the app. For
 * performace reasons the timestamp of the file is stored in the db, so 'new'
 * eTemplates need to have a newer file. The distribution-file is generated with the
 * function dump, usually by pressing a button in the editor.
 * writeLangFile writes an lang-file with all Labels, incorporating an existing one.
 * Beside a name eTemplates use the following keys to find the most suitable template
 * for an user (in order of precedence):
 *  1) User-/Group-Id (not yet implemented)
 *  2) preferd languages of the user (templates for all langs have $lang='')
 *  3) selected template: verdilak, ... (the default is called '' in the db, not default)
 *  4) a version-number of the form, eg: '0.9.13.001' (filled up with 0 same size)
 */
class soetemplate
{
	public $debug;		// =1 show some debug-messages, = 'app.name' show messages only for eTemplate 'app.name'
	public $name;		// name of the template, e.g. 'infolog.edit'
	public $template;	// '' = default (not 'default')
	public $lang;		// '' if general template else language short, e.g. 'de'
	public $group;		// 0 = not specific else groupId or if < 0  userId
	public $version;	// like 0.9.13.001
	public $style;		// embeded CSS style-sheet
	public $children;	// array with children
	public $data;		// depricated: first grid of the children
	public $size;		// depricated: witdh,height,border of first grid
	/**
	 * private reference to the global db-object
	 *
	 * @public egw_db
	 */
	private $db;
	/**
	 * name of table
	 */
	const TABLE = 'egw_etemplate';
	static $db_key_cols = array(
		'et_name' => 'name',
		'et_template' => 'template',
		'et_lang' => 'lang',
		'et_group' => 'group',
		'et_version' => 'version'
	);
	static $db_data_cols = array(
		'et_data' => 'data',
		'et_size' => 'size',
		'et_style' => 'style',
		'et_modified' => 'modified'
	);
	static $db_cols;
	/**
	 * widgets that contain other widgets, eg. for tree_walk method
	 * widget-type is the key, the value specifys how the children are stored.
	 *
	 * @public array
	 */
	static $widgets_with_children = array(
		'template' => 'template',
		'grid' => 'grid',
		'box' => 'box',
		'vbox' => 'box',
		'hbox' => 'box',
		'groupbox' => 'box',
		'deck' => 'box',
	);

	/**
	 * constructor of the class
	 *
	 * calls init or read depending on a name for the template is given
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param int $rows initial size of the template, default 1, only used if no name given !!!
	 * @param int $cols initial size of the template, default 1, only used if no name given !!!
	 * @return soetemplate
	 */
	function __construct($name='',$template='',$lang='',$group=0,$version='',$rows=1,$cols=1)
	{
		if (isset($GLOBALS['egw']->db))
		{
			$this->db = $GLOBALS['egw']->db;
		}
		else
		{
			$GLOBALS['egw_info']['server']['eTemplate-source'] = 'files';
		}
		if (empty($name))
		{
			$this->init($name,$template,$lang,$group,$version,$rows,$cols);
		}
		else
		{
			$this->read($name,$template,$lang,$group,$version);
		}
	}

	/**
	 * generates column-names from index: 'A', 'B', ..., 'AA', 'AB', ..., 'ZZ' (not more!)
	 *
	 * @param int $num numerical index to generate name from 1 => 'A'
	 * @return string the name
	 */
	static function num2chrs($num)
	{
		$min = ord('A');
		$max = ord('Z') - $min + 1;
		if ($num >= $max)
		{
			$chrs = chr(($num / $max) + $min - 1);
		}
		$chrs .= chr(($num % $max) + $min);

		return $chrs;
	}

	/**
	 * generates column-names from index: 'A', 'B', ..., 'AA', 'AB', ..., 'ZZ' (not more!)
	 *
	 * @param string $chrs column letter to generate name from 'A' => 1
	 * @return int the index
	 */
	static function chrs2num($chrs)
	{
		$min = ord('A');
		$max = ord('Z') - $min + 1;

		$num = 1+ord($chrs{0})-$min;
		if (strlen($chrs) > 1)
		{
			$num *= 1 + $max - $min;
			$num += 1+ord($chrs{1})-$min;
		}
		return $num;
	}

	/**
	 * constructor for a new / empty cell/widget
	 *
	 * nothing fancy so far
	 *
	 * @param string $type type of the widget
	 * @param string $name name of widget
	 * @param array $attributes=null array with further attributes
	 * @return array the cell
	 */
	static function empty_cell($type='label',$name='',$attributes=null)
	{
		$cell = array(
			'type' => $type,
			'name' => $name,
		);
		if ($attributes && is_array($attributes))
		{
			$cell += $attributes;
		}
		return $cell;
	}

	/**
	 * constructs a new cell in a give row or the last row, not existing rows will be created
	 *
	 * @deprecated as it uses this->data
	 * @param int $row row-number starting with 1 (!)
	 * @param string $type type of the cell
	 * @param string $label label for the cell
	 * @param string $name name of the cell (index in the content-array)
	 * @param array $attributes other attributes for the cell
	 * @return array a reference to the new cell, use $new_cell = &$tpl->new_cell(); (!)
	*/
	function &new_cell($row=False,$type='label',$label='',$name='',$attributes=False)
	{
		$row = $row >= 0 ? intval($row) : 0;
		if ($row && !isset($this->data[$row]) || !isset($this->data[1]))	// new row ?
		{
			if (!$row) $row = 1;

			$this->data[$row] = array();
		}
		if (!$row)	// use last row
		{
			$row = count($this->data);
			while (!isset($this->data[$row]))
			{
				--$row;
			}
		}
		$row = &$this->data[$row];
		$col = $this->num2chrs(count($row));
		$cell = &$row[$col];
		$cell = $this->empty_cell($type,$name);
		if ($label !== '')
		{
			$attributes['label'] = $label;
		}
		if (is_array($attributes))
		{
			foreach($attributes as $name => $value)
			{
				$cell[$name] = $value;
			}
		}
		return $cell;
	}

	/**
	 * adds $cell to it's parent at the parent-type spezific location for childs
	 *
	 * @param array &$parent referenc to the parent
	 * @param array &$cell cell to add (need to be unset after the call to add_child, as it's a referenc !)
	 */
	static function add_child(&$parent,&$cell)
	{
		if (is_object($parent))	// parent is the template itself
		{
			$parent->children[] = &$cell;
			return;
		}
		switch($parent['type'])
		{
			case 'vbox':
			case 'hbox':
			case 'groupbox':
			case 'box':
			case 'deck':
				list($n,$options) = explode(',',$parent['size'],2);
				$parent[++$n] = &$cell;
				$parent['size'] = $n . ($options ? ','.$options : '');
				break;

			case 'grid':
				$data = &$parent['data'];
				$cols = &$parent['cols'];
				$rows = &$parent['rows'];
				$row = &$data[$rows];
				$col = count($row);
				if (!$rows || !is_array($cell))	// create a new row
				{
					$row = &$data[++$rows];
					$row = array();
				}
				if (is_array($cell))	// real cell to add
				{
					$row[soetemplate::num2chrs($col++)] = &$cell;
					list($spanned) = explode(',',$cell['span']);
					$spanned = $spanned == 'all' ? 1 + $cols - $col : $spanned;
					while (--$spanned > 0)
					{
						$row[soetemplate::num2chrs($col++)] = soetemplate::empty_cell();
					}
					if ($col > $cols) $cols = $col;
				}
				break;
		}
	}

	/**
	 * initialises internal vars rows & cols from the data of a grid
	 *
	 * @param array &$grid to calc rows and cols
	 */
	static function set_grid_rows_cols(&$grid)
	{
		$grid['rows'] = count($grid['data']) - 1;
		$grid['cols'] = 0;
		for($r = 1; $r <= $grid['rows']; ++$r)
		{
			$cols = count($grid['data'][$r]);
			if ($grid['cols'] < $cols)
			{
				$grid['cols'] = $cols;
			}
		}
	}

	/**
	 * initialises internal vars rows & cols from the data of the first (!) grid
	 *
	 * @deprecated as it uses this->data
	 */
	function set_rows_cols()
	{
		if (is_null($this->data))	// tmpl contains no grid
		{
			$this->rows = $this->cols = 0;
		}
		else
		{
			$grid['data'] = &$this->data;
			$grid['rows'] = &$this->rows;
			$grid['cols'] = &$this->cols;
			$this->set_grid_rows_cols($grid);
			unset($grid);
		}
	}

	/**
	 * initialises all internal data-structures of the eTemplate and sets the keys
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys and possibly data
	 * @param string $template template-set or '' for the default one
	 * @param string $lang language or '' for the default one
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @param int $rows initial size of the template, default 1
	 * @param int $cols initial size of the template, default 1
	 */
	function init($name='',$template='',$lang='',$group=0,$version='',$rows=1,$cols=1)
	{
		// unset children and data as they are referenzes to each other
		unset($this->children); unset($this->data);

		foreach(self::$db_cols as $db_col => $col)
		{
			if ($col != 'data') $this->$col = is_array($name) ? (string) $name[$col] : $$col;
		}
		if ($this->template == 'default')
		{
			$this->template = '';
		}
		if ($this->lang == 'default')
		{
			$this->lang = '';
		}
		$this->tpls_in_file = is_array($name) ? $name['tpls_in_file'] : 0;

		if (is_array($name) && $name['onclick_handler']) $this->onclick_handler = $name['onclick_handler'];

		if (is_array($name)  && isset($name['data']))
		{
			// data/children are in $name['data']
			$this->children = is_array($name['data']) ? $name['data'] : unserialize($name['data']);

			$this->fix_old_template_format();
		}
		else
		{
			$this->size = $this->style = '';
			$this->data = array(0 => array());
			$this->rows = $rows < 0 ? 1 : $rows;
			$this->cols = $cols < 0 ? 1 : $cols;
			for ($row = 1; $row <= $rows; ++$row)
			{
				for ($col = 0; $col < $cols; ++$col)
				{
					$this->data[$row][$this->num2chrs($col)] = $this->empty_cell();
				}
			}
			$this->children[0]['type'] = 'grid';
			$this->children[0]['data'] = &$this->data;
			$this->children[0]['rows'] = &$this->rows;
			$this->children[0]['cols'] = &$this->cols;
			$this->children[0]['size'] = &$this->size;
		}
	}

	/**
	 * reads an eTemplate from the database
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @return boolean True if a fitting template is found, else False
	 */
	function read($name,$template='default',$lang='default',$group=0,$version='')
	{
		$this->init($name,$template,$lang,$group,$version);
		if ($this->debug == 1 || $this->debug == $this->name)
		{
			echo "<p>soetemplate::read('$this->name','$this->template','$this->lang',$this->group,'$this->version')</p>\n";
		}
		if (($GLOBALS['egw_info']['server']['eTemplate-source'] == 'files' ||
					$GLOBALS['egw_info']['server']['eTemplate-source'] == 'xslt') && $this->readfile())
		{
			return True;
		}
		if ($this->name)
		{
			$this->test_import($this->name);	// import updates in setup-dir
		}
		$pref_lang = $GLOBALS['egw_info']['user']['preferences']['common']['lang'];
		$pref_templ = $GLOBALS['egw_info']['server']['template_set'];

		$where = array(
			'et_name' => $this->name,
		);
		if (is_array($name))
		{
			$template = $name['template'];
		}
		if ($template == 'default')
		{
			$where[] = '(et_template='.$this->db->quote($pref_templ)." OR et_template='')";
		}
		else
		{
			$where['et_template'] = $this->template;
		}
		if (is_array($name))
		{
			$lang = $name['lang'];
		}
		if ($lang == 'default' || $name['lang'] == 'default')
		{
			$where[] = '(et_lang='.$this->db->quote($pref_lang)." OR et_lang='')";
		}
		else
		{
			$where['et_lang'] = $this->lang;
		}
		if ($this->version != '')
		{
			$where['et_version'] = $this->version;
		}
		if (!($row = $this->db->select(self::TABLE,'*',$where,__LINE__,__FILE__,false,'ORDER BY et_lang DESC,et_template DESC,et_version DESC','etemplate')->fetch()))
		{
			$version = $this->version;
			return $this->readfile() && (empty($version) || $version == $this->version);
		}
		$this->db2obj($row);

		if ($this->debug == $this->name)
		{
			$this->echo_tmpl();
		}
		return True;
	}

	/**
	 * Reads an eTemplate from the filesystem, the keys are already set by init in read
	 *
	 * @return boolean True if a template was found, else False
	 */
	function readfile()
	{
		list($app,$name) = explode('.',$this->name,2);
		$template = $this->template == '' ? 'default' : $this->template;

		if ($this->lang)
		{
			$lang = '.' . $this->lang;
		}
		$first_try = $ext = $GLOBALS['egw_info']['server']['eTemplate-source'] == 'xslt' ? '.xsl' : '.xet';

		while ((!$lang || !@file_exists($file = EGW_SERVER_ROOT . "/$app/templates/$template/$name$lang$ext") &&
											!@file_exists($file = EGW_SERVER_ROOT . "/$app/templates/default/$name$lang$ext")) &&
						!@file_exists($file = EGW_SERVER_ROOT . "/$app/templates/$template/$name$ext") &&
						!@file_exists($file = EGW_SERVER_ROOT . "/$app/templates/default/$name$ext"))
		{
			if ($ext == $first_try)
			{
				$ext = $ext == '.xet' ? '.xsl' : '.xet';

				if ($this->debug == 1 || $this->name != '' && $this->debug == $this->name)
				{
					echo "<p>tried '$file' now trying it with extension '$ext' !!!</p>\n";
				}
			}
			else
			{
				break;
			}
		}
		if ($this->name == '' || $app == '' || $name == '' || !@file_exists($file) || !($f = @fopen($file,'r')))
		{
			if ($this->debug == 1 || $this->name != '' && $this->debug == $this->name)
			{
				echo "<p>Can't open template '$this->name' / '$file' !!!</p>\n";
			}
			return False;
		}
		$xml = fread ($f, filesize ($file));
		fclose($f);

		if ($ext == '.xsl')
		{
			$cell = $this->empty_cell();
			$cell['type'] = 'xslt';
			$cell['size'] = $this->name;
			//$cell['xslt'] = &$xml;	xslttemplate class cant use it direct at the moment
			$cell['name'] = '';
			$this->data = array(0 => array(),1 => array('A' => &$cell));
			$this->rows = $this->cols = 1;
		}
		else
		{
			if (!is_object($this->xul_io))
			{
				$this->xul_io =& CreateObject('etemplate.xul_io');
			}
			$loaded = $this->xul_io->import($this,$xml);

			if (!is_array($loaded))
			{
				return False;
			}
			$this->name = $app . '.' . $name;	// if template was copied or app was renamed

			$this->tpls_in_file = count($loaded);
		}
		return True;
	}

	/**
	 * Convert the usual *,? wildcards to the sql ones and quote %,_
	 *
	 * @param string $pattern
	 * @return string
	 */
	static function sql_wildcards($pattern)
	{
		return str_replace(array('%','_','*','?'),array('\\%','\\_','%','_'),$pattern);
	}

	/**
	 * Lists the eTemplates matching the given criteria, sql wildcards % and _ possible
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set, '' loads the prefered template of the user, 'default' loads the default one '' in the db
	 * @param string $lang language, '' loads the pref. lang of the user, 'default' loads the default one '' in the db
	 * @param int $group id of the (primary) group of the user or 0 for none, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @return array of arrays with the template-params
	 */
	function search($name,$template='default',$lang='default',$group=0,$version='')
	{
		if ($this->name)
		{
			$this->test_import($this->name);	// import updates in setup-dir
		}
		if (is_array($name))
		{
			$template = (string) $name['template'];
			$lang     = (string) $name['lang'];
			$group    = (int) $name['group'];
			$version  = (string) $name['version'];
			$name     = (string) $name['name'];
		}
		$where[] = 'et_name LIKE '.$this->db->quote($this->sql_wildcards($name).'%');
		if ($template != '' && $template != 'default')
		{
			$where[] = 'et_template LIKE '.$this->db->quote($this->sql_wildcards($template).'%');
		}
		if ($lang != '' && $lang != 'default')
		{
			$where[] = 'et_lang LIKE '.$this->db->quote($this->sql_wildcards($lang).'%');
		}
		if ($this->version != '')
		{
			$where[] = 'et_version LIKE '.$this->db->quote($this->sql_wildcards($version).'%');
		}
		$result = array();
		foreach($this->db->select(self::TABLE,'et_name,et_template,et_lang,et_group,et_version',$where,__LINE__,__FILE__,false,'ORDER BY et_name DESC,et_lang DESC,et_template DESC,et_version DESC','etemplate') as $row)
		{
			if ($row['et_lang'] != '##')	// exclude or import-time-stamps
			{
				$result[] = egw_db::strip_array_keys($row,'et_');
			}
		}
		if ($this->debug)
		{
			_debug_array($result);
		}
		return $result;
	}

	/**
	 * copies all cols into the obj and unserializes the data-array
	 */
	function db2obj(array $row)
	{
		// unset children and data as they are referenzes to each other
		unset($this->children); unset($this->data);

		foreach (self::$db_cols as $db_col => $name)
		{
			if ($name != 'data')
			{
				$this->$name = $row[$db_col];
			}
			else
			{
				$this->children = unserialize($row[$db_col]);
			}
		}
		$this->fix_old_template_format();
	}

	/**
	 *  test if we have an old/original template-format and fixes it to the new format
	 */
	function fix_old_template_format()
	{
		if (!is_array($this->children)) $this->children = array();

		if (!isset($this->children[0]['type']))
		{
			// old templates are treated as having one children of type grid (the original template)
			$this->data = &$this->children;
			unset($this->children);

			$this->children[0]['type'] = 'grid';
			$this->children[0]['data'] = &$this->data;
			$this->children[0]['rows'] = &$this->rows;
			$this->children[0]['cols'] = &$this->cols;
			$this->children[0]['size'] = &$this->size;

			// that code fixes a bug in very old templates, not sure if it's still needed
			if ($this->name[0] != '.' && is_array($this->data))
			{
				reset($this->data); each($this->data);
				while (list($row,$cols) = each($this->data))
				{
					while (list($col,$cell) = each($cols))
					{
						if (is_array($cell['type']))
						{
							$this->data[$row][$col]['type'] = $cell['type'][0];
							//echo "corrected in $this->name cell $col$row attribute type<br>\n";
						}
						if (is_array($cell['align']))
						{
							$this->data[$row][$col]['align'] = $cell['align'][0];
							//echo "corrected in $this->name cell $col$row attribute align<br>\n";
						}
					}
				}
			}
		}
		else
		{
			unset($this->data);
			// for the moment we make $this->data as a referenz to the first grid
			foreach($this->children as $key => $child)
			{
				if ($child['type'] == 'grid')
				{
					$this->data = &$this->children[$key]['data'];
					$this->rows = &$this->children[$key]['rows'];
					$this->cols = &$this->children[$key]['cols'];
					if (!isset($this->children[$key]['size']) && !empty($this->size))
					{
						$this->children[$key]['size'] = &$this->size;
					}
					else
					{
						$this->size = &$this->children[$key]['size'];
					}
					break;
				}
			}
		}
		$this->set_rows_cols();
	}

	static private $compress_array_recursion = array();

	/**
	 * all empty values and objects in the array got unset (to save space in the db )
	 *
	 * The never empty type field ensures a cell does not disapear completely.
	 * Calls it self recursivly for arrays / the rows
	 *
	 * @param array $arr the array to compress
	 * @param boolean $remove_all_objs if true unset all objs, on false use as_array to save only the data of objs
	 * @return array
	 */
	function compress_array($arr,$remove_objs=false)
	{
		static $recursion = array();

		if (!is_array($arr))
		{
			return $arr;
		}
		foreach($arr as $key => $val)
		{
			if ($remove_objs && $key === 'obj')	// it can be an array too
			{
				unset($arr[$key]);
			}
			elseif (is_array($val))
			{
				$arr[$key] = $this->compress_array($val,$remove_objs);
			}
			elseif (!$remove_objs && $key == 'obj' && is_object($val) && method_exists($val,'as_array') &&
				// this test prevents an infinit recursion of templates calling itself, atm. etemplate.editor.new
				self::$compress_array_recursion[$this->name]++ < 2)
			{
				$arr['obj'] = $val->as_array(2);
			}
			elseif ($val == '' || is_object($val))
			{
				unset($arr[$key]);
			}
		}
		return $arr;
	}

	/**
	 * returns obj-data/-vars as array
	 *
	 * the returned array ($data_too > 0) can be used with init to recreate the template
	 *
	 * @param int $data_too -1 = only keys, 0 = no data array, 1 = data array too, 2 = serialize data array,
	 *	3 = only data values and data serialized
	 * @param boolean $db_keys use db-column-names or internal names, default false=internal names
	 * @return array with template-data
	 */
	function as_array($data_too=0,$db_keys=false)
	{
		//echo "<p>soetemplate::as_array($data_too,$db_keys) name='$this->name', ver='$this->version'</p>\n";
		$arr = array();
		switch($data_too)
		{
			case -1:
				$cols = self::$db_key_cols;
				break;
			case 3:
				$cols = self::$db_data_cols;
				break;
			default:
				$cols = self::$db_cols;
		}
		foreach($cols as $db_col => $col)
		{
			if ($col == 'data')
			{
				if ($data_too > 0)
				{
					$arr[$db_keys ? $db_col : $col] = $data_too < 2 ? $this->children :
						serialize($this->compress_array($this->children,$db_keys));
				}
			}
			else
			{
				$arr[$db_keys ? $db_col : $col] = $this->$col;
			}
		}
		if ($data_too != -1 && $this->tpls_in_file && !$db_keys)
		{
			$arr['tpls_in_file'] = $this->tpls_in_file;
		}
		if ($data_too != -1 && $this->onclick_handler && !$db_keys)
		{
			$arr['onclick_handler'] = $this->onclick_handler;
		}
		return $arr;
	}

	/**
	 * saves eTemplate-object to db, can be used as saveAs by giving keys as params
	 *
	 * @param string $name name of the eTemplate or array with the values for all keys
	 * @param string $template template-set
	 * @param string $lang language or ''
	 * @param int $group id of the (primary) group, not used at the moment !!!
	 * @param string $version version of the eTemplate
	 * @return int number of affected rows, 1 should be ok, 0 somethings wrong
	 */
	function save($name='',$template='.',$lang='.',$group='',$version='.')
	{
		if (is_array($name))
		{
			$template = $name['template'];
			$lang     = $name['lang'];
			$group    = $name['group'];
			$version  = $name['version'];
			$name     = $name['name'];
		}
		if ($name != '')
		{
			$this->name = $name;
		}
		if ($lang != '.')
		{
			$this->lang = $lang;
		}
		if ($template != '.')
		{
			$this->template = $template;
		}
		if ($group != '')
		{
			$this->group = $group;
		}
		if ($version != '.')
		{
			$this->version = $version;
		}
		if ($this->name == '')	// name need to be set !!!
		{
			return False;
		}
		if ($this->debug > 0 || $this->debug == $this->name)
		{
			echo "<p>soetemplate::save('$this->name','$this->template','$this->lang',$this->group,'$this->version')</p>\n";
		}
		if ($this->name[0] != '.' && is_array($this->data))		// correct old messed up templates
		{
			reset($this->data); each($this->data);
			while (list($row,$cols) = each($this->data))
			{
				while (list($col,$cell) = each($cols))
				{
					if (is_array($cell['type'])) {
						$this->data[$row][$col]['type'] = $cell['type'][0];
						//echo "corrected in $this->name cell $col$row attribute type<br>\n";
					}
					if (is_array($cell['align'])) {
						$this->data[$row][$col]['align'] = $cell['align'][0];
						//echo "corrected in $this->name cell $col$row attribute align<br>\n";
					}
				}
			}
		}
		if (!$this->modified)
		{
			$this->modified = time();
		}
		if (is_null($this->group) && !is_int($this->group)) $this->group = 0;

		$this->db->insert(self::TABLE,$this->as_array(3,true),$this->as_array(-1,true),__LINE__,__FILE__,'etemplate');

		if (!($rows = $this->db->affected_rows()))
		{
			echo "<p>soetemplate::save('$this->name','$this->template','$this->lang',$this->group,'$this->version') <b>nothing written!!!</b></p>\n";
			function_backtrace();
			_debug_array($this->db);
		}
		return $rows;
	}

	/**
	 * Deletes the eTemplate from the db, object itself is unchanged
	 *
	 * @return int number of affected rows, 1 should be ok, 0 somethings wrong
	 */
	function delete()
	{
		$this->db->delete(self::TABLE,$this->as_array(-1,true),__LINE__,__FILE__,'etemplate');

		return $this->db->affected_rows();
	}

	/**
	 * dumps all eTemplates to <app>/setup/etemplates.inc.php for distribution
	 *
	 * @param string $app app- or template-name contain app
	 * @return string translated message with number of dumped templates or error-message (webserver has no write access)
	 */
	function dump4setup($app)
	{
		list($app) = explode('.',$app);

		$dir = EGW_SERVER_ROOT . "/$app/setup";
		if (!is_writeable($dir))
		{
			return lang("Error: webserver is not allowed to write into '%1' !!!",$dir);
		}
		$file = "$dir/etemplates.inc.php";
		if (file_exists($file))
		{
			$old_file = "$dir/etemplates.old.inc.php";
			if (file_exists($old_file))
			{
				unlink($old_file);
			}
			rename($file,$old_file);
		}

		if (!($f = fopen($file,'w')))
		{
			return 0;
		}
		fwrite($f,'<?php
/**
	* eGroupWare - eTemplates for Application '. $app. '
	* http://www.egroupware.org
	* generated by soetemplate::dump4setup() '.date('Y-m-d H:i'). '
	*
	* @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	* @package '. $app. '
	* @subpackage setup
	* @version $Id$
	*/'."\n\n\$templ_version=1;\n\n");

		$n = 0;
		$exported = array();
		foreach($this->db->select(self::TABLE,'*','et_name LIKE '.$this->db->quote($app.'%'),__LINE__, __FILE__,false,'ORDER BY et_name,et_version DESC','etemplate',0,'',false,egw_db::FETCH_ASSOC) as $row)
		{
			if (isset($exported[$row['et_name']]) && $exported[$row['et_name']] === (string)$row['et_template'])
			{
				continue;	// only export highest version (we sort by version DESC!)
			}
			$exported[$row['et_name']] = (string)$row['et_template'];

			$str = '$templ_data[] = array(';
			foreach (self::$db_cols as $db_col => $name)
			{
				// escape only backslashes and single quotes (in that order)
				$str .= "'$name' => '".str_replace(array('\\','\'',"\r"),array('\\\\','\\\'',''),$row[$db_col])."',";
			}
			$str .= ");\n\n";
			fwrite($f,$str);
			++$n;
		}
		fclose($f);

		return lang("%1 eTemplates for Application '%2' dumped to '%3'",$n,$app,$file);
	}

	/**
	 * extracts all texts: labels and helptexts from the cells of an eTemplate-object
	 *
	 * some extensions use a '|' to squezze multiple texts in a label or help field
	 *
	 * @return array with messages as key AND value
	 */
	function getToTranslate()
	{
		$to_trans = array();

		$this->widget_tree_walk(array('soetemplate','getToTranslateCell'),$to_trans);

		//echo '<b>'.$this->name.'</b>'; _debug_array($to_trans);
		return $to_trans;
	}

	/**
	 * Read all eTemplates of an app an extracts the texts to an array
	 *
	 * @param string $app name of the app
	 * @return array with texts
	 */
	function getToTranslateApp($app)
	{
		$to_trans = array();

		$tpls = $this->search($app);

		$tpl = new soetemplate;	// to not alter our own data

		while (list(,$keys) = each($tpls))
		{
			if (($keys['name'] != $last['name'] ||		// write only newest version
					$keys['template'] != $last['template']) &&
					(strpos($keys['name'],'test') === false || $app == 'test'))
			{
				$tpl->read($keys);
				$to_trans += $tpl->getToTranslate();
				$last = $keys;
			}
		}
		return $to_trans;
	}

	/**
	 * Write new lang-file using the existing one and all text from the eTemplates
	 *
	 * @param string $app app- or template-name
	 * @param string $lang language the messages in the template are, defaults to 'en'
	 * @param array $additional extra texts to translate, if you pass here an array with all messages and
	 * 	select-options they get writen too (form is <unique key> => <message>)
	 * @return string translated message with number of messages written (total and new), or error-message
	 */
	function writeLangFile($app,$lang='en',$additional='')
	{
		if (!$additional)
		{
			$additional = array();
		}
		list($app) = explode('.',$app);

		if (!file_exists(EGW_SERVER_ROOT.'/developer_tools/inc/class.solangfile.inc.php'))
		{
			$solangfile =& CreateObject('etemplate.solangfile');
		}
		else
		{
			$solangfile =& CreateObject('developer_tools.solangfile');
		}
		$langarr = $solangfile->load_app($app,$lang);
		if (!is_array($langarr))
		{
			$langarr = array();
		}
		$commonarr = $solangfile->load_app('phpgwapi',$lang) + $solangfile->load_app('etemplate',$lang);

		$to_trans = $this->getToTranslateApp($app);
		if (is_array($additional))
		{
			//echo "writeLangFile: additional ="; _debug_array($additional);
			foreach($additional as $msg)
			{
				if (!is_array($msg)) $to_trans[trim(strtolower($msg))] = $msg;
			}
		}
		unset($to_trans['']);

		for ($new = $n = 0; list($message_id,$content) = each($to_trans); ++$n)
		{
			if (!isset($langarr[$message_id]) && !isset($commonarr[$message_id]))
			{
				if (@isset($langarr[$content]))	// caused by not lowercased-message_id's
				{
					unset($langarr[$content]);
				}
				$langarr[$message_id] = array(
					'message_id' => $message_id,
					'app_name'   => $app,
					'content'    => $content
				);
				++$new;
			}
		}
		ksort($langarr);

		$dir = EGW_SERVER_ROOT . "/$app/setup";
		if (!is_writeable($dir))
		{
			return lang("Error: webserver is not allowed to write into '%1' !!!",$dir);
		}
		$file = "$dir/phpgw_$lang.lang";
		if (file_exists($file))
		{
			$old_file = "$dir/phpgw_$lang.old.lang";
			if (file_exists($old_file))
			{
				unlink($old_file);
			}
			rename($file,$old_file);
		}
		$solangfile->write_file($app,$langarr,$lang);
		$solangfile->loaddb($app,$lang);

		return lang("%1 (%2 new) Messages writen for Application '%3' and Languages '%4'",$n,$new,$app,$lang);
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
		$templ = new etemplate($app);

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
		}
		return lang("%1 new eTemplates imported for Application '%2'",$n,$app);
	}

	static private $import_tested = array();

	/**
	 * test if new template-import necessary for app and does the import
	 *
	 * Get called on every read of a eTemplate, caches the result in phpgw_info.
	 * The timestamp of the last import for app gets written into the db.
	 *
	 * @param string $app app- or template-name
	 * @return string translated message with number of templates imported
	 */
	static function test_import($app)	// should be done from the setup-App
	{
		list($app) = explode('.',$app);

		if (!$app || self::$import_tested[$app])
		{
			return '';	// ensure test is done only once per call and app
		}
		self::$import_tested[$app] = True;	// need to be done before new ...

		$path = EGW_SERVER_ROOT."/$app/setup/etemplates.inc.php";

		if ($time = @filemtime($path))
		{
			$templ = new soetemplate(".$app",'','##');
			if ($templ->lang != '##' || $templ->modified < $time) // need to import
			{
				$ret = self::import_dump($app);
				$templ->modified = $time;
				$templ->save('.'.$app,'','##');
			}
		}
		return $ret;
	}

	/**
	 * prints/echos the template's content, eg. for debuging
	 *
	 * @param boolean $backtrace = true give a function backtrace
	 * @param boolean $no_other_objs = true dump other objs (db, html, ...) too
	 */
	function echo_tmpl($backtrace=true,$no_other_objs=true)
	{
		static $objs = array('db','html','xul_io');

		if ($backtrace) echo "<p>".function_backtrace(1)."</p>\n";

		if ($no_other_objs)
		{
			foreach($objs as $obj)
			{
				$$obj = &$this->$obj;
				unset($this->$obj);
			}
		}
		_debug_array($this);

		if ($no_other_objs)
		{
			foreach($objs as $obj)
			{
				$this->$obj = &$$obj;
				unset($$obj);
			}
		}
	}

	/**
	 * applys a function to each widget in the children tree of the template
	 *
	 * The function should be defined as [&]func([&]$widget,[&]$extra[,$path])
	 * If the function returns anything but null or sets $extra['__RETURN__NOW__'] (func has to reference $extra !!!),
	 * the walk stops imediatly and returns that result
	 *
	 * Only some widgets have a sub-tree of children: *box, grid, template, ...
	 * For them we call tree_walk($widget,$func,$extra) instead of func direct
	 *
	 * Please note: as call_user_func_array does not return references, methods ($func is an array) can not either!!!
	 *
	 * @param string/array $func function to use or array($obj,'method')
	 * @param mixed &$extra extra parameter passed to function
	 * @param string $path='/' start-path
	 * @return mixed return-value of func or null if nothing returned at all
	 */
	function &widget_tree_walk($func,&$extra,$path='/')
	{
		if (!is_callable($func))
		{
			echo "<p><b>boetemplate($this->name)::widget_tree_walk</b>(".print_r($func,true).", ".print_r($extra,true).", ".print_r($opts,true).") func is not callable !!!<br>".function_backtrace()."</p>";
			return false;
		}
		foreach($this->children as $c => &$child)
		{
			$child = &$this->children[$c];
			if (isset(soetemplate::$widgets_with_children[$child['type']]))
			{
				$result =& $this->tree_walk($child,$func,$extra,$path.$c);
			}
			elseif (is_array($func))
			{
				$result =& call_user_func_array($func,array(&$child,&$extra,$path.$c));
			}
			else
			{
				$result =& $func($child,$extra,$path.$c);
			}
			if (!is_null($result) || is_array($extra) && isset($extra['__RETURN_NOW__'])) break;
		}
		return $result;
	}

	/**
	 * applys a function to each child in the tree of a widget (incl. the widget itself)
	 *
	 * The function should be defined as [&]func([&]$widget,[&]$extra[,$path]) [] = optional
	 * If the function returns anything but null or sets $extra['__RETURN__NOW__'] (func has to reference $extra !!!),
	 * the walk stops imediatly and returns that result
	 *
	 * Only some widgets have a sub-tree of children: *box, grid, template, ...
	 * For performance reasons the function use recursion only if a widget with children contains
	 * a further widget with children.
	 *
	 * @param array $widget the widget(-tree) the function should be applied too
	 * @param string/array $func function to use or array($obj,'method')
	 * @param mixed &$extra extra parameter passed to function
	 * @param string $path path of widget in the widget-tree
	 * @return mixed return-value of func or null if nothing returned at all
	 */
	function &tree_walk(&$widget,$func,&$extra,$path='')
	{
		if (!is_callable($func))
		{
			echo "<p><b>boetemplate::tree_walk</b>(, ".print_r($func,true).", ".print_r($extra,true).", ".print_r($opts,true).") func is not callable !!!<br>".function_backtrace()."</p>";
			return false;
		}
		if(is_array($func))
		{
			$result =& call_user_func_array($func,array(&$widget,&$extra,$path));
		}
		else
		{
			$result =& $func($widget,$extra,$path);
		}
		if (!is_null($result) || is_array($extra) && isset($extra['__RETURN__NOW__']) ||
			!isset(soetemplate::$widgets_with_children[$widget['type']]))
		{
			return $result;
		}
		switch($widget['type'])
		{
			case 'box':
			case 'vbox':
			case 'hbox':
			case 'groupbox':
			case 'deck':
				for($n = 1; is_array($widget[$n]); ++$n)
				{
					$child = &$widget[$n];
					if (isset(soetemplate::$widgets_with_children[$child['type']]))
					{
						$result =& $this->tree_walk($child,$func,$extra,$path.'/'.$n);
					}
					elseif(is_array($func))
					{
						$result =& call_user_func_array($func,array(&$child,&$extra,$path.'/'.$n));
					}
					else
					{
						$result =& $func($child,$extra,$path.'/'.$n);
					}
					if (!is_null($result) || is_array($extra) && isset($extra['__RETURN__NOW__'])) return $result;
				}
				break;

			case 'grid':
				$data = &$widget['data'];
				if (!is_array($data)) break;	// no children

				foreach($data as $r => $row)
				{
					if (!$r || !is_array($row)) continue;

					foreach($row as $c => $col)
					{
						$child = &$data[$r][$c];
						if (isset(soetemplate::$widgets_with_children[$child['type']]))
						{
							$result =& $this->tree_walk($child,$func,$extra,$path.'/'.$r.$c);
						}
						elseif(is_array($func))
						{
							$result =& call_user_func_array($func,array(&$child,&$extra,$path.'/'.$r.$c));
						}
						else
						{
							$result =& $func($child,$extra,$path.'/'.$r.$c);
						}
						if (!is_null($result) || is_array($extra) && isset($extra['__RETURN__NOW__'])) return $result;
						unset($child);
					}
				}
				break;

			case 'template':
				if (!isset($widget['obj']) && $widget['name'][0] != '@')
				{
					$widget['obj'] = new etemplate;
					if (!$widget['obj']->read($widget['name'])) $widget['obj'] = false;
				}
				if (!is_object($widget['obj'])) break;	// cant descent into template

				$result =& $widget['obj']->widget_tree_walk($func,$extra,$path.'/');
				break;
		}
		return $result;
	}

	/**
	 * extracts all translatable labels from a widget
	 *
	 * @param array $cell the widget
	 * @param array &$to_trans array with (lowercased) label => translation pairs
	 */
	static function getToTranslateCell($cell,&$to_trans)
	{
		//echo $cell['name']; _debug_array($cell);
		$strings = explode('|',$cell['help']);

		if ($cell['type'] != 'image')
		{
			$strings = array_merge($strings,explode('|',$cell['label']));
		}
		list($extra_row) = explode(',',$cell['size']);
		if (substr($cell['type'],0,6) == 'select' && !empty($extra_row) && !intval($extra_row))
		{
			$strings[] = $extra_row;
		}
		if (!empty($cell['blur']))
		{
			$strings[] = $cell['blur'];
		}
		foreach($strings as $str)
		{
			if (strlen($str) > 1 && $str{0} != '@' && $str{0} != '$' &&
				strpos($str,'$row') === false && strpos($str,'$cont') === false)
			{
				$to_trans[trim(strtolower($str))] = $str;
			}
		}
	}

	/**
	 * init our static vars
	 */
	static function _init_static()
	{
		self::$db_cols = self::$db_key_cols + self::$db_data_cols;
	}
}
soetemplate::_init_static();