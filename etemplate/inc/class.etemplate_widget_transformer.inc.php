<?php
/**
 * EGroupware - eTemplate serverside base widget, to define new widgets using a transformation out of existing widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-16 by RalfBecker@outdoor-training.de
 * @version $Id$
 */

use EGroupware\Api\Etemplate\Widget\Transformer;

/**
 * eTemplate serverside base widget, to define new widgets using a transformation out of existing widgets
 *
 * @deprecated use Api\Etemplate\Widget\Transformer
 */
abstract class etemplate_widget_transformer extends Transformer
{
	/**
	 * Rendering transformer widget serverside as an old etemplate extension
	 *
	 * This function is called before the extension gets rendered
	 *
	 * @param string $name form-name of the control
	 * @param mixed &$value value / existing content, can be modified
	 * @param array &$cell array with the widget, can be modified for ui-independent widgets
	 * @param array &$readonlys names of widgets as key, to be made readonly
	 * @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	public function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		unset($readonlys, $extension_data, $tmpl);	// not used but required by function signature

		$_value = $value;
		$_cell = $cell;
		$cell['value'] =& $value;
		$cell['options'] =& $cell['size'];	// old engine uses 'size' instead of 'options' for legacy options
		$cell['id'] =& $cell['name'];		// dto for 'name' instead of 'id'

		// run the transformation
		foreach(static::$transformation as $filter => $data)
		{
			$this->action($filter, $data, $cell);
		}
		unset($cell['value']);
		error_log(__METHOD__."('$name', ".(is_array($_value)?$_value['id']:$_value).", ".array2string($_cell).", ...) transformed to ".array2string($cell)." and value=".array2string($value));
		return true;
	}
}
