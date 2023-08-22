<?php
/**
 * EGroupware - eTemplate serverside base widget, to define new widgets using a transformation out of existing widgets
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
 * eTemplate serverside base widget, to define new widgets using a transformation out of existing widgets
 */
abstract class Transformer extends Etemplate\Widget
{
	/**
	 * Array with a transformation description, based on attributes to modify.
	 *
	 * Exampels:
	 *
	 * * 'type' => array('some' => 'other')
	 *   if 'type' attribute equals 'some' replace it with 'other'
	 *
	 * * 'type' => array('some' => array('type' => 'other', 'options' => 'otheroption')
	 *   same as above, but additonally set 'options' attr to 'otheroption'
	 *
	 * --> leaf element is the action, if previous filters are matched:
	 *     - if leaf is scalar, it just replaces the previous filter value
	 *     - if leaf is an array, it contains assignments for (multiple) attributes: attr => value pairs
	 *
	 * * 'type' => array(
	 *      'some' => array(...),
	 *      'other' => array(...),
	 *      '__default__' => array(...),
	 *   )
	 *   it's possible to have a list of filters with actions to run, plus a '__default__' which matches all not explicitly named values
	 *
	 * * 'value' => array('__callback__' => 'app.class.method' || 'class::method' || 'method')
	 *   run value through a *serverside* callback, eg. reading an entry based on it's given id
	 *   callback signature: mixed function(mixed $attr[, array $attrs])
	 *
	 * * 'value' => array('__js__' => 'function(value) { return value+5; }')
	 *   run value through a *clientside* callback running in the context of the widget
	 *
	 * * 'name' => '@name[@options]'
	 *   replace value of 'name' attribute with itself (@name) plus value of options in square brackets
	 * * 'value' => '@value[@options]'
	 *   replace value array with value for key taken from value of options attribute
	 *
	 * --> attribute name prefixed with @ sign means value of given attribute
	 *
	 * @var array
	 */
	protected static $transformation = array();

