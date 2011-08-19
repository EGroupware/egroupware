<?php
/**
 * EGroupware - eTemplate widget baseclass
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-11 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

// include only widgets which can't be autoloaded (or contain sub-widgets which cant)
require_once EGW_INCLUDE_ROOT.'/etemplate/inc/class.etemplate_widget_textbox.inc.php';

/**
 * eTemplate widget baseclass
 *
 * @todo text content, eg. the styles of a template are not parsed, thought they are not used here either
 * @todo validation: disabled attribute
 * - widget
 * - grid row
 * - grid column
 */
class etemplate_widget
{
	/**
	 * Widget type
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Widget id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Widget attributes
	 *
	 * @var array
	 */
	public $attrs = array();

	/**
	 * Children
	 *
	 * @var array
	 */
	protected $children = array();

	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options;

	/**
	 * Request object of the currently created request
	 *
	 * It's a static variable as etemplates can contain further etemplates (rendered by a different object)
	 *
	 * @var etemplate_request
	 */
	static protected $request;

	/**
	 * JSON response object, if we run via a JSON request
	 *
	 * @var egw_json_response
	 */
	static protected $response;

	/**
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml)
	{
		$reader = self::get_reader($xml);
		$this->type = $reader->name;
		$depth = $reader->depth;

		$this->id = $reader->getAttribute('id');

		// read all attributes
		$this->set_attrs($reader);

		while($reader->read() && $reader->depth > $depth)
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->depth > $depth)
			{
				$this->children[] = self::factory($reader->name, $reader, $reader->getAttribute('id'));
			}
		}
	}

	/**
	 * Get XMLReader for given xml string
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on an element
	 * @throws egw_exception_wrong_parameter
	 */
	protected static function get_reader($xml)
	{
		if (is_a($xml, 'XMLReader'))
		{
			$reader = $xml;
		}
		else
		{
			$reader = new XMLReader();
			if (!$reader->XML($xml))
			{
				throw new egw_exception_wrong_parameter("Can't parse xml:\n$xml");
			}
		}
		return $reader;
	}

