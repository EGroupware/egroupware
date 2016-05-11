<?php
/**
 * EGroupware - TranslationTools
 *
 * @link http://www.egroupware.org
 * @author Miles Lott <milos(at)groupwhere.org>
 * @author Ralf Becker <RalfBecker(at)outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage translationtools
 * @version $Id$
 */

class bolangfile
{
	var $total;
	var $debug = False;
	/**
	 * storage object
	 *
	 * @var solangfile
	 */
	var $so;
	var $loaded_apps = array();
	var $source_langarray = '';
	var $missing_langarray = '';
	var $target_langarray = '';
	var $extra_langarray = array();
	var $src_file;
	var $src_apps = array();
	var $tgt_file;
	var $tgt_lang;

	function __construct()
	{
		$this->so = new solangfile();	// is now in etemplate, to not doublicate it
	}

	/* Sessions used to save state and not reread the langfile between adding/deleting phrases */
	function save_sessiondata($source='##unset##',$target='##unset##')
	{
		if ($source == '##unset##')
		{
			$source = &$this->source_langarray;
		}
		if ($target == '##unset##')
		{
			$target = &$this->target_langarray;
		}
		if($this->debug) { echo '<br>Save:'; _debug_array($source); }
		$GLOBALS['egw']->session->appsession('developer_source_lang','developer_tools',$source);
		if($this->debug) { echo '<br>Save:'; _debug_array($target); }
		$GLOBALS['egw']->session->appsession('developer_target_lang','developer_tools',$target);
		$GLOBALS['egw']->session->appsession('developer_source_file','developer_tools',$this->src_file);
		$GLOBALS['egw']->session->appsession('developer_target_file','developer_tools',$this->tgt_file);
		$GLOBALS['egw']->session->appsession('developer_t_lang','developer_tools',$this->tgt_lang);
		$GLOBALS['egw']->session->appsession('developer_loaded_apps','developer_tools',$this->loaded_apps);
		$GLOBALS['egw']->session->appsession('developer_src_apps','developer_tools',$this->src_apps);
		$GLOBALS['egw']->session->appsession('developer_missing_lang','developer_tools',$this->missing_langarray);
	}

	function read_sessiondata()
	{
		$source = $GLOBALS['egw']->session->appsession('developer_source_lang','developer_tools');
		if($this->debug) { echo '<br>Read:'; _debug_array($source); }

		$target = $GLOBALS['egw']->session->appsession('developer_target_lang','developer_tools');
		if($this->debug) { echo '<br>Read:'; _debug_array($target); }

		$src_file = $GLOBALS['egw']->session->appsession('developer_source_file','developer_tools');
		$tgt_file = $GLOBALS['egw']->session->appsession('developer_target_file','developer_tools');
		$tgt_lang = $GLOBALS['egw']->session->appsession('developer_t_lang','developer_tools');
		$loaded_apps = $GLOBALS['egw']->session->appsession('developer_loaded_apps','developer_tools');
		$src_apps = $GLOBALS['egw']->session->appsession('developer_src_apps','developer_tools');
		$missing = $GLOBALS['egw']->session->appsession('developer_missing_lang','developer_tools');

		$this->set_sessiondata($source,$target,$src_file,$tgt_file,$tgt_lang,$loaded_apps,$src_apps,$missing);
	}

	function set_sessiondata($source,$target,$src_file,$tgt_file,$tgt_lang,$loaded_apps,$src_apps,$missing)
	{
		$this->source_langarray = $source;
		$this->target_langarray = $target;
		$this->src_file = $src_file;
		$this->tgt_file = $tgt_file;
		$this->tgt_lang = $tgt_lang;
		$this->loaded_apps = $loaded_apps;
		$this->src_apps = $src_apps;
		$this->missing_langarray = $missing;
	}

	function clear_sessiondata()
	{
		$GLOBALS['egw']->session->appsession('developer_source_lang','developer_tools','');
		$GLOBALS['egw']->session->appsession('developer_target_lang','developer_tools','');
		$GLOBALS['egw']->session->appsession('developer_source_file','developer_tools','');
		$GLOBALS['egw']->session->appsession('developer_target_file','developer_tools','');
		$GLOBALS['egw']->session->appsession('developer_t_lang','developer_tools','');
		$GLOBALS['egw']->session->appsession('developer_loaded_apps','developer_tools','');
	}

