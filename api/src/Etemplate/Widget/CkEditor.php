<?php
/**
 * EGroupware - eTemplate serverside ckeditor widget
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
 * eTemplate ckeditor widget
 */
class CkEditor extends Etemplate\Widget
{

	protected $legacy_options = 'mode,height,width,expand_toolbar,base_href';

	public $attrs = array(
		'height' => '400px',
	);

	/**
	 * Fill config options
	 *
	 * @param string $cname
	 */
	public function beforeSendToClient($cname)
	{
		$form_name = self::form_name($cname, $this->id);

		Api\Framework::includeJS('/vendor/egroupware/ckeditor/ckeditor.js');
		Api\Framework::includeJS('/vendor/egroupware/ckeditor/ckeditor.config.js');
		Api\Framework::includeJS('/vendor/egroupware/ckeditor/ckeditor.adapters/jquery.js');

		$config = Api\Html\CkEditorConfig::get_ckeditor_config_array($this->attrs['mode'], $this->attrs['height'],
			$this->attrs['expand_toolbar'],$this->attrs['base_href']
		);
		// User preferences
		$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
		$font_size = Api\Html\CkEditorConfig::font_size_from_prefs();
		$font_span = '<span style="width: 100%; display: inline; '.
			($font?'font-family:'.$font.'; ':'').($font_size?'font-size:'.$font_size.'; ':'').
			'">&#8203;</span>';
		if (empty($font) && empty($font_size)) $font_span = '';
		if($font_span)
		{
			$config['preference_style'] = $font_span;
		}
		self::$request->modifications[$form_name]['config'] = $config;
	}

	/**
	 * Validate input
	 *
	 * Input is run throught HTMLpurifier, to make sure users can NOT enter javascript or other nasty stuff (XSS!).
	 *
	 * @param string $cname current namespace
	 * @param array $expand values for keys 'c', 'row', 'c_', 'row_', 'cont'
	 * @param array $content
	 * @param array &$validated=array() validated content
	 * @return boolean true if no validation error, false otherwise
	 */
	public function validate($cname, array $expand, array $content, &$validated=array())
	{
		$form_name = self::form_name($cname, $this->id, $expand);

		if (!$this->is_readonly($cname, $form_name))
		{
			$value = self::get_array($content, $form_name);
			// only purify for html, mode "ascii" is NO html and content get lost!
			if ($this->attrs['mode'] != 'ascii')
			{
				$value = Api\Html\HtmLawed::purify($value, $this->attrs['validation_rules']);
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\CkEditor', 'ckeditor');
