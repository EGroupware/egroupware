<?php
/**
 * EGroupware - messenger - setup definitions
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @package messenger
 * @subpackage setup
 * @copyright (c) 2014 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: $
 */

$setup_info['messenger']['name'] = 'messenger';
$setup_info['messenger']['version']   = '15.1';
$setup_info['messenger']['app_order'] = 5;
$setup_info['messenger']['enable']    = 1;
$setup_info['messenger']['index']     = 'messenger.messenger_ui.index&ajax=true';


/* The hooks this app includes, needed for hooks registration */
$setup_info['messenger']['hooks']['search_link'] = 'messenger_hooks::search_link';
$setup_info['messenger']['hooks']['sidebox_menu'] = 'messenger_hooks::sidebox_menu';

$setup_info['messenger']['author'] =
$setup_info['messenger']['maintainer'] = array(
	'name'  => 'Hadi Nategh',
	'email' => 'hn@stylite.de'
);
$setup_info['messenger']['license']  = 'GPL';
$setup_info['messenger']['description'] =
'Collaboration Media for egroupware';
$setup_info['messenger']['note'] =
'The messenger application is sponsored by:<ul>
<li> <a href="http://www.stylite.de" target="_blank">Stylite AG</a></li>
<li> <a href="http://www.outdoor-training.de" target="_blank">Outdoor Unlimited Training GmbH</a></li>
</ul>';

/* Dependencies for this app to work */
$setup_info['messenger']['depends'][] = array(
	 'appname' => 'phpgwapi',
	 'versions' => Array('14.1')
);
$setup_info['messenger']['depends'][] = array(
	 'appname' => 'etemplate',
	 'versions' => Array('14.1')
);
