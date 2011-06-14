<?php
/**
 * EGroupware - Document merge print
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package addressbook
 * @copyright (c) 2007-11 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * Plugins registered by extending class to create a table with multiple rows
	 *
	 * $$table/$plugin$$ ... $$endtable$$
	 *
	 * Callback returns replacements for row $n (stringing with 0) or null if no further rows
	 *
	 * @var array $plugin => array callback($plugin,$id,$n)
	 */
	var $table_plugins = array();

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
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms word 2007 xml format
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':	// ms excel 2007 xml format
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.shee':
				if (!$zip_available) break;
				return true;	// ms word xml format
			case 'application/xml':
				return true;	// alias for text/xml, eg. ms office 2003 word format
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

		// Add in extra cat field
		$cats = array();
		foreach(is_array($contact['cat_id']) ? $contact['cat_id'] : explode(',',$contact['cat_id']) as $cat_id)
		{
			if(!$cat_id) continue;
			if($GLOBALS['egw']->categories->id2name($cat_id,'main') != $cat_id)
			{
				$path = $GLOBALS['egw']->categories->id2name($cat_id,'path');
				$path = explode(' / ', $path);
				unset($path[0]); // Drop main
				$cats[$GLOBALS['egw']->categories->id2name($cat_id,'main')][] = implode(' / ', $path);
			} elseif($cat_id) {
				$cats[$cat_id] = array();
			}
		}
		foreach($cats as $main => $cat) {
			$replacements['$$'.($prefix ? $prefix.'/':'').'categories$$'] .= $GLOBALS['egw']->categories->id2name($main,'name')
				. (count($cat) > 0 ? ': ' : '') . implode(', ', $cats[$main]) . "\n";
		}
		return $replacements;
	}

	/**
	 * Format a datetime
	 *
	 * @param int|string|DateTime $time unix timestamp or Y-m-d H:i:s string (in user time!)
	 * @param string $format=null format string, default $this->datetime_format
	 * @deprecated use egw_time::to($time='now',$format='')
	 * @return string
	 */
	protected function format_datetime($time,$format=null)
	{
		if (is_null($format)) $format = $this->datetime_format;

		return egw_time::to($time,$format);
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
		// fix application/msword mimetype for rtf files
		if ($mimetype == 'application/msword' && strtolower(substr($document,-4)) == '.rtf')
		{
			$mimetype = 'application/rtf';
		}

		$config = config::read('phpgwapi');
		if($config['export_limit'] && !$GLOBALS['egw_info']['user']['apps']['admin']) {
			$ids = array_slice($ids, 0, (int)$config['export_limit']);
		}
		return $this->merge_string($content,$ids,$err,$mimetype,$fix);
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $content
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @param array $fix=null regular expression => replacement pairs eg. to fix garbled placeholders
	 * @return string|boolean merged document or false on error
	 */
	public function &merge_string($content,$ids,&$err,$mimetype,array $fix=null)
	{
		if ($mimetype == 'application/xml' &&
			preg_match('/'.preg_quote('<?mso-application progid="').'([^"]+)'.preg_quote('"?>').'/',substr($content,0,200),$matches))
		{
			$mso_application_progid = $matches[1];
		}
		else
		{
			$mso_application_progid = '';
		}
		// alternative syntax using double curly brackets (eg. {{cat_id}} instead $$cat_id$$),
		// agressivly removing all xml-tags eg. Word adds within placeholders
		$content = preg_replace_callback('/{{[^}]+}}/i',create_function('$p','return \'$$\'.strip_tags(substr($p[0],2,-2)).\'$$\';'),$content);

		// make currently processed mimetype available to class methods;
		$this->mimetype = $mimetype;

		// fix garbled placeholders
		if ($fix && is_array($fix))
		{
			$content = preg_replace(array_keys($fix),array_values($fix),$content);
			//die("<pre>".htmlspecialchars($content)."</pre>\n");
		}
		list($contentstart,$contentrepeat,$contentend) = preg_split('/\$\$pagerepeat\$\$/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
		if ($mimetype == 'text/plain' && count($ids) > 1)
		{
			// textdocuments are simple, they do not hold start and end, but they may have content before and after the $$pagerepeat$$ tag
			// header and footer should not hold any $$ tags; if we find $$ tags with the header, we assume it is the pagerepeatcontent
			$nohead = false;
			if (stripos($contentstart,'$$') !== false) $nohead = true;
			if ($nohead)
			{
				$contentend = $contentrepeat;
				$contentrepeat = $contentstart;
				$contentstart = '';
			}

		}
		if ($mimetype == 'application/vnd.oasis.opendocument.text' && count($ids) > 1)
		{
			//for odt files we have to split the content and add a style for page break to  the style area
			list($contentstart,$contentrepeat,$contentend) = preg_split('/office:body>/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
			$contentstart = substr($contentstart,0,strlen($contentstart)-1);  //remove "<"
			$contentrepeat = substr($contentrepeat,0,strlen($contentrepeat)-2);  //remove "</";
			// need to add page-break style to the style list
			list($stylestart,$stylerepeat,$styleend) = preg_split('/<\/office:automatic-styles>/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document style sheets
			$contentstart = $stylestart.'<style:style style:name="P200" style:family="paragraph" style:parent-style-name="Standard"><style:paragraph-properties fo:break-before="page"/></style:style></office:automatic-styles>';
			$contentstart .= '<office:body>';
			$contentend = '</office:body></office:document-content>';
		}
		if ($mimetype == 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && count($ids) > 1)
		{
			//for Word 2007 XML files we have to split the content and add a style for page break to  the style area
			list($contentstart,$contentrepeat,$contentend) = preg_split('/w:body>/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get differt parts of document, seperatet by Pagerepeat
			$contentstart = substr($contentstart,0,strlen($contentstart)-1);  //remove "</"
			$contentrepeat = substr($contentrepeat,0,strlen($contentrepeat)-2);  //remove "</";
			$contentstart .= '<w:body>';
			$contentend = '</w:body></w:document>';
		}
		list($Labelstart,$Labelrepeat,$Labeltend) = preg_split('/\$\$label\$\$/',$contentrepeat,-1, PREG_SPLIT_NO_EMPTY);  //get the Lable content
		preg_match_all('/\$\$labelplacement\$\$/',$contentrepeat,$countlables, PREG_SPLIT_NO_EMPTY);
		$countlables = count($countlables[0]);
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
			if(!($replacements = $this->get_replacements($id,$content)))
			{
				$err = lang('Entry not found!');
				return false;
			}
			// some general replacements: current user, date and time
			if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
			{
				$replacements += $this->contact_replacements($user,'user');
			}
			$replacements['$$date$$'] = egw_time::to('now',true);
			$replacements['$$datetime$$'] = egw_time::to('now');
			$replacements['$$time$$'] = egw_time::to('now',false);

			// does our extending class registered table-plugins AND document contains table tags
			if ($this->table_plugins && preg_match_all('/\\$\\$table\\/([A-Za-z0-9_]+)\\$\\$(.*?)\\$\\$endtable\\$\\$/s',$content,$matches,PREG_SET_ORDER))
			{
				// process each table
				foreach($matches as $match)
				{
					$plugin   = $match[1];	// plugin name
					$callback = $this->table_plugins[$plugin];
					$repeat   = $match[2];	// line to repeat
					$repeats = '';
					if (isset($callback))
					{
						for($n = 0; ($row_replacements = $this->$callback($plugin,$id,$n,$repeat)); ++$n)
						{
							$_repeat = $this->process_commands($repeat, $row_replacements);
							$repeats .= $this->replace($_repeat,$row_replacements,$mimetype,$mso_application_progid);
						}
					}
					$content = str_replace($match[0],$repeats,$content);
				}
			}
			$content = $this->replace($content,$replacements,$mimetype,$mso_application_progid);

			$content = $this->process_commands($content, $replacements);

			// remove not existing replacements (eg. from calendar array)
			if (strpos($content,'$$') !== null)
			{
				$content = preg_replace('/\$\$[a-z0-9_\/]+\$\$/i','',$content);
			}
			if ($contentrepeat) $contentrep[is_array($id) ? implode(':',$id) : $id] = $content;
		}
		if ($Labelrepeat)
		{
			$countpage=0;
			$count=0;
			$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;

			foreach ($contentrep as $Label)
			{
				$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/',$Label,$contentrepeatpages[$countpage],1);
				$count=$count+1;
				if (($count % $countlables) == 0 && count($contentrep)>$count)  //new page
				{
					$countpage = $countpage+1;
					$contentrepeatpages[$countpage] = $Labelstart.$Labeltend;
				}
			}
			$contentrepeatpages[$countpage] = preg_replace('/\$\$labelplacement\$\$/','',$contentrepeatpages[$countpage],-1);  //clean empty fields

			switch($mimetype)
			{
				case 'application/rtf':
				case 'text/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrepeatpages).$contentend;
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.oasis.opendocument.spreadsheet':
					// todo OO writer files
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					return $contentstart.implode('<w:br w:type="page" />',$contentrep).$contentend;
				case 'text/plain':
					return $contentstart.implode("\r\n",$contentrep).$contentend;
			}
			$err = lang('%1 not implemented for %2!','$$labelplacement$$',$mimetype);
			return false;
		}

		if ($contentrepeat)
		{
			switch($mimetype)
			{
				case 'application/rtf':
				case 'text/rtf':
					return $contentstart.implode('\\par \\page\\pard\\plain',$contentrep).$contentend;
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.oasis.opendocument.spreadsheet':
				case 'application/xml':
					return $contentstart.implode('',$contentrep).$contentend;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					return $contentstart.implode('<w:br w:type="page" />',$contentrep).$contentend;
				case 'text/plain':
					return $contentstart.implode("\r\n",$contentrep).$contentend;
			}
			$err = lang('%1 not implemented for %2!','$$pagerepeat$$',$mimetype);
			return false;
		}

		return $content;
	}

	/**
	 * Replace placeholders in $content of $mimetype with $replacements
	 *
	 * @param string $content
	 * @param array $replacements name => replacement pairs
	 * @param string $mimetype mimetype of content
	 * @param string $mso_application_progid='' MS Office 2003: 'Excel.Sheet' or 'Word.Document'
	 * @return string
	 */
	protected function replace($content,array $replacements,$mimetype,$mso_application_progid='')
	{
		switch($mimetype)
		{
			case 'application/vnd.oasis.opendocument.text':		// open office
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/xml':
			case 'text/xml':
			case 'text/html':
				$is_xml = true;
				$charset = 'utf-8';	// xml files --> always use utf-8
				break;

			default:	// div. text files --> use our export-charset, defined in addressbook prefs
				$charset = $this->contacts->prefs['csv_charset'];
				break;
		}
		//error_log(__METHOD__."('$document', ... ,$mimetype) --> $charset (egw=".translation::charset().', export='.$this->contacts->prefs['csv_charset'].')');
		// do we need to convert charset
		if ($charset && $charset != translation::charset())
		{
			$replacements = translation::convert($replacements,translation::charset(),$charset);
		}
		if ($is_xml)	// zip'ed xml document (eg. OO)
		{
			// Numeric fields
			$names = array();

			// clean replacements from array values and html or html-entities, which mess up xml
			foreach($replacements as $name => &$value)
			{
				// set unresolved array values to empty string
				if(is_array($value))
				{
					$value = '';
					continue;
				}
				// decode html entities back to utf-8
				if (is_string($value) && (strpos($value,'&') !== false))
				{
					$value = html_entity_decode($value,ENT_QUOTES,$charset);

					// remove all non-decodable entities
					if (strpos($value,'&') !== false)
					{
						$value = preg_replace('/&[^; ]+;/','',$value);
					}
				}
				// remove all html tags, evtl. included
				if (is_string($value) && (strpos($value,'<') !== false))
				{
					// replace </p> and <br /> with CRLF (remove <p> and CRLF)
					$value = str_replace(array("\r","\n",'<p>','</p>','<br />'),array('','','',"\r\n","\r\n"),$value);
					$value = strip_tags($value);
				}
				// replace all control chars (C0+C1) but CR (\015), LF (\012) and TAB (\011) (eg. vertical tabulators) with space
				// as they are not allowed in xml
				$value = preg_replace('/[\000-\010\013\014\016-\037\177-\237]/u',' ',$value);
				if(is_numeric($value) && $name != '$$user/account_id$$') // account_id causes problems with the preg_replace below
				{
					$names[] = preg_quote($name,'/');
				}
			}

			// Look for numbers, set their value if needed
			$format = $replacement = '';
			if($this->numeric_fields || count($names))
			{
				foreach((array)$this->numeric_fields as $fieldname) {
					$names[] = preg_quote($fieldname,'/');
				}
				switch($mimetype.$mso_application_progid)
				{
					case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
						$format = '/<table:table-cell([^>]+?)office:value-type="[^"]+"([^>]*?)>.?<([a-z].*?)[^>]*>('.implode('|',$names).')<\/\3>.?<\/table:table-cell>/s';
						$replacement = '<table:table-cell$1office:value-type="float" office:value="$4"$2>$4</table:table-cell>';
						break;
					case 'application/xmlExcel.Sheet':	// Excel 2003
						$format = '/'.preg_quote('<Data ss:Type="String">','/').'('.implode('|',$names).')'.preg_quote('</Data>','/').'/';
						$replacement = '<Data ss:Type="Number">$1</Data>';

						break;
				}
				if($format && $names)
				{
					// Dealing with backtrack limit per AmigoJack 10-Jul-2010 comment on php.net preg-replace docs
					$increase = 0;
					while($increase < 10) {
						$result = preg_replace($format, $replacement, $content, -1, $count);
						if( preg_last_error()== PREG_BACKTRACK_LIMIT_ERROR ) {  // Only check on backtrack limit failure
							ini_set( 'pcre.backtrack_limit', (int)ini_get( 'pcre.backtrack_limit' )+ 10000 );  // Get current limit and increase
							$increase++;  // Do not overkill the server
						} else {  // No fail
							$content = $result;  // On failure $result would be NULL
							break;  // Exit loop
						}
					}
					if($increase == 10) {
						error_log('Backtrack limit exceeded @ ' . ini_get('pcre.backtrack_limit') . ', some cells left as text.');
					}
				}
			}
			// replace CRLF with linebreak tag of given type
			switch($mimetype.$mso_application_progid)
			{
				case 'application/vnd.oasis.opendocument.text':		// open office writer
					$break = '<text:line-break/>';
					break;
				case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
					$break = '<text:line-break/>';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms word 2007
					$break = '<w:br/>';
					break;
				case 'application/xmlExcel.Sheet':	// Excel 2003
					$break = '&#10;';
					break;
				case 'application/xmlWord.Document':	// Word 2003*/
					$break = '<w:br/>';
					break;
				case 'text/html':
					$break = '<br/>';
					break;
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':	// ms excel 2007
				default:
					$break = "\r\n";
					break;
			}
			// now decode &, < and >, which need to be encoded as entities in xml
			$replacements = str_replace(array('&','<','>',"\r","\n"),array('&amp;','&lt;','&gt;','',$break),$replacements);
		}
		return str_replace(array_keys($replacements),array_values($replacements),$content);
	}

	/**
	 * Process special flags, such as IF or NELF
	 *
	 * @param content Text to be examined and changed
	 * @param replacements array of markers => replacement
	 *
	 * @return changed content
	 */
	private function process_commands($content, $replacements)
	{
		if (strpos($content,'$$IF'))
		{	//Example use to use: $$IF n_prefix~Herr~Sehr geehrter~Sehr geehrte$$
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$IF ([0-9a-z_\/-]+)~(.*)~(.*)~(.*)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$NELF'))
		{	//Example: $$NEPBR org_unit$$ sets a LF and value of org_unit, only if there is a value
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$NELF ([0-9a-z_\/-]+)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$NENVLF'))
		{	//Example: $$NEPBRNV org_unit$$ sets only a LF if there is a value for org_units, but did not add any value
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$NENVLF ([0-9a-z_\/-]+)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$LETTERPREFIX$$'))
		{	//Example use to use: $$LETTERPREFIX$$
			$LETTERPREFIXCUSTOM = '$$LETTERPREFIXCUSTOM n_prefix title n_family$$';
			$content = str_replace('$$LETTERPREFIX$$',$LETTERPREFIXCUSTOM,$content);
		}
		if (strpos($content,'$$LETTERPREFIXCUSTOM'))
		{	//Example use to use for a custom Letter Prefix: $$LETTERPREFIX n_prefix title n_family$$
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$LETTERPREFIXCUSTOM ([0-9a-z_-]+)(.*)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
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
		switch($this->mimetype)
		{
			case 'application/vnd.oasis.opendocument.text':		// open office
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/xml':
			case 'text/xml':
			case 'text/html':
				$is_xml = true;
				break;
		}

		switch($this->mimetype)
			{
				case 'application/rtf':
				case 'text/rtf':
					$LF = '}\par \pard\plain{';
					break;
				case 'application/vnd.oasis.opendocument.text':
				case 'application/vnd.oasis.opendocument.spreadsheet':
					$LF ='<text:line-break/>';
					break;
				case 'application/xmlExcel.Sheet':	// Excel 2003
					$LF = '&#10;';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					$LF ='</w:r></w:p><w:r><w:t>';
					break;
				case 'application/xml';
					$LF ='</w:t></w:r><w:r><w:br w:type="text-wrapping" w:clear="all"/></w:r><w:r><w:t>';
					break;
				default:
					$LF = "\n";
			}
		if($is_xml) {
			$this->replacements = str_replace(array('&','<','>',"\r","\n"),array('&amp;','&lt;','&gt;','',$LF),$this->replacements);
		}
		if (strpos($param[0],'$$NELF') === 0)
		{	//sets a Pagebreak and value, only if the field has a value
			if ($this->replacements['$$'.$param[1].'$$'] !='') $replace = $LF.$this->replacements['$$'.$param[1].'$$'];
		}
		if (strpos($param[0],'$$NENVLF') === 0)
		{	//sets a Pagebreak without any value, only if the field has a value
			if ($this->replacements['$$'.$param[1].'$$'] !='') $replace = $LF;
		}
		if (strpos($param[0],'$$LETTERPREFIXCUSTOM') === 0)
		{	//sets a Letterprefix
			$replaceprefix = array();
			// ToDo Stefan: $contentstart is NOT defined here!!!
			$replaceprefix = explode(' ',substr($param[0],21,strlen($contentstart)-2));
			foreach ($replaceprefix as $key => $nameprefix)
			{
				if ($this->replacements['$$'.$nameprefix.'$$'] !='') $replaceprefixsort[] = $this->replacements['$$'.$nameprefix.'$$'];
			}
			$replace = implode($replaceprefixsort,' ');
		}
		return $replace;
	}

	/**
	 * Download document merged with contact(s)
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @param string $name='' name to use for downloaded document
	 * @param string $dirs comma or whitespace separated directories, used if $document is a relative path
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	public function download($document, $ids, $name='', $dirs='')
	{
		if (($error = $this->check_document($document, $dirs)))
		{
			return $error;
		}
		$content_url = egw_vfs::PREFIX.$document;
		switch (($mimetype = egw_vfs::mime_content_type($document)))
		{
			case 'application/vnd.oasis.opendocument.text':
			case 'application/vnd.oasis.opendocument.spreadsheet':
				$ext = $mimetype == 'application/vnd.oasis.opendocument.text' ? '.odt' : '.ods';
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,$ext).'-').$ext;
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'content.xml');
				break;
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				$mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.docx').'-').'.docx';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'word/document.xml');
				$fix = array(		// regular expression to fix garbled placeholders
					'/'.preg_quote('$$</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>','/').'/i' => '$$\\1$$',
					'/'.preg_quote('$$</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:rPr><w:lang w:val="','/').
						'([a-z]{2}-[A-Z]{2})'.preg_quote('"/></w:rPr><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:rPr><w:lang w:val="','/').
						'([a-z]{2}-[A-Z]{2})'.preg_quote('"/></w:rPr><w:t>$$','/').'/i' => '$$\\2$$',
					'/'.preg_quote('$</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>','/').'/i' => '$\\1$',
					'/'.preg_quote('$ $</w:t></w:r><w:proofErr w:type="spellStart"/><w:r><w:t>','/').'([a-z0-9_]+)'.
						preg_quote('</w:t></w:r><w:proofErr w:type="spellEnd"/><w:r><w:t>','/').'/i' => '$ $\\1$ $',
				);
				break;
			case 'application/xml':
				$fix = array(	// hack to get Excel 2003 to display additional rows in tables
					'/ss:ExpandedRowCount="\d+"/' => 'ss:ExpandedRowCount="9999"',
				);
				break;
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.shee':
				$mimetype = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				$fix = array(	// hack to get Excel 2007 to display additional rows in tables
					'/ss:ExpandedRowCount="\d+"/' => 'ss:ExpandedRowCount="9999"',
				);
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.xlsx').'-').'.xlsx';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'xl/sharedStrings.xml');
				break;
		}
		if (!($merged =& $this->merge($content_url,$ids,$err,$mimetype,$fix)))
		{
			return $err;
		}
		if(!empty($name))
		{
			if(empty($ext))
			{
				$ext = '.'.pathinfo($document,PATHINFO_EXTENSION);
			}
			$name .= $ext;
		}
		else
		{
			$name = basename($document);
		}
		if (isset($archive))
		{
			$zip = new ZipArchive;
			if ($zip->open($archive,ZIPARCHIVE::CHECKCONS) !== true)
			{
				error_log(__METHOD__.__LINE__." !ZipArchive::open('$archive',ZIPARCHIVE::CHECKCONS) failed. Trying open without validating");
				if ($zip->open($archive) !== true) throw new Exception("!ZipArchive::open('$archive',|ZIPARCHIVE::CHECKCONS)");
			}
			if ($zip->addFromString($content_file,$merged) !== true) throw new Exception("!ZipArchive::addFromString('$content_file',\$merged)");
			if ($zip->close() !== true) throw new Exception("!ZipArchive::close()");
			unset($zip);
			unset($merged);
			if (substr($mimetype,0,35) == 'application/vnd.oasis.opendocument.' && 			// only open office archives need that, ms word files brake
				file_exists('/usr/bin/zip') && version_compare(PHP_VERSION,'5.3.1','<'))	// fix broken zip archives generated by current php
			{
				exec('/usr/bin/zip -F '.escapeshellarg($archive));
			}
			html::content_header($name,$mimetype,filesize($archive));
			readfile($archive,'r');
		}
		else
		{
			if ($mimetype == 'application/xml')
			{
				if (strpos($merged,'<?mso-application progid="Word.Document"?>') !== false)
				{
					$mimetype = 'application/msword';	// to open it automatically in word or oowriter
				}
				elseif (strpos($merged,'<?mso-application progid="Excel.Sheet"?>') !== false)
				{
					$mimetype = 'application/vnd.ms-excel';	// to open it automatically in excel or oocalc
				}
			}
			ExecMethod2('phpgwapi.browser.content_header',$name,$mimetype);
			echo $merged;
		}
		common::egw_exit();
	}

	/**
	 * Get a list of document actions / files from the given directory
	 *
	 * @param dir Directory to search
	 *
	 * @return List of documents, suitable for a selectbox.  The key is document_<filename>.
	 */
	public static function get_documents($dir, $prefix='document_')
	{
		if (!$dir) return array();

		// split multiple comma or whitespace separated directories
		// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
		if (count($dir = preg_split('/[,\s]+\//', $dir)) > 1)
		{
			foreach($dir as $n => &$d) if ($n) $d = '/'.$d;	// re-adding trailing slash removed by split
		}
		$list = array();
		if (($files = egw_vfs::find($dir,array('need_mime'=>true),true)))
		{
			foreach($files as $file)
			{
				// return only the mime-types we support
				if (!self::is_implemented($file['mime'],'.'.array_pop($parts=explode('.',$file['name'])))) continue;

				$list[$prefix.$file['name']] = egw_vfs::decodePath($file['name']);
			}
		}
		return $list;
	}

	/**
	 * From this number of documents, show them in submenus by mime type
	 */
	const SHOW_DOCS_BY_MIME_LIMIT = 10;

	/**
	 * Get insert-in-document action with optional default document on top
	 *
	 * If more then SHOW_DOCS_BY_MIME_LIMIT=10 documents found, they are displayed in submenus by mime type.
	 *
	 * @param string $dir
	 * @param int $group see nextmatch_widget::egw_actions
	 * @param string $caption='Insert in document'
	 * @param string $prefix='document_'
	 * @param string $default_doc='' full path to default document to show on top with action == 'document'!
	 * @return array see nextmatch_widget::egw_actions
	 */
	public static function document_action($dir, $group=0, $caption='Insert in document', $prefix='document_', $default_doc='')
	{
		$documents = array();

		if ($default_doc && ($file = egw_vfs::stat($default_doc)))	// put default document on top
		{
			$documents['document'] = array(
				'icon' => egw_vfs::mime_icon($file['mime']),
				'caption' => egw_vfs::decodePath(egw_vfs::basename($default_doc)),
				'group' => 1,
			);
		}

		// split multiple comma or whitespace separated directories
		// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
		if ($dir && count($dir = preg_split('/[,\s]+\//', $dir)) > 1)
		{
			foreach($dir as $n => &$d) if ($n) $d = '/'.$d;	// re-adding trailing slash removed by split
		}
		if ($dir && ($files = egw_vfs::find($dir,array(
			'need_mime' => true,
			'order' => 'fs_name',
			'sort' => 'ASC',
		),true)))
		{
			foreach($files as $key => $file)
			{
				// use only the mime-types we support
				if (!self::is_implemented($file['mime'],'.'.array_pop($parts=explode('.',$file['name']))) ||
					$file['path'] === $default_doc)	// default doc already added
				{
					unset($files[$key]);
				}
			}
			foreach($files as $file)
			{
				if (count($files) >= self::SHOW_DOCS_BY_MIME_LIMIT)
				{
					if (!isset($documents[$file['mime']]))
					{
						$documents[$file['mime']] = array(
							'icon' => egw_vfs::mime_icon($file['mime']),
							'caption' => mime_magic::mime2label($file['mime']),
							'group' => 2,
							'children' => array(),
						);
					}
					$documents[$file['mime']]['children'][$prefix.$file['name']] = egw_vfs::decodePath($file['name']);
				}
				else
				{
					$documents[$prefix.$file['name']] = array(
						'icon' => egw_vfs::mime_icon($file['mime']),
						'caption' => egw_vfs::decodePath($file['name']),
						'group' => 2,
					);
				}
			}
		}

		return array(
			'icon' => 'etemplate/merge',
			'caption' => $caption,
			'children' => $documents,
			'enabled' => (boolean)$documents,
			'hideOnDisabled' => true,	// do not show 'Insert in document', if no documents defined
			'group' => $group,
		);
	}

	/**
	 * Check if given document (relative path from document_actions()) exists in one of the given dirs
	 *
	 * @param string &$document maybe relative path of document, on return true absolute path to existing document
	 * @param string $dirs comma or whitespace separated directories
	 * @return string|boolean false if document exists, otherwise string with error-message
	 */
	public static function check_document(&$document, $dirs)
	{
		if($document[0] !== '/')
		{
			// split multiple comma or whitespace separated directories
			// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
			if ($dirs && ($dirs = preg_split('/[,\s]+\//', $dirs)))
			{
				foreach($dirs as $n => $dir)
				{
					if ($n) $dir = '/'.$dir;	// re-adding trailing slash removed by split
					if (egw_vfs::stat($dir.'/'.$document) && egw_vfs::is_readable($dir.'/'.$document))
					{
						$document = $dir.'/'.$document;
						return false;
					}
				}
			}
		}
		return lang("Document '%1' does not exist or is not readable for you!",$document);
	}

	/**
	 * Get a list of supported extentions
	 */
	public static function get_file_extensions()
	{
		return array('txt', 'rtf', 'odt', 'ods', 'docx', 'xml');
	}

	/**
	 * Format a number according to user prefs with decimal and thousands separator
	 *
	 * Reimplemented from etemplate to NOT use user prefs for Excel 2003, which gives an xml error
	 *
	 * @param int|float|string $number
	 * @param int $num_decimal_places=2
	 * @param string $_mimetype=''
	 * @return string
	 */
	static public function number_format($number,$num_decimal_places=2,$_mimetype='')
	{
		if ((string)$number === '') return '';
		//error_log(__METHOD__.$_mimetype);
		switch($_mimetype)
		{
			case 'application/xml':	// Excel 2003
			case 'application/vnd.oasis.opendocument.spreadsheet': // OO.o spreadsheet
				return number_format(str_replace(' ','',$number),$num_decimal_places,'.','');
		}
		return etemplate::number_format($number,$num_decimal_places);
	}
}
