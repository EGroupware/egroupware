<?php
/**
 * EGroupware - eTemplate widget baseclass
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

/**
 * *box widgets having an own namespace
 */
class Box extends Etemplate\Widget
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
	 * Reimplemented because grids and boxes can have an own namespace.
	 * GroupBox has no namespace!
	 *
	 * @param string|callable $method_name or function($cname, $expand, $widget)
	 * @param array $params =array('') parameter(s) first parameter has to be cname!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false)
	{
		$cname =& $params[0];
		$expand =& $params[1];
		$old_cname = $params[0];
		$old_expand = $params[1];

		if ($this->id && $this->type != 'groupbox') $cname = self::form_name($cname, $this->id, $params[1]);
		if (($expand['cname'] ?? null) !== $cname && trim($cname))
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname, false, true);
			$expand['cname'] = $cname;
		}
		if ($respect_disabled && isset($this->attrs['disabled']) && self::check_disabled($this->attrs['disabled'], $expand))
		{
			//error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'=".array2string($disabled).": NOT running");
			return;
		}
		if (is_string($method_name) && method_exists($this, $method_name))
		{
			call_user_func_array(array($this, $method_name), $params);
		}
		// allow calling with a function or closure --> call it with widget as first param
		elseif (is_callable($method_name))
		{
			$params[2] = $this;
			call_user_func_array($method_name, $params);
		}

		// Expand children
		$columns_disabled = null;
		if($this->id && isset($this->children[0]) && strpos($this->children[0]->id, '$') !== false)
		{
			// Need to set this so the first child can repeat
			$expand['row'] = 0;
		}
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
			$params[0] = $cname;
			$params[1] = $expand;
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
	 * @param Etemplate\Widget $widget
	 * @param string $cname
	 * @param array $expand
	 */
	private function need_autorepeat(Etemplate\Widget $widget, $cname, array $expand)
	{
		foreach(array($widget) + $widget->children as $check_widget)
		{
			$pat = $check_widget->id;
			while(($pattern = strstr($pat, '$')))
			{
				$pat = substr($pattern,$pattern[1] == '{' ? 2 : 1);

				$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' ||
					substr($pat,0,4) == 'row_' && substr($pat,0,8) != 'row_cont');

				if ($Ok && ($fname=self::form_name($cname, $check_widget->id, $expand)) &&
					// need to break if fname ends in [] as get_array() will ignore it and returns whole array
					// for an id like "run[$row_cont[appname]]"
					substr($fname, -2) != '[]' &&
					($value = self::get_array(self::$request->content, $fname)) !== null)	// null = not found (can be false!)
				{
					//error_log(__METHOD__."($widget,$cname) $this autorepeating row $expand[row] because of $check_widget->id = '$fname' is ".array2string($value));
					unset($value);
					return true;
				}
			}
		}

		return false;
	}
}
// register class for layout widgets, which can have an own namespace
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\Box', array('box', 'hbox', 'vbox', 'groupbox', 'old-box', 'old-vbox', 'old-hbox'));