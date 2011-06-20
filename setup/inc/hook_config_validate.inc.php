<?php
/**
 * Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/*
  Set a global flag to indicate this file was found by setup/config.php.
  config.php will unset it after parsing the form values.
*/
$GLOBALS['egw_info']['server']['found_validation_hook'] = True;

/**
 * Set vfs_fstab depending from what the user selected for vfs_storage_mode
 *
 * @param array $settings
 */
function vfs_storage_mode($settings)
{
	switch($settings['vfs_storage_mode'])
	{
		case 'fs':
			config::save_value('vfs_fstab','','phpgwapi');
			break;
		case 'db':
			config::save_value('vfs_fstab',serialize(array(
				'/' => 'sqlfs://$host/?storage=db',
				'/apps' => 'links://$host/apps?storage=db',
			)),'phpgwapi');
			break;
	}
}

function mail_server($settings)
{
	if (!$settings['smtp_server'])
	{
		$GLOBALS['config_error'] = lang('Missing or uncomplete mailserver configuration');
	}
	if (@file_exists('../emailadmin/inc/class.emailadmin_bo.inc.php') && $GLOBALS['egw_setup']->table_exist(array('egw_emailadmin')))
	{
		$emailadmin = new emailadmin_bo(false,false);	// false=no session stuff
		if (is_object($emailadmin))
		{
			$emailadmin->setDefaultProfile($settings);
		}
	}
}

function temp_dir($settings)
{
	if (!setup_detection::check_dir($settings['temp_dir'],$error_msg))
	{
		$GLOBALS['config_error'] = lang("Your temporary directory '%1' %2",$settings['temp_dir'],$error_msg);
	}
}

function files_dir($settings)
{
	if ($settings['file_repository'] == 'sql' && $settings['file_store_contents'] == 'filesystem' &&
		!setup_detection::check_dir($settings['files_dir'],$error_msg,true))
	{
		$GLOBALS['config_error'] = lang("Your files directory '%1' %2",$settings['files_dir'],$error_msg);
	}
}

function backup_dir(&$settings)
{
	if (@is_writeable($settings['files_dir']) && !$settings['backup_dir'] && $settings['file_store_contents'] == 'filesystem')
	{
		$settings['backup_dir'] = $settings['files_dir'].'/db_backup';
	}
	if (!setup_detection::check_dir($settings['backup_dir'],$error_msg,true))
	{
		$GLOBALS['config_error'] = lang("Your backup directory '%1' %2",$settings['backup_dir'],$error_msg);
	}
}

function _mcrypt_test_module_mode($module,$mode)
{
	/* Data */
	$key = 'this is a very long key, even too long for the cipher';
	$plain_text = 'very important data';

	/* Open module, and create IV */
	if(!$GLOBALS['td'] = @mcrypt_module_open($module, '',$mode, ''))
	{
		@mcrypt_module_close($GLOBALS['td']);
		return False;
	}
	$key = substr($key, 0, mcrypt_enc_get_key_size($GLOBALS['td']));
	$iv_size = mcrypt_enc_get_iv_size($GLOBALS['td']);
	$iv = @mcrypt_create_iv($iv_size, MCRYPT_RAND);

	/* Initialize encryption handle */
	if(mcrypt_generic_init($GLOBALS['td'], $key, $iv) != -1)
	{
		/* Encrypt data */
		$c_t = mcrypt_generic($GLOBALS['td'], $plain_text);
		mcrypt_generic_deinit($GLOBALS['td']);

		// close the module
		mcrypt_module_close($GLOBALS['td']);

		/* Reinitialize buffers for decryption */
		/* Open module */
		$GLOBALS['td'] = mcrypt_module_open($module, '', $mode, '');
		$key = substr($key, 0, mcrypt_enc_get_key_size($GLOBALS['td']));

		mcrypt_generic_init($GLOBALS['td'], $key, $iv);
		$p_t = mdecrypt_generic($GLOBALS['td'], $c_t);

		/* Clean up */
		mcrypt_generic_deinit($GLOBALS['td']);
		mcrypt_module_close($GLOBALS['td']);
	}
	else
	{
		@mcrypt_module_close($GLOBALS['td']);
	}
	return rtrim($p_t) === $plain_text;
}

/* run a self-test through every listed cipher and mode - from robert at peakepro dot com (php.net manual) */
function mcrypt_check_sanity()
{
	$modes = mcrypt_list_modes();
	$algorithms = mcrypt_list_algorithms();

	foreach($algorithms as $cipher)
	{
		if(mcrypt_module_self_test($cipher))
		{
			$GLOBALS['ciphers'][$cipher] = array();
			foreach($modes as $mode)
			{
				if(_mcrypt_test_module_mode($cipher,$mode))
				{
					$result = True;
				}
				else
				{
					$result = False;
				}
				@$GLOBALS['ciphers'][$cipher][$mode] = $result;
			}
		}
	}
	ksort($GLOBALS['ciphers']);
	/* _debug_array($GLOBALS['ciphers']); */
}

function mcrypt_algo($settings)
{
	/* _debug_array($settings);exit; */
	if(@function_exists('mcrypt_list_modes'))
	{
		mcrypt_check_sanity();
		if(!@$GLOBALS['ciphers'][$settings['mcrypt_algo']][$settings['mcrypt_mode']])
		{
			$GLOBALS['config_error'] = lang('Invalid Mcrypt Algorithm/Mode combination');
		}
	}
}

/**
 * Add bracket around the ldap_search_filter, if admin forgot it
 *
 * @param array $settings
 */
function ldap_search_filter(&$settings)
{
	$filter =& $settings['ldap_search_filter'];

	if ($filter && $filter{0} != '(') $filter = '('.$filter.')';
}
