<?php
/**
 * EGroupware minimal default template used to render login screen, if template does not provide own
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> rewrite in 12/2006
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;

/**
 * minimal default template used to render login screen, if template does not provide own
 */
class Minimal extends Api\Framework
{
	/**
	* Instance of the phplib Template class for the API's template dir (EGW_TEMPLATE_DIR)
	*
	* @var Template
	*/
	var $tpl;

	/**
	* Constructor
	*
	* @param string $template ='default' name of the template
	*/
	function __construct($template='default')
	{
		parent::__construct($template);		// call the constructor of the extended class
	}

	/**
	* Returns the html-header incl. the opening body tag
	*
	* @param array $extra =array() extra attributes passed as data-attribute to egw.js
	* @return string with html
	*/
	function header(array $extra=array())
	{
		// make sure header is output only once
		if (self::$header_done) return '';
		self::$header_done = true;

		// js stuff is not needed by login page or in popups
		$GLOBALS['egw_info']['flags']['js_link_registry'] =
			!(in_array($GLOBALS['egw_info']['flags']['currentapp'], array('login', 'logout', 'setup')) ||
				$GLOBALS['egw_info']['flags']['nonavbar'] === 'popup');
		//error_log(__METHOD__."() ".__LINE__.' js_link_registry='.array2string($GLOBALS['egw_info']['flags']['js_link_registry']).' '.function_backtrace());

		$this->send_headers();

		// catch error echo'ed before the header, ob_start'ed in the header.inc.php
		$content = ob_get_contents();
		ob_end_clean();

		// the instanciation of the template has to be here and not in the constructor,
		// as the old Template class has problems if restored from the session (php-restore)
		$this->tpl = new Template(EGW_SERVER_ROOT.$this->template_dir, 'keep');
		$this->tpl->set_file(array('_head' => 'head.tpl'));
		$this->tpl->set_block('_head','head');

		$this->tpl->set_var($this->_get_header($extra));

		$content .= $this->tpl->fp('out','head');

		return $content;
	}

	/**
	* Returns the html from the body-tag til the main application area (incl. opening div tag)
	*
	* @return string with html
	*/
	function navbar()
	{
		return '';
	}

	/**
	 * Return true if we are rendering the top-level EGroupware window
	 *
	 * A top-level EGroupware window has a navbar: eg. no popup and for a framed template (jdots) only frameset itself
	 *
	 * @return boolean $consider_navbar_not_yet_called_as_true=true
	 * @return boolean
	 */
	public function isTop($consider_navbar_not_yet_called_as_true=true)
	{
		unset($consider_navbar_not_yet_called_as_true);

		return true;
	}

	/**
	* Add menu items to the topmenu template class to be displayed
	*
	* @param array $app application data
	* @param mixed $alt_label string with alternative menu item label default value = null
	* @param string $urlextra string with alternate additional code inside <a>-tag
	* @access protected
	* @return void
	*/
	function _add_topmenu_item(array $app_data,$alt_label=null)
	{
		unset($app_data, $alt_label);
	}

	/**
	* Add info items to the topmenu template class to be displayed
	*
	* @param string $content html of item
	* @param string $id =null
	* @access protected
	* @return void
	*/
	function _add_topmenu_info_item($content, $id=null)
	{
		unset($content, $id);
	}

	/**
	* Returns the html from the closing div of the main application area to the closing html-tag
	*
	* @return string html or null if no footer needed/wanted
	*/
	function footer()
	{
		static $footer_done=0;
		if ($footer_done++) return;	// prevent multiple footers, not sure we still need this (RalfBecker)

		return "</body>\n</html>\n";	// close body and html tag, eg. for popups
	}

	/**
	* Parses one sidebox menu and add's the html to $this->sidebox_content for later use by $this->navbar
	*
	* @param string $appname
	* @param string $menu_title
	* @param array $file
	* @param string $type =null 'admin', 'preferences', 'favorites', ...
	*/
	function sidebox($appname,$menu_title,$file,$type=null)
	{
		unset($appname, $menu_title, $file, $type);
	}

	/**
	* called by hooks to add an icon in the topmenu info location
	*
	* @param string $id unique element id
	* @param string $icon_src src of the icon image. Make sure this nog height then 18pixels
	* @param string $iconlink where the icon links to
	* @param boolean $blink set true to make the icon blink
	* @param mixed $tooltip string containing the tooltip html, or null of no tooltip
	* @access public
	* @return void
	*/
	function topmenu_info_icon($id,$icon_src,$iconlink,$blink=false,$tooltip=null)
	{
		unset($id, $icon_src, $iconlink, $blink, $tooltip);
	}

	/**
	 * Return javascript (eg. for onClick) to open manual with given url
	 *
	 * @param string $url
	 * @return string
	 */
	function open_manual_js($url)
	{
		return "egw_openWindowCentered2('$url','manual',800,600,'yes')";
	}
}
