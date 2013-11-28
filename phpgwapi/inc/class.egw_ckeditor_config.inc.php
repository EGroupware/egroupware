<?php
/**
 * EGroupware - Class which generates JSON encoded configuration for the ckeditor
 *
 * @link http://www.egroupware.org
 * @author RalfBecker-AT-outdoor-training.de
 * @author Andreas Stoeckel <as-AT-stylite.de>
 * @package api
 * @subpackage tools
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * CK-Editor configuration
 */
class egw_ckeditor_config
{
	private static $lang = null;
	private static $country = null;
	private static $enterMode = null;
	private static $skin = null;

	// Defaults, defined in phpgwapi/js/ckeditor/plugins/font/plugin.js
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
	public static $font_unit_options = array(
		'pt' => 'pt: points (1/72 inch)',
		'px' => 'px: display pixels',
	);

	/**
	 * Get font size from preferences
	 *
	 * @param array $prefs=null default $GLOBALS['egw_info']['user']['preferences']
	 * @param string &$size=null on return just size, without unit
	 * @param string &$unit=null on return just unit
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
	 * Read language and country settings for the ckeditor and store them in static
	 * variables
	 */
	private static function read_lang_country()
	{
		//use the lang and country information to construct a possible lang info for CKEditor UI and scayt_slang
		self::$lang = ($GLOBALS['egw_info']['user']['preferences']['common']['spellchecker_lang'] ?
			$GLOBALS['egw_info']['user']['preferences']['common']['spellchecker_lang']:
			$GLOBALS['egw_info']['user']['preferences']['common']['lang']);

		self::$country = $GLOBALS['egw_info']['user']['preferences']['common']['country'];

		if (!(strpos(self::$lang, '-')===false))
			list(self::$lang, self::$country) = explode('-', self::$lang);
	}

	/**
	 * Returns the current user language
	 */
	private static function get_lang()
	{
		if (self::$lang == null || self::$country == null)
			self::read_lang_country();

		return self::$lang;
	}

	/**
	 * Returns the current user country
	 */
	private static function get_country()
	{
		if (self::$lang == null || self::$country == null)
			self::read_lang_country();

		return strtoupper(self::$country);
	}

	/**
	 * Returns the ckeditor basepath
	 */
	private static function get_base_path()
	{
		//Get the ckeditor base url
		return $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/ckeditor/';
	}

	/**
	 * Returns the ckeditor enter mode which defaults to "BR"
	 */
	private static function get_enter_mode()
	{
		if (self::$enterMode == null)
		{
			//Get the input name
			$enterMode = 2;
			if (isset($GLOBALS['egw_info']['user']['preferences']['common']['rte_enter_mode']))
			{
				switch ($GLOBALS['egw_info']['user']['preferences']['common']['rte_enter_mode'])
				{
					case 'p':
						$enterMode = 1;
						break;
					case 'br':
						$enterMode = 2;
						break;
					case 'div':
						$enterMode = 3;
						break;
				}
			}

			self::$enterMode = $enterMode;
		}

		return self::$enterMode;
	}

	/**
	 * Returns the skin the ckeditor should use
	 */
	private static function get_skin()
	{
		if (self::$skin == null)
		{
			//Get the skin name
			$skin = $GLOBALS['egw_info']['user']['preferences']['common']['rte_skin'];

			//Convert old fckeditor skin names to new ones
			switch ($skin)
			{
				case 'moono':
					$skin = "moono";
					break;
				case 'silver':
				case 'office2003':
				case 'kama':
				case 'default':
					$skin = "kama";
					if (html::$user_agent != 'firefox') break;
				case 'moonocolor':
					$skin = "moonocolor";
					break;
				default:
					$skin = "kama";
			}

			//Check whether the skin actually exists, if not, switch to a default
			if (!(file_exists($basePath.'skins/'.$skin) || file_exists($skin) || !empty($skin)))
				$skin = "moono"; //this is the basic skin for ckeditor

			self::$skin = $skin;
		}

		return self::$skin;
	}

	/**
	 * Returns the URL of the filebrowser
	 *
	 * @param string $start_path start path for file browser
	 */
	private static function get_filebrowserBrowseUrl($start_path = '')
	{
		return $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=filemanager.filemanager_select.select&mode=open&method=ckeditor_return'
		.($start_path != '' ? '&path='.$start_path : '');
	}

