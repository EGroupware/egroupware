<?php
/**
 * EGroupware - eTemplate widget baseclass
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-18 by RalfBecker@outdoor-training.de
 */

namespace EGroupware\Api\Etemplate;

use EGroupware\Api;
use XMLReader;
use ReflectionMethod;

/**
 * eTemplate widget baseclass
 *
 * @property-read string $required check $attrs['required'] ?? $attrs['needed']
 */
class Widget
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
	 * Names and defaults of boolean attributes to correctly cast them from XML in set_attrs
	 *
	 * @var array
	 */
	public $bool_attr_default = array(
		'disabled' => null,	// null = no default
		'statustext_html' => false,
		'statustextHtml' => false,
		'no_lang' => false,
		'noLang' => false,
		// strictly speeding only for input widgets, but server-side input-widgets have a validation method, but no shared parent
		'readonly' => null,	// null = no default
		'needed' => false,
		'required' => false,
	);

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
	 * @var Request
	 */
	static protected $request;

	/**
	 * JSON response object, if we run via a JSON request
	 *
	 * @var Api\Json\Response
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
	 * @param string|\XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws Api\Exception\WrongParameter
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
		if($this->id && is_array(self::$cont[$this->id] ?? null))
		{
			$old_cont = self::$cont;
			self::$cont = self::$cont[$this->id];
		}

		// read all attributes
		$this->set_attrs($reader);

		libxml_clear_errors();
		while(($ok=$reader->read()) && $reader->depth > $depth)
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->depth > $depth)
			{
				$this->children[] = self::factory($reader->name, $reader, $reader->getAttribute('id'));
			}
			// read eg. option content to "#text"
			if ($reader->nodeType == XMLReader::TEXT)
			{
				$this->attrs[(string)$reader->name] = (string)$reader->value;
			}
		}
		// give a clear indication where the template error is
		if (!$ok && ($error = libxml_get_last_error()))
		{
			$file = str_replace([EGW_SERVER_ROOT.'/', '//default/etemplates/'], '', $error->file);
			if(!$file && is_string($xml))
			{
				$file = $xml;
			}
			throw new \Exception("Error passing XML file '$file' line $error->line: $error->message");
		}

		// Reset content as we leave
		if (isset($old_cont))
		{
			self::$cont = $old_cont;
		}
	}

	/**
	 * Get XMLReader for given xml string
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on an element
	 * @throws Api\Exception\WrongParameter
	 */
	protected static function get_reader($xml)
	{
		if (is_a($xml, 'XMLReader'))
		{
			$reader = $xml;
		}
		else
		{
			libxml_use_internal_errors(true);
			libxml_clear_errors();
			$reader = new XMLReader();
			if (!$reader->XML($xml))
			{
				throw new Api\Exception\WrongParameter("Can't parse xml:\n$xml");
			}
			$reader->read();
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
	 * @param boolean $cloned =true true: object does NOT need to be cloned, false: to set attribute, set them in cloned object
	 * @return Template current object or clone, if any attribute was set
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
			if ($reader->name != 'id' && (!isset($template->attr[$reader->name]) || $template->attr[$reader->name] !== $reader->value))
			{
				if (!$cloned)
				{
					$template = clone($this);
					$cloned = true;	// only clone it once, otherwise we loose attributes!
				}
				// $reader->value is an object and therefore assigned by reference
				// this is important to not loose content when validating dynamic generated tabs as in settings!
				$template->attrs[$reader->name] = $value = $reader->value;

				// expand attributes values, otherwise eg. validation can not use attrs referencing to content
				if (!empty($value) && ($value[0] === '@' || strpos($value, '$cont') !== false))
				{
					$value = self::expand_name($value, null, null, null, null,
						isset(self::$cont) ? self::$cont : self::$request->content);
				}

				// split legacy options
				if ($legacy_options && $reader->name == 'options')
				{
					$legacy_options = explode(',', $legacy_options);
					foreach(self::csv_split($value, count($legacy_options)) as $n => $val)
					{
						if ($legacy_options[$n] && (string)$val !== '') $template->attrs[$legacy_options[$n]] = $val;
					}
				}
			}
		}

		// Add in anything in the modification array
		if (is_array(self::$request->modifications[$this->id] ?? null))
		{
			$this->attrs = array_merge($this->attrs,self::$request->modifications[$this->id]);
		}

		// cast boolean attributes to boolean and set their defaults, if they have one
		//$old_attrs = $this->attrs;
		foreach($this->bool_attr_default as $name => $default_value)
		{
			if (!isset($this->attrs[$name]))
			{
				if (isset($default_value)) $this->attrs[$name] = $default_value;
			}
			// cast to bool, only if NOT containing variables
			elseif (!is_bool($this->attrs[$name]) && strpos($this->attrs[$name], '@') === false &&
				strpos($this->attrs[$name], '$') === false)
			{
				// use PHP default evaluation, with the exception of "false" --> false
				$this->attrs[$name] = !(!$this->attrs[$name] || $this->attrs[$name] === 'false');
			}
		}
		// report modifications
		//if (($modifications = array_diff_assoc($this->attrs, $old_attrs))) error_log(__METHOD__."() id=$this->id, bool_modifications=".array2string($modifications).", old_values=".array2string(array_intersect_key($old_attrs, $this->bool_attr_default)).", bool_attr_default=".array2string($this->bool_attr_default));

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
	 * @param int $num =null in how many parts to split maximal, parts over this number end up (unseparated) in the last part
	 * @param string $delimiter =','
	 * @param string $enclosure ='"'
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
		$parts_renum = array_values($parts);	// renumber the parts (in case we had to concat them)

		if ($num > 0 && count($parts_renum) > $num)
		{
			$parts_renum[$num-1] = implode($delimiter,array_slice($parts_renum,$num-1,count($parts_renum)-$num+1));
			$parts_renum = array_slice($parts_renum,0,$num);
		}
		return $parts_renum;
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
	 * eg. class Etemplate\Widget\Template does NOT need to be registered.
	 *
	 * @param string $class
	 * @param string|array $widgets
	 */
	public static function registerWidget($class, $widgets)
	{
		if (!is_subclass_of($class, __CLASS__))
		{
			throw new Api\Exception\WrongParameter(__METHOD__."('$class', ".array2string($widgets).") $class is no subclass of ".__CLASS__.'!');
		}
		foreach((array)$widgets as $widget)
		{
			self::$widget_registry[$widget] = $class;
		}
	}

	/**
	 * Try to discover all widgets, as names don't always match tags (eg:
	 * listbox is in menupopup)
	 *
	 * Look through filesystem for widgets, then process the hook
	 * 'etemplate2_register_widgets', which may return a list of widget class
	 * names.
	 *
	 * The list is cached for an hour, to avoid rescanning the filesystem but
	 * also to make sure the list is always available, even when calling static
	 * functions of widgets.
	 */
	public static function scanForWidgets()
	{
		$widget_registry = Api\Cache::getInstance('etemplate', 'widget_registry');

		if (!$widget_registry)	// not in instance cache --> rescan from filesystem
		{
			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/Widget')) as $path)
			{
				if(strpos($path, 'tests/') !== FALSE)
				{
					continue;
				}
				if(substr($path, -4) == '.php')
				{
					try
					{
						include_once($path);
					}
					catch(\Exception $e)
					{
						error_log($e->getMessage());
					}
				}
			}

			// Use hook to load custom widgets from other apps
			$widgets = Api\Hooks::process('etemplate2_register_widgets',array(),true);
			foreach($widgets as $list)
			{
				if (is_array($list))
				{
					foreach($list as $class)
					{
						try
						{
							class_exists($class);	// trigger autoloader
						}
						catch(\Exception $e)
						{
							error_log($e->getMessage());
						}
					}
				}
			}
			if (self::$widget_registry['htmlarea'] === Api\Etemplate\Widget\HtmlArea::class)
			Api\Cache::setInstance('etemplate', 'widget_registry', self::$widget_registry, 3600);
			else error_log(__METHOD__."() wrong class for Htmlarea: ".function_backtrace ());
		}
		else
		{
			self::$widget_registry = $widget_registry;
		}
		return self::$widget_registry;
	}

	/**
	 * Factory method to construct all widgets
	 *
	 * @param string $type
	 * @param string|XMLReader $xml
	 * @param string $id =null
	 */
	public static function factory($type, $xml, $id=null)
	{
		$class_name =& self::$widget_registry[$type];

		if (!isset($class_name))
		{
			$exploded = explode('-', $type);
			$basetype = $exploded[0] == "et2" ? $exploded[1] : $exploded[0];
			if(//dont think this is used: !class_exists($class_name = 'etemplate_widget_'.str_replace('-','_',$type)) &&
				!class_exists($class_name = __CLASS__ . '\\' . ucfirst($basetype)) &&
				// widgets supplied by application in class ${app}_widget_etemplate or ${app}_${subtype}_widget_etemplate
				!(isset($GLOBALS['egw_info']['apps'][$basetype]) &&
					(class_exists($class_name = str_replace('-', '_', $type) . '_etemplate_widget') ||
						class_exists($class_name = $basetype . '_etemplate_widget'))))
			{
				// Try for base type, it's probably better than the root
				if(isset(self::$widget_registry[$basetype]) && self::$widget_registry[$basetype] !== $class_name)
				{
					$class_name = self::$widget_registry[$basetype];
				}
				// Look for old widgets that were adapted but not renamed
				else if (class_exists($class_name = $basetype.'_widget') && in_array(__CLASS__, class_parents($class_name)))
				{
					// Side-effects set $class_name
					//error_log("Ported old widget: $class_name");
				}
				else
				{
					// Fall back to widget class, we can not ignore it, as the widget may contain other widgets
					$class_name = __CLASS__;
				}
			}
		}
		else if (!class_exists($class_name))
		{
			// Class in widget registry, but not loaded
			// Try for a base class
			$subtypes = explode('-',$type);
			$basetype = $subtypes[0];
			$class_name = __CLASS__.'\\'.implode('\\',array_map('ucfirst',$subtypes));
			if(!class_exists($class_name) && self::$widget_registry[$basetype] && self::$widget_registry[$basetype] != $class_name)
			{
				// Try for base type, it's probably better than the root
				$class_name = self::$widget_registry[$basetype];
			}
			if(!class_exists($class_name))
			{
				// Fall back to widget class, we can not ignore it, as the widget may contain other widgets
				$class_name = __CLASS__;
			}
		}

		if(!$xml)
		{
			if (empty($type)) $type = 'widget';
			$xml = "<$type id=\"$id\"/>";
		}
		//error_log(__METHOD__."('$type', ..., '$id') using $class_name");

		// currently only overlays can contain templates, other widgets can only reference to templates via id
		if ($type == 'template' && $id && ($template = Widget\Template::instance($id)))
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
	 * @param string $type =null
	 * @return Widget|NULL
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
	 * @return array of Widget or empty array
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
			$elements = array_merge($elements, $child->getElementsByType($type));
		}
		return $elements;
	}

	/**
	 * Run a given method on all children
	 *
	 * Default implementation only calls method on itself and run on all children
	 *
	 * @param string|callable $method_name or function($cname, $expand, $widget)
	 * @param array $params =array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		// maintain $expand array name-expansion
		$cname = $params[0];
		$expand =& $params[1];
		if (isset($expand['cname']) && $expand['cname'] !== $cname)
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname);
			$expand['cname'] = $cname;
		}
		if ($respect_disabled && ($disabled = isset($this->attrs['disabled']) && self::check_disabled($this->attrs['disabled'], $expand)))
		{
			//error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'=".array2string($disabled).": NOT running");
			return;
		}
		if (is_string($method_name) && method_exists($this, $method_name))
		{
			// Some parameter checking to avoid fatal errors
			$call = true;
			$method = new ReflectionMethod($this, $method_name);
			foreach($method->getParameters() as $index => $param)
			{
				if(!$param->isOptional() && !array_key_exists($index, $params))
				{
					error_log("Missing required parameter {$param->getPosition()}: {$param->getName()}");
					$call = false;
				}
				// Check to see if method wants an array, and we're providing it
				$paramType = $param->getType();
				if(!$paramType)
				{
					continue;
				}
				$types = $paramType instanceof \ReflectionUnionType
					? $paramType->getTypes()
					: [$paramType];
				if(in_array('array', array_map(static function(\ReflectionNamedType $t)
				{
					return $t->getName();
				}, $types)) && !is_array($params[$index]))
				{
					error_log("$method_name expects an array for {$param->getPosition()}: {$param->getName()}");
					$params[$index] = (array)$params[$index];
				}
			}
			if($call) call_user_func_array(array($this, $method_name), $params);
		}
		// allow calling with a function or closure --> call it with widget as first param
		elseif (is_callable($method_name))
		{
			$params[2] = $this;
			call_user_func_array($method_name, $params);
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
	 * @param Widget& $child Widget to check & expand if needed
	 * @param array& $expand Expansion array
	 */
	protected function expand_widget(Widget &$child, array &$expand)
	{
		$attrs = $child->attrs;
		unset($attrs['type']);
		foreach($attrs as $name => &$value)
		{
			if(!is_string($value)) continue;
			$value = self::expand_name($value, $expand['c'] ?? null, $expand['row'] ?? null, $expand['c_'] ?? null, $expand['row_'] ?? null, $expand['cont'] ?? []);
		}
		if (!empty($attrs['attributes']))
		{
			$attrs = array_merge($attrs, $attrs['attributes']);
		}
		if (!empty($child->attrs['type']) && (strpos($child->attrs['type'], '@') !== false || strpos($child->attrs['type'], '$') !== false))
		{
			$type = self::expand_name($child->attrs['type'],$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
			$id = self::expand_name($child->id,$expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
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
		if(($not = $disabled[0] == '!'))
		{
			$disabled = substr($disabled, 1);
		}
		list($value, $check) = $vals = explode('=', $disabled);

		// use expand_name to be able to use @ or $
		$val = self::expand_name($value, $expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
		$check_val = self::expand_name($check, $expand['c'], $expand['row'], $expand['c_'], $expand['row_'], $expand['cont']);
		$result = count($vals) == 1 ?
			boolval($val) :
			($check_val[0] == '/' ? preg_match($check_val, $val) : $val == $check_val);
		if($not)
		{
			$result = !$result;
		}

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
	 * "Row${row}[length]" instead. Only one indirection is allowed in a string by php !!!
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
	 * @param int $c_ =0 is the value of the previous template-inclusion,
	 * 	eg. the column-headers in the eTemplate-editor are templates itself,
	 * 	to show the column-name in the header you can not use $col as it will
	 * 	be constant as it is always the same col in the header-template,
	 * 	what you want is the value of the previous template-inclusion.
	 * @param int $row_ =0 is the value of the previous template-inclusion,
	 * @param array $cont =array() content of the template, you might use it to generate button-names with id values in it:
	 * 	"del[$cont[id]]" expands to "del[123]" if $cont = array('id' => 123)
	 * @return string the expanded name
	 */
	protected static function expand_name($name,$c,$row,$c_=0,$row_=0,$cont=array())
	{
		$is_index_in_content = !empty($name) && $name[0] == '@';
		if (($pos_var=strpos($name,'$')) !== false)
		{
			if (!$cont)
			{
				$cont = array();
			}
			if (!is_numeric($c)) $c = self::chrs2num($c);
			$col = self::num2chrs($c-1);	// $c-1 to get: 0:'@', 1:'A', ...
			if (is_numeric($c_)) $col_ = self::num2chrs($c_-1);
			$row_cont = $cont[$row] ?? null;
			$col_row_cont = $cont[$col.$row] ?? null;

			$er = error_reporting(0);
			try {
				eval('$name = "' . str_replace('"', '\\"', $name) . '";');
			}
			catch(\Throwable $e) {
				error_log(__METHOD__."() eval('\$name = \"".str_replace('"', '\\"', $name) . "\";)");
				_egw_log_exception($e);
			}
			error_reporting($er);
			unset($col_, $row_, $row_cont, $col_row_cont);	// quieten IDE warning about used vars, they might be used in above eval!
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
		return $name;
	}

	/**
	 * generates column-names from index: 'A', 'B', ..., 'AA', 'AB', ..., 'ZZ' (not more!)
	 *
	 * @param string $chrs column letter to generate name from 'A' => 1
	 * @return int the index
	 */
	static function chrs2num($chrs)
	{
		if (empty($chrs)) return 0;
		$min = ord('A');
		$max = ord('Z') - $min + 1;
		$num = 1+ord($chrs[0])-$min;
		if (strlen($chrs) > 1)
		{
			$num *= 1 + $max - $min;
			$num += 1+ord($chrs[1])-$min;
		}
		return $num;
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
		} else $chrs = '';
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
	 * Implement some (readonly) attributes
	 *
	 * @param $name
	 * @return void
	 */
	public function __get($name)
	{
		switch($name)
		{
			case 'required':
				return $this->attrs['required'] ?? $this->attrs['needed'] ?? false;
		}
	}

	/**
	 * Convert widget (incl. children) to xml
	 *
	 * @param string $indent =''
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
	 * @param array $expand =null values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @return string complete form-name
	 */
	static function form_name($cname,$name,array $expand=null)
	{
		if ($expand && !empty($name))
		{
			$name = self::expand_name($name, $expand['c'] ?? null, $expand['row'] ?? null, $expand['c_'] ?? null, $expand['row_'] ?? null, $expand['cont'] ?? []);
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
	 * @param string $_idx the index, may contain sub-indices like a[b], see example below
	 * @param boolean $reference_into default False, if True none-existing sub-arrays/-indices get created to be returned as referenz, else False is returned
	 * @param bool $skip_empty returns false if $idx is not present in $arr
	 * @return mixed reference to $arr[$idx] or null if $idx is not set and not $reference_into
	 */
	static function &get_array(&$arr,$_idx,$reference_into=False,$skip_empty=False)
	{
		if (!is_array($arr))
		{
			throw new Api\Exception\AssertionFailed(__METHOD__."(\$arr,'$_idx',$reference_into,$skip_empty) \$arr is no array!");
		}
		if (is_object($_idx))
		{
			$ret = false;	// given an error in php5.2
			return $ret;
		}

		// Make sure none of these are left
		$idx = str_replace(array('&#x5B;','&#x5D;'), array('[',']'), $_idx);

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
				$ret = null;
				return $ret;
			}
			if($skip_empty && (!is_array($pos) || !isset($pos[$idx])))
			{
				$ret = null;
				return $ret;
			}
			$pos = &$pos[$idx];
		}
		return $pos;
	}

	/**
	 * return a reference to $arr[$idx]
	 *
	 * This works for non-trival indexes like 'a[b][c]' too: it returns &$arr[a][b][c]
	 * $sub = get_array($arr,'a[b]'); $sub = 'c'; is equivalent to $arr['a']['b'] = 'c';
	 *
	 * @param array& $_arr the array to search, referenz as a referenz gets returned
	 * @param string $_idx the index, may contain sub-indices like a[b], see example below
	 * @param mixed $_value value to set
	 */
	static function set_array(&$_arr, $_idx, $_value)
	{
		$ref =& self::get_array($_arr, $_idx, true);
		if (true) $ref = $_value;
	}

	/**
	 * Checks if a widget is readonly:
	 * 1. $readonlys set to true for $form_name:
	 *    a) $readonlys[$form_name] is set to true (flat array)
	 *    b) self::get_array($readonlys, $form_name) is set to true (hierarchical)
	 * 2. ($readonlys[__ALL__] or widget readonly attribute) is true AND NOT $readonlys set to false for $form_name
	 *
	 * @param string $cname =''
	 * @param string $form_name =null form_name, to not calculate him again
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
		// readonlys can either be set / used as flat array with complete form-name, hierarchical
		$readonlys = self::$request->readonlys[$form_name] ?? self::get_array(self::$request->readonlys,$form_name);

		$readonly = $readonlys === true ||
			// exception to __ALL__ or readonly="true" attribute by setting $readonlys[$from_name] === false
			(!empty($this->attrs['readonly']) || isset(self::$request->readonlys['__ALL__'])) && $readonlys !== false;

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
	 * @param string $cname =null set it to '', if the name is already a form-name, defaults to self::$name_vars
	 */
	public static function set_validation_error($name,$error,$cname=null)
	{
		// not yet used: if (is_null($cname)) $cname = self::$name_vars;
		//error_log(__METHOD__."('$name','$error','$cname') ".function_backtrace());

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
	* @param string $ignore_validation ='' if not empty regular expression for validation-errors to ignore
	* @param string $cname =null name-prefix, which need to be ignored, default self::$name_vars
	* @return boolean true if there are not ignored validation errors, false otherwise
	*/
	public static function validation_errors($ignore_validation='',$cname='')
	{
		// not yet used: if (is_null($cname)) $cname = self::$name_vars;
		//echo "<p>uietemplate::validation_errors('$ignore_validation','$cname') validation_error="; _debug_array(self::$validation_errors);
		if (!$ignore_validation) return count(self::$validation_errors) > 0;

		foreach(array_values(self::$validation_errors) as $name)
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
		if (!isset(self::$request))
		{
			throw new \Exception(__METHOD__."('$name', '$attr', ".json_encode($val)." called before instanciating Api\Etemplate!");
		}
		//error_log(__METHOD__."('$name', '$attr', ...) request=".get_class(self::$request).", response=".get_class(self::$response).function_backtrace());
		$ref =& self::$request->modifications[$name][$attr];
		if(self::$request && self::$response)
		{
			// In an AJAX response - automatically add
			self::$response->generic('assign',array(
				'etemplate_exec_id' => self::$request->id(),
				'id' => $name,
				'key' => $attr,
				'value' => $val
			));
			// Don't delete it
			self::$request->unset_to_process('');
			//error_log(__METHOD__."('$name', '$attr', ...) ".function_backtrace());
		}
		if (!is_null($val)) $ref = $val;

		//error_log(__METHOD__."('$name', '$attr', ".array2string($val).')');
		return $ref;
	}

	/**
	 *  disables all cells with name == $name
	 *
	 * @param sting $name cell-name
	 * @param boolean $disabled =true disable or enable a cell, default true=disable
	 * @return reference to attribute
	 */
	public function disableElement($name,$disabled=True)
	{
		return self::setElementAttribute($name, 'disabled', $disabled);
	}
}

// Scan for widget classes and cache for 1 hour
Widget::scanForWidgets();