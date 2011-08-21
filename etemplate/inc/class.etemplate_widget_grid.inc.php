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
	 * - added 4th var-parameter to hold key of disabled column
	 * - if a column is disabled run returns false and key get added by columns run to $columns_disabled
	 * - row run method checks now for each child (arbitrary widget) if it the column's key is included in $columns_disabled
	 * - as a grid can contain other grid's as direct child, we have to backup and initialise $columns_disabled in grid run!
	 *
	 * @param string $method_name
	 * @param array $params=array('') parameter(s) first parameter has to be the cname!
	 * @param boolean $respect_disabled=false false (default): ignore disabled, true: method is NOT run for disabled widgets AND their children
	 * @param array $columns_disabled=array() disabled columns
	 */
	public function run($method_name, $params=array(''), $respect_disabled=false, &$columns_disabled=array())
	{
		// as a grid can contain other grid's as direct child, we have to backup and initialise $columns_disabled
		if ($this->type == 'grid')
		{
			$backup_columns_disabled = $columns_disabled;
			$columns_disabled = array();
		}
		if ($respect_disabled && ($disabled = $this->attrs['disabled']))
		{
			// check if disabled contains @ or !
			$cname = $params[0];
			$disabled = self::check_disabled($disabled, $cname ? self::get_array(self::$request->content, $cname) : self::$request->content);
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
		foreach($this->children as $n => $child)
		{
			if ($type == 'row')
			{
				if (in_array($n, $columns_disabled))
				{
					error_log(__METHOD__."('$method_name', ".array2string($params).', '.array2string($respect_disabled).") $this column $n is disabled: NOT running");
					continue;	// do NOT run $method_name on disabled columns
				}
			}
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
		return true;
	}
}
etemplate_widget::registerWidget('etemplate_widget_grid', array('grid', 'rows', 'row', 'columns', 'column'));
