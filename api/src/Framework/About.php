<?php
/**
 * EGroupware: About informations
 *
 * rewrite of the old PHPLib based about page
 * it now uses eTemplate2
 *
 * LICENSE:  GPL.
 *
 * @package     api
 * @subpackage  about
 * @author      Sebastian Ebling <hudeldudel@php.net>
 * @author      Ralf Becker <RalfBecker@outdoor-training.de>
 * @license     http://www.gnu.org/copyleft/gpl.html
 * @link        http://www.egroupware.org
 * @version     SVN: $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api;
use EGroupware\Api\Etemplate;

/**
 * Shows informations about eGroupWare
 *  - general information
 *  - installed applications
 *  - installed templates
 *  - installed languages
 *
 * Usage:
 * <code>
 *  $aboutPage = new about();
 * </code>
 * The constuctor will do all
 *
 * There is no typical so, bo ui structure, this class is all in one
 *
 * @since		1.4
 */
class About
{
	/**
	 * Methods callable by menuaction GET parameter
	 *
	 * @var array
	 */
	var $public_functions = array(
		'index' => true,
		'detail' => true,
	);

	/**
	 * Constructor
	 */
	function __construct()
	{
		Api\Translation::add_app('admin');
	}


	/**
	 * output of list view
	 * collects data and shows a tabbed view that lists
	 *  - general informations
	 *  - installed applications
	 *  - installed templates
	 *  - installed languages
	 */
	function index()
	{
		$text_content = str_replace('GPLLINK',self::$knownLicenses['GPL'][0],'
<p><b>EGroupware is an enterprise ready multilingual groupware solution</b> for your team.
It enables you to manage and share your e-mail, contacts, appointments, tasks
and files within your organisation.</p>
<p>The <b>native web-interface</b> for EGroupware allows to access your data from any platform wherever you are.
Chrome, Firefox and Safari are preferred internet browsers for best EGroupware experience.</p>
<p><EGroupware is platform independent. The server runs on Linux, Mac, Windows and many more other operating systems.
EGroupware can be integrated easily into existing authentication solutions such as LDAP or Active Directory.</p>
<p>EGroupware offers CalDAV, CardDAV, WebDAV and Active Sync for synchronising data to your smartphone or desktop client.</p>
<p>EGroupware is developed by EGroupware GmbH with contributions from community developers.</p>
<p>For more information visit the <b><a href="http://www.egroupware.org" target="_blank">EGroupware Website</a></b></p>
<p><b>Support is available</b> via the following options:
<ul>
<li><a href="https://www.egroupware.org/egroupware-support/" target="_blank">Commercial support and consulting</a> from EGroupware GmbH</li>
<li><a href="https://www.egroupware.org/egroupware-reseller/" target="_blank">EGroupware partners</a></li>
<li><a href="https://help.egroupware.org/" target="_blank">Community forum</a></li>
</ul>
</p>');

		// get informations about the applications
		$apps = array();
		foreach (array_keys(isset($GLOBALS['egw_info']['user']['apps']['admin']) ?
			$GLOBALS['egw_info']['apps'] : $GLOBALS['egw_info']['user']['apps']) as $app)
		{
			if (($app_info = $this->_getParsedAppInfo($app)))
			{
				$apps[] = $app_info;
			}
		}
		usort($apps, function($a, $b)
		{
			return strcasecmp($a['title'], $b['title']);
		});
		array_unshift($apps, false);	// first empty row for eTemplate

		// putting templates below apps
		foreach($GLOBALS['egw']->framework->list_templates(true) as $info)
		{
			$apps[] = $this->_getParsedTemplateInfo($info);
		}

		// fill content array for eTemplate
		$changelog = null;
		Api\Framework::api_version($changelog);
		$content = array(
			'apiVersion'	=> '<p>'.lang('EGroupware version').
				' <b>'.$GLOBALS['egw_info']['server']['versions']['maintenance_release'].'</b></p>',
			'applications'	=> $apps,
			'text_content'  => $text_content,
			'changelog'     => file_exists($changelog) ? file_get_contents($changelog) : 'not available',
		);

		$tmpl = new Etemplate('api.about.index');
		$tmpl->exec('api.'.__CLASS__.'.index', $content);
	}

	/**
	 * parse template informations from setup.inc.php file
	 *
	 * @param   array  $info	template template info
	 * @return  array   html formated informations about author(s),
	 *                  maintainer(s), version, license of the
	 *                  given application
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getParsedTemplateInfo($info)
	{
		// define the return array
		$info['image'] = file_exists(EGW_SERVER_ROOT.'/'.$info['icon']) ? $GLOBALS['egw_info']['server']['webserver_url'].'/'.$info['icon'] : Api\Image::find('thisdoesnotexist',array('navbar','nonav'));
		$info['author'] = $this->_getHtmlPersonalInfo($info, 'author');
		$info['maintainer'] = $this->_getHtmlPersonalInfo($info, 'maintainer');

		return $this->_linkLicense($info);
	}

	/**
	 * parse application informations from setup.inc.php file
	 *
	 * @param	string	$app	application name
	 * @return	array	html formated informations about author(s),
	 *					maintainer(s), version, license of the
	 *					given application
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getParsedAppInfo($app)
	{
		// we read all setup files once, as no every app has it's own file
		static $setup_info=null;
		if (is_null($setup_info))
		{
			foreach(array_keys($GLOBALS['egw_info']['apps']) as $_app)
			{
				if (file_exists($file = EGW_INCLUDE_ROOT.'/'.$_app.'/setup/setup.inc.php'))
				{
					include($file);
				}
			}
		}
		if (!isset($setup_info[$app]) || !is_array($setup_info[$app])) return null;	// app got eg. removed in filesystem

		$app_info  = array_merge($GLOBALS['egw_info']['apps'][$app], $setup_info[$app]);

		// define the return array
		$icon_app = isset($app_info['icon_app']) ? $app_info['icon_app'] : $app;
		$icon = isset($app_info['icon']) ? $app_info['icon'] : 'navbar';
		$ret = $app_info+array(
			'app'           => $app,
			'title'         => lang(!empty($app_info['title']) ? $app_info['title'] : $app),
			'image'			=> Api\Image::find($icon_app, $icon) ? $icon_app.'/'.$app : 'api/nonav',
			'author'		=> '',
			'maintainer'	=> '',
			'version'		=> $app_info['version'],
			'license'		=> '',
			'description'	=> '',
			'note'			=> ''
		);

		if (isset($setup_info[$app]))
		{
			$ret['author'] = $this->_getHtmlPersonalInfo($setup_info[$app], 'author');
			$ret['maintainer'] = $this->_getHtmlPersonalInfo($setup_info[$app], 'maintainer');
			if ($app_info['version'] != $setup_info[$app]['version']) $ret['version'] .= ' ('.$setup_info[$app]['version'].')';
			$ret['license'] = $setup_info[$app]['license'];
			$ret['description'] = $setup_info[$app]['description'];
			$ret['note'] = $setup_info[$app]['note'];
		}

		return $this->_linkLicense($ret);
	}


	/**
	 * helper to parse author and maintainer info from setup_info array
	 *
	 * @param	array	$setup_info	setup_info[$app] array
	 *								($GLOBALS['egw_info']['template'][$template] array for template informations)
	 * @param	string	$f			'author' or 'maintainer', default='author'
	 * @return	string	html formated informations about author/maintainer
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _getHtmlPersonalInfo($setup_info, $f = 'author')
	{
		$authors = array();
			// get the author(s)
			if ($setup_info[$f]) {
				// author is set
				if (!is_array($setup_info[$f])) {
					// author is no array
					$authors[0]['name'] = $setup_info[$f];
					if ($setup_info[$f.'_email']) {
						$authors[0]['email'] = $setup_info[$f.'_email'];
					}
					if ($setup_info[$f.'_url']) {
						$authors[0]['url'] = $setup_info[$f.'_url'];
					}

				} else {
					// author is array
					if ($setup_info[$f]['name']) {
						// only one author
						$authors[0]['name'] = $setup_info[$f]['name'];
						if ($setup_info[$f]['email']) {
							$authors[0]['email'] = $setup_info[$f]['email'];
						}
						if ($setup_info[$f]['url']) {
							$authors[0]['url'] = $setup_info[$f]['url'];
						}
					} else {
						// may be more authors
						foreach (array_keys($setup_info[$f]) as $number) {
							if ($setup_info[$f][$number]['name']) {
									$authors[$number]['name'] = $setup_info[$f][$number]['name'];
							}
							if ($setup_info[$f][$number]['email']) {
									$authors[$number]['email'] = $setup_info[$f][$number]['email'];
							}
							if ($setup_info[$f][$number]['url']) {
									$authors[$number]['url'] = $setup_info[$f][$number]['url'];
							}
						}
					}
				}
			}

		// html format authors
		$s = '';
		foreach ($authors as $author) {
			if ($s != '') {
					$s .= '<br />';
			}
			$s .= lang('name').': '.$author['name'];
			if ($author['email']) {
					$s .= '<br />'.lang('email').': <a href="mailto:'.$author['email'].'">'.$author['email'].'</a>';
			}
			if ($author['url']) {
					$s .= '<br />'.lang('url').': <a href="'.$author['url'].'" target="_blank">'.$author['url'].'</a>';
			}
		}
		return $s;
	}

	static public $knownLicenses = array(
		'GPL'	=> array('https://opensource.org/licenses/gpl-license.php','GNU General Public License version 2.0 or (at your option) any later version'),
		'GPL2'	=> array('https://opensource.org/licenses/gpl-2.0.php','GNU General Public License version 2.0'),
		'GPL3'	=> array('https://opensource.org/licenses/gpl-3.0.php','GNU General Public License version 3.0'),
		'LGPL'	=> array('https://opensource.org/licenses/lgpl-2.1.php','GNU Lesser General Public License, version 2.1'),
		'LGPL3'	=> array('https://opensource.org/licenses/lgpl-3.0.php','GNU Lesser General Public License, version 3.0'),
		'PHP'   => array('https://opensource.org/licenses/php.php','PHP License'),
		'AGPL3' => array('https://opensource.org/licenses/AGPL-3.0','GNU Affero General Public License'),
	);

	/**
	 * surround license string with link to license if it is known
	 *
	 * @param   array  $info	containing value for key "license" with either string of license-name of array with keys "name" and "url"
	 * @return  array  with added values for keys "license_url", "license_title"
	 *
	 * @access  private
	 * @since   1.4
	 */
	function _linkLicense($info)
	{
		$license = $info['license'];
		$name = is_array($license) ? $license['name'] : $license;
		$url = is_array($license) && isset($license['url']) ? $license['url'] : '';
		$title = is_array($license) && isset($license['title']) ? $license['title'] : '';

		if (isset(self::$knownLicenses[strtoupper($name)]))
		{
			if (empty($url)) $url = self::$knownLicenses[$name=strtoupper($name)][0];
			if (empty($title)) $title = self::$knownLicenses[$name=strtoupper($name)][1];
		}

		return array(
			'license' => $name ? $name : 'none',
			'license_url' => $url,
			'license_title' => $title,
		)+$info;
	}
}