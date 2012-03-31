<?php
/**
 * eGroupWare - eTemplate Extension - Resource Select Widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author RalfBecker-AT-outdoor-training.de
 * @version $Id$
 */

/**
 * eTemplate Extension: select a resource
 *
 * @package resources
 */
class resources_select_widget
{
	/** 
	* exported methods of this class
	* @var array
	*/
	var $public_functions = array(
		'pre_process' => True,
	);
	/**
	* availible extensions and there names for the editor
	* @var array
	*/
	var $human_name = 'Select Resources';

	/**
	* Constructor of the extension
	*
	* @param string $ui '' for html
	*/
	function resources_select_widget($ui)
	{
		$this->ui = $ui;
	}

	/**
	* pre-processing of the extension
	*
	* This function is called before the extension gets rendered
	*
	* @param string $name form-name of the control
	* @param mixed &$value value / existing content, can be modified
	* @param array &$cell array with the widget, can be modified for ui-independent widgets 
	* @param array &$readonlys names of widgets as key, to be made readonly
	* @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	* @param object &$tmpl reference to the template we belong too
	* @return boolean true if extra label is allowed, false otherwise
	*/
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		if ($cell['readonly'] && !is_array($value))
		{
			// no acl check here cause names are allways viewable
			list($res_id,$quantity) = explode(':',$value);
			$data = ExecMethod('resources.resources_bo.get_calendar_info',$res_id);
			$cell['type'] = 'label';
			$value = $data[0]['name']. ($data[0]['useable'] > 1 ? ' ['. ($quantity > 1 ? $quantity : 1). '/'. $data[0]['useable']. ']' : '');
			return true;
		}
		
		if (!$GLOBALS['egw_info']['user']['apps']['resources'])
		{
			$cell = $tmpl->empty_cell();
			$cell['label'] = 'no resources';
			return false;
		}
		$tpl = new etemplate('resources.resource_selectbox');
		// keep the editor away from the generated tmpls
		$tpl->no_onclick = true;			
		
		if ($value)
		{
			$value = is_array($value) ? $value : explode(',',$value);
			foreach((array)$value as $id)
			{
				list($res_id,$quantity) = explode(':',$id);
				$data = ExecMethod('resources.resources_bo.get_calendar_info',$res_id);
				$sel_options[$data[0]['res_id'].($quantity > 1 ? (':'.$quantity) : '')] = 
					$data[0]['name'].' ['.($quantity > 1 ? $quantity : 1).'/'.$data[0]['useable'].']';
			}
			$tpl->set_cell_attribute('resources','sel_options',$sel_options);
		}
		
		$tpl->set_cell_attribute('resources','size',(int)$cell['size'].'+');
		$tpl->set_cell_attribute('resources','label',$cell['label']);
		$tpl->set_cell_attribute('resources','id','resources_selectbox');
		$tpl->set_cell_attribute('resources','name',$cell['name']);
		if ($cell['help'])
		{
			$tpl->set_cell_attribute('resources','help',$cell['help']);
			$tpl->set_cell_attribute('popup','label',$cell['help']);
		}
		$cell['type'] = 'template';
		$cell['size'] = $cell['label'] = '';
		$cell['name'] = 'resources.resource_selectbox';
		$cell['obj'] =& $tpl;

		return True;	// extra Label Ok
	}
}