	/**
	 * Parse and set extra attributes from xml in template object
	 *
	 * Returns a cloned template object, if any attribute needs to be set.
	 * This is necessary as templates can be used multiple time, so we can not alter the cached template!
	 *
	 * @param string|XMLReader $xml
	 * @param boolean $cloned=true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return etemplate_widget_template current object or clone, if any attribute was set
	 */
	public function set_attrs($xml, $cloned=true)
	{
		$reader = self::get_reader($xml);

		// check if we have to split legacy options (can be by type)
		$legacy_options = $this->legacy_options;
		if (is_array($legacy_options))
		{
			if (!($type = $reader->getAttribute('type')))
			{
				$type = $this->type;
			}
			$legacy_options = $legacy_options[$type];
		}

		// read and set all attributes
		$template = $this;
		while($reader->moveToNextAttribute())
		{
			if ($reader->name != 'id' && $template->attr[$reader->name] != $reader->value)
			{
				if (!$cloned) $template = clone($this);
				$template->attrs[$reader->name] = $reader->value;

				// split legacy options
				if ($legacy_options && $reader->name == 'options')
				{
					$legacy_options = explode(',', $legacy_options);
					foreach(self::csv_split($reader->value, count($legacy_options)) as $n => $val)
					{
						if ($legacy_options[$n] && (string)$val !== '') $template->attrs[$legacy_options[$n]] = $val;
					}
				}
			}
		}
		return $template;
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
	public static function csv_split($str,$num=null,$delimiter=',',$enclosure='"')
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
	 * Registry of classes implementing widgets
	 *
	 * @var array
	 */
	static protected $widget_registry = array();

	/**
	 * Register a given class for certain widgets
	 *
	 * Registration is only needed if widget (base-)name is not autoloadable,
	 * eg. class etemplate_widget_template does NOT need to be registered.
	 *
	 * @param string $class
	 * @param string|array $widgets
	 */
	public static function registerWidget($class, $widgets)
	{
		foreach((array)$widgets as $widget)
		{
			self::$widget_registry[$widget] = $class;
		}
	}

	/**
	 * Factory method to construct all widgets
	 *
	 * @param string $type
	 * @param string|XMLReader $xml
	 * @param string $id=null
	 */
	public static function factory($type, $xml, $id=null)
	{
		$class_name =& self::$widget_registry[$type];

		if (!isset($class_name))
		{
			list($basetype) = explode('-',$type);
			if (!class_exists($class_name = 'etemplate_widget_'.str_replace('-','_',$type)) &&
				!class_exists($class_name = 'etemplate_widget_'.str_replace('-','_',$basetype)))
			{
				// default to widget class, we can not ignore it, as the widget may contain other widgets
				$class_name = 'etemplate_widget';
			}
		}
		// currently only overlays can contain templates, other widgets can only reference to templates via id
		if ($type == 'template' && $id && ($template = etemplate_widget_template::instance($id)))
		{
			// references can set different attributes like: class, span, content (namespace)
			return $template->set_attrs($xml, false);	// false = need to clone template, if attributs are set!
		}
		return new $class_name($xml);
	}

	/**
	 * Iterate over children to find the one with the given id and optional type
	 *
	 * @param string $id
	 * @param string $type=null
	 * @return etemplate_widget or NULL
	 */
	public function getElementById($id, $type=null)
	{
		foreach($this->children as $child)
		{
			if ($child->id === $id && (is_null($type) || $child->type === $type))
			{
				return $child;
			}
			if (($element = $child->getElementById($id, $type)))
			{
				return $element;
			}
		}
		return null;
	}

	/**
	 * Validate input
	 *
	 * Default implementation only calls validate on it's children
	 *
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param string $cname='' current namespace
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate(array $content, &$validated=array(), $cname = '')
	{
		$ok = true;
		foreach($this->children as $child)
		{
			$ok = $child->validate($content, $validated, $cname) && $ok;
		}
		return $ok;
	}

	/**
	 * Convert object to string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->type.($this->attrs['type'] && $this->attrs['type'] != $this->type ? '('.$this->attrs['type'].')' : '').'#'.$this->id;
	}

	/**
	 * Convert widget (incl. children) to xml
	 *
	 * @param string $indent=''
	 * @return string
	 */
	public function toXml($indent='')
	{
		echo "$indent<$this->type";
		if ($this->id) echo ' id="'.htmlspecialchars($this->id).'"';
		foreach($this->attrs as $name => $value)
		{
			if ($name == 'options' && $this->legacy_options && (!is_array($this->legacy_options) ||
				isset($this->legacy_options[$this->attrs['type'] ? $this->attrs['type'] : $this->type])))
			{
				continue;	// do NOT output already converted legacy options
			}
			echo ' '.$name.'="'.htmlspecialchars($value).'"';
		}
		echo ' php-class="'.get_class($this).'"';

		if ($this->children)
		{
			echo ">\n";
			foreach($this->children as $child)
			{
				$child->toXml($indent."\t");
			}
			echo "$indent</$this->type>\n";
		}
		else
		{
			echo " />\n";
		}
	}

	/**
	 * build the name of a form-element from a basename and name
	 *
	 * name and basename can contain sub-indices in square bracets, eg. basename="base[basesub1][basesub2]"
	 * and name = "name[sub]" gives "base[basesub1][basesub2][name][sub]"
	 *
	 * @param string $cname basename
	 * @param string $name name
	 * @return string complete form-name
	 */
	static function form_name($cname,$name)
	{
		$name_parts = explode('[',str_replace(']','',$name));
		if (!empty($cname))
		{
			array_unshift($name_parts,$cname);
		}
		$form_name = array_shift($name_parts);
		if (count($name_parts))
		{
			$form_name .= '['.implode('][',$name_parts).']';
		}
		return $form_name;
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
	 * Checks if a widget is readonly:
	 * - readonly attribute set
	 * - $readonlys[__ALL__] set and $readonlys[$form_name] !== false
	 * - $readonlys[$form_name] evaluates to true
	 *
	 * @return boolean
	 */
	public function is_readonly($cname='')
	{
		$form_name = self::form_name($cname, $this->id);

		$readonly = $this->attrs['readonly'] || self::$request->readonlys[$form_name] ||
			isset(self::$request->readonlys['__ALL__']) && self::$request->readonlys[$form_name] !== false;

		error_log(__METHOD__."('$cname') this->id='$this->id' --> form_name='$form_name' returning ".array2string($readonly));

		return $readonly;
	}
	/**
	 * Validation errors from process_show and the extensions, should be set via etemplate::set_validation_error
	 *
	 * @public array form_name => message pairs
	 */
	static protected $validation_errors = array();

	/**
	 * Sets a validation error, to be displayed in the next exec
	 *
	 * @param string $name (complete) name of the widget causing the error
	 * @param string|boolean $error error-message already translated or false to reset all existing error for given name
	 * @param string $cname=null set it to '', if the name is already a form-name, defaults to self::$name_vars
	 */
	public static function set_validation_error($name,$error,$cname=null)
	{
		// not yet used: if (is_null($cname)) $cname = self::$name_vars;
		error_log(__METHOD__."('$name','$error','$cname')");

		if ($cname) $name = self::form_name($cname,$name);

		if ($error === false)
		{
			unset(self::$validation_errors[$name]);
		}
		else
		{
			if (self::$validation_errors[$name])
			{
				self::$validation_errors[$name] .= ', ';
			}
			self::$validation_errors[$name] .= $error;
		}
	}

	/**
	* Check if we have not ignored validation errors
	*
	* @param string $ignore_validation='' if not empty regular expression for validation-errors to ignore
	* @param string $cname=null name-prefix, which need to be ignored, default self::$name_vars
	* @return boolean true if there are not ignored validation errors, false otherwise
	*/
	public static function validation_errors($ignore_validation='',$cname='')
	{
		// not yet used: if (is_null($cname)) $cname = self::$name_vars;
		//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') validation_error="; _debug_array(self::$validation_errors);
		if (!$ignore_validation) return count(self::$validation_errors) > 0;

		foreach(self::$validation_errors as $name => $error)
		{
			if ($cname) $name = preg_replace('/^'.$cname.'\[([^\]]+)\](.*)$/','\\1\\2',$name);

			// treat $ignoare_validation only as regular expression, if it starts with a slash
			if ($ignore_validation[0] == '/' && !preg_match($ignore_validation,$name) ||
				$ignore_validation[0] != '/' && $ignore_validation != $name)
			{
				//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') name='$name' ($error) not ignored!!!</p>\n";
				return true;
			}
			//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') name='$name' ($error) ignored</p>\n";
		}
		return false;
	}
}

/**
 * Named widget having an own namespace: grid, *box
 */
class etemplate_widget_named extends etemplate_widget
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = array(
		'box' => ',cellpadding,cellspacing,keep',
		'hbox' => 'cellpadding,cellspacing,keep',
		'vbox' => 'cellpadding,cellspacing,keep',
		'groupbox' => 'cellpadding,cellspacing,keep',
		'grid' => null,	// not used
	);

	/**
	 * Validate input
	 *
	 * Reimplemented because grids can have an own namespace
	 *
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @param string $cname='' current namespace
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate(array $content, &$validated=array(), $cname = '')
	{
		if ($this->id) $cname = self::form_name($cname, $this->id);

		return parent::validate($content, $validated, $cname);
	}
}
// register class for layout widgets, which can have an own namespace
etemplate_widget::registerWidget('etemplate_widget_named', array('grid', 'box', 'hbox', 'vbox', 'groupbox'));

/**
 * Describtion widget
 *
 * Reimplemented to set legacy options
 */
class etemplate_widget_description extends etemplate_widget
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = 'bold-italic,link,activate_links,label_for,link_target,link_popup_size,link_title';
}
