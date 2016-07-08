<?php
/**
 * EGgroupware administration
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

$GLOBALS['egw_info']['flags'] = array(
	'noheader'   => True,
	'nonavbar'   => True,
	'currentapp' => 'admin'
);
include('../header.inc.php');

if ($GLOBALS['egw']->acl->check('info_access',1,'admin'))
{
	$GLOBALS['egw']->redirect_link('/index.php');
}

$cache_provider = Api\Cache::getProvider();
$cache_info = '<table><tbody><tr>';
$cache_info .= '<td class="e">EGroupware caching provider</td><td class="v">'.Api\Cache::getProvider();
if ($cache_provider == 'EGroupware\\Api\\Cache\\Apcu')
{
	$cache_info .= ' <a href="'.htmlspecialchars(Api\Egw::link('/admin/apcu.php')).'">View APCu stats</a>';
}
$cache_info .= '</td></tr></tbody></table>'."\n";

ob_start();
phpinfo();
$phpinfo = ob_get_clean();

$info = str_ireplace('<body><div class="center">', '<body><div class="center">'."\n".$cache_info, $phpinfo);
if ($info == $phpinfo)
{
	echo $cache_info;
}
echo $info;
