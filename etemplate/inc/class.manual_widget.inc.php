<?php
	/**
	 * eGroupWare  eTemplate Extension - Manual Widget
	 *
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 * @package etemplate
	 * @link http://www.egroupware.org
	 * @author Ralf Becker <RalfBecker@outdoor-training.de>
	 * @version $Id$
	 */

	/**
	 * eTemplate Extension: Manual widget
	 *
	 * This widget is an icon which opens the online help system (manual) in a popup.
	 *
	 * With the value or the name of the widget, you can specify a certain manual page
	 * (eg. ManualAddressbook). Additional params can be past to the manual app with
	 * $GLOBALS['egw_info']['flags']['params']['manual'].
	 * If no page is set after that two mechanisms the URL will contains the referer.
	 *
	 * @package etemplate
	 * @subpackage extensions
	 * @author RalfBecker-AT-outdoor-training.de
	 * @license GPL
	 */
	class manual_widget
	{
		/**
		 * exported methods of this class
		 * @var array $public_functions
		 */
		var $public_functions = array(
			'pre_process' => True,
		);
		/**
		 * availible extensions and there names for the editor
		 *
		 * @var string/array $human_name
		 */
		var $human_name = 'Manual';
		/**
		 * @var array
		 */

		/**
		 * Constructor of the extension
		 *
		 * @param string $ui '' for html
		 */
		function __construct($ui)
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
			$link = array('menuaction' => 'manual.uimanual.view');
			if (is_array($GLOBALS['egw_info']['flags']['params']['manual']))
			{
				$link = array_merge($link,$GLOBALS['egw_info']['flags']['params']['manual']);
			}
			$page = $cell['name'] ? $cell['name'] : $value;
			if (!empty($page))
			{
				$link['page'] = $page;
			}
			if (!$link['page'])
			{
				$link['referer'] = ($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			}
			$link = egw::link('/index.php',$link);

			$cell['type'] = 'button';
			$cell['size'] = 'manual-small';
			$cell['onclick'] = $GLOBALS['egw']->framework->open_manual_js($link).'; return false;';
			if (!$cell['label']) $cell['label'] = 'Manual';
			if (!$cell['help']) $cell['help'] = /*lang(*/'Open the online help.'/*)*/;

			if (!$cell['readonly'] && !isset($GLOBALS['egw_info']['user']['apps']['manual']))
			{
				$cell['readonly'] = true;	// we disable / remove the button, if use has no run-rights for the manual
			}
			return False;	// no extra label, label is tooltip for image
		}
	}
