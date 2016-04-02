<?php
/**
 * EGroupware - API Setup
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage setup
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/* Basic information about this app */
$setup_info['phpgwapi']['name']      = 'phpgwapi';
$setup_info['phpgwapi']['title']     = 'EGroupware old API';
$setup_info['phpgwapi']['version']   = '14.3.908';
$setup_info['phpgwapi']['versions']['current_header'] = '1.29';
$setup_info['phpgwapi']['enable']    = 3;
$setup_info['phpgwapi']['app_order'] = 1;
$setup_info['phpgwapi']['license'] = 'GPL';
$setup_info['phpgwapi']['maintainer']	= $setup_info['phpgwapi']['author']	= array(
	'name'  => 'EGroupware coreteam',
	'email' => 'egroupware-developers@lists.sourceforge.net',
);

/* The tables this app creates */
$setup_info['phpgwapi']['tables'][]  = 'egw_config';
$setup_info['phpgwapi']['tables'][]  = 'egw_applications';
$setup_info['phpgwapi']['tables'][]  = 'egw_acl';
$setup_info['phpgwapi']['tables'][]  = 'egw_accounts';
$setup_info['phpgwapi']['tables'][]  = 'egw_preferences';
$setup_info['phpgwapi']['tables'][]  = 'egw_access_log';
$setup_info['phpgwapi']['tables'][]  = 'egw_languages';
$setup_info['phpgwapi']['tables'][]  = 'egw_lang';
$setup_info['phpgwapi']['tables'][]  = 'egw_categories';
$setup_info['phpgwapi']['tables'][]  = 'egw_history_log';
$setup_info['phpgwapi']['tables'][]  = 'egw_async';
$setup_info['phpgwapi']['tables'][]  = 'egw_links';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_extra';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook_lists';
$setup_info['phpgwapi']['tables'][]  = 'egw_addressbook2list';
$setup_info['phpgwapi']['tables'][]  = 'egw_sqlfs';
$setup_info['phpgwapi']['tables'][]  = 'egw_index_keywords';
$setup_info['phpgwapi']['tables'][]  = 'egw_index';
$setup_info['phpgwapi']['tables'][]  = 'egw_cat2entry';
$setup_info['phpgwapi']['tables'][]  = 'egw_locks';
$setup_info['phpgwapi']['tables'][]  = 'egw_sqlfs_props';
$setup_info['phpgwapi']['tables'][]  = 'egw_customfields';
$setup_info['phpgwapi']['tables'][]  = 'egw_sharing';
