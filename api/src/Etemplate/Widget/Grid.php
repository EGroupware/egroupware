<?php
/**
 * EGroupware - eTemplate serverside grid widget
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
 * eTemplate grid widget incl. row(s) and column(s)
 *
 * Example of a grid containing 3 columns and 2 rows (last one autorepeated)
 *
 * <grid>
 *  <columns>
 *   <column>
 *   <column width="75%">
 *   <column disabled="@no_actions">
 *  </columns>
 *  <rows>
 *   <row>
 *    <description value="ID" />
 *    <description value="Text" />
 *    <description value="Action" />
 *   <row>
 *   <row>
 *    <description id="${row}[id]" />
 *    <textbox id="${row}[text]" />
 *    <button id="delete[$row_cont[id]]" value="Delete" />
 *   </row>
 *  </rows>
 * </grid>
 */
class Grid extends Box
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * Not used for grid, just need to be set to unset array of extended Box
	 *
	 * @var string|array
	 */
	protected $legacy_options = null;

	/**
	 * Run a given method on all children
	 *
	 * Reimplemented to implement column based disabling:
	 * - added 5th var-parameter to hold key of disabled column
	 * - if a column is disabled run returns false and key get added by columns run to $columns_disabled
	 * - row run method checks now for each child (arbitrary widget) if it the column's key is included in $columns_disabled
	 * - as a grid can contain other grid's as direct child, we have to backup and initialise $columns_disabled in grid run!
	 *
	 * @param string|callable $method_name or function($cname, $expand, $widget)
	 * @param array $params =array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled =false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 * @param array $columns_disabled=array() disabled columns
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false, &$columns_disabled=array())
	{
		// maintain $expand array name-expansion
		$cname =& $params[0];
		$expand =& $params[1];
		$old_cname = $params[0];
		$old_expand = $params[1];

		// as a grid can contain other grid's as direct child, we have to backup and initialise $columns_disabled
		if ($this->type == 'grid')
		{
			$backup_columns_disabled = $columns_disabled;
			$columns_disabled = array();
		}

		if ($respect_disabled && isset($this->attrs['disabled']) && self::check_disabled($this->attrs['disabled'], $expand))
		{
			//error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'=".array2string($disabled).": NOT running");
			$params[0] = $old_cname;
			$params[1] = $old_expand;
			return false;	// return
		}

		if ($this->id && $this->type !== 'row') $cname = self::form_name($cname, $this->id, $expand);
		if (!empty($expand['cname']) && $expand['cname'] !== $cname && $cname)
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname);
			$expand['cname'] = $cname;
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
		//foreach($this->children as $n => $child)
		$repeat_child = null;
		for($n = 0; ; ++$n)
		{
			// maintain $expand array name-expansion
			switch($this->type)
			{
				case 'rows':
					$expand['row'] = $n;
					break;
				case 'columns':
					$expand['c'] = $n;
					break;
			}
			if (isset($this->children[$n]))
			{
				$child = $this->children[$n];
				if($this->type == 'rows' || $this->type == 'columns')
				{
					/*
					 * We store a clone of the repeated child, because at the end
					 * of this loop the function $method_name is run on $child.
					 * We want to run the function again each repeat, on an unmodified
					 * row / column.
					 */
					$repeat_child = clone $child;
				}
			}
			// check if we need to autorepeat last row ($child)
			elseif (isset($child) && ($this->type == 'rows' || $this->type == 'columns') && $child->need_autorepeat($cname, $expand))
			{
				// not breaking repeats last row/column ($child)
				// Clone the repeating child, to avoid modifying it
				$child = clone $repeat_child;
			}
			else
			{
				break;
			}
			if ($this->type == 'row')
			{
				if (in_array($n, $columns_disabled))
				{
					//error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this column $n is disabled: NOT running");
					continue;	// do NOT run $method_name on disabled columns
				}
			}
			//error_log('Running ' . $method_name . ' on child ' . $n . '(' . $child . ') ['.$expand['row'] . ','.$expand['c'] . ']');
			$disabled = $child->run($method_name, $params, $respect_disabled, $columns_disabled) === false;

			if ($this->type == 'columns' && $disabled)
			{
				$columns_disabled[] = $n;	// mark column as disabled
			}
		}
		if ($this->type == 'grid')
		{
			$columns_disabled = $backup_columns_disabled;
		}
		$params[0] = $old_cname;
		$params[1] = $old_expand;

		return true;
	}

	/**
	 * Check if a row or column needs autorepeating, because still content left
	 *
	 * We only check direct children and grandchildren of first child (eg. box in first column)!
	 *
	 * @param string $cname
	 * @param array $expand
	 */
	private function need_autorepeat($cname, array $expand)
	{
		// check id's of children
		foreach($this->children as $n => $direct_child)
		{
			foreach(array_merge(array($direct_child), $n ? array() : $direct_child->children) as $child)
			{
				$pat = $child->id;
				while(($patstr = strstr($pat, '$')))
				{
					$pat = substr($patstr,$patstr[1] == '{' ? 2 : 1);

					switch ($this->type)
					{
						case 'column':
							$Ok = $pat[0] == 'c' && !(substr($pat,0,4) == 'cont' || substr($pat,0,2) == 'c_' ||
								substr($pat,0,4) == 'col_');
							break;
						case 'row':
							$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' ||
								substr($pat,0,4) == 'row_' && substr($pat,0,8) != 'row_cont');
							//error_log(__METHOD__."() pat='$pat' --> Ok=".array2string($Ok));
							break;
						default:
							return false;
					}
					if ($Ok && ($fname=self::form_name($cname, $child->id, $expand)) &&
						// need to break if fname ends in [] as get_array() will ignore it and returns whole array
						// for an id like "run[$row_cont[appname]]"
						substr($fname, -2) != '[]' &&
						($value = self::get_array(self::$request->content,$fname)) !== null)	// null = not found (can be false!)
					{
						//error_log(__METHOD__."('$cname', ) $this autorepeating row $expand[row] because of $child->id = '$fname' is ".array2string($value));
						unset($value);
						return true;
					}
				}
			}
		}
		//error_log(__METHOD__."('$cname', ) $this NOT autorepeating row $expand[row]");
		return false;
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\Grid', array('grid', 'rows', 'row', 'columns', 'column'));
