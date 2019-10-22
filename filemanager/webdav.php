<?php
/**
 * EGroupware - old WebDAV access
 *
 * Just for backward compatibility of the url: better use the webdav.php in the root.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-19 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 */

// we permanent redirect to the correct URL instead of processing the request under the old url
// (processing under misses certain special handling like not access_logging the request)
http_response_code(301);
header('Location: '.str_replace('/filemanager/webdav.php/', '/webdav.php/', $_SERVER['REQUEST_URI']));
