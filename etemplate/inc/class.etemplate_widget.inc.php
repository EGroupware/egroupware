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

/**
 * eTemplate widget baseclass
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
	 * Constructor
	 *
	 * @param string|XMLReader $xml string with xml or XMLReader positioned on the element to construct
	 * @throws egw_exception_wrong_parameter
	 */
	public function __construct($xml)
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
		$this->type = $reader->name;
		$depth = $reader->depth;

		// read all attributes
		while($reader->moveToNextAttribute())
		{
			if ($reader->name == 'id')
			{
				$this->id = $reader->value;
			}
			else
			{
				$this->attrs[$reader->name] = $reader->value;
			}
		}

		while($reader->read() && $reader->depth > $depth)
		{
			if ($reader->nodeType == XMLReader::ELEMENT && $reader->depth > $depth)
			{
				$this->children[] = self::factory($reader->name, $reader, $reader->getAttribute('id'));
			}
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
		static $type2class_name = array();

		$class_name =& $type2class_name[$type];

		if (!isset($class_name))
		{
			list($basetype) = explode('-',$type);
			if (!class_exists($class_name = 'etemplate_'.str_replace('-','_',$type).'_widget') &&
				!class_exists($class_name = 'etemplate_'.str_replace('-','_',$basetype).'_widget'))
			{
				// default to widget class, we can not ignore it, as the widget may contain other widgets
				$class_name = 'etemplate_widget';
			}
		}
		// currently only overlays can contain templates, other widgets can only reference to templates via id
		if ($type == 'template' && $id && ($template = etemplate_template_widget::read($id)))
		{
			return $template;
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
	 * Validate input of a widget
	 *
	 * @param array $content
	 * @param string $cname='' current namespace
	 * @return mixed
	 */
	public function validate(array $content, $cname = '')
	{

	}

	/**
	 * Convert object to string
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->type.'#'.$this->id;
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
			echo ' '.$name.'="'.htmlspecialchars($value).'"';
		}
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
}
