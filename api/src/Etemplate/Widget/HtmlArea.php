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
	 * @var array
	 */
	public static $font_options = array(
		'andale mono,times' => 'Andale Mono',
		'arial, helvetica, sans-serif' => 'Arial',
		'arial black,avant garde' => 'Arial Black',
		'book antiqua,palatino' => 'Book Antiqua',
		'Comic Sans MS, cursive' => 'Comic Sans MS',
		'Courier New, Courier, monospace' => 'Courier New',
		'Georgia, serif' => 'Georgia',
		'helvetica' => 'Helvetica',
		'impact,chicago' => 'Impact',
		'Lucida Sans Unicode, Lucida Grande, sans-serif' => 'Lucida Sans Unicode',
		'Segoe' => 'segoe,segoe ui',
		'symbol' => 'Symbol',
		'Tahoma, Geneva, sans-serif' => 'Tahoma',
		'terminal, "monaco' => 'Terminal',
		'times new roman, times, serif' => 'Times New Roman',
		'Trebuchet MS, Helvetica, sans-serif' => 'Trebuchet MS',
		'Verdana, Geneva, sans-serif' => 'Verdana',
		'webdings' => 'Webdings',
		'wingdings,zapf dingbats' => 'Wingdings'
	);

	/**
	 * font size options
	 * @var array
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
	 * @var array
	 */
	public static $font_unit_options = array(
		'pt' => 'pt: points (1/72 inch)',
		'px' => 'px: display pixels',
	);

	/**
	 * List of exisitng toolbar actions
	 * @var array
	 */
	public static $toolbar_list = [
		'undo', 'redo', 'bold', 'italic', 'underline', 'strikethrough', 'forecolor', 'backcolor',
		'link', 'alignleft', 'aligncenter', 'alignright', 'alignjustify',
		'numlist', 'bullist', 'outdent', 'indent', 'ltr', 'rtl','pastetext',
		'removeformat', 'code', 'image', 'searchreplace','formatselect', 'fontselect', 'fontsizeselect', 'fullscreen', 'table'
	];

	/**
	 * Default list of toolbar actions
	 * @var array
	 */
	public static $toolbar_default_list = [
		'undo', 'redo','formatselect', 'fontselect', 'fontsizeselect',
		'bold' ,'italic', 'underline', 'removeformat', 'forecolor', 'backcolor', 'alignleft',
		'aligncenter', 'alignright', 'alignjustify', 'numlist', 'bullist', 'outdent',
		'indent', 'link', 'image', 'pastetext', 'table'
	];

	/**
	 * Create an array of toolbar as sel options
	 *
	 * @return array
	 *        [
	 *            id => {string}
	 *            value => {string}
	 *            label => {string}
	 *            title => {string}
	 *            icon => {string}
	 *            app => {string}
	 *        ]
	 */
	public static function get_toolbar_as_selOptions ()
	{
		$toolbar_selOptions = array();
		foreach (self::$toolbar_list as $toolbar)
		{
			$file = '/api/templates/default/images/htmlarea/' . $toolbar . '.svg';
			$toolbar_selOptions[$toolbar] = array(
				'id'    => $toolbar,
				// Selectbox validation can't understand all these options without value
				'value' => $toolbar,
				'label' => lang($toolbar),
				'title' => lang($toolbar),
				'icon'  => file_exists(EGW_SERVER_ROOT . $file) ? Api\Framework::getUrl($GLOBALS['egw_info']['server']['webserver_url'] . $file) : '',
				'app'   => 'api'
			);
		}
		return $toolbar_selOptions;
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
				$value = Api\Html\HtmLawed::purify(
					self::get_array($content, $form_name),
					$this->attrs['validationRules'] ?? $this->attrs['validation_rules']
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

	/**
	 * Content CSS für TinyMCE
	 *
	 * Can/should also be added to mails, to ensure identical display on the receiving MUA.
	 *
	 * @return string
	 */
	public static function contentCss()
	{
		$font_family = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font'] ?? 'arial, helvetica, sans-serif';
		$font_size = ($GLOBALS['egw_info']['user']['preferences']['common']['rte_font_size'] ?? '10').
			($GLOBALS['egw_info']['user']['preferences']['common']['rte_font_unit'] ?? 'pt');

		return <<<EOF
/**
 * Copyright (c) Tiny Technologies, Inc. All rights reserved.
 * Licensed under the LGPL or a commercial license.
 * For LGPL see License.txt in the project root for license information.
 * For commercial licenses see https://www.tiny.cloud/
 */
body, p, div {
  font-family: $font_family;
  font-size: $font_size;
  line-height: 1.4;
  margin: 1rem 0;
}
body {
  margin: 1rem;
}
table {
  border-collapse: collapse;
}
/* Apply a default padding if legacy cellpadding attribute is missing */
table:not([cellpadding]) th,
table:not([cellpadding]) td {
  padding: 0.4rem;
}
/* Set default table styles if a table has a positive border attribute
   and no inline css */
table[border]:not([border="0"]):not([style*="border-width"]) th,
table[border]:not([border="0"]):not([style*="border-width"]) td {
  border-width: 1px;
}
/* Set default table styles if a table has a positive border attribute
   and no inline css */
table[border]:not([border="0"]):not([style*="border-style"]) th,
table[border]:not([border="0"]):not([style*="border-style"]) td {
  border-style: solid;
}
/* Set default table styles if a table has a positive border attribute
   and no inline css */
table[border]:not([border="0"]):not([style*="border-color"]) th,
table[border]:not([border="0"]):not([style*="border-color"]) td {
  border-color: #ccc;
}
figure {
  display: table;
  margin: 1rem auto;
}
figure figcaption {
  color: #999;
  display: block;
  margin-top: 0.25rem;
  text-align: center;
}
hr {
  border-color: #ccc;
  border-style: solid;
  border-width: 1px 0 0 0;
}
code {
  background-color: #e8e8e8;
  border-radius: 3px;
  padding: 0.1rem 0.2rem;
}
.mce-content-body:not([dir=rtl]) blockquote {
  border-left: 2px solid #ccc;
  margin-left: 0;
  padding-left: 10px;
}
.mce-content-body[dir=rtl] blockquote {
  border-right: 2px solid #ccc;
  margin-right: 0;
  padding-right: 10px;
}
fieldset {
	border: 2px solid silver; 
	border-left: none; 
	border-right: none;
	font-family: $font_family;
	font-size: $font_size;
	margin: .5rem 0;
}
/* EGroupware users preferred font and -size */
h1:not([style*="font-family"]),h2:not([style*="font-family"]),h3:not([style*="font-family"]),h4:not([style*="font-family"]),h5:not([style*="font-family"]),h6:not([style*="font-family"]),
	div:not([style*="font-family"]),li:not([style*="font-family"]),p:not([style*="font-family"]),blockquote:not([style*="font-family"]),
	td:not([style*="font-family"]),th:not([style*="font-family"]) {
	font-family: $font_family;
}
div:not([style*="font-size"]),li:not([style*="font-size"]),p:not([style*="font-size"]),blockquote:not([style*="font-size"]),
	td:not([style*="font-size"]),th:not([style*="font-size"]) {
	font-size: $font_size;
}
EOF;
	}
}
Etemplate\Widget::registerWidget(__NAMESPACE__.'\\HtmlArea', 'htmlarea');