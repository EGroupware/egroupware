<?php
 /**
 * Addressbook - configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.bocontacts.inc.php 21831 2006-06-14 16:53:14Z ralfbecker $ 
 */
 
function contact_repositories($config)
{
	$repositories = array('sql' => 'SQL');
	// check account-repository, contact-repository LDAP is only availible for account-repository == ldap
	if ($config['account_repository'] == 'ldap' || !$config['account_repository'] && $config['auth_type'] == 'ldap')
	{
		$repositories['ldap'] = 'LDAP';
		$repositories['sql-ldap'] = 'SQL --> LDAP ('.lang('read only').')';
	}
	$options = '';
	foreach($repositories as $repo => $label)
	{
		$options .= '<option value="'.$repo.'"'.($config['contact_repository'] == $repo ? ' selected="1">' : '>').
			$label."</option>\n";
	}
	return $options;
}
