<?php
 /**
 * eGroupware - Addressbook configuration
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Hook to query available contact repositories for addressbook config
 *
 * @param array $config
 * @return string with options
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

/**
 * Hook to get available fileas types selectbox for addressbook config
 *
 * @param array $config
 * @return string html
 */
function select_fileas($config)
{
	$bocontacts = new addressbook_bo();

	return html::select('fileas','',array('' => lang('Set only full name'))+$bocontacts->fileas_options(),true);
}


/**
 * Hook to get a multiselect box with all fieleds of onw-account-acl for addressbook config
 *
 * @param array $config
 * @return string html
 */
function own_account_acl($config)
{
	$bocontacts = new addressbook_bo();
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
		foreach(config::get_customfields('addressbook') as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}
	}
	return html::checkbox_multiselect('newsettings[own_account_acl]',$config['own_account_acl'],$fields,true,'',4);
}

/**
 * Hook to get a multiselect box with all fields of org-fields-to-update for addressbook config
 *
 * @param array $config
 * @return string html
 */
function org_fileds_to_update($config)
{
	$bocontacts = new addressbook_bo();
	$supported_fields = $bocontacts->get_fields('supported',null,0);	// fields supported by the backend (ldap schemas!)
	// get the list of account fields
	$fields = array();
	foreach($bocontacts->contact_fields as $field => $label)
	{
		// some fields never making sense for an organisation
		if (!in_array($field,array('id','tid','owner','created','creator','modified','modifier','private','n_prefix','n_given','n_middle','n_family','n_suffix','n_fn')))
		{
			$fields[$field] = $label;
		}
	}

	if ($config['contact_repository'] != 'ldap')	// no custom-fields in ldap
	{
		foreach(config::get_customfields('addressbook') as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}
	}
	
	// Remove country codes as an option, it will be added by BO constructor
	if(in_array('adr_one_countrycode', $supported_fields))
	{
		unset($fields['adr_one_countrycode']);
		unset($fields['adr_two_countrycode']);
	}
	return html::checkbox_multiselect('newsettings[org_fileds_to_update]',
		$config['org_fileds_to_update'] ? $config['org_fileds_to_update'] : $bocontacts->org_fields,$fields,true,'',4);
}

/**
 * Hook to get a multiselect box with all fieleds of fields used for copying for addressbook config
 *
 * @param array $config
 * @return string html
 */
function copy_fields($config)
{
	$bocontacts = new addressbook_bo();
	$supported_fields = $bocontacts->get_fields('supported',null,0);	// fields supported by the backend (ldap schemas!)
	// get the list of account fields
	$fields = array();
	foreach($bocontacts->contact_fields as $field => $label)
	{
		// some fields the user should never be allowed to copy or are coverted by an other attribute (n_fn for all n_*)
		if (!in_array($field,array('id','tid','created','creator','modified','modifier','account_id','uid','etag','n_fn')))
		{
			$fields[$field] = $label;
		}
	}
	if ($config['contact_repository'] != 'ldap')	// no custom-fields in ldap
	{
		foreach(config::get_customfields('addressbook') as $name => $data)
		{
			$fields['#'.$name] = $data['label'];
		}
	}
	// Remove country codes as an option, it will be added by UI constructor
	if(in_array('adr_one_countrycode', $supported_fields))
	{
		unset($fields['adr_one_countrycode']);
		unset($fields['adr_two_countrycode']);
	}

	return html::checkbox_multiselect('newsettings[copy_fields]',
		$config['copy_fields'] ? $config['copy_fields'] : addressbook_ui::$copy_fields,
		$fields,true,'',4
	);
}
