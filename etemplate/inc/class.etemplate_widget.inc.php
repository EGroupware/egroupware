<?php
/**
 * EGroupware - eTemplate widget baseclass
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-13 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate widget baseclass
 *
 * @todo text content, eg. the styles of a template are not parsed, thought they are not used here either
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
	 * Namespaced content array, used when trying to initialize
	 *
	 * This is pretty much a global static variable, used when reading
	 * a template with the content set.  This allows variable expansion
	 * in the constructor.
	 *
	 * @protected $cont
	 */
	static protected $cont = null;

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

		// Update content?
		if(self::$cont == null)
			self::$cont = is_array(self::$request->content) ? self::$request->content : array();
		if($this->id && is_array(self::$cont[$this->id]))
		{
			$old_cont = self::$cont;
			self::$cont = self::$cont[$this->id];
		}

		// read all attributes
		$this->set_attrs($reader);

		while($reader->read() && $reader->depth > $depth)
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->depth > $depth)
			{
				$this->children[] = self::factory($reader->name, $reader, $reader->getAttribute('id'));
			}
		}

		// Reset content as we leave
		if($old_cont) {
			self::$cont = $old_cont;
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
				if (!$cloned)
				{
					$template = clone($this);
					$cloned = true;	// only clone it once, otherwise we loose attributes!
				}
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

		// Add in anything in the modification array
		if(is_array(self::$request->modifications[$this->id]))
		{
			$this->attrs = array_merge($this->attrs,self::$request->modifications[$this->id]);
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
		if (!is_subclass_of($class, __CLASS__))
		{
			throw new egw_exception_wrong_parameter(__METHOD__."('$class', ".array2string($widgets).") $class is no subclass of ".__CLASS__.'!');
		}
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
				// Try for base type, it's probably better than the root
				if(self::$widget_registry[$basetype] && self::$widget_registry[$basetype] != $class_name)
				{
					$class_name = self::$widget_registry[$basetype];
				}
				else
				{
					// Fall back to widget class, we can not ignore it, as the widget may contain other widgets
					$class_name = 'etemplate_widget';
				}
			}
		}

		if(!$xml)
		{
			if (empty($type)) $type = 'widget';
			$xml = "<$type id='$id'/>";
		}
		//error_log(__METHOD__."('$type', ..., '$id') using $class_name");

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
	 * Iterate over children to find the one with the given type
	 *
	 * @param string $type
	 * @return etemplate_widget or NULL
	 */
	public function getElementsByType($type)
	{
		$elements = array();
		foreach($this->children as $child)
		{
			if ($child->type === $type)
			{
				$elements[] = $child;
			}
			$elements += $child->getElementsByType($type, $subclass_ok);
		}
		return $elements;
	}

	/**
	 * Run a given method on all children
	 *
	 * Default implementation only calls method on itself and run on all children
	 *
	 * @param string $method_name
	 * @param array $params=array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled=false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		// maintain $expand array name-expansion
		$cname = $params[0];
		$expand =& $params[1];
		if ($expand['cname'] && $expand['cname'] !== $cname)
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname);
			$expand['cname'] = $cname;
		}
		if ($respect_disabled && ($disabled = $this->attrs['disabled'] && self::check_disabled($this->attrs['disabled'], $expand)))
		{
			error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'=".array2string($disabled).": NOT running");
			return;
		}
		if (method_exists($this, $method_name))
		{
			// Some parameter checking to avoid fatal errors
			$call = true;
			$method = new ReflectionMethod($this, $method_name);
			foreach($method->getParameters() as $index => $param)
			{
				if(!$param->isOptional() && !array_key_exists($index,$params))
				{
					error_log("Missing required parameter {$param->getPosition()}: {$param->getName()}");
					$call = false;
				}
				if($param->isArray() && !is_array($params[$index]))
				{
					error_log("$method_name expects an array for {$param->getPosition()}: {$param->getName()}");
					$params[$index] = (array)$params[$index];
				}
			}
			if($call) call_user_func_array(array($this, $method_name), $params);
		}
		foreach($this->children as $child)
		{
			// If type has something that can be expanded, we need to expand it so the correct method is run
			$this->expand_widget($child, $expand);
			$child->run($method_name, $params, $respect_disabled);
		}
	}

	/**
	 * If a widget's type is expandable, we need to expand it to make sure we have
	 * the right class before running the method on it
	 *
	 * @param etemplate_widget $child Widget to check & expand if needed
	 * @param Array $expand Expansion array
	 */
	protected function expand_widget(etemplate_widget &$child, Array &$expand)
	{
		if(strpos($child->attrs['type'], '@') !== false || strpos($child->attrs['type'], '$') !== false)
		{
			$type = self::expand_name($child->attrs['type'],$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
			$id = self::expand_name($child->id,$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
			$attrs = $child->attrs;
			unset($attrs['type']);
			$expanded_child = self::factory($type, false,$id);
			$expanded_child->id = $id;
			$expanded_child->type = $type;
			$expanded_child->attrs = $attrs + array('type' => $type);
			$child = $expanded_child;
		}
	}

	/**
	 * Checks if a grid row or column is disabled
	 *
	 * Expression: [!][@]val[=[@]check]
	 * Parts in square brackets are optional, a ! negates the expression, @val evaluates to $content['val']
	 * if no =check is given all set non-empty and non-zero strings are true (standard php behavior)
	 *
	 * @param string $disabled expression to check, eg. "!@var" for !$content['var']
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @return boolean true if the row/col is disabled or false if not
	 */
	protected static function check_disabled($disabled, array $expand)
	{
		if ($not = $disabled[0] == '!')
		{
			$disabled = substr($disabled,1);
		}
		list($val,$check_val) = $vals = explode('=',$disabled);

		// use expand_name to be able to use @ or $
		$val = self::expand_name($val,$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
		$check_val = self::expand_name($check_val,$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
		$result = count($vals) == 1 ? $val != '' : ($check_val[0] == '/' ? preg_match($check_val,$val) : $val == $check_val);
		if ($not) $result = !$result;

		//error_log(__METHOD__."('".($not?'!':'')."$disabled' = '$val' ".(count($vals) == 1 ? '' : ($not?'!':'=')."= '$check_val'")." = ".($result?'True':'False'));
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
	 * @param string $name the name to expand
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
	protected static function expand_name($name,$c,$row,$c_='',$row_='',$cont='')
	{
		$is_index_in_content = $name[0] == '@';
		if (($pos_var=strpos($name,'$')) !== false)
		{
			if (!$cont)
			{
				$cont = array();
			}
			if (!is_numeric($c)) $c = boetemplate::chrs2num($c);
			$col = self::num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
			$col_ = self::num2chrs($c_-1);
			$row_cont = $cont[$row];
			$col_row_cont = $cont[$col.$row];

			/* RB: dont think any of this is needed in eTemplate2, as this escaping probably needs to be done on clientside anyway

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
						$val = "'".str_replace(array("'",'"','[',']'),array('\\\'','&quot;','&#x5B;','&#x5D;'),$val)."'";
					}
					$value = '&#x5B; '.implode(', ',$value).' &#x5D;';
					$name = str_replace("'".$matches[1]."'",$value,$name);
				}
				else
				{
					$value = str_replace(array("'",'"','[',']'),array('\\\'','&quot;','&#x5B;','&#x5D;'),$value);
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
			}*/
			eval('$name = "'.str_replace('"','\\"',$name).'";');
		}
		if ($is_index_in_content)
		{
			if ($name[1] == '@' && is_array(self::$request->content))
			{
				$name = self::get_array(self::$request->content,substr($name,2));
			}
			elseif(is_array($cont))
			{
				$name = self::get_array($cont,substr($name,1));
			}
			else
			{
				// Content not set expands to ''
				$name = '';
			}
		}
		// RB: not sure why this business with entity encoding for square brakets, it messes up validation
		//$name = str_replace(array('[',']'),array('&#x5B;','&#x5D;'),$name);
		return $name;
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
	 * Convert object to string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return '['.get_class($this).'] ' .
			$this->type.($this->attrs['type'] && $this->attrs['type'] != $this->type ? '('.$this->attrs['type'].')' : '').'#'.$this->id;
	}

	/**
	 * When cloning a widget, we also clone children
	 */
	public function __clone()
	{
		foreach($this->children as $child_num => $child) {
			$this->children[$child_num] = clone $child;
		}
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
	 * @param array $expand=null values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @return string complete form-name
	 */
	static function form_name($cname,$name,array $expand=null)
	{
		if ($expand && !empty($name))
		{
			$name = self::expand_name($name, $expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
		}
		if (count($name_parts = explode('[', $name, 2)) > 1)
		{
			$name_parts = array_merge(array($name_parts[0]), explode('][', substr($name_parts[1],0,-1)));
		}
		if (!empty($cname))
		{
			array_unshift($name_parts,$cname);
		}
		$form_name = array_shift($name_parts);
		if (count($name_parts))
		{
			// RB: not sure why this business with entity encoding for square brakets, it messes up validation
			//$form_name .= '&#x5B;'.implode('&#x5D;&#x5B;',$name_parts).'&#x5D;';
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
	 * @return mixed reference to $arr[$idx] or null if $idx is not set and not $reference_into
	 */
	static function &get_array(&$arr,$idx,$reference_into=False,$skip_empty=False)
	{
		if (!is_array($arr))
		{
			throw new egw_exception_assertion_failed(__METHOD__."(\$arr,'$idx',$reference_into,$skip_empty) \$arr is no array!");
		}
		if (is_object($idx)) return false;	// given an error in php5.2

		// Make sure none of these are left
		$idx = str_replace(array('&#x5B;','&#x5D;'),array('[',']'),$idx);

		// Handle things expecting arrays - ends in []
		if(substr($idx,-2) == "[]")
		{
			$idx = substr($idx,0,-2);
		}

		if (count($idxs = explode('[', $idx, 2)) > 1)
		{
			$idxs = array_merge(array($idxs[0]), explode('][', substr($idxs[1],0,-1)));
		}
		$pos = &$arr;
		foreach($idxs as $idx)
		{
			if (!is_array($pos) && (!$reference_into || $reference_into && isset($pos)))
			{
				//if ($reference_into) error_log(__METHOD__."(".(strlen($s=array2string($arr))>512?substr($s,0,512).'...':$s).", '$idx', ".array2string($reference_into).", ".array2string($skip_empty).") ".function_backtrace());
				return null;
			}
			if($skip_empty && (!is_array($pos) || !isset($pos[$idx]))) return null;
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
	 * @param string $cname=''
	 * @param string $form_name=null form_name, to not calculate him again
	 * @return boolean
	 */
	public function is_readonly($cname='', $form_name=null)
	{
		if (!isset($form_name))
		{
			$expand = array(
				'cont' => self::get_array(self::$request->content, $cname),
			);
			$form_name = self::form_name($cname, $this->id, $expand);
		}
		$readonly = $this->attrs['readonly'] || self::$request->readonlys[$form_name] ||
			self::get_array(self::$request->readonlys,$form_name) === true ||
			isset(self::$request->readonlys['__ALL__']) && (
				// Exceptions to all
				self::$request->readonlys[$form_name] !== false &&
				self::get_array(self::$request->readonlys,$form_name) !== false
			);

		//error_log(__METHOD__."('$cname') this->id='$this->id' --> form_name='$form_name': attrs[readonly]=".array2string($this->attrs['readonly']).", readonlys['$form_name']=".array2string(self::$request->readonlys[$form_name]).", readonlys[$form_name]=".array2string(self::get_array(self::$request->readonlys,$form_name)).", readonlys['__ALL__']=".array2string(self::$request->readonlys['__ALL__'])." returning ".array2string($readonly));
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
		error_log(__METHOD__."('$name','$error','$cname') ".function_backtrace());

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

	/**
	 * Returns reference to an attribute in a named cell
	 *
	 * Currently we always return a reference to an not set value, unless it was set before.
	 * We do not return a reference to the actual cell, as it get's contructed on client-side!
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @return mixed reference to attribute, usually NULL
	 */
	public function &getElementAttribute($name, $attr)
	{
		//error_log(__METHOD__."('$name', '$attr')");
		return self::$request->modifications[$name][$attr];
	}

	/**
	 * Set an attribute in a named cell if val is not NULL else return the attribute
	 *
	 * Can be called static, in which case it only sets modifications.
	 *
	 * @param string $name cell-name
	 * @param string $attr attribute-name
	 * @param mixed $val if not NULL sets attribute else returns it
	 * @return reference to attribute
	 */
	public static function &setElementAttribute($name,$attr,$val)
	{
		error_log(__METHOD__."('$name', '$attr', ...) request=".get_class(self::$request).", response=".get_class(self::$response).function_backtrace());
		$ref =& self::$request->modifications[$name][$attr];
		if(self::$request && self::$response && (!isset($this) || $val != $this->attrs[$attr]))
		{
			// In an AJAX response - automatically add
			self::$response->generic('assign',array(
				'etemplate_exec_id' => self::$request->id(),
				'id' => $name,
				'key' => $attr,
				'value' => $val
			));
			error_log(__METHOD__."('$name', '$attr', ...) ".function_backtrace());
		}
		if (isset($this)) $this->attrs[$attr] = $val;
		if (!is_null($val)) $ref = $val;

		//error_log(__METHOD__."('$name', '$attr', ".array2string($val).')');
		return $ref;
	}

	/**
	 *  disables all cells with name == $name
	 *
	 * @param sting $name cell-name
	 * @param boolean $disabled=true disable or enable a cell, default true=disable
	 * @return reference to attribute
	 */
	public function disableElement($name,$disabled=True)
	{
		return self::setElementAttribute($name, 'disabled', $disabled);
	}
}

/**
 * *box widgets having an own namespace
 */
class etemplate_widget_box extends etemplate_widget
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
	);

	/**
	 * Run a given method on all children
	 *
	 * Reimplemented because grids and boxes can have an own namespace
	 *
	 * @param string $method_name
	 * @param array $params=array('') parameter(s) first parameter has to be cname!
	 * @param boolean $respect_disabled=false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$cname =& $params[0];
		$expand =& $params[1];
		$old_cname = $params[0];
		$old_expand = $params[1];

		if ($this->id) $cname = self::form_name($cname, $this->id, $params[1]);
		if ($expand['cname'] !== $cname && $cname)
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname);
			$expand['cname'] = $cname;
		}
		if ($respect_disabled && ($disabled = $this->attrs['disabled'] && self::check_disabled($this->attrs['disabled'], $expand)))
		{
			error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'=".array2string($disabled).": NOT running");
			return;
		}
		if (method_exists($this, $method_name))
		{
			call_user_func_array(array($this, $method_name), $params);
		}

		// Expand children
		for($n = 0; ; ++$n)
		{
			if (isset($this->children[$n]))
			{
				$child =& $this->children[$n];
				// If type has something that can be expanded, we need to expand it so the correct method is run
				$this->expand_widget($child, $expand);
			}
			// check if we need to autorepeat last row ($child)
			elseif (isset($child) && $child->type == 'box' && $this->need_autorepeat($child, $cname, $expand))
			{
				// Set row for repeating
				$expand['row'] = $n;
				// not breaking repeats last row/column ($child)
			}
			else
			{
				break;
			}
			//error_log('Running ' . $method_name . ' on child ' . $n . '(' . $child . ') ['.$expand['row'] . ','.$expand['c'] . ']');
			$disabled = $child->run($method_name, $params, $respect_disabled, $columns_disabled) === false;
		}

		$params[0] = $old_cname;
		$params[1] = $old_expand;

		return true;
	}

	/**
	 * Check if a box child needs autorepeating, because still content left
	 *
	 * We only check passed widget and direct children.
	 *
	 * @param string $cname
	 * @param array $expand
	 */
	private function need_autorepeat(etemplate_widget &$widget, $cname, array $expand)
	{
		foreach(array($widget) + $widget->children as $check_widget)
		{
			$pat = $check_widget->id;
			while(($pat = strstr($pat, '$')))
			{
				$pat = substr($pat,$pat[1] == '{' ? 2 : 1);

				$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' || substr($pat,0,4) == 'row_');

				if ($Ok && ($value = self::get_array(self::$request->content,
					$fname=self::form_name($cname, $check_widget->id, $expand))) !== false && isset($value))
				{
					error_log(__METHOD__."($widget,$cname) $this autorepeating row $expand[row] because of $check_widget->id = '$fname' is ".array2string($value));
					return true;
				}
			}
		}

		return false;
	}
}
// register class for layout widgets, which can have an own namespace
etemplate_widget::registerWidget('etemplate_widget_box', array('box', 'hbox', 'vbox', 'groupbox'));

/**
 * Description widget
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
