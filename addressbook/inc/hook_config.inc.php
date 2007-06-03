<?php
 /**
 * Addressbook - configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
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

function own_account_acl($config)
{
	$bocontacts =& CreateObject('addressbook.bocontacts');
	$supported_fields = $bocontacts->get_fields('supported',null,0);	// fields supported by the backend (ldap schemas!)
	// get the list of account fields
	$fields = array();
	foreach($bocontacts->contact_fields as $field => $label)
	{
		// some fields the user should never be allowed to edit or are covert by an other attribute (n_fn for all n_*)
		if (!in_array($field,array('id','tid','owner','created','creator','modified','modifier','private','n_prefix','n_given','n_middle','n_family','n_suffix')))
		{
			$fields[$field] = $label;
		}
	}
	$fields['link_to'] = 'Links';

	if ($config['account_repository'] != 'ldap')	// no custom-fields in ldap
	{
		$custom =& CreateObject('admin.customfields','addressbook');
		foreach($custom->get_customfields() as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}
	}
	if (!is_object($GLOBALS['egw']->html))
	{
		$GLOBALS['egw']->html =& CreateObject('phpgwapi.html');
	}
	return $GLOBALS['egw']->html->checkbox_multiselect('newsettings[own_account_acl]',$config['own_account_acl'],$fields,true,'',8);
}