	/**
	 * Adds all "easy to write" options to the configuration
	 *
	 * @param array& $config array were config get's added to
	 * @param int|string $height integer height in pixel or string with css unit
	 * @param boolean|string $expanded_toolbar show toolbar expanded, boolean value, string "false", or string casted to boolean
	 * @param string $start_path start path for file browser
	 */
	private static function add_default_options(&$config, $height, $expanded_toolbar, $start_path)
	{
		//Convert the pixel height to an integer value
		$config['resize_enabled'] = false;
		$config['height'] = is_numeric($height) ? (int)$height : $height;
		//disable encoding as entities needs to set the config value to false, as the default is true with the current ckeditor version
		$config['entities'] = false;
		$config['entities_latin'] = false;
		$config['editingBlock'] = true;
		$config['disableNativeSpellChecker'] = true;
		// we set allowedContent to true as the 4.1 contentFiltering system allows only activated features as content
		$config['allowedContent'] = true;

		$config['removePlugins'] = 'elementspath';

		$config['toolbarCanCollapse'] = true;
		$config['toolbarStartupExpanded'] = is_bool($expanded_toolbar) ? $expanded_toolbar :
			($expanded_toolbar === 'false' ? false : (boolean)$expanded_toolbar);

		$config['filebrowserBrowseUrl'] = self::get_filebrowserBrowseUrl($start_path);
		$config['filebrowserWindowHeight'] = 640;
		$config['filebrowserWindowWidth'] = 580;

		$config['language'] = self::get_lang();
		$config['enterMode'] = self::get_enter_mode();
		$config['skin'] = self::get_skin();

		$config['fontSize_sizes'] = '';
		$unit = $GLOBALS['egw_info']['user']['preferences']['common']['rte_font_unit'];
		if (empty($unit)) $unit = 'px';
		foreach(self::$font_size_options as $k => $v)
		{
			$config['fontSize_sizes'] .= $v.$unit.'/'.$k.$unit.';';
		}
	}

	/**
	 * Adds the spellchecker configuration to the options and writes the name of
	 * the spellchecker toolbar button to the "spellchecker_button" parameter
	 */
	private static function add_spellchecker_options(&$config, &$spellchecker_button, &$scayt_button)
	{
		//error_log(__METHOD__.__LINE__.' Spellcheck:'.$GLOBALS['egw_info']['server']['enabled_spellcheck']);
		if (isset($GLOBALS['egw_info']['server']['enabled_spellcheck']) && $GLOBALS['egw_info']['server']['enabled_spellcheck'])
		{
			// enable browsers native spellchecker as default, if e.g.: aspell fails
			// to use browsers native spellchecker, you have to hold CMD/CTRL button on rightclick to
			// access the browsers spell correction options
			if ($GLOBALS['egw_info']['server']['enabled_spellcheck']!='YesNoSCAYT') $config['disableNativeSpellChecker'] = false;
			$spellchecker_button = 'SpellChecker';
			if (!empty($GLOBALS['egw_info']['server']['aspell_path']) &&
				is_executable($GLOBALS['egw_info']['server']['aspell_path']) &&
				($GLOBALS['egw_info']['server']['enabled_spellcheck']!='YesUseWebSpellCheck' &&
				 $GLOBALS['egw_info']['server']['enabled_spellcheck']!='YesBrowserBased')
			)
			{
				$spellchecker_button = 'SpellCheck';
				$config['extraPlugins'] = "aspell";
			}
			if ($GLOBALS['egw_info']['server']['enabled_spellcheck']!='YesNoSCAYT' &&
				$GLOBALS['egw_info']['server']['enabled_spellcheck']!='YesBrowserBased'
			)
			{
				$scayt_button='Scayt';
				$config['scayt_autoStartup'] = true;
				$config['scayt_sLang'] = self::get_lang().'_'.self::get_country();
				$config['disableNativeSpellChecker'] = true; // only one spell as you type
			}
		}
		else
		{
			$config['scayt_autoStartup'] = false;
		}
	}