	/**
	 * Switching debug messages to error_log on/off
	 *
	 * @var boolean
	 */
	const DEBUG = false;

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname, array $expand=array())
	{
		$attrs = $this->attrs;
		$form_name = self::form_name($cname, $this->id);
		if (empty($this->id))
		{
			error_log(__METHOD__."() $this has no id!");
			return;
		}
		$attrs['value'] = $value =& self::get_array(self::$request->content, $form_name, false, true);
		$attrs['type'] = $this->type;
		$attrs['id'] = $this->id;

		$unmodified = $attrs;

		// run the transformation
		foreach(static::$transformation as $filter => $data)
		{
			$this->action($filter, $data, $attrs);
		}

		//echo $this; _debug_array($unmodified); _debug_array($attrs); _debug_array(array_diff_assoc($attrs, $unmodified));
		// compute the difference and send it to the client as modifications
		$type_changed = false;
		foreach(array_diff_assoc($attrs, $unmodified) as $attr => $val)
		{
			switch($attr)
			{
				case 'value':
					if ($val != $value)
					{
						$value = $val;	// $value is reference to self::$request->content
					}
					break;
				case 'sel_options':
					self::$request->sel_options[$form_name] = $val;
					break;
				case 'type':	// not an attribute in etemplate2
					$type_changed = true;
					if($val == 'template')
					{
						// If the widget has been transformed into a template, we
						// also need to try and instantiate & parse the template too
						$transformed_template = Template::instance($attrs['template']);
						if($transformed_template)
						{
							$this->expand_widget($transformed_template, $expand);
							$transformed_template->run('beforeSendToClient',array($cname,$expand));
						}
						$type_changed = false;
					}
				default:
					// transform attributes for web-components to camelCase
					if (substr($attrs['type'], 0, 4) === 'et2-')
					{
						if (count($parts = preg_split('/[_-]/', $attr)) > 1)
						{
							if ($attr === 'parent_node') $parts[1] = 'Id';  // we can not use DOM property parentNode --> parentId
							$attr = array_shift($parts).implode('', array_map('ucfirst', $parts));
						}
					}
					self::setElementAttribute($form_name, $attr, $val);
					break;
			}
		}
		if($type_changed)
		{
			// Run the new widget type's beforeSendToClient
			$expanded_child = self::factory($attrs['type'], false,$this->id);
			$expanded_child->id = $this->id;
			$expanded_child->type = $attrs['type'];
			$expanded_child->attrs = $attrs;
			$expanded_child->run('beforeSendToClient',array($cname,$expand));
		}
	}

	/**
	 * Recursively run given action(s) on an attribute value
	 *
	 * @param string $attr attribute concerned
	 * @param int|string|array $action action to run
	 * @param array &$attrs attributes
	 * @throws Api\Exception\WrongParameter if $action is of wrong type
	 */
	protected function action($attr, $action, array &$attrs)
	{
		if (self::DEBUG) error_log(__METHOD__."('$attr', ".array2string($action).')');
		// action is an assignment
		if (is_scalar($action) || is_null($action))
		{
			// check if assignment contains placeholders --> replace them
			if (strpos($action, '@') !== false)
			{
				$replace = array();
				foreach($attrs as $a => $v)
				{
					if (is_scalar($v) || is_null($v)) $replace['@'.$a] = $v;
				}
				$action = strtr($action, $replace);
				// now replace with non-scalar value, eg. if values is an array: "@value", "@value[key] or "@value[@key]"
				if (($a = strstr($action, '@')))
				{
					$action = self::get_array($attrs, substr($a,1));
				}
			}
			$attrs[$attr] = $action;
			if (self::DEBUG) error_log(__METHOD__."('$attr', ".array2string($action).") attrs['$attr'] = ".array2string($action).', attrs='.array2string($attrs));
		}
		// action is a serverside callback
		elseif(is_array($action) && isset($action['__callback__']))
		{
			if (!is_string(($callback = $action['__callback__'])))
			{
				throw new Api\Exception\WrongParameter(__METHOD__."('$attr', ".array2string($action).', '.array2string($attrs).') wrong datatype for callback!');
			}
			if (method_exists($this, $callback))
			{
				$attrs[$attr] = $this->$callback($attrs[$attr], $attrs);
			}
			elseif(count(explode('.', $callback)) == 3)
			{
				$attrs[$attr] = ExecMethod($callback, $attrs[$attr], $attrs);
			}
			elseif (is_callable($callback, false))
			{
				$attrs[$attr] = call_user_func($callback, $attrs[$attr], $attrs);
			}
			else
			{
				throw new Api\Exception\WrongParameter(__METHOD__."('$attr', ".array2string($action).', '.array2string($attrs).') wrong datatype for callback!');
			}
		}
		// action is a clientside callback
		elseif(is_array($action) && isset($action['__js__']))
		{
			// nothing to do here
		}
		// TODO: Might be a better way to handle when value to be set is an array
		elseif(is_array($action) && $attr == 'sel_options')
		{
			$attrs[$attr] = $action;
		}
		// action is a switch --> check cases
		elseif(is_array($action))
		{
			// case matches --> run all actions
			if(isset($attrs[$attr]) && isset($action[$attrs[$attr]]) || (!isset($attrs[$attr]) || !isset($action[$attrs[$attr]])) && isset($action['__default__']))
			{
				$actions = isset($attrs[$attr]) && isset($action[$attrs[$attr]]) ? $action[$attrs[$attr]] : $action['__default__'];
				if(!is_array($actions))
				{
					$attrs[$attr] = $actions;
					$actions = array($attr => $actions);
				}
				if(self::DEBUG)
				{
					error_log(__METHOD__ . "(attr='$attr', action=" . array2string($action) . ") attrs['$attr']=='{$attrs[$attr]}' --> running actions");
				}
				foreach($actions as $attr => $action)
				{
					$this->action($attr, $action, $attrs);
				}
			}
		}
		else
		{
			throw new Api\Exception\WrongParameter(__METHOD__."(attr='$attr', action=".array2string($action).', attrs='.array2string($attrs).') wrong datatype for action!');
		}
	}
}