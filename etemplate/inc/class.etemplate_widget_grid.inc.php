<?php
/**
 * EGroupware - eTemplate serverside grid widget
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
class etemplate_widget_grid extends etemplate_widget_box
{
	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * Not used for grid, just need to be set to unset array of etemplate_widget_box
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
	 * @param string $method_name
	 * @param array $params=array('') parameter(s) first parameter has to be the cname, second $expand!
	 * @param boolean $respect_disabled=false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 * @param array $columns_disabled=array() disabled columns
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false, &$columns_disabled=array())
	{
		// maintain $expand array name-expansion
		$cname =& $params[0];
		$expand =& $params[1];
		$old_cname = $params[0];
		if ($this->id) $cname = self::form_name($cname, $this->id, $expand);
		if ($expand['cname'] !== $cname)
		{
			$expand['cont'] =& self::get_array(self::$request->content, $cname);
			$expand['cname'] = $cname;
		}
		// as a grid can contain other grid's as direct child, we have to backup and initialise $columns_disabled
		if ($this->type == 'grid')
		{
			$backup_columns_disabled = $columns_disabled;
			$columns_disabled = array();
		}

		if ($respect_disabled && ($disabled = $this->attrs['disabled']))
		{
			// check if disabled contains @ or !
			$disabled = self::check_disabled($disabled, $expand);
			if ($disabled)
			{
				error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this disabled='{$this->attrs['disabled']}'='$disabled': NOT running");
				return false;	// return
			}
		}
		if (method_exists($this, $method_name))
		{
			call_user_func_array(array($this, $method_name), $params);
		}
		//foreach($this->children as $n => $child)
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
			}
			// check if we need to autorepeat last row ($child)
			elseif (isset($child) && ($this->type == 'rows' || $this->type == 'columns') && $child->need_autorepeat($cname, $expand))
			{
				// not breaking repeats last row/column ($child)
			}
			else
			{
				break;
			}
			if ($this->type == 'row')
			{
				if (in_array($n, $columns_disabled))
				{
					error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this column $n is disabled: NOT running");
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
				while(($pat = strstr($pat, '$')))
				{
					$pat = substr($pat,$pat[1] == '{' ? 2 : 1);

					switch ($this->type)
					{
						case 'column':
							$Ok = $pat[0] == 'c' && !(substr($pat,0,4) == 'cont' || substr($pat,0,2) == 'c_' ||
								substr($pat,0,4) == 'col_');
							break;
						case 'row':
							$Ok = $pat[0] == 'r' && !(substr($pat,0,2) == 'r_' || substr($pat,0,4) == 'row_');
							break;
						default:
							return false;
					}
					if ($Ok && ($value = self::get_array(self::$request->content,
						$fname=self::form_name($cname, $child->id, $expand))) !== false && isset($value))
					{
						//error_log(__METHOD__."('$method_name', ) $this autorepeating row $expand[row] because of $child->id = '$fname' is ".array2string($value));
						return true;
					}
				}
			}
		}
		return false;
	}
}
etemplate_widget::registerWidget('etemplate_widget_grid', array('grid', 'rows', 'row', 'columns', 'column'));