	/**
	 * Writes the toolbar configuration to the options which depends on the chosen
	 * mode and the spellchecker_button written by the add_spellchecker_options button
	 */
	private static function add_toolbar_options(&$config, $mode, $spellchecker_button, $scayt_button=false)
	{
		$config['toolbar'] = array();
		switch ($mode)
		{
			case 'advanced':
				$config['toolbar'][] = array('name' => 'document', 'items' => array('Source','DocProps','-','Preview','-','Templates'));
				$config['toolbar'][] = array('name' => 'clipboard', 'items' => array('Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'));
				if ($spellchecker_button||$scayt_button)
				{
					$configArray = array();
					if ($spellchecker_button) $configArray[] = $spellchecker_button;
					if ($scayt_button) $configArray[] = $scayt_button;
					$config['toolbar'][] = array('name' => 'tools', 'items' => $configArray);
				}
				$config['toolbar'][] = array('name' => 'edit', 'items' => array('Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'));

				$config['toolbar'][] = '/';

				$config['toolbar'][] = array('name' => 'basicstyles', 'items' => array('Bold','Italic','Underline','Strike','-','Subscript','Superscript'));
				$config['toolbar'][] = array('name' => 'justify', 'items' => array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'));
				$config['toolbar'][] = array('name' => 'paragraph', 'items' => array('BulletedList','NumberedList','-','Outdent','Indent'));
				$config['toolbar'][] = array('name' => 'links', 'items' => array('Link','Unlink','Anchor'));
				$config['toolbar'][] = array('name' => 'insert', 'items' => array('Maximize','Image','Table','HorizontalRule','SpecialChar'/*,'Smiley'*/));

				$config['toolbar'][] = '/';

				$config['toolbar'][] = array('name' => 'styles', 'items' => array('Style','Format','Font','FontSize'));
				$config['toolbar'][] = array('name' => 'colors', 'items' => array('TextColor','BGColor'));
				$config['toolbar'][] = array('name' => 'tools', 'items' => array('ShowBlocks','-','About'));
				break;

			case 'extended': default:
				$config['toolbar'][] = array('name' => 'clipboard', 'items' => array('Bold','Italic','Underline'));
				$config['toolbar'][] = array('name' => 'justify', 'items' => array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'));
				$config['toolbar'][] = array('name' => 'paragraph', 'items' => array('BulletedList','NumberedList'/*,'Smiley'*/,'Outdent','Indent','Undo','Redo'));
				$config['toolbar'][] = array('name' => 'clipboard', 'items' => array('Cut','Copy','Paste','PasteText','PasteFromWord','-','Print'));

				if ($mode == 'extended')
				{
					$config['toolbar'][] = array('name' => 'insert', 'items' => array('Image','Link','Unlink','Anchor'));
					if ($spellchecker_button||$scayt_button)
					{
						$configArray = array('Maximize');
						if ($spellchecker_button) $configArray[] = $spellchecker_button;
						if ($scayt_button) $configArray[] = $scayt_button;
						$config['toolbar'][] = array('name' => 'tools', 'items' => $configArray);
					}
					else
						$config['toolbar'][] = array('name' => 'insert', 'items' => array('Maximize'));//, 'Image', 'Table');

					$config['toolbar'][count($config['toolbar']) - 1][] = array('name' => 'insert', 'items' => array('Image', 'Table'));
				}
				else
				{
					if ($spellchecker_button||$scayt_button)
					{
						$configArray = array('Maximize');
						if ($spellchecker_button) $configArray[] = $spellchecker_button;
						if ($scayt_button) $configArray[] = $scayt_button;
						$config['toolbar'][] = array('name' => 'tools', 'items' => $configArray);
					}
					else
						$config['toolbar'][] = array('name' => 'tools', 'items' => array('Maximize'));
				}

				$config['toolbar'][] = '/';
				$config['toolbar'][] = array('name' => 'edit', 'items' => array('Find','Replace','-','SelectAll','RemoveFormat'));
				if ($mode == 'simple-withimage') $config['toolbar'][] = array('name' => 'links', 'items' => array('Image','Link','Unlink'));
				$config['toolbar'][] = array('name' => 'styles', 'items' => array('Format','Font','FontSize'));
				$config['toolbar'][] = array('name' => 'colors', 'items' => array('TextColor','BGColor'));
				$config['toolbar'][] = array('name' => 'tools', 'items' => array('ShowBlocks','-','About'));
		}
	}

	/**
	 * @see get_ckeditor_config
	 */
	public static function get_ckeditor_config_array($mode = '', $height = 400, $expanded_toolbar = true, $start_path = '')
	{
		// set for CK-Editor necessary CSP script-src attributes
		self::set_csp_script_src_attrs();

		// If not explicitly set, use preference for toolbar mode
		if(!$mode || trim($mode) == '') $mode = $GLOBALS['egw_info']['user']['preferences']['common']['rte_features'];
		$config = array();
		$spellchecker_button = null;

		self::add_default_options($config, $height, $expanded_toolbar, $start_path);
		self::add_spellchecker_options($config, $spellchecker_button, $scayt_button);
		self::add_toolbar_options($config, $mode, $spellchecker_button, $scayt_button);
		//error_log(__METHOD__."('$mode', $height, ".array2string($expanded_toolbar).") returning ".array2string($config));
		return $config;
	}

	/**
	 * Returns a json encoded string containing the configuration for the ckeditor.
	 * @param string $mode specifies the count of toolbar buttons available to the user. Possible
	 * values are 'simple', 'extended' and 'advanced'. All other values will default to 'simple'
	 * @param integer $height contains the height of the ckeditor in pixels
	 * @param boolean $expanded_toolbar specifies whether the ckeditor should start with an expanded toolbar or not
	 * @param string $start_path specifies
	 */
	public static function get_ckeditor_config($mode = '', $height = 400, $expanded_toolbar = true, $start_path = '')
	{
		return json_encode(self::get_ckeditor_config_array($mode, $height, $expanded_toolbar, $start_path));
	}

	/**
	 * Set for CK-Editor necessary CSP script-src attributes
	 *
	 * Get's called automatic from get_ckeditor_config(_array)
	 */
	public static function set_csp_script_src_attrs()
	{
		$attrs = array('unsafe-eval', 'unsafe-inline');

		// if webspellchecker is enabled in EGroupware config, allow access to it's url
		if (in_array($GLOBALS['egw_info']['server']['enabled_spellcheck'], array('True', 'YesUseWebSpellCheck')))
		{
			$attrs[] = 'http://svc.webspellchecker.net';
		}
		//error_log(__METHOD__."() egw_info[server][enabled_spellcheck]='{$GLOBALS['egw_info']['server']['enabled_spellcheck']}' --> attrs=".array2string($attrs));
		// tell framework CK Editor needs eval and inline javascript :(
		egw_framework::csp_script_src_attrs($attrs);
	}
}
