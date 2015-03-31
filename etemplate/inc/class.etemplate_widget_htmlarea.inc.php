<?php
/**
 * EGroupware - eTemplate serverside htmlarea widget
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
 * eTemplate htmlarea widget
 */
class etemplate_widget_htmlarea extends etemplate_widget
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

		$config = egw_ckeditor_config::get_ckeditor_config_array($this->attrs['mode'], $this->attrs['height'],
			$this->attrs['expand_toolbar'],$this->attrs['base_href']
		);
		// User preferences
		$font = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'];
		$font_size = egw_ckeditor_config::font_size_from_prefs();
		$font_span = '<span style="width: 100%; display: inline-block; '.
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
				$value = html::purify($value, $this->attrs['validation_rules']);
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}
}
