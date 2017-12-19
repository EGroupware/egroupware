/**
 * EGroupware - Collabeditor - dojo configuration
 *
 * @link http://www.egroupware.org
 * @package Collabeditor
 * @author Hadi Nategh <hn-AT-egroupware.de>
 * @copyright (c) 2016 by Hadi Nategh <hn-AT-egroupware.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */
(function(){
	// Dojo configuration needed for filemanager collab editor and
	// it should be load before app.js
	var basedir = egw.webserverUrl+'/collabeditor/js/webodf/collab';
	var usedLocale = "C";
	if (navigator && navigator.language && navigator.language.match(/^(de)/)) {
		usedLocale = navigator.language.substr(0,2);
	}
	// dojo Lib configuration needs to be set before dojo.js is loaded
	window.dojoConfig = {
		locale: usedLocale,
		baseUrl: basedir,
		paths: {
			"webodf/editor": basedir,
			"dijit": basedir + "/dijit",
			"dojo": basedir + "/dojo",
			"dojox": basedir + "/dojox",
			"resources": basedir + "/resources",
			"egwCollab": basedir
		}
	}
})();