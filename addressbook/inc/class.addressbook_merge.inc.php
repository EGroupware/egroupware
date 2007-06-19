<?php
/**
 * Addressbook - document merge
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$ 
 */

require_once(EGW_API_INC.'/class.vfs.inc.php');
require_once(EGW_INCLUDE_ROOT.'/addressbook/inc/class.bocontacts.inc.php');

/**
 * Addressbook - document merge object
 */
class addressbook_merge	// extends bo_merge
{
	/**
	 * Functions that can be called via menuaction
	 *
	 * @var array
	 */
	var $public_functions = array('show_replacements' => true);
	/**
	 * Instance of the vfs class
	 *
	 * @var vfs
	 */
	var $vfs;
	/**
	 * Instance of the bocontacts class
	 *
	 * @var bocontacts
	 */
	var $contacts;

	/**
	 * Constructor
	 *
	 * @return addressbook_merge
	 */
	function addressbook_merge()
	{
		$this->vfs =& new vfs();
		$this->contacts =& new bocontacts();
	}
	
	/**
	 * Return replacements for a contact
	 *
	 * @param int/string/array $contact contact-array or id
	 * @param string $prefix='' prefix like eg. 'user'
	 * @return array
	 */
	function contact_replacements($contact,$prefix='')
	{
		if (!is_array($contact))
		{
			$contact = $this->contacts->read($contact);
		}
		if (!is_array($contact)) return array();

		$replacements = array();
		foreach($contact as $name => $value)
		{
			switch($name)
			{
				case 'created': case 'modified':
					$value = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].' '.
						($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i a':'H:i'));
					break;
				case 'bday':
					if ($value)
					{
						list($y,$m,$d) = explode('-',$value);
						$contact[$name] = $GLOBALS['egw']->common->dateformatorder($y,$m,$d,true);
					}
					break;
				case 'owner': case 'creator': case 'modifier':
					$value = $GLOBALS['egw']->common->grab_owner_name($value);
					break;
				case 'cat_id':
					if ($value)
					{
						if (!is_object($GLOBALS['egw']->cats))
						{
							require_once(EGW_API_INC.'/class.categories.inc.php');
							$GLOBALS['egw']->cats =& new categories;
						}
						$cats = array();
						foreach(is_array($value) ? $value : explode(',',$value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->cats->id2name($cat_id);
						}
						$value = explode(', ',$cats);
					}
					break;
				case 'jpegphoto':	// returning a link might make more sense then the binary photo
					if ($contact['photo'])
					{
						$value = ($GLOBALS['egw_info']['server']['webserver_url']{0} == '/' ?
							($_SERVER['HTTPS'] ? 'https://' : 'http://').$_SERVER['HTTP_HOST'] : '').
							$GLOBALS['egw']->link('/index.php',$contact['photo']);
					}
					break;
				case 'tel_prefer':
					if ($value && $contact[$value])
					{
						$value = $contact[$value];
					}
					break;
			}
			if ($name != 'photo') $replacements[($prefix ? $prefix.'[':'').'$$'.$name.'$$'.($prefix ? ']':'')] = $value;
		}
		return $replacements;
	}

	/**
	 * Return replacements for the calendar (next events) of a contact
	 * 
	 * ToDo: not yet implemented!!!
	 *
	 * @param int $contact contact-id
	 * @return array
	 */
	function calendar_replacements($id)
	{
		return array();
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @return string/boolean merged document or false on error
	 */
	function merge($document,$ids,&$err)
	{
		if (count($ids) > 1)
		{
			$err = 'Inserting more then one contact in a document is not yet implemented!';
			return false;
		}
		$id = $ids[0];

		if (!($content = $this->vfs->read(array(
				'string' => $document,
				'relatives' => RELATIVE_ROOT,
			))))
		{
			$err = lang("Document '%1' does not exist or is not readable for you!",$document);
			return false;
		}
		// generate replacements
		if (!($replacements = $this->contact_replacements($id)))
		{
			$err = lang('Contact not found!');
			return false;
		}
		if (strpos($content,'$$user[') !== null)
		{
			$replacements += $this->contact_replacements('account_id:'.$GLOBALS['egw_info']['user']['account_id'],'user');
		}
		if (strpos($content,'$$calendar[') !== null)
		{
			$replacements += $this->calendar_replacements($id);
		}
		$replacements['$$date$$'] = date($GLOBALS['egw_info']['user']['preferences']['common']['dateformat'],time()+$this->contacts->tz_offset_s);

		return str_replace(array_keys($replacements),array_values($replacements),$content);
	}
	
	/**
	 * Download document merged with contact(s)
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	function download($document,$ids)
	{
		if (!($merged = $this->merge($document,$ids,$err)))
		{
			return $err;
		}
		$mime_type = $this->vfs->file_type(array(
			'string' => $document,
			'relatives' => RELATIVE_ROOT,
		));
		ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
		echo $merged;

		$GLOBALS['egw']->common->egw_exit();
	}
	
	/**
	 * Generate table with replacements for the preferences
	 *
	 */
	function show_replacements()
	{
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Addressbook').' - '.lang('Replacements for inserting contacts into documents');
		$GLOBALS['egw_info']['flags']['nonavbar'] = false;
		$GLOBALS['egw']->common->egw_header();

		echo "<table width='90%' align='center'>\n";
		echo '<tr><td colspan="4"><h3>'.lang('Contact fields:')."</h3></td></tr>";

		$n = 0;
		foreach($this->contacts->contact_fields as $name => $label)
		{
			if (in_array($name,array('tid','label','geo'))) continue;	// dont show them, as they are not used in the UI atm.

			if (in_array($name,array('email','org_name','tel_work','url')) && $n&1)		// main values, which should be in the first column
			{
				echo "</tr>\n";
				$n++;
			}
			if (!($n&1)) echo '<tr>';
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}

		echo '<tr><td colspan="4"><h3>'.lang('General fields:')."</h3></td></tr>";
		foreach(array(
			'date' => lang('Date'),
			'user[n_fn]' => lang('Name of current user, all other contact fields are valid too'),
			'user[account_lid]' => lang('Username'),
		) as $name => $label)
		{
			echo '<tr><td>$$'.$name.'$$</td><td colspan="3">'.$label."</td></tr>\n";
		}

		echo '<tr><td colspan="4"><h3>'.lang('Calendar fields:')."</h3></td></tr>";
		foreach(array(
		) as $name => $label)
		{
			if (!($n&1)) echo '<tr>';
			echo '<td>$$'.$name.'$$</td><td>'.$label.'</td>';
			if ($n&1) echo "</tr>\n";
			$n++;
		}
		echo "</table>\n";

		$GLOBALS['egw']->common->egw_footer();
	}
}