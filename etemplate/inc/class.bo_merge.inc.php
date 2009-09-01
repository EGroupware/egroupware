<?php
/**
 * EGroupware - Document merge print
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * Document merge print
 */
abstract class bo_merge
{
	/**
	 * Instance of the addressbook_bo class
	 *
	 * @var addressbook_bo
	 */
	var $contacts;

	/**
	 * Datetime format according to user preferences
	 *
	 * @var string
	 */
	var $datetime_format = 'Y-m-d H:i';

	/**
	 * Mimetype of document processed by merge
	 *
	 * @var string
	 */
	var $mimetype;

	/**
	 * Constructor
	 *
	 * @return bo_merge
	 */
	function __construct()
	{
		$this->contacts = new addressbook_bo();

		$this->datetime_format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].' '.
			($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12 ? 'h:i a' : 'H:i');
	}

	/**
	 * Get all replacements, must be implemented in extending class
	 *
	 * Can use eg. the following high level methods:
	 * - contact_replacements($contact_id,$prefix='')
	 * - format_datetime($time,$format=null)
	 *
	 * @param int $id id of entry
	 * @param string &$content=null content to create some replacements only if they are use
	 * @return array|boolean array with replacements or false if entry not found
	 */
	abstract protected function get_replacements($id,&$content=null);

	/**
	 * Return if merge-print is implemented for given mime-type (and/or extension)
	 *
	 * @param string $mimetype eg. text/plain
	 * @param string $extension only checked for applications/msword and .rtf
	 */
	static public function is_implemented($mimetype,$extension=null)
	{
		static $zip_available;
		if (is_null($zip_available))
		{
			$zip_available = check_load_extension('zip') &&
				class_exists('ZipArchive');	// some PHP has zip extension, but no ZipArchive (eg. RHEL5!)
		}
		switch ($mimetype)
		{
			case 'application/msword':
				if (strtolower($extension) != '.rtf') break;
			case 'application/rtf':
			case 'text/rtf':
				return true;	// rtf files
			case 'application/vnd.oasis.opendocument.text':	// oo text
			case 'application/vnd.oasis.opendocument.spreadsheet':	// oo spreadsheet
				if (!$zip_available) break;
				return true;	// open office write xml files
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				if (!$zip_available) break;
				return true;	// ms word xml format
			default:
				if (substr($mimetype,0,5) == 'text/')
				{
					return true;	// text files
				}
				break;
		}
		return false;

		// As browsers not always return correct mime types, one could use a negative list instead
		//return !($mimetype == egw_vfs::DIR_MIME_TYPE || substr($mimetype,0,6) == 'image/');
	}

	/**
	 * Return replacements for a contact
	 *
	 * @param int|string|array $contact contact-array or id
	 * @param string $prefix='' prefix like eg. 'user'
	 * @return array
	 */
	public function contact_replacements($contact,$prefix='')
	{
		if (!is_array($contact))
		{
			$contact = $this->contacts->read($contact);
		}
		if (!is_array($contact)) return array();

		$replacements = array();
		foreach(array_keys($this->contacts->contact_fields) as $name)
		{
			$value = $contact[$name];
			switch($name)
			{
				case 'created': case 'modified':
					$value = $this->format_datetime($value);
					break;
				case 'bday':
					if ($value)
					{
						list($y,$m,$d) = explode('-',$value);
						$value = common::dateformatorder($y,$m,$d,true);
					}
					break;
				case 'owner': case 'creator': case 'modifier':
					$value = common::grab_owner_name($value);
					break;
				case 'cat_id':
					if ($value)
					{
						// if cat-tree is displayed, we return a full category path not just the name of the cat
						$use = $GLOBALS['egw_info']['server']['cat_tab'] == 'Tree' ? 'path' : 'name';
						$cats = array();
						foreach(is_array($value) ? $value : explode(',',$value) as $cat_id)
						{
							$cats[] = $GLOBALS['egw']->categories->id2name($cat_id,$use);
						}
						$value = implode(', ',$cats);
					}
					break;
				case 'jpegphoto':	// returning a link might make more sense then the binary photo
					if ($contact['photo'])
					{
						$value = ($GLOBALS['egw_info']['server']['webserver_url'][0] == '/' ?
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
				case 'account_id':
					if ($value)
					{
						$replacements['$$'.($prefix ? $prefix.'/':'').'account_lid$$'] = $GLOBALS['egw']->accounts->id2name($value);
					}
					break;
			}
			if ($name != 'photo') $replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		// set custom fields
		foreach($this->contacts->customfields as $name => $field)
		{
			$name = '#'.$name;
			$value = (string)$contact[$name];
			switch($field['type'])
			{
				case 'select-account':
					if ($value) $value = common::grab_owner_name($value);
					break;

				case 'select':
					if (count($field['values']) == 1 && isset($field['values']['@']))
					{
						$field['values'] = customfields_widget::_get_options_from_file($field['values']['@']);
					}
					$values = array();
					foreach($field['rows'] > 1 ? explode(',',$value) : (array) $value as $value)
					{
						$values[] = $field['values'][$value];
					}
					$value = implode(', ',$values);
					break;

				case 'date':
				case 'date-time':
					if ($value)
					{
						$format = $field['len'] ? $field['len'] : ($field['type'] == 'date' ? 'Y-m-d' : 'Y-m-d H:i:s');
						$date = array_combine(preg_split('/[\\/. :-]/',$format),preg_split('/[\\/. :-]/',$value));
						$value = common::dateformatorder($date['Y'],$date['m'],$date['d'],true);
						if (isset($date['H'])) $value .= ' '.common::formattime($date['H'],$date['i']);
					}
					break;
			}
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] = $value;
		}
		return $replacements;
	}

	/**
	 * Format a datetime
	 *
	 * @param int|string $time unix timestamp or Y-m-d H:i:s string (in user time!)
	 * @param string $format=null format string, default $this->datetime_format
	 * @return string
	 */
	protected function format_datetime($time,$format=null)
	{
		if (is_null($format)) $format = $this->datetime_format;

		if (empty($time)) return '';

		return date($this->datetime_format,is_numeric($time) ? $time : strtotime($time));
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $document path/url of document
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @param array $fix=null regular expression => replacement pairs eg. to fix garbled placeholders
	 * @return string|boolean merged document or false on error
	 */
	public function &merge($document,$ids,&$err,$mimetype,array $fix=null)
	{
		if (!($content = file_get_contents($document)))
		{
			$err = lang("Document '%1' does not exist or is not readable for you!",$document);
			return false;
		}
		// make currently processed mimetype available to class methods;
		$this->mimetype = $mimetype;

		// fix garbled placeholders
		if ($fix && is_array($fix))
		{
			$content = preg_replace(array_keys($fix),array_values($fix),$content);
			//die("<pre>".htmlspecialchars($content)."</pre>\n");
		}
		list($contentstart,$contentrepeat,$contentend) = preg_split('/\$\$pagerepeat\$\$/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
		if ($mimetype == 'application/vnd.oasis.opendocument.text' && count($ids) > 1)
		{
			//for odt files we have to slpit the content and add a style for page break to  the style area
			list($contentstart,$contentrepeat,$contentend) = preg_split('/office:body>/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
			$contentstart = substr($contentstart,0,strlen($contentstart)-1);  //remove "<"
			$contentrepeat = substr($contentrepeat,0,strlen($contentrepeat)-2);  //remove "</";
			// need to add page-break style to the style list
			list($stylestart,$stylerepeat,$styleend) = preg_split('/<\/office:automatic-styles>/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document style sheets
			$contentstart = $stylestart.'<style:style style:name="P200" style:family="paragraph" style:parent-style-name="Standard"><style:paragraph-properties fo:break-before="page"/></style:style></office:automatic-styles>';
			$contentstart .= '<office:body>';
			$contentend = '</office:body></office:document-content>';
		}
		list($Labelstart,$Labelrepeat,$Labeltend) = preg_split('/\$\$label\$\$/',$contentrepeat,-1, PREG_SPLIT_NO_EMPTY);  //get the Lable content
		preg_match_all('/\$\$labelplacement\$\$/',$contentrepeat,$countlables, PREG_SPLIT_NO_EMPTY);
		$countlables = count($countlables[0])+1;
		preg_replace('/\$\$labelplacement\$\$/','',$Labelrepeat,1);
		if ($countlables > 1) $lableprint = true;
		if (count($ids) > 1 && !$contentrepeat)
		{
			$err = lang('for more then one contact in a document use the tag pagerepeat!');
			return false;
		}
		foreach ((array)$ids as $id)
		{
			if ($contentrepeat) $content = $contentrepeat;   //content to repeat
			if ($lableprint) $content = $Labelrepeat;

			// generate replacements
			if (!($replacements = $this->get_replacements($id,$content)))
			{
				$err = lang('Entry not found!');
				return false;
			}
			// some general replacements: current user, date and time
			if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
			{
				$replacements += $this->contact_replacements($user,'user');
			}
			$now = time()+$this->contacts->tz_offset_s;
			$replacements['$$date$$'] = $this->format_datetime($now,$GLOBALS['egw_info']['user']['preferences']['common']['dateformat']);
			$replacements['$$datetime$$'] = $this->format_datetime($now);
			$replacements['$$time$$'] = $this->format_datetime($now,$GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12?'h:i a':'H:i');

			if ($this->contacts->prefs['csv_charset'])	// if we have an export-charset defined, use it here to
			{
				$replacements = $GLOBALS['egw']->translation->convert($replacements,$GLOBALS['egw']->translation->charset(),$this->contacts->prefs['csv_charset']);
			}
			$content = str_replace(array_keys($replacements),array_values($replacements),$content);

			if (strpos($content,'$$IF'))
			{	//Example use to use: $$IF n_prefix~Herr~Sehr geehrter~Sehr geehrte$$
				$this->replacements =& $replacements;
				$content = preg_replace_callback('/\$\$IF ([0-9a-z_-]+)~(.*)~(.*)~(.*)\$\$/imU',Array($this,'replace_callback'),$content);
				unset($this->replacements);
			}
			// remove not existing replacements (eg. from calendar array)
			if (strpos($content,'$$') !== null)
			{
				$content = preg_replace('/\$\$[a-z0-9_]+\$\$/i','',$content);
			}
			if ($contentrepeat) $contentrep[$id] = $content;
		}
		if ($Labelrepeat)
		{
			$countpage=0;
			$count=0;
			$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;

			foreach ($contentrep as $Label)
			{
				$count=$count+1;
				if ($count % $countlables == 0)
				{
					$countpage=$countpage+1;
					$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;
				}
				$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/',$Label,$contentrepeatpages[$countpage],1);
			}
			$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/','',$contentrepeatpages[$countpage],-1);  //clean empty fields

			switch($mimetype)
			{
				case 'application/msword':
					if (strtolower(substr($document,-4)) != '.rtf') break;	// no binary word documents
				case 'application/rtf':
				case 'text/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrepeatpages).$contentend;
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.oasis.opendocument.spreadsheet':
					// todo OO writer files
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
					// todo ms word xml files
					break;
			}
			$err = lang('%1 not implemented for %2!','$$labelplacement$$',$mimetype);
			return false;
		}

		if ($contentrepeat)
		{
			switch($mimetype)
			{
				case 'application/msword':
					if (strtolower(substr($document,-4)) != '.rtf') break;	// no binary word documents
				case 'application/rtf':
				case 'text/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrep).$contentend;
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.oasis.opendocument.spreadsheet':
					return $contentstart.implode('',$contentrep).$contentend;
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
					// todo ms word xml files
					break;
			}
			$err = lang('%1 not implemented for %2!','$$pagerepeat$$',$mimetype);
			return false;
		}
		return $content;
	}

	/**
	 * Callback for preg_replace to process $$IF
	 *
	 * @param array $param
	 * @return string
	 */
	private function replace_callback($param)
	{
		if (array_key_exists('$$'.$param[4].'$$',$this->replacements)) $param[4] = $this->replacements['$$'.$param[4].'$$'];
		if (array_key_exists('$$'.$param[3].'$$',$this->replacements)) $param[3] = $this->replacements['$$'.$param[3].'$$'];
		$replace = preg_match('/'.$param[2].'/',$this->replacements['$$'.$param[1].'$$']) ? $param[3] : $param[4];
		return $replace;
	}

	/**
	 * Download document merged with contact(s)
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	public function download($document,$ids)
	{
		$content_url = egw_vfs::PREFIX.$document;
		switch (($mime_type = egw_vfs::mime_content_type($document)))
		{
			case 'application/vnd.oasis.opendocument.text':
			case 'application/vnd.oasis.opendocument.spreadsheet':
				$ext = $mime_type == 'application/vnd.oasis.opendocument.text' ? '.odt' : '.ods';
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,$ext).'-').$ext;
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'content.xml');
				break;
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				$mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.dotx').'-').'.dotx';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'word/document.xml');
				$fix = array(		// regular expression to fix garbled placeholders
					'/'.preg_quote('$$</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>','/').'/i' => '$$\\1$$',
					'/'.preg_quote('$$</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:rPr><w:lang w:val="','/').
						'([a-z]{2}-[A-Z]{2})'.preg_quote('"/></w:rPr><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:rPr><w:lang w:val="','/').
						'([a-z]{2}-[A-Z]{2})'.preg_quote('"/></w:rPr><w:t>$$','/').'/i' => '$$\\2$$',
				);
				break;
		}
		if (!($merged =& $this->merge($content_url,$ids,$err,$mime_type,$fix)))
		{
			return $err;
		}
		if (isset($archive))
		{
			$zip = new ZipArchive;
			if ($zip->open($archive,ZIPARCHIVE::CHECKCONS) !== true) throw new Exception("!ZipArchive::open('$archive',ZIPARCHIVE::OVERWRITE)");
			if ($zip->addFromString($content_file,$merged) !== true) throw new Exception("!ZipArchive::addFromString('$content_file',\$merged)");
			if ($zip->close() !== true) throw new Exception("!ZipArchive::close()");
			unset($zip);
			unset($merged);
			if (file_exists('/usr/bin/zip') && version_compare(PHP_VERSION,'5.3.1','<'))	// fix broken zip archives generated by current php
			{
				exec('/usr/bin/zip -F '.escapeshellarg($archive));
			}
			ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
			readfile($archive,'r');
		}
		else
		{
			ExecMethod2('phpgwapi.browser.content_header',basename($document),$mime_type);
			echo $merged;
		}
		common::egw_exit();
	}
}
