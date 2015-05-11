<?php
/**
 * EGroupware - eTemplate serverside ajax select widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2015 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate ajax select widget
 *
 */
class etemplate_widget_ajax_select extends etemplate_widget_menupopup
{

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 */
	public function beforeSendToClient($cname, array $expand=null)
	{
		$matches = null;
		if ($cname == '$row')	// happens eg. with custom-fields: $cname='$row', this->id='#something'
		{
			$form_name = $this->id;
		}
		// happens with fields in nm-header: $cname='nm', this->id='${row}[something]' or '{$row}[something]'
		elseif (preg_match('/(\${row}|{\$row})\[([^]]+)\]$/', $this->id, $matches))
		{
			$form_name = $matches[2];
		}
		// happens in auto-repeat grids: $cname='', this->id='something[{$row}]'
		elseif (preg_match('/([^[]+)\[({\$row})\]$/', $this->id, $matches))
		{
			$form_name = $matches[1];
		}
		// happens with autorepeated grids: this->id='some[$row_cont[thing]][else]' --> just use 'else'
		elseif (preg_match('/\$row.*\[([^]]+)\]$/', $this->id, $matches))
		{
			$form_name = $matches[1];
		}
		else
		{
			$form_name = self::form_name($cname, $this->id, $expand);
		}
		
		// Make sure &nbsp;s, etc.  are properly encoded when sent, and not double-encoded
		$options = (isset(self::$request->sel_options[$form_name]) ? $form_name : $this->id);
		if(is_array(self::$request->sel_options[$options]))
		{
			
			// Fix any custom options from application
			self::fix_encoded_options(self::$request->sel_options[$options],true);
			
			if(!self::$request->sel_options[$options])
			{
				unset(self::$request->sel_options[$options]);
			}
		}
	}
}

etemplate_widget::registerWidget('etemplate_widget_ajax_select', array('ajax_select'));
