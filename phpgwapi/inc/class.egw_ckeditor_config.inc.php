<?php
/**
 * eGroupWare - Class which generates JSON encoded configuration for the ckeditor
 *
 * @link http://www.egroupware.org
 * @author RalfBecker-AT-outdoor-training.de
 * @author Andreas Stoeckel <as-AT-stylite.de>
 * @package api
 * @subpackage tools
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

class egw_ckeditor_config
{
	private static $lang = null;
	private static $country = null;
	private static $enterMode = null;
	private static $skin = null;

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
		return $GLOBALS['egw_info']['server']['webserver_url'].'/phpgwapi/js/ckeditor3/';
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
				case 'silver':
					$skin = "v2";
					break;
				case 'default':
					$skin = "kama";
					break;
				case 'office2003':
					$skin = "office2003";
					break;				
			}

			//Check whether the skin actually exists, if not, switch to a default
			if (!(file_exists($basePath.'skins/'.$skin) || file_exists($skin) || !empty($skin)))
				$skin = "office2003";

			self::$skin = $skin;
		}

		return self::$skin;
	}

	/**
	 * Returns the URL of the filebrowser
	 */
	private static function get_filebrowserBrowseUrl($start_path = '')
	{
		return $GLOBALS['egw_info']['server']['webserver_url'].'/index.php?menuaction=filemanager.filemanager_select.select&mode=open&method=ckeditor_return'
		.($start_path != '' ? '&path='.$start_path : '');
	}

	/**
	 * Adds all "easy to write" options to the configuration
	 */
	private static function add_default_options(&$config, $height, $expanded_toolbar, $start_path)
	{
		//Convert the pixel height to an integer value
		$config['resize_enabled'] = false;
		$config['height'] = (int)$height;

		$config['entities'] = true;
		$config['entities_latin'] = true;
		$config['editingBlock'] = true;
		$config['disableNativeSpellChecker'] = true;

		$config['removePlugins'] = 'elementspath';

		$config['toolbarStartupExpanded'] = $expanded_toolbar;

		$config['filebrowserBrowseUrl'] = self::get_filebrowserBrowseUrl($start_path);
		$config['filebrowserWindowHeight'] = 640;
		$config['filebrowserWindowWidth'] = 580;

		$config['language'] = self::get_lang();
		$config['enterMode'] = self::get_enter_mode();
		$config['skin'] = self::get_skin();
	}

	/**
	 * Adds the spellchecker configuration to the options and writes the name of
	 * the spellchecker toolbar button to the "spellchecker_button" parameter
	 */
	private static function add_spellchecker_options(&$config, &$spellchecker_button, &$scayt_button)
	{
		//error_log(__METHOD__.__LINE__.' Spellcheck:'.$GLOBALS['egw_info']['server']['enabled_spellcheck']);
		if (isset($GLOBALS['egw_info']['server']['enabled_spellcheck']))
		{
			$spellchecker_button = 'SpellChecker';
			if (!empty($GLOBALS['egw_info']['server']['aspell_path']) &&
				is_executable($GLOBALS['egw_info']['server']['aspell_path']) &&
				!($GLOBALS['egw_info']['server']['enabled_spellcheck']=='YesUseWebSpellCheck')
			)
			{
				$spellchecker_button = 'SpellCheck';
				$config['extraPlugins'] = "aspell";
			}
			if (!($GLOBALS['egw_info']['server']['enabled_spellcheck']=='YesNoSCAYT'))
			{
				$scayt_button='Scayt';
				$config['scayt_autoStartup'] = true;
				$config['scayt_sLang'] = self::get_lang().'_'.self::get_country();
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
				$config['toolbar'][] = array('Source','DocProps','-','Preview','-','Templates');
				$config['toolbar'][] = array('Cut','Copy','Paste','PasteText','PasteFromWord','-','Print');
				if ($spellchecker_button)
					$config['toolbar'][count($config['toolbar']) - 1][] = $spellchecker_button;
				if ($scayt_button)
					$config['toolbar'][count($config['toolbar']) - 1][] = $scayt_button;
				$config['toolbar'][] = array('Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat');

				$config['toolbar'][] = '/';

				$config['toolbar'][] = array('Bold','Italic','Underline','Strike','-','Subscript','Superscript');
				$config['toolbar'][] = array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock');
				$config['toolbar'][] = array('BulletedList','NumberedList','-','Outdent','Indent');
				$config['toolbar'][] = array('Link','Unlink','Anchor');
				$config['toolbar'][] = array('Maximize','Image','Table','HorizontalRule','SpecialChar'/*,'Smiley'*/);

				$config['toolbar'][] = '/';

				$config['toolbar'][] = array('Style','Format','Font','FontSize');
				$config['toolbar'][] = array('TextColor','BGColor');
				$config['toolbar'][] = array('ShowBlocks','-','About');
				break;

			case 'extended': default:
				$config['toolbar'][] = array('Bold','Italic','Underline');
				$config['toolbar'][] = array('JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock');
				$config['toolbar'][] = array('BulletedList','NumberedList'/*,'Smiley'*/,'Outdent','Indent','Undo','Redo');
				$config['toolbar'][] = array('Cut','Copy','Paste','PasteText','PasteFromWord','-','Print');

				if ($mode == 'extended')
				{
					$config['toolbar'][] = array('Link','Unlink','Anchor');
					$config['toolbar'][] = array('Find', 'Replace');
					if ($spellchecker_button)
						$config['toolbar'][] = array('Maximize', $spellchecker_button);//, 'Image', 'Table');
					else
						$config['toolbar'][] = array('Maximize');//, 'Image', 'Table');
					if ($scayt_button)
						$config['toolbar'][count($config['toolbar']) - 1][] = $scayt_button;
					$config['toolbar'][count($config['toolbar']) - 1][] = array('Image', 'Table');
				}
				else
				{
					if ($spellchecker_button)
						$config['toolbar'][] = array('Maximize', $spellchecker_button);
					else
						$config['toolbar'][] = array('Maximize');
					if ($scayt_button)
						$config['toolbar'][count($config['toolbar']) - 1][] = $scayt_button;
				}

				$config['toolbar'][] = '/';
				$config['toolbar'][] = array('Find','Replace','-','SelectAll','RemoveFormat');
				$config['toolbar'][] = array('Format','Font','FontSize');
				$config['toolbar'][] = array('TextColor','BGColor');
				$config['toolbar'][] = array('ShowBlocks','-','About');
		}
	}

	/**
	 * @see get_ckeditor_config
	 */
	public static function get_ckeditor_config_array($mode = 'simple', $height = 400, $expanded_toolbar = true, $start_path = '')
	{
		$config = array();
		$spellchecker_button = null;

		self::add_default_options($config, $height, $expanded_toolbar, $start_path);
		self::add_spellchecker_options($config, $spellchecker_button, $scayt_button);
		self::add_toolbar_options($config, $mode, $spellchecker_button, $scayt_button);

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
	public static function get_ckeditor_config($mode = 'simple', $height = 400, $expanded_toolbar = true, $start_path = '')
	{
		return json_encode(self::get_ckeditor_config_array($mode, $height, $expanded_toolbar, $start_path));
	}
}
