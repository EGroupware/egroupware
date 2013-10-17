<?php
/**
 * EGroupware - eTemplate serverside date widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2011 Nathan Gray
 * @version $Id$
 */

/**
 * eTemplate project manager widgets
 *
 * @todo Move to projectmanager app once etemplate_new recognises other app's widgets
 */
class etemplate_widget_projectmanager extends etemplate_widget_transformer
{
	protected static $transformation = array(
		'type' => array(
			'projectmanager-select' => 'menupopup',
			'projectmanager-pricelist' => 'menupopup',
			'projectmanager-select-erole' => 'menupopup',
		)
	);

	/**
	 * (Array of) comma-separated list of legacy options to automatically replace when parsing with set_attrs
	 *
	 * @var string|array
	 */
	protected $legacy_options = '';

	/**
	 * Fill type options in self::$request->sel_options to be used on the client
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);
		if (!is_array(self::$request->sel_options[$form_name])) self::$request->sel_options[$form_name] = array();

		if ($this->type)
		{
			$pm_widget = new projectmanager_widget();
			$cell = $this->attrs;
			$cell['type']=$this->type;
			$cell['readonly'] = false;	// Send not read-only to get full list
			$pm_widget->pre_process($form_name, self::get_array(self::$request->content, $form_name),
				$cell,
				$garbage,
				$extension,
				$template
			);
			self::$request->sel_options[$form_name] += (array)$cell['sel_options'];

			// if no_lang was modified, forward modification to the client
			if ($cell['no_lang'] != $this->attr['no_lang'])
			{
				self::setElementAttribute($form_name, 'no_lang', $no_lang);
			}
		}

		parent::beforeSendToClient($cname);
	}

	/**
	 * Validate input
	 *
	 * @todo
	 * @param string $cname current namespace
	 * @param array $content
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

                $ok = true;
                if (!$this->is_readonly($cname, $form_name))
                {
                        $value = $value_in = self::get_array($content, $form_name);

			switch($this->type)
			{
				case 'projectmanager-select-erole':
					$value = null;
					if(is_array($value_in)) $value = implode(',',$value_in);
					break;
				default:
					$value = $value_in;
					break;
			}
			$valid =& self::get_array($validated, $form_name, true);
                        $valid = $value;
		}
	}
}
