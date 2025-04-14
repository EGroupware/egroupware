<?php
/**
 * EGroupware Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Get the options for vfs_storage_mode, select the right one depending on vfs_fstab
 *
 * @param array $config
 * @return string
 */
function vfs_storage_mode_options($config)
{
	if (!isset($config['vfs_fstab']) || $config['vfs_fstab'] == json_encode($default=array(
		'/' => 'sqlfs://$host/',
		'/apps' => 'links://$host/apps',
	)) || $config['vfs_fstab'] == serialize($default))	// detect old serialized value too
	{
		$config['vfs_storage_mode'] = 'fs';
	}
	elseif($config['vfs_fstab'] == json_encode($default_db=array(
		'/' => 'sqlfs://$host/?storage=db',
		'/apps' => 'links://$host/apps?storage=db',
	)) || $config['vfs_fstab'] == serialize($default_db))	// detect old serialized value too
	{
		$config['vfs_storage_mode'] = 'db';
	}
	else
	{
		$config['vfs_storage_mode'] = 'custom';
	}
	//_debug_array(array_intersect_key($config,array('vfs_fstab'=>1,'vfs_storage_mode'=>1)));
	foreach(array(
		'fs' => lang('Filesystem (default)'),
		'db' => lang('Database').' (problems with files > 1MB)',
		'custom' => lang('Custom set via %1','filemanager/cli.php mount'),
	) as $name => $label)
	{
		if ($name != 'custom' || $name === $config['vfs_storage_mode'])	// dont show custom, if not custom
		{
			$options .= '<option value="'.$name.($name === $config['vfs_storage_mode'] ? '" selected="selected' : '').
				'">'.htmlspecialchars($label)."</options>\n";
		}
	}
	//echo "<pre>".htmlspecialchars($options)."</pre>\n";
	return $options;
}

function encryptalgo($config)
{
	if(@function_exists('mcrypt_list_algorithms'))
	{
		$listed = array();
		if(!isset($config['mcrypt_algo']))
		{
			$config['mcrypt_algo'] = 'tripledes';  /* MCRYPT_TRIPLEDES */
		}
		$algos = @mcrypt_list_algorithms();
		$found = False;

		$out = '';
		foreach($algos as $key => $value)
		{
			$found = True;
			/* Only show each once - seems this is a problem in some installs */
			if(!in_array($value,$listed))
			{
				if($config['mcrypt_algo'] == $value)
				{
					$selected = ' selected="selected"';
				}
				else
				{
					$selected = '';
				}
				$descr = strtoupper($value);

				$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
				$listed[] = $value;
			}
		}
		if(!$found)
		{
			/* Something is wrong with their mcrypt install or php.ini */
			$out = '<option value="">' . lang('no algorithms available') . '</option>' . "\n";
		}
	}
	else
	{
		$out = '<option value="tripledes">TRIPLEDES</option>' . "\n";
	}
	return $out;
}

function encryptmode($config)
{
	if(@function_exists('mcrypt_list_modes'))
	{
		$listed = array();
		if(!isset($config['mcrypt_mode']))
		{
			$config['mcrypt_mode'] = 'cbc'; /* MCRYPT_MODE_CBC */
		}
		$modes = @mcrypt_list_modes();
		$found = False;

		$out = '';
		foreach($modes as $key => $value)
		{
			$found = True;
			/* Only show each once - seems this is a problem in some installs */
			if(!in_array($value,$listed))
			{
				if($config['mcrypt_mode'] == $value)
				{
					$selected = ' selected="selected"';
				}
				else
				{
					$selected = '';
				}
				$descr = strtoupper($value);

				$out .= '<option value="' . $value . '"' . $selected . '>' . $descr . '</option>' . "\n";
				$listed[] = $value;
			}
		}
		if(!$found)
		{
			/* Something is wrong with their mcrypt install or php.ini */
			$out = '<option value="" selected="selected">' . lang('no modes available') . '</option>' . "\n";
		}
	}
	else
	{
		$out = '<option value="cbc" selected="selected">CBC</option>' . "\n";
	}
	return $out;
}

function passwdhashes($config,$return_hashes=false)
{
	$hashes = sql_passwdhashes($config,true);
	if (isset($hashes['crypt']))
	{
		$hashes['des'] = 'des (=crypt)';	// old LDAP name for crypt
	}
	return $return_hashes ? $hashes : _options_from($hashes, $config['ldap_encryption_type'] ? $config['ldap_encryption_type'] : 'des');
}

function sql_passwdhashes($config, $return_hashes=false, &$securest=null)
{
	$hashes = Api\Auth::passwdhashes($securest);

	return $return_hashes ? $hashes : _options_from($hashes, $config['sql_encryption_type'] ? $config['sql_encryption_type'] : 'md5');
}

/**
 * Make auth-types from setup_cmd_config available
 *
 * @param array $config
 * @return string
 */
function auth_types($config)
{
	return _options_from(setup_cmd_config::auth_types(),$config['auth_type']);
}
function auth_type_groupdav($config)
{
	return _options_from(setup_cmd_config::auth_types(),$config['auth_type_groupdav']);
}
function auth_type_activesync($config)
{
	return _options_from(setup_cmd_config::auth_types(),$config['auth_type_activesync']);
}
function auth_type_host($config)
{
	return _options_from(setup_cmd_config::auth_types(),$config['auth_type_host']);
}
function auth_type_security($config)
{
	return _options_from(setup_cmd_config::auth_types()+['fallback' => 'Fallback (LDAP->SQL)'],$config['auth_type_mail']);
}

/**
 * Make account-repository-types from setup_cmd_config available
 *
 * @param array $config
 * @return string
 */
function account_repository($config)
{
	return _options_from(setup_cmd_config::account_repositories($config['account_repository']), $config['account_repository']);
}

/**
 * Returns options string
 *
 * @param array $options value => label pairs
 * @param string $selected value of selected optino
 * @return string
 */
function _options_from(array $options,$selected)
{
	foreach($options as $value => $label)
	{
		$out .= '<option value="' . htmlspecialchars($value) . '"' .
			($selected == $value ? ' selected="selected"' : '') . '>' . $label . '</option>' . "\n";
	}
	return $out;
}