	function addphrase($entry)
	{
		/* _debug_array($this->source_langarray);exit; */
		if (empty($entry['content'])) $entry['content'] = $entry['message_id'];

		$mess_id = strtolower(trim($entry['message_id']));
		$this->source_langarray[$mess_id] = array(
			'message_id' => $mess_id,
			'content'    => $entry['content'],
			'app_name'   => $entry['app_name'] == 'phpgwapi' ? 'common' : $entry['app_name'],
			'lang'       => 'en'
		);
		@ksort($this->source_langarray);

		if (!empty($entry['target']))
		{
			$this->target_langarray[$mess_id] = array(
				'message_id' => $mess_id,
				'content'    => $entry['target'],
				'app_name'   => $entry['app_name'] == 'phpgwapi' ? 'common' : $entry['app_name'],
				'lang'       => $this->tgt_lang
			);
		}
	}

	function movephrase($mess='')
	{
		if ($mess !='' && ($this->missing_langarray[$mess]['message_id']))
		{
			$this->source_langarray[$mess] = $m = array(
				'message_id' => $this->missing_langarray[$mess]['message_id'],
				'content'    => $this->missing_langarray[$mess]['content'],
				'app_name'   => $this->missing_langarray[$mess]['app_name'],
				'lang'       => 'en'
			);
			@ksort($this->source_langarray);
			reset($this->source_langarray);

			if ($this->tgt_lang == 'en')
			{
				$this->target_langarray[$mess] = $m;

				@ksort($this->target_langarray);
				reset($this->target_langarray);
			}
		}
		//else echo "'$mess' not found in missing_langarray !!!<br>\n";
	}

	function missing_app($app,$userlang='en')
	{
		$this->missing_langarray = array();

		if (!is_array($this->extra_langarray['common']))
		{
			$this->extra_langarray['common'] = $this->so->load_app('phpgwapi',$userlang);
		}
		$plist = $this->so->missing_app($app = trim($app),$userlang);

		foreach($plist as $p => $loc)
		{
			$_mess_id = strtolower(trim($p));
			if ($loc != $app)
			{
				if (!is_array($this->extra_langarray[$loc]))
				{
					$this->extra_langarray[$loc] = $this->so->load_app($loc,$userlang);
					//echo "<p>loading translations for '$loc'</p>\n";
				}
			}
			if (!empty($_mess_id) && !$this->source_langarray[$_mess_id] &&
					!$this->extra_langarray['common'][$_mess_id] &&
				($app == $loc || !$this->extra_langarray[$loc][$_mess_id]))
			{
				//echo "Havn't found '$_mess_id'/$loc !!!<br>\n";
				$this->missing_langarray[$_mess_id] = array(
					'message_id' => $_mess_id,
					'app_name'   => $loc,
					'content'    => $p
				);
			}
		}
		if (is_array($this->missing_langarray))
		{
			reset ($this->missing_langarray);
			@ksort($this->missing_langarray);
		}
		return $this->missing_langarray;
	}

	function add_app($app,$userlang='en')
	{
		if(gettype($this->source_langarray) == 'array')
		{
			return $this->source_langarray;
		}
		$this->source_langarray = $this->so->load_app($app,$userlang,False);
		$this->src_file = $this->so->src_file;
		$this->loaded_apps = $this->so->loaded_apps;
		$this->src_apps = $this->so->src_apps;
		return $this->source_langarray;
	}

	function load_app($app,$userlang='en')
	{
		if(gettype($this->target_langarray) == 'array')
		{
			if ($this->tgt_lang == $userlang)
			{
				return $this->target_langarray;
			}
		}
		$this->target_langarray = $this->so->load_app($app,$userlang);
		$this->tgt_file = $this->so->tgt_file;
		$this->tgt_lang = $userlang;
		$this->loaded_apps = $this->so->loaded_apps;
		return $this->target_langarray;
	}

	function write_file($which,$app_name,$userlang)
	{
		switch ($which)
		{
			case 'source':
				$this->src_file = $this->so->write_file($app_name,$this->source_langarray,$userlang,$which);
				break;
			case 'target':
				// removing phrases not in the source language
				$before = count($this->target_langarray);
				$this->target_langarray = array_intersect_assoc($this->target_langarray,$this->source_langarray);
				if ($before > ($after = count($this->target_langarray)))
				{
					echo '<br />'. lang('Removed %1 phrases from the target language, as they are not present in the source language!',$before-$after)."\n";
				}
				$this->tgt_file = $this->so->write_file($app_name,$this->target_langarray,$userlang,$which);
				break;
			default:
				break;
		}
		$this->save_sessiondata();
	}
}
