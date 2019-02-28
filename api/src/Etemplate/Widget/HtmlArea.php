<?php
/**
 * EGroupware - eTemplate serverside htmlarea widget
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
 * eTemplate htmleditor widget
 */
class HtmlArea extends Etemplate\Widget
{
	/**
	 * font families
	 * @var type array
	 */
	public static $font_options = array(
		'arial, helvetica, sans-serif' => 'Arial',
		'Comic Sans MS, cursive' => 'Comic Sans MS',
		'Courier New, Courier, monospace' => 'Courier New',
		'Georgia, serif' => 'Georgia',
		'Lucida Sans Unicode, Lucida Grande, sans-serif' => 'Lucida Sans Unicode',
		'Tahoma, Geneva, sans-serif' => 'Tahoma',
		'times new roman, times, serif' => 'Times New Roman',
		'Trebuchet MS, Helvetica, sans-serif' => 'Trebuchet MS',
		'Verdana, Geneva, sans-serif' => 'Verdana'
	);

	/**
	 * font size options
	 * @var type array
	 */
	public static $font_size_options = array(
		8  => '8',
		9  => '9',
		10 => '10',
		11 => '11',
		12 => '12',
		14 => '14',
		16 => '16',
		18 => '18',
		20 => '20',
		22 => '22',
		24 => '24',
		26 => '26',
		28 => '28',
		36 => '36',
		48 => '48',
		72 => '72',
	);

	/**
	 * font unit options
	 * @var type array
	 */
	public static $font_unit_options = array(
		'pt' => 'pt: points (1/72 inch)',
		'px' => 'px: display pixels',
	);

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
				$value = Api\Html\HtmLawed::purify(
					self::get_array($content, $form_name),
					$this->attrs['validation_rules']
				);
			}
			$valid =& self::get_array($validated, $form_name, true);
			if (true) $valid = $value;
		}
	}

	/**
	 * Get font size from preferences
	 *
	 * @param array $prefs =null default $GLOBALS['egw_info']['user']['preferences']
	 * @param string &$size =null on return just size, without unit
	 * @param string &$unit =null on return just unit
	 * @return string font-size including unit
	 */
	public static function font_size_from_prefs(array $prefs=null, &$size=null, &$unit=null)
	{
		if (is_null($prefs)) $prefs = $GLOBALS['egw_info']['user']['preferences'];

		$size = $prefs['common']['rte_font_size'];
		$unit = $prefs['common']['rte_font_unit'];
		if (substr($size, -2) == 'px')
		{
			$unit = 'px';
			$size = (string)(int)$size;
		}
		return $size.($size?$unit:'');
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\HtmlArea', 'htmlarea');
