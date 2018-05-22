<?php
/**
 * EGroupware - Document merge print
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @subpackage storage
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

namespace EGroupware\Api\Storage;

use EGroupware\Api;
use EGroupware\Stylite;

use DOMDocument;
use XSLTProcessor;
use tidy;
use ZipArchive;

// explicit import old, non-namespaced phpgwapi classes
use uiaccountsel;

/**
 * Document merge print
 *
 * @todo move apply_styles call into merge_string to run for each entry merged and not all together to lower memory requirements
 */
abstract class Merge
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
	 * Fields that are to be treated as datetimes, when merged into spreadsheets
	 */
	var $date_fields = array();

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
	 * Export limit in number of entries or some non-numerical value, if no export allowed at all, empty means no limit
	 *
	 * Set by constructor to $GLOBALS[egw_info][server][export_limit]
	 *
	 * @var int|string
	 */
	public $export_limit;


	/**
	 * Configuration for HTML Tidy to clean up any HTML content that is kept
	 */
	public static $tidy_config = array(
		'output-xml'		=> true,	// Entity encoding
		'show-body-only'	=> true,
		'output-encoding'	=> 'utf-8',
		'input-encoding'	=> 'utf-8',
		'quote-ampersand'	=> false,	// Prevent double encoding
		'quote-nbsp'		=> true,	// XSLT can handle spaces easier
		'preserve-entities'	=> true,
		'wrap'			=> 0,		// Wrapping can break output
	);

	/**
	 * Parse HTML styles into target document style, if possible
	 *
	 * Apps not using html in there own data should set this with Customfields::use_html($app)
	 * to avoid memory and time consuming html processing.
	 */
	protected $parse_html_styles = true;

	/**
	 * Enable this to report memory_usage to error_log
	 *
	 * @var boolean
	 */
	public $report_memory_usage = false;

	/**
	 * Constructor
	 */
	function __construct()
	{
		// Common messages are in preferences
		Api\Translation::add_app('preferences');
		// All contact fields are in addressbook
		Api\Translation::add_app('addressbook');

		$this->contacts = new Api\Contacts();

		$this->datetime_format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'].' '.
			($GLOBALS['egw_info']['user']['preferences']['common']['timeformat']==12 ? 'h:i a' : 'H:i');

		$this->export_limit = self::getExportLimit();
	}

	/**
	 * Hook returning options for export_limit_excepted groups
	 *
	 * @param array $config
	 */
	public static function hook_export_limit_excepted($config)
	{
		$accountsel = new uiaccountsel();

		return '<input type="hidden" value="" name="newsettings[export_limit_excepted]" />'.
			$accountsel->selection('newsettings[export_limit_excepted]','export_limit_excepted',$config['export_limit_excepted'],'both',4);
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
		static $zip_available=null;
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
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
				if (!$zip_available) break;
				return true;	// open office write xml files
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms word 2007 xml format
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
			case 'application/vnd.ms-word.document.macroenabled.12':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':	// ms excel 2007 xml format
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.shee':
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
				if (!$zip_available) break;
				return true;	// ms word xml format
			case 'application/xml':
				return true;	// alias for text/xml, eg. ms office 2003 word format
			case 'message/rfc822':
				return true; // ToDo: check if you are theoretical able to send mail
			case 'application/x-yaml':
				return true;	// yaml file, plain text with marginal syntax support for multiline replacements
			default:
				if (substr($mimetype,0,5) == 'text/')
				{
					return true;	// text files
				}
				break;
		}
		return false;

		// As browsers not always return correct mime types, one could use a negative list instead
		//return !($mimetype == Api\Vfs::DIR_MIME_TYPE || substr($mimetype,0,6) == 'image/');
	}

	/**
	 * Return replacements for a contact
	 *
	 * @param int|string|array $contact contact-array or id
	 * @param string $prefix ='' prefix like eg. 'user'
	 * @param boolean $ignore_acl =false true: no acl check
	 * @return array
	 */
	public function contact_replacements($contact,$prefix='',$ignore_acl=false, &$content = '')
	{
		if (!is_array($contact))
		{
			$contact = $this->contacts->read($contact, $ignore_acl);
		}
		if (!is_array($contact)) return array();

		$replacements = array();
		foreach(array_keys($this->contacts->contact_fields) as $name)
		{
			$value = $contact[$name];
			switch($name)
			{
				case 'created': case 'modified':
					if($value) $value = Api\DateTime::to($value);
					break;
				case 'bday':
					if ($value)
					{
						try {
							$value = Api\DateTime::to($value, true);
						}
						catch (\Exception $e) {
							unset($e);	// ignore exception caused by wrongly formatted date
						}
					}
					break;
				case 'owner': case 'creator': case 'modifier':
					$value = Api\Accounts::username($value);
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
						$value = Api\Framework::getUrl(Api\Framework::link('/index.php',$contact['photo']));
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
		// set custom fields, should probably go to a general method all apps can use
		// need to load all cfs for $ignore_acl=true
		foreach($ignore_acl ? Customfields::get('addressbook', true) : $this->contacts->customfields as $name => $field)
		{
			$name = '#'.$name;
			$replacements['$$'.($prefix ? $prefix.'/':'').$name.'$$'] =
				// use raw data for yaml, no user-preference specific formatting
				$this->mimetype == 'application/x-yaml' ? (string)$contact[$name] :
				Customfields::format($field, (string)$contact[$name]);
		}

		if($content && strpos($content, '$$#') !== FALSE)
		{
			$this->cf_link_to_expand($contact, $content, $replacements, 'addressbook');
		}

		// Add in extra cat field
		$cats = array();
		foreach(is_array($contact['cat_id']) ? $contact['cat_id'] : explode(',',$contact['cat_id']) as $cat_id)
		{
			if(!$cat_id) continue;
			if($GLOBALS['egw']->categories->id2name($cat_id,'main') != $cat_id)
			{
				$path = explode(' / ', $GLOBALS['egw']->categories->id2name($cat_id, 'path'));
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
	 * Get links for the given record
	 *
	 * Uses egw_link system to get link titles
	 *
	 * @param app Name of current app
	 * @param id ID of current entry
	 * @param only_app Restrict links to only given application
	 * @param exclude Exclude links to these applications
	 * @param style String One of:
	 * 	'title' - plain text, just the title of the link
	 * 	'link' - URL to the entry
	 * 	'href' - HREF tag wrapped around the title
	 */
	protected function get_links($app, $id, $only_app='', $exclude = array(), $style = 'title')
	{
		$links = Api\Link::get_links($app, $id, $only_app);
		$link_titles = array();
		foreach($links as $link_info)
		{
			// Using only_app only returns the ID
			if(!is_array($link_info) && $only_app && $only_app[0] !== '!')
			{
				$link_info = array(
					'app'	=> $only_app,
					'id'	=> $link_info
				);
			}
			if($exclude && in_array($link_info['id'], $exclude)) continue;

			$title = Api\Link::title($link_info['app'], $link_info['id']);
			if(class_exists('EGroupware\Stylite\Vfs\Links\StreamWrapper') && $link_info['app'] != Api\Link::VFS_APPNAME)
			{
				$title = Stylite\Vfs\Links\StreamWrapper::entry2name($link_info['app'], $link_info['id'], $title);
			}
			if($style == 'href' || $style == 'link')
			{
				$link = Api\Link::view($link_info['app'], $link_info['id'], $link_info);
				if($link_info['app'] != Api\Link::VFS_APPNAME)
				{
					// Set app to false so we always get an external link
					$link = str_replace(',', '%2C', $GLOBALS['egw']->framework->link('/index.php',$link, false));
				}
				else
				{
					$link = Api\Framework::link($link, array());
				}
				// Prepend site
				if ($link[0] == '/') $link = Api\Framework::getUrl($link);

				$title = $style == 'href' ? Api\Html::a_href(Api\Html::htmlspecialchars($title), $link) : $link;
			}
			$link_titles[] = $title;
		}
		return implode("\n",$link_titles);
	}

	/**
	 * Get all link placeholders
	 *
	 * Calls get_links() repeatedly to get all the combinations for the content.
	 *
	 * @param $app String appname
	 * @param $id String ID of record
	 * @param $prefix
	 * @param $content String document content
	 */
	protected function get_all_links($app, $id, $prefix, &$content)
	{
		$array = array();
		$pattern = '@\$\$(links_attachments|links|attachments|link)\/?(title|href|link)?\/?([a-z]*)\$\$@';
		static $link_cache=null;
		$matches = null;
		if(preg_match_all($pattern, $content, $matches))
		{
			foreach($matches[0] as $i => $placeholder)
			{
				$placeholder = substr($placeholder, 2, -2);
				if($link_cache[$id][$placeholder])
				{
					$array[$placeholder] = $link_cache[$id][$placeholder];
					continue;
				}
				switch($matches[1][$i])
				{
					case 'link':
						// Link to current record
						$title = Api\Link::title($app, $id);
						if(class_exists('EGroupware\Stylite\Vfs\Links\StreamWrapper') && $app != Api\Link::VFS_APPNAME)
						{
							$title = Stylite\Vfs\Links\StreamWrapper::entry2name($app, $id, $title);
						}

						$link = Api\Link::view($app, $id);
						if($app != Api\Link::VFS_APPNAME)
						{
							// Set app to false so we always get an external link
							$link = str_replace(',', '%2C', $GLOBALS['egw']->framework->link('/index.php',$link, false));
						}
						else
						{
							$link = Api\Framework::link($link, array());
						}
						// Prepend site
						if ($link[0] == '/') $link = Api\Framework::getUrl($link);

						$array['$$'.($prefix?$prefix.'/':'').$placeholder.'$$'] = Api\Html::a_href(Api\Html::htmlspecialchars($title), $link);
						break;
					case 'links':
						$link_app = $matches[3][$i] ? $matches[3][$i] :  '!'.Api\Link::VFS_APPNAME;
						$array['$$'.($prefix?$prefix.'/':'').$placeholder.'$$'] = $this->get_links($app, $id, $link_app, array(),$matches[2][$i]);
						break;
					case 'attachments':
						$array['$$'.($prefix?$prefix.'/':'').$placeholder.'$$'] = $this->get_links($app, $id, Api\Link::VFS_APPNAME,array(),$matches[2][$i]);
						break;
					default:
						$array['$$'.($prefix?$prefix.'/':'').$placeholder.'$$'] = $this->get_links($app, $id, $matches[3][$i], array(), $matches[2][$i]);
						break;
				}
				$link_cache[$id][$placeholder] = $array[$placeholder];
			}
		}
		return $array;
	}

	/**
	 * Format a datetime
	 *
	 * @param int|string|DateTime $time unix timestamp or Y-m-d H:i:s string (in user time!)
	 * @param string $format =null format string, default $this->datetime_format
	 * @deprecated use Api\DateTime::to($time='now',$format='')
	 * @return string
	 */
	protected function format_datetime($time,$format=null)
	{
		trigger_error(__METHOD__ . ' is deprecated, use Api\DateTime::to($time, $format)', E_USER_DEPRECATED);
		if (is_null($format)) $format = $this->datetime_format;

		return Api\DateTime::to($time,$format);
	}

	/**
	 * Checks if current user is excepted from the export-limit:
	 * a) access to admin application
	 * b) he or one of his memberships is named in export_limit_excepted config var
	 *
	 * @return boolean
	 */
	public static function is_export_limit_excepted()
	{
		static $is_excepted=null;

		if (is_null($is_excepted))
		{
			$is_excepted = isset($GLOBALS['egw_info']['user']['apps']['admin']);

			// check export-limit and fail if user tries to export more entries then allowed
			if (!$is_excepted && (is_array($export_limit_excepted = $GLOBALS['egw_info']['server']['export_limit_excepted']) ||
				is_array($export_limit_excepted = unserialize($export_limit_excepted))))
			{
				$id_and_memberships = $GLOBALS['egw']->accounts->memberships($GLOBALS['egw_info']['user']['account_id'],true);
				$id_and_memberships[] = $GLOBALS['egw_info']['user']['account_id'];
				$is_excepted = (bool) array_intersect($id_and_memberships, $export_limit_excepted);
			}
		}
		return $is_excepted;
	}

	/**
	 * Checks if there is an exportlimit set, and returns
	 *
	 * @param string $app ='common' checks and validates app_limit, if not set returns the global limit
	 * @return mixed - no if no export is allowed, false if there is no restriction and int as there is a valid restriction
	 *		you may have to cast the returned value to int, if you want to use it as number
	 */
	public static function getExportLimit($app='common')
	{
		static $exportLimitStore=array();
		if (empty($app)) $app='common';
		//error_log(__METHOD__.__LINE__.' called with app:'.$app);
		if (!array_key_exists($app,$exportLimitStore))
		{
			//error_log(__METHOD__.__LINE__.' -> '.$app_limit.' '.function_backtrace());
			$exportLimitStore[$app] = $GLOBALS['egw_info']['server']['export_limit'];
			if ($app !='common')
			{
				$app_limit = Api\Hooks::single('export_limit',$app);
				if ($app_limit) $exportLimitStore[$app] = $app_limit;
			}
			//error_log(__METHOD__.__LINE__.' building cache for app:'.$app.' -> '.$exportLimitStore[$app]);
			if (empty($exportLimitStore[$app]))
			{
				$exportLimitStore[$app] = false;
				return false;
			}

			if (is_numeric($exportLimitStore[$app]))
			{
				$exportLimitStore[$app] = (int)$exportLimitStore[$app];
			}
			else
			{
				$exportLimitStore[$app] = 'no';
			}
			//error_log(__METHOD__.__LINE__.' -> '.$exportLimit);
		}
		//error_log(__METHOD__.__LINE__.' app:'.$app.' -> '.$exportLimitStore[$app]);
		return $exportLimitStore[$app];
	}

	/**
	 * hasExportLimit
	 * checks wether there is an exportlimit set, and returns true or false
	 * @param mixed $app_limit app_limit, if not set checks the global limit
	 * @param string $checkas [AND|ISALLOWED], AND default; if set to ISALLOWED it is checked if Export is allowed
	 *
	 * @return bool - true if no export is allowed or a limit is set, false if there is no restriction
	 */
	public static function hasExportLimit($app_limit,$checkas='AND')
	{
		if (strtoupper($checkas) == 'ISALLOWED') return (empty($app_limit) || ($app_limit !='no' && $app_limit > 0) );
		if (empty($app_limit)) return false;
		if ($app_limit == 'no') return true;
		if ($app_limit > 0) return true;
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $document path/url of document
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @param array $fix =null regular expression => replacement pairs eg. to fix garbled placeholders
	 * @return string|boolean merged document or false on error
	 */
	public function &merge($document,$ids,&$err,$mimetype,array $fix=null)
	{
		if (!($content = file_get_contents($document)))
		{
			$err = lang("Document '%1' does not exist or is not readable for you!",$document);
			return false;
		}

		if (self::hasExportLimit($this->export_limit) && !self::is_export_limit_excepted() && count($ids) > (int)$this->export_limit)
		{
			$err = lang('No rights to export more than %1 entries!',(int)$this->export_limit);
			return false;
		}

		// fix application/msword mimetype for rtf files
		if ($mimetype == 'application/msword' && strtolower(substr($document,-4)) == '.rtf')
		{
			$mimetype = 'application/rtf';
		}

		try {
			$content = $this->merge_string($content,$ids,$err,$mimetype,$fix);
		} catch (\Exception $e) {
			_egw_log_exception($e);
			$err = $e->getMessage();
			return false;
		}
		return $content;
	}

	protected function apply_styles (&$content, $mimetype, $mso_application_progid=null)
	{
		if (!isset($mso_application_progid))
		{
			$matches = null;
			$mso_application_progid = $mimetype == 'application/xml' &&
				preg_match('/'.preg_quote('<?mso-application progid="').'([^"]+)'.preg_quote('"?>').'/',substr($content,0,200),$matches) ?
					$matches[1] : '';
		}
		// Tags we can replace with the target document's version
		$replace_tags = array();
		switch($mimetype.$mso_application_progid)
		{
			case 'application/vnd.oasis.opendocument.text':	// oo text
			case 'application/vnd.oasis.opendocument.spreadsheet':	// oo spreadsheet
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
				// It seems easier to split the parent tags here
				$replace_tags = array(
					'/<(ol|ul|table)( [^>]*)?>/' => '</text:p><$1$2>',
					'/<\/(ol|ul|table)>/' => '</$1><text:p>',
					//'/<(li)(.*?)>(.*?)<\/\1>/' => '<$1 $2>$3</$1>',
				);
				$content = preg_replace(array_keys($replace_tags),array_values($replace_tags),$content);

				$doc = new DOMDocument();
				$xslt = new XSLTProcessor();
				$doc->load(EGW_INCLUDE_ROOT.'/api/templates/default/Merge/openoffice.xslt');
				$xslt->importStyleSheet($doc);

//echo $content;die();
				break;
			case 'application/xmlWord.Document':	// Word 2003*/
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
			case 'application/vnd.ms-word.document.macroenabled.12':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
				// It seems easier to split the parent tags here
				$replace_tags = array(
					// Tables, lists don't go inside <w:p>
					'/<(ol|ul|table)( [^>]*)?>/' => '</w:t></w:r></w:p><$1$2>',
					'/<\/(ol|ul|table)>/' => '</$1><w:p><w:r><w:t>',
					// Fix for things other than text (newlines) inside table row
					'/<(td)( [^>]*)?>((?!<w:t>))(.*?)<\/td>[\s]*?/' => '<$1$2><w:t>$4</w:t></td>',
					// Remove extra whitespace
					'/<li([^>]*?)>[^:print:]*?(.*?)<\/li>/' => '<li$1>$2</li>', // This doesn't get it all
					'/<w:t>[\s]+(.*?)<\/w:t>/' => '<w:t>$1</w:t>',
					// Remove spans with no attributes, linebreaks inside them cause problems
					'/<span>(.*?)<\/span>/' => '$1'
				);
				$content = preg_replace(array_keys($replace_tags),array_values($replace_tags),$content);

				/*
				In the case where you have something like <span><span></w:t><w:br/><w:t></span></span> (invalid - mismatched tags),
				it takes multiple runs to get rid of both spans.  So, loop.
				OO.o files have not yet been shown to have this problem.
				*/
				$count = $i = 0;
				do
				{
					$content = preg_replace('/<span>(.*?)<\/span>/','$1',$content, -1, $count);
					$i++;
				} while($count > 0 && $i < 10);

				$doc = new DOMDocument();
				$xslt = new XSLTProcessor();
				$xslt_file = $mimetype == 'application/xml' ? 'wordml.xslt' : 'msoffice.xslt';
				$doc->load(EGW_INCLUDE_ROOT.'/api/templates/default/Merge/'.$xslt_file);
				$xslt->importStyleSheet($doc);
				break;
		}

		// XSLT transform known tags
		if($xslt)
		{
			// does NOT work with php 5.2.6: Catchable fatal error: argument 1 to transformToXml() must be of type DOMDocument
			//$element = new SimpleXMLelement($content);
			$element = new DOMDocument('1.0', 'utf-8');
			$result = $element->loadXML($content);
			if(!$result)
			{
				throw new Api\Exception('Unable to parse merged document for styles.  Check warnings in log for details.');
			}
			$content = $xslt->transformToXml($element);

			// Word 2003 needs two declarations, add extra declaration back in
			if($mimetype == 'application/xml' && $mso_application_progid == 'Word.Document' && strpos($content, '<?xml') !== 0) {
				$content = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$content;
			}
			// Validate
			/*
			$doc = new DOMDocument();
			$doc->loadXML($content);
			$doc->schemaValidate(*Schema (xsd) file*);
			*/
		}
	}

	/**
	 * Merges a given document with contact data
	 *
	 * @param string $_content
	 * @param array $ids array with contact id(s)
	 * @param string &$err error-message on error
	 * @param string $mimetype mimetype of complete document, eg. text/*, application/vnd.oasis.opendocument.text, application/rtf
	 * @param array $fix =null regular expression => replacement pairs eg. to fix garbled placeholders
	 * @param string $charset =null charset to override default set by mimetype or export charset
	 * @return string|boolean merged document or false on error
	 */
	public function &merge_string($_content,$ids,&$err,$mimetype,array $fix=null,$charset=null)
	{
		$matches = null;
		if ($mimetype == 'application/xml' &&
			preg_match('/'.preg_quote('<?mso-application progid="').'([^"]+)'.preg_quote('"?>').'/',substr($_content,0,200),$matches))
		{
			$mso_application_progid = $matches[1];
		}
		else
		{
			$mso_application_progid = '';
		}
		// alternative syntax using double curly brackets (eg. {{cat_id}} instead $$cat_id$$),
		// agressivly removing all xml-tags eg. Word adds within placeholders
		$content = preg_replace_callback('/{{[^}]+}}/i', function($matches)
		{
			return '$$'.strip_tags(substr($matches[0], 2, -2)).'$$';
		}, $_content);
		// Handle escaped placeholder markers in RTF, they won't match when escaped
		if($mimetype == 'application/rtf')
		{
			$content = preg_replace('/\\\{\\\{([^\\}]+)\\\}\\\}/i','$$\1$$',$content);
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
		if (in_array($mimetype, array('application/vnd.oasis.opendocument.text','application/vnd.oasis.opendocument.text-template')) && count($ids) > 1)
		{
			if(strpos($content, '$$pagerepeat') === false)
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
			else
			{
				// Template specifies where to repeat
				list($contentstart,$contentrepeat,$contentend) = preg_split('/\$\$pagerepeat\$\$/',$content,-1, PREG_SPLIT_NO_EMPTY);  //get different parts of document, seperated by pagerepeat
			}
		}
		if (in_array($mimetype, array('application/vnd.ms-word.document.macroenabled.12', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')) && count($ids) > 1)
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
			$err = lang('for more than one contact in a document use the tag pagerepeat!');
			return false;
		}
		if ($this->report_memory_usage) error_log(__METHOD__."(count(ids)=".count($ids).") strlen(contentrepeat)=".strlen($contentrepeat).', strlen(labelrepeat)='.strlen($Labelrepeat));

		if ($contentrepeat)
		{
			$content_stream = fopen('php://temp','r+');
			fwrite($content_stream, $contentstart);
			$joiner = '';
			switch($mimetype)
			{
				case 'application/rtf':
				case 'text/rtf':
					$joiner = '\\par \\page\\pard\\plain';
					break;
				case 'application/vnd.oasis.opendocument.text':	// oo text
				case 'application/vnd.oasis.opendocument.spreadsheet':	// oo spreadsheet
				case 'application/vnd.oasis.opendocument.presentation':
				case 'application/vnd.oasis.opendocument.text-template':
				case 'application/vnd.oasis.opendocument.spreadsheet-template':
				case 'application/vnd.oasis.opendocument.presentation-template':
				case 'application/xml':
				case 'text/html':
				case 'text/csv':
					$joiner = '';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.ms-word.document.macroenabled.12':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				case 'application/vnd.ms-excel.sheet.macroenabled.12':
					$joiner = '<w:br w:type="page" />';
					break;
				case 'text/plain':
					$joiner = "\r\n";
					break;
				default:
					$err = lang('%1 not implemented for %2!','$$pagerepeat$$',$mimetype);
					return false;
			}
		}
		foreach ((array)$ids as $n => $id)
		{
			if ($contentrepeat) $content = $contentrepeat;   //content to repeat
			if ($lableprint) $content = $Labelrepeat;

			// generate replacements; if exeption is thrown, catch it set error message and return false
			try
			{
				if(!($replacements = $this->get_replacements($id,$content)))
				{
					$err = lang('Entry not found!');
					return false;
				}
			}
			catch (Api\Exception\WrongUserinput $e)
			{
				// if this returns with an exeption, something failed big time
				$err = $e->getMessage();
				return false;
			}
			if ($this->report_memory_usage) error_log(__METHOD__."() $n: $id ".Api\Vfs::hsize(memory_get_usage(true)));
			// some general replacements: current user, date and time
			if (strpos($content,'$$user/') !== null && ($user = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')))
			{
				$replacements += $this->contact_replacements($user,'user');
				$replacements['$$user/primary_group$$'] = $GLOBALS['egw']->accounts->id2name($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'account_primary_group'));
			}
			$replacements['$$date$$'] = Api\DateTime::to('now',true);
			$replacements['$$datetime$$'] = Api\DateTime::to('now');
			$replacements['$$time$$'] = Api\DateTime::to('now',false);

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
			$content = $this->process_commands($this->replace($content,$replacements,$mimetype,$mso_application_progid,$charset), $replacements);

			// remove not existing replacements (eg. from calendar array)
			if (strpos($content,'$$') !== null)
			{
				$content = preg_replace('/\$\$[a-z0-9_\/]+\$\$/i','',$content);
			}
			if ($contentrepeat)
			{
				fwrite($content_stream, ($n == 0 ? '' : $joiner) . $content);
			}
			if($lableprint)
			{
				$contentrep[is_array($id) ? implode(':',$id) : $id] = $content;
			}
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
				case 'application/vnd.oasis.opendocument.presentation':
				case 'application/vnd.oasis.opendocument.text-template':
				case 'application/vnd.oasis.opendocument.presentation-template':
					return $contentstart.implode('<text:line-break />',$contentrepeatpages).$contentend;
				case 'application/vnd.oasis.opendocument.spreadsheet':
				case 'application/vnd.oasis.opendocument.spreadsheet-template':
					return $contentstart.implode('</text:p><text:p>',$contentrepeatpages).$contentend;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.ms-word.document.macroenabled.12':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				case 'application/vnd.ms-excel.sheet.macroenabled.12':
					return $contentstart.implode('<w:br w:type="page" />',$contentrepeatpages).$contentend;
				case 'text/plain':
					return $contentstart.implode("\r\n",$contentrep).$contentend;
			}
			$err = lang('%1 not implemented for %2!','$$labelplacement$$',$mimetype);
			return false;
		}

		if ($contentrepeat)
		{
			fwrite($content_stream, $contentend);
			rewind($content_stream);
			return stream_get_contents($content_stream);
		}
		if ($this->report_memory_usage) error_log(__METHOD__."() returning ".Api\Vfs::hsize(memory_get_peak_usage(true)));

		return $content;
	}

	/**
	 * Replace placeholders in $content of $mimetype with $replacements
	 *
	 * @param string $content
	 * @param array $replacements name => replacement pairs
	 * @param string $mimetype mimetype of content
	 * @param string $mso_application_progid ='' MS Office 2003: 'Excel.Sheet' or 'Word.Document'
	 * @param string $charset =null charset to override default set by mimetype or export charset
	 * @return string
	 */
	protected function replace($content,array $replacements,$mimetype,$mso_application_progid='',$charset=null)
	{
		switch($mimetype)
		{
			case 'application/vnd.oasis.opendocument.text':		// open office
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
			case 'application/vnd.ms-word.document.macroenabled.12':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
			case 'application/xml':
			case 'text/xml':
				$is_xml = true;
				$charset = 'utf-8';	// xml files --> always use utf-8
				break;

			case 'text/html':
				$is_xml = true;
				$matches = null;
				if (preg_match('/<meta http-equiv="content-type".*charset=([^;"]+)/i',$content,$matches))
				{
					$charset = $matches[1];
				}
				elseif (empty($charset))
				{
					$charset = 'utf-8';
				}
				break;

			default:	// div. text files --> use our export-charset, defined in addressbook prefs
				if (empty($charset)) $charset = $this->contacts->prefs['csv_charset'];
				break;
		}
		//error_log(__METHOD__."('$document', ... ,$mimetype) --> $charset (egw=".Api\Translation::charset().', export='.$this->contacts->prefs['csv_charset'].')');

		// do we need to convert charset
		if ($charset && $charset != Api\Translation::charset())
		{
			$replacements = Api\Translation::convert($replacements,Api\Translation::charset(),$charset);
		}

		// Date only placeholders for timestamps
		if(is_array($this->date_fields))
		{
			foreach($this->date_fields as $field)
			{
				if(($value = $replacements['$$'.$field.'$$']))
				{
					$time = Api\DateTime::createFromFormat('+'.Api\DateTime::$user_dateformat.' '.Api\DateTime::$user_timeformat.'*', $value);
					$replacements['$$'.$field.'/date$$'] = $time ? $time->format(Api\DateTime::$user_dateformat)  : '';
				}
			}
		}
		if ($is_xml)	// zip'ed xml document (eg. OO)
		{
			// Numeric fields
			$names = array();

			// Tags we can replace with the target document's version
			$replace_tags = array();
			// only keep tags, if we have xsl extension available
			if (class_exists('XSLTProcessor') && class_exists('DOMDocument') && $this->parse_html_styles)
			{
				switch($mimetype.$mso_application_progid)
				{
					case 'text/html':
						$replace_tags = array(
							'<b>','<strong>','<i>','<em>','<u>','<span>','<ol>','<ul>','<li>',
							'<table>','<tr>','<td>','<a>','<style>',
						);
						break;
					case 'application/vnd.oasis.opendocument.text':		// open office
					case 'application/vnd.oasis.opendocument.spreadsheet':
					case 'application/vnd.oasis.opendocument.presentation':
					case 'application/vnd.oasis.opendocument.text-template':
					case 'application/vnd.oasis.opendocument.spreadsheet-template':
					case 'application/vnd.oasis.opendocument.presentation-template':
						$replace_tags = array(
							'<b>','<strong>','<i>','<em>','<u>','<span>','<ol>','<ul>','<li>',
							'<table>','<tr>','<td>','<a>',
						);
						break;
					case 'application/xmlWord.Document':	// Word 2003*/
					case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
					case 'application/vnd.ms-word.document.macroenabled.12':
					case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					case 'application/vnd.ms-excel.sheet.macroenabled.12':
						$replace_tags = array(
							'<b>','<strong>','<i>','<em>','<u>','<span>','<ol>','<ul>','<li>',
							'<table>','<tr>','<td>',
						);
						break;
				}
			}
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

				if (is_string($value) && (strpos($value,'&') !== false) && $this->parse_html_styles)
				{
					$value = html_entity_decode($value,ENT_QUOTES,$charset);

					// remove all non-decodable entities
					if (strpos($value,'&') !== false)
					{
						$value = preg_replace('/&[^; ]+;/','',$value);
					}
				}
				if(!$this->parse_html_styles || (
					strpos($value, "\n") !== FALSE && strpos($value,'<br') === FALSE && strpos($value, '<span') === FALSE && strpos($value, '<p') === FALSE
				))
				{
					// Encode special chars so they don't break the file
					$value = htmlspecialchars($value,ENT_NOQUOTES);
				}
				else if (is_string($value) && (strpos($value,'<') !== false))
				{
					// Clean HTML, if it's being kept
					if($replace_tags && extension_loaded('tidy')) {
						$tidy = new tidy();
						$cleaned = $tidy->repairString($value, self::$tidy_config);
						// Found errors. Strip it all so there's some output
						if($tidy->getStatus() == 2)
						{
							error_log($tidy->errorBuffer);
							$value = strip_tags($value);
						}
						else
						{
							$value = $cleaned;
						}
					}
					// replace </p> and <br /> with CRLF (remove <p> and CRLF)
					$value = strip_tags(str_replace(array("\r","\n",'<p>','</p>','<div>','</div>','<br />'),
						array('','','',"\r\n",'',"\r\n","\r\n"), $value),
						implode('', $replace_tags));

					// Change <tag>...\r\n</tag> to <tag>...</tag>\r\n or simplistic line break below will mangle it
					// Loop to catch things like <b><span>Break:\r\n</span></b>
					if($mso_application_progid)
					{
						$count = $i = 0;
						do
						{
							$value = preg_replace('/<(b|strong|i|em|u|span)\b([^>]*?)>(.*?)'."\r\n".'<\/\1>/u', '<$1$2>$3</$1>'."\r\n",$value,-1,$count);
							$i++;
						} while($count > 0 && $i < 10); // Limit of 10 chosen arbitrarily just in case
					}
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
			if($this->numeric_fields || count($names))
			{
				foreach((array)$this->numeric_fields as $fieldname) {
					$names[] = preg_quote($fieldname,'/');
				}
				$this->format_spreadsheet_numbers($content, $names, $mimetype.$mso_application_progid);
			}

			// Look for dates, set their value if needed
			if($this->date_fields || count($names))
			{
				$names = array();
				foreach((array)$this->date_fields as $fieldname) {
					$names[] = $fieldname;
				}
				$this->format_spreadsheet_dates($content, $names, $replacements, $mimetype.$mso_application_progid);
			}

			// replace CRLF with linebreak tag of given type
			switch($mimetype.$mso_application_progid)
			{
				case 'application/vnd.oasis.opendocument.text':		// open office writer
				case 'application/vnd.oasis.opendocument.text-template':
				case 'application/vnd.oasis.opendocument.presentation':
				case 'application/vnd.oasis.opendocument.presentation-template':
					$break = '<text:line-break/>';
					break;
				case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
				case 'application/vnd.oasis.opendocument.spreadsheet-template':
					$break = '</text:p><text:p>';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms word 2007
				case 'application/vnd.ms-word.document.macroenabled.12':
					$break = '</w:t><w:br/><w:t>';
					break;
				case 'application/xmlExcel.Sheet':	// Excel 2003
					$break = '&#10;';
					break;
				case 'application/xmlWord.Document':	// Word 2003*/
					$break = '</w:t><w:br/><w:t>';
					break;
				case 'text/html':
					$break = '<br/>';
					break;
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':	// ms excel 2007
				case 'application/vnd.ms-excel.sheet.macroenabled.12':
				default:
					$break = "\r\n";
					break;
			}
			// now decode &, < and >, which need to be encoded as entities in xml
			// Check for encoded >< getting double-encoded
			if($this->parse_html_styles)
			{
				$replacements = str_replace(array('&',"\r","\n",'&amp;lt;','&amp;gt;'),array('&amp;','',$break,'&lt;','&gt;'),$replacements);
			}
			else
			{
				// Need to at least handle new lines, or it'll be run together on one line
				$replacements = str_replace(array("\r","\n"),array('',$break),$replacements);
			}
		}
		if ($mimetype == 'application/x-yaml')
		{
			$content = preg_replace_callback('/^( +)([^$\n]*)(\$\$.+?\$\$)/m', function($matches) use ($replacements)
			{
				// allow with {{name/replace/with}} syntax to replace eg. commas with linebreaks: "{{name/, */\n}}"
				$parts = null;
				if (preg_match('|^\$\$([^/]+)/([^/]+)/([^$]*)\$\$$|', $matches[3], $parts) && isset($replacements['$$'.$parts[1].'$$']))
				{
					$replacement =& $replacements['$$'.$parts[1].'$$'];
					$replacement = preg_replace('/'.$parts[2].'/', strtr($parts[3], array(
						'\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\v' => "\v", '\\\\' => '\\', '\\f' => "\f",
					)), $replacement);
				}
				else
				{
					$replacement =& $replacements[$matches[3]];
				}
				// replacement with multiple lines --> add same number of space as before placeholder
				if (isset($replacement))
				{
					return $matches[1].$matches[2].implode("\n".$matches[1], preg_split("/\r?\n/", $replacement));
				}
				return $matches[0];	// regular replacement below
			}, $content);
		}
		return str_replace(array_keys($replacements),array_values($replacements),$content);
	}

	/**
	 * Convert numeric values in spreadsheets into actual numeric values
	 */
	protected function format_spreadsheet_numbers(&$content, $names, $mimetype)
	{
		foreach((array)$this->numeric_fields as $fieldname) {
			$names[] = preg_quote($fieldname,'/');
		}
		switch($mimetype)
		{
			case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
				$format = '/<table:table-cell([^>]+?)office:value-type="[^"]+"([^>]*?)(?:calcext:value-type="[^"]+")?>.?<([a-z].*?)[^>]*>('.implode('|',$names).')<\/\3>.?<\/table:table-cell>/s';
				$replacement = '<table:table-cell$1office:value-type="float" office:value="$4"$2><$3>$4</$3></table:table-cell>';
				break;
			case 'application/vnd.oasis.opendocument.text':		// tables in open office writer
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
				$format = '/<table:table-cell([^>]+?)office:value-type="[^"]+"([^>]*?)>.?<([a-z].*?)[^>]*>('.implode('|',$names).')<\/\3>.?<\/table:table-cell>/s';
				$replacement = '<table:table-cell$1office:value-type="float" office:value="$4"$2><text:p text:style-name="Standard">$4</text:p></table:table-cell>';
				break;
			case 'application/vnd.oasis.opendocument.text':		// open office writer
			case 'application/xmlExcel.Sheet':	// Excel 2003
				$format = '/'.preg_quote('<Data ss:Type="String">','/').'('.implode('|',$names).')'.preg_quote('</Data>','/').'/';
				$replacement = '<Data ss:Type="Number">$1</Data>';

				break;
		}
		if($format && $names)
		{
			// Dealing with backtrack limit per AmigoJack 10-Jul-2010 comment on php.net preg-replace docs
			do {
				$result = preg_replace($format, $replacement, $content, -1);
			}
			// try to increase/double pcre.backtrack_limit failure
			while(preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR && self::increase_backtrack_limit());

			if ($result) $content = $result;  // On failure $result would be NULL
		}
	}

	/**
	 * Increase/double prce.backtrack_limit up to 1/4 of memory_limit
	 *
	 * @return boolean true: backtrack_limit increased, may try again, false limit already to high
	 */
	protected static function increase_backtrack_limit()
	{
		static $backtrack_limit=null,$memory_limit=null;
		if (!isset($backtrack_limit))
		{
			$backtrack_limit = ini_get('pcre.backtrack_limit');
		}
		if (!isset($memory_limit))
		{
			$memory_limit = ini_get('memory_limit');
			switch(strtoupper(substr($memory_limit, -1)))
			{
				case 'G': $memory_limit *= 1024;
				case 'M': $memory_limit *= 1024;
				case 'K': $memory_limit *= 1024;
			}
		}
		if ($backtrack_limit < $memory_limit/8)
		{
			ini_set( 'pcre.backtrack_limit', $backtrack_limit*=2);
			return true;
		}
		error_log("pcre.backtrack_limit exceeded @ $backtrack_limit, some cells left as text.");
		return false;
	}

	/**
	 * Convert date / timestamp values in spreadsheets into actual date / timestamp values
	 */
	protected function format_spreadsheet_dates(&$content, $names, &$values, $mimetype)
	{
		if(!in_array($mimetype, array(
			'application/vnd.oasis.opendocument.spreadsheet',		// open office calc
			'application/xmlExcel.Sheet',					// Excel 2003
			//'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'//Excel WTF
		))) return;

		// Some different formats dates could be in, depending what they've been through
		$formats = array(
			'!'.Api\DateTime::$user_dateformat . ' ' .Api\DateTime::$user_timeformat.':s',
			'!'.Api\DateTime::$user_dateformat . '*' .Api\DateTime::$user_timeformat.':s',
			'!'.Api\DateTime::$user_dateformat . '* ' .Api\DateTime::$user_timeformat,
			'!'.Api\DateTime::$user_dateformat . '*',
			'!'.Api\DateTime::$user_dateformat,
			'!Y-m-d\TH:i:s'
		);

		// Properly format values for spreadsheet
		foreach($names as $idx => &$field)
		{
			$key = '$$'.$field.'$$';
			$field = preg_quote($field, '/');
			if($values[$key])
			{
				$date = Api\DateTime::createFromUserFormat($values[$key]);
				if($mimetype == 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ||
					$mimetype == 'application/vnd.ms-excel.sheet.macroenabled.12')//Excel WTF
				{
					$interval = $date->diff(new Api\DateTime('1900-01-00 0:00'));
					$values[$key] = $interval->format('%a')+1;// 1900-02-29 did not exist
					// 1440 minutes in a day - fractional part
					$values[$key] += ($date->format('H') * 60 + $date->format('i'))/1440;
				}
				else
				{
					$values[$key] = date('Y-m-d\TH:i:s',Api\DateTime::to($date,'ts'));
				}
			}
			else
			{
				unset($names[$idx]);
			}
		}

		switch($mimetype)
		{
			case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
				// Removing these forces calc to respect our set value-type
				$content = str_ireplace('calcext:value-type="string"','',$content);

				$format = '/<table:table-cell([^>]+?)office:value-type="[^"]+"([^>]*?)>.?<([a-z].*?)[^>]*>\$\$('.implode('|',$names).')\$\$<\/\3>.?<\/table:table-cell>/s';
				$replacement = '<table:table-cell$1office:value-type="date" office:date-value="\$\$$4\$\$"$2><$3>\$\$$4\$\$</$3></table:table-cell>';
				break;
			case 'application/xmlExcel.Sheet':	// Excel 2003
				$format = '/'.preg_quote('<Data ss:Type="String">','/').'..('.implode('|',$names).')..'.preg_quote('</Data>','/').'/';
				$replacement = '<Data ss:Type="DateTime">\$\$$1\$\$</Data>';

				break;
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
				break;
		}
		if($format && $names)
		{
			// Dealing with backtrack limit per AmigoJack 10-Jul-2010 comment on php.net preg-replace docs
			do {
				$result = preg_replace($format, $replacement, $content, -1);
			}
			// try to increase/double pcre.backtrack_limit failure
			while(preg_last_error() == PREG_BACKTRACK_LIMIT_ERROR && self::increase_backtrack_limit());

			if ($result) $content = $result;  // On failure $result would be NULL
		}
	}

	/**
	 * Expand link_to custom fields with the merge replacements from the app
	 * but only if the template uses them.
	 */
	public function cf_link_to_expand($values, $content, &$replacements, $app = null)
	{
		if($app == null)
		{
			$app = str_replace('_merge','',get_class($this));
		}
		$cfs = Api\Storage\Customfields::get($app);

		// Cache, in case more than one sub-placeholder is used
		$app_replacements = array();

		// Custom field placeholders look like {{#name}}
		// Placeholders that need expanded will look like {{#name/placeholder}}
		$matches = null;
		preg_match_all('/\${2}(([^\/#]*?\/)?)#([^$\/]+)\/(.*?)[$}]{2}/', $content, $matches);
		list($placeholders, , , $cf, $sub) = $matches;

		// Collect any used custom fields from entries so you can do
		// {{#other_app/#other_app_cf/n_fn}}
		$expand_sub_cfs = [];
		foreach($sub as $index => $cf_sub)
		{
			if(strpos($cf_sub, '#') === 0)
			{
				$expand_sub_cfs[$cf[$index]] .= '$$'.$cf_sub . '$$ ';
			}
		}

		foreach($cf as $index => $field)
		{
			if($cfs[$field])
			{
				if(in_array($cfs[$field]['type'],array_keys($GLOBALS['egw_info']['apps'])))
				{
					$field_app = $cfs[$field]['type'];
				}
				else if ($cfs[$field]['type'] == 'api-accounts' || $cfs[$field]['type'] == 'select-account')
				{
					// Special case for api-accounts -> contact
					$field_app = 'addressbook';
					$account = $GLOBALS['egw']->accounts->read($values['#'.$field]);
					$app_replacements[$field] = $this->contact_replacements($account['person_id']);
				}
				else if (($list = explode('-',$cfs[$field]['type']) && in_array($list[0], array_keys($GLOBALS['egw_info']['apps']))))
				{
					// Sub-type - use app
					$field_app = $list[0];
				}
				else
				{
					continue;
				}

				// Get replacements for that application
				if(!$app_replacements[$field])
				{
					// If we send the real content it can result in infinite loop of lookups
					// so we send only the used fields
					$content = $expand_sub_cfs[$field] ? $expand_sub_cfs[$field] : '';
					$app_replacements[$field] = $this->get_app_replacements($field_app, $values['#'.$field], $content);
				}
				$replacements[$placeholders[$index]] = $app_replacements[$field]['$$'.$sub[$index].'$$'];
			}
			else
			{
				if ($cfs[$field]['type'] == 'date' || $cfs[$field]['type'] == 'date-time') $this->date_fields[] = '#'.$field;
			}
		}
	}

	/**
	 * Get the replacements for any entry specified by app & id
	 *
	 * @param stribg $app
	 * @param string $id
	 * @param string $content
	 * @return array
	 */
	protected function get_app_replacements($app, $id, $content, $prefix='')
	{
		$replacements = array();
		if($app == 'addressbook')
		{
			return $this->contact_replacements($id, $prefix, false, $content);
		}

		try
		{
			$classname = "{$app}_merge";
			$class = new $classname();
			$method = $app.'_replacements';
			if(method_exists($class,$method))
			{
				$replacements = $class->$method($id, $prefix, $content);
			}
			else
			{
				$replacements = $class->get_replacements($id, $content);
			}
		}
		catch (\Exception $e)
		{
			// Don't break merge, just log it
			error_log($e->getMessage());
		}
		return $replacements;
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
		if (strpos($content,'$$IF') !== false)
		{	//Example use to use: $$IF n_prefix~Herr~Sehr geehrter~Sehr geehrte$$
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$IF ([#0-9a-z_\/-]+)~(.*)~(.*)~(.*)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$NELF') !== false)
		{	//Example: $$NEPBR org_unit$$ sets a LF and value of org_unit, only if there is a value
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$NELF ([#0-9a-z_\/-]+)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$NENVLF') !== false)
		{	//Example: $$NEPBRNV org_unit$$ sets only a LF if there is a value for org_units, but did not add any value
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$NENVLF ([#0-9a-z_\/-]+)\$\$/imU',Array($this,'replace_callback'),$content);
			unset($this->replacements);
		}
		if (strpos($content,'$$LETTERPREFIX$$') !== false)
		{	//Example use to use: $$LETTERPREFIX$$
			$LETTERPREFIXCUSTOM = '$$LETTERPREFIXCUSTOM n_prefix title n_family$$';
			$content = str_replace('$$LETTERPREFIX$$',$LETTERPREFIXCUSTOM,$content);
		}
		if (strpos($content,'$$LETTERPREFIXCUSTOM') !== false)
		{	//Example use to use for a custom Letter Prefix: $$LETTERPREFIX n_prefix title n_family$$
			$this->replacements =& $replacements;
			$content = preg_replace_callback('/\$\$LETTERPREFIXCUSTOM ([#0-9a-z_-]+)(.*)\$\$/imU',Array($this,'replace_callback'),$content);
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

		$pattern = '/'.preg_quote($param[2], '/').'/';
		if (strpos($param[0],'$$IF') === 0 && (trim($param[2]) == "EMPTY" || $param[2] === ''))
		{
			$pattern = '/^$/';
		}
		$replace = preg_match($pattern,$this->replacements['$$'.$param[1].'$$']) ? $param[3] : $param[4];
		switch($this->mimetype)
		{
			case 'application/vnd.oasis.opendocument.text':		// open office
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':	// ms office 2007
			case 'application/vnd.ms-word.document.macroenabled.12':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
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
				case 'application/vnd.oasis.opendocument.presentation':
				case 'application/vnd.oasis.opendocument.text-template':
				case 'application/vnd.oasis.opendocument.presentation-template':
					$LF ='<text:line-break/>';
					break;
				case 'application/vnd.oasis.opendocument.spreadsheet':		// open office calc
				case 'application/vnd.oasis.opendocument.spreadsheet-template':
					$LF = '</text:p><text:p>';
					break;
				case 'application/xmlExcel.Sheet':	// Excel 2003
					$LF = '&#10;';
					break;
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
				case 'application/vnd.ms-word.document.macroenabled.12':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
				case 'application/vnd.ms-excel.sheet.macroenabled.12':
					$LF ='</w:t></w:r></w:p><w:p><w:r><w:t>';
					break;
				case 'application/xml';
					$LF ='</w:t></w:r><w:r><w:br w:type="text-wrapping" w:clear="all"/></w:r><w:r><w:t>';
					break;
				case 'text/html':
					$LF = "<br/>";
					break;
				default:
					$LF = "\n";
			}
		if($is_xml) {
			$this->replacements = str_replace(array('&','&amp;amp;','<','>',"\r","\n"),array('&amp;','&amp;','&lt;','&gt;','',$LF),$this->replacements);
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
			$replaceprefixsort = array();
			// ToDo Stefan: $contentstart is NOT defined here!!!
			$replaceprefix = explode(' ',substr($param[0],21,-2));
			foreach ($replaceprefix as $nameprefix)
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
	 * @param string $name ='' name to use for downloaded document
	 * @param string $dirs comma or whitespace separated directories, used if $document is a relative path
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	public function download($document, $ids, $name='', $dirs='')
	{
		$result = $this->merge_file($document, $ids, $name, $dirs, $header);

		if(is_file($result) && is_readable($result))
		{
			Api\Header\Content::type($header['name'],$header['mime'],$header['filesize']);
			readfile($result,'r');
			exit;
		}

		return $result;
	}

	/**
	 * Merge the IDs into the document, puts the document into the output buffer
	 *
	 * @param string $document vfs-path of document
	 * @param array $ids array with contact id(s)
	 * @param string $name ='' name to use for downloaded document
	 * @param string $dirs comma or whitespace separated directories, used if $document is a relative path
	 * @param Array $header File name, mime & filesize if you want to send a header
	 *
	 * @return string with error-message on error
	 * @throws Api\Exception
	 */
	public function merge_file($document, $ids, &$name='', $dirs='', &$header)
	{
		//error_log(__METHOD__."('$document', ".array2string($ids).", '$name', dirs='$dirs') ->".function_backtrace());
		if (($error = $this->check_document($document, $dirs)))
		{
			return $error;
		}
		$content_url = Api\Vfs::PREFIX.$document;
		switch (($mimetype = Api\Vfs::mime_content_type($document)))
		{
			case 'message/rfc822':
				//error_log(__METHOD__."('$document', ".array2string($ids).", '$name', dirs='$dirs')=>$content_url ->".function_backtrace());
				$mail_bo = Api\Mail::getInstance();
				$mail_bo->openConnection();
				try
				{
					$msgs = $mail_bo->importMessageToMergeAndSend($this, $content_url, $ids, $_folder='');
				}
				catch (Api\Exception\WrongUserinput $e)
				{
					// if this returns with an exeption, something failed big time
					return $e->getMessage();
				}
				//error_log(__METHOD__.__LINE__.' Message after importMessageToMergeAndSend:'.array2string($msgs));
				$retString = '';
				if (count($msgs['success'])>0) $retString .= count($msgs['success']).' '.(count($msgs['success'])+count($msgs['failed'])==1?lang('Message prepared for sending.'):lang('Message(s) send ok.'));//implode('<br />',$msgs['success']);
				//if (strlen($retString)>0) $retString .= '<br />';
				foreach($msgs['failed'] as $c =>$e)
				{
					$errorString .= lang('contact').' '.lang('id').':'.$c.'->'.$e.'.';
				}
				if (count($msgs['failed'])>0) $retString .= count($msgs['failed']).' '.lang('Message(s) send failed!').'=>'.$errorString;
				return $retString;
			case 'application/vnd.oasis.opendocument.text':
			case 'application/vnd.oasis.opendocument.spreadsheet':
			case 'application/vnd.oasis.opendocument.presentation':
			case 'application/vnd.oasis.opendocument.text-template':
			case 'application/vnd.oasis.opendocument.spreadsheet-template':
			case 'application/vnd.oasis.opendocument.presentation-template':
				switch($mimetype)
				{
					case 'application/vnd.oasis.opendocument.text':	$ext = '.odt'; break;
					case 'application/vnd.oasis.opendocument.spreadsheet': $ext = '.ods'; break;
					case 'application/vnd.oasis.opendocument.presentation': $ext = '.odp'; break;
					case 'application/vnd.oasis.opendocument.text-template': $ext = '.ott'; break;
					case 'application/vnd.oasis.opendocument.spreadsheet-template': $ext = '.ots'; break;
					case 'application/vnd.oasis.opendocument.presentation-template': $ext = '.otp'; break;
				}
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,$ext).'-').$ext;
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'content.xml');
				$styles_url = 'zip://'.$archive.'#'.($styles_file = 'styles.xml');
				$this->parse_html_styles = true;
				break;
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.d':	// mimetypes in vfs are limited to 64 chars
				$mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/vnd.ms-word.document.macroenabled.12':
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
			case 'application/vnd.ms-excel.sheet.macroenabled.12':
				$fix = array(	// hack to get Excel 2007 to display additional rows in tables
					'/ss:ExpandedRowCount="\d+"/' => 'ss:ExpandedRowCount="9999"',
				);
				$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.xlsx').'-').'.xlsx';
				copy($content_url,$archive);
				$content_url = 'zip://'.$archive.'#'.($content_file = 'xl/sharedStrings.xml');
				break;
		}
		$err = null;
		if (!($merged =& $this->merge($content_url,$ids,$err,$mimetype,$fix)))
		{
			//error_log(__METHOD__."() !this->merge() err=$err");
			return $err;
		}
		if (!($merged_styles =& $this->merge($styles_url,$ids,$err,$mimetype,$fix)))
		{
			//error_log(__METHOD__."() !this->merge() err=$err");
			return $err;
		}
		// Apply HTML formatting to target document, if possible
		// check if we can use the XSL extension, to not give a fatal error and rendering whole merge-print non-functional
		if (class_exists(XSLTProcessor) && class_exists(DOMDocument) && $this->parse_html_styles)
		{
			try
			{
				$this->apply_styles($merged, $mimetype);
				$this->apply_styles($merged_styles, $mimetype);
			}
			catch (\Exception $e)
			{
				// Error converting HTML styles over
				error_log($e->getMessage());
				error_log("Target document: $content_url, IDs: ". array2string($ids));

				// Try again, but strip HTML so user gets something
				$this->parse_html_styles = false;
				if (!($merged =& $this->merge($content_url,$ids,$err,$mimetype,$fix)))
				{
					return $err;
				}
			}
			if ($this->report_memory_usage) error_log(__METHOD__."() after HTML processing ".Api\Vfs::hsize(memory_get_peak_usage(true)));
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
		$header = array('name' => $name, 'mime' => $mimetype);
		if (isset($archive))
		{
			$zip = new ZipArchive;
			if ($zip->open($archive, ZipArchive::CHECKCONS) !== true)
			{
				error_log(__METHOD__.__LINE__." !ZipArchive::open('$archive',ZIPARCHIVE"."::CHECKCONS) failed. Trying open without validating");
				if ($zip->open($archive) !== true) throw new Api\Exception("!ZipArchive::open('$archive',|ZIPARCHIVE::CHECKCONS)");
			}
			if ($zip->addFromString($content_file,$merged) !== true) throw new Api\Exception("!ZipArchive::addFromString('$content_file',\$merged)");
			if ($zip->addFromString($styles_file,$merged_styles) !== true) throw new Api\Exception("!ZipArchive::addFromString('$styles_file',\$merged)");
			if ($zip->close() !== true) throw new Api\Exception("!ZipArchive::close()");
			unset($zip);
			unset($merged);
			if ($this->report_memory_usage) error_log(__METHOD__."() after ZIP processing ".Api\Vfs::hsize(memory_get_peak_usage(true)));
			$header['filesize'] = filesize($archive);
		}
		else
		{
			$archive = tempnam($GLOBALS['egw_info']['server']['temp_dir'], basename($document,'.'.$ext).'-').'.'.$ext;
			if ($mimetype == 'application/xml')
			{
				if (strpos($merged,'<?mso-application progid="Word.Document"?>') !== false)
				{
					$header['mimetype'] = 'application/msword';	// to open it automatically in word or oowriter
				}
				elseif (strpos($merged,'<?mso-application progid="Excel.Sheet"?>') !== false)
				{
					$header['mimetype'] = 'application/vnd.ms-excel';	// to open it automatically in excel or oocalc
				}
			}
			$handle = fopen($archive, 'w');
			fwrite($handle, $merged);
			fclose($handle);
		}
		return $archive;
	}

	/**
	 * Download document merged with contact(s)
	 * frontend for HTTP POST requests
	 * accepts POST vars and calls internal function download()
	 *   string data_document_name: the document name
	 *   string data_document_dir: the document vfs directory
	 *   string data_checked: contact id(s) to merge with (can be comma separated)
	 *
	 * @return string with error-message on error, otherwise it does NOT return
	 */
	public function download_by_request()
	{
		if(empty($_POST['data_document_name'])) return false;
		if(empty($_POST['data_document_dir'])) return false;
		if(empty($_POST['data_checked'])) return false;

		return $this->download(
			$_POST['data_document_name'],
			explode(',',$_POST['data_checked']),
			'',
			$_POST['data_document_dir']
		);
	}

	/**
	 * Get a list of document actions / files from the given directory
	 *
	 * @param string $dirs Directory(s comma or space separated) to search
	 * @param string $prefix='document_' prefix for array keys
	 * @param array|string $mime_filter=null allowed mime type(s), default all, negative filter if $mime_filter[0] === '!'
	 * @return array List of documents, suitable for a selectbox.  The key is document_<filename>.
	 */
	public static function get_documents($dirs, $prefix='document_', $mime_filter=null, $app='')
	{
		$export_limit=self::getExportLimit($app);
		if (!$dirs || (!self::hasExportLimit($export_limit,'ISALLOWED') && !self::is_export_limit_excepted())) return array();

		// split multiple comma or whitespace separated directories
		// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
		if (count($dirs = preg_split('/[,\s]+\//', $dirs)) > 1)
		{
			foreach($dirs as $n => &$d)
			{
				if ($n) $d = '/'.$d;	// re-adding trailing slash removed by split
			}
		}
		if ($mime_filter && ($negativ_filter = $mime_filter[0] === '!'))
		{
			if (is_array($mime_filter))
			{
				unset($mime_filter[0]);
			}
			else
			{
				$mime_filter = substr($mime_filter, 1);
			}
		}
		$list = array();
		foreach($dirs as $dir)
		{
			if (($files = Api\Vfs::find($dir,array('need_mime'=>true),true)))
			{
				foreach($files as $file)
				{
					// return only the mime-types we support
					$parts = explode('.',$file['name']);
					if (!self::is_implemented($file['mime'],'.'.array_pop($parts))) continue;
					if ($mime_filter && $negativ_filter === in_array($file['mime'], (array)$mime_filter)) continue;
					$list[$prefix.$file['name']] = Api\Vfs::decodePath($file['name']);
				}
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
	 * If more than SHOW_DOCS_BY_MIME_LIMIT=10 documents found, they are displayed in submenus by mime type.
	 *
	 * @param string $dirs Directory(s comma or space separated) to search
	 * @param int $group see nextmatch_widget::egw_actions
	 * @param string $caption ='Insert in document'
	 * @param string $prefix ='document_'
	 * @param string $default_doc ='' full path to default document to show on top with action == 'document'!
	 * @param int|string $export_limit =null export-limit, default $GLOBALS['egw_info']['server']['export_limit']
	 * @return array see nextmatch_widget::egw_actions
	 */
	public static function document_action($dirs, $group=0, $caption='Insert in document', $prefix='document_', $default_doc='',
		$export_limit=null)
	{
		$documents = array();
		$editable_mimes = array();
		if ($export_limit == null) $export_limit = self::getExportLimit(); // check if there is a globalsetting

		try {
			if (class_exists('EGroupware\\collabora\\Bo') &&
					$GLOBALS['egw_info']['user']['apps']['collabora'] &&
					($discovery = \EGroupware\collabora\Bo::discover()) &&
					$GLOBALS['egw_info']['user']['preferences']['filemanager']['merge_open_handler'] != 'download'
			)
			{
				$editable_mimes = $discovery;
			}
		}
		catch (\Exception $e)
		{
			// ignore failed discovery
			unset($e);
		}
		if ($default_doc && ($file = Api\Vfs::stat($default_doc)))	// put default document on top
		{
			if(!$file['mime'])
			{
				$file['mime'] = Api\Vfs::mime_content_type($default_doc);
				$file['path'] = $default_doc;
			}
			$documents['document'] = array(
				'icon' => Api\Vfs::mime_icon($file['mime']),
				'caption' => Api\Vfs::decodePath(Api\Vfs::basename($default_doc)),
				'group' => 1,
				'postSubmit' => true,	// download needs post submit (not Ajax) to work
			);
			if ($file['mime'] == 'message/rfc822')
			{
				self::document_mail_action($documents['document'], $file);
			}
			else if ($editable_mimes[$file['mime']])
			{
				self::document_editable_action($documents['document'], $file);
			}
		}

		$files = array();
		if ($dirs)
		{
			// split multiple comma or whitespace separated directories
			// to still allow space or comma in dirnames, we also use the trailing slash of all pathes to split
			if (count($dirs = preg_split('/[,\s]+\//', $dirs)) > 1)
			{
				foreach($dirs as $n => &$d)
				{
					if ($n) $d = '/'.$d;	// re-adding trailing slash removed by split
				}
			}
			foreach($dirs as $dir)
			{
				$files += Api\Vfs::find($dir,array(
					'need_mime' => true,
					'order' => 'fs_name',
					'sort' => 'ASC',
				),true);
			}
		}

		$dircount = array();
		foreach($files as $key => $file)
		{
			// use only the mime-types we support
			$parts = explode('.',$file['name']);
			if (!self::is_implemented($file['mime'],'.'.array_pop($parts)) ||
				!Api\Vfs::check_access($file['path'], Api\Vfs::READABLE, $file) ||	// remove files not readable by user
				$file['path'] === $default_doc)	// default doc already added
			{
				unset($files[$key]);
			}
			else
			{
				$dirname = Api\Vfs::dirname($file['path']);
				if(!isset($dircount[$dirname]))
				{
					$dircount[$dirname] = 1;
				}
				else
				{
					$dircount[$dirname] ++;
				}
			}
		}
		foreach($files as $file)
		{
			if (count($dircount) > 1)
			{
				$name_arr = explode('/', $file['name']);
				$current_level = &$documents;
				for($count = 0; $count < count($name_arr); $count++)
				{
					if($count == 0)
					{
						$current_level = &$documents;
					}
					else
					{
						$current_level = &$current_level[$prefix.$name_arr[($count-1)]]['children'];
					}
					switch($count)
					{
						case (count($name_arr)-1):
							$current_level[$prefix.$file['name']] = array(
								'icon'		=> Api\Vfs::mime_icon($file['mime']),
								'caption'	=> Api\Vfs::decodePath($name_arr[$count]),
								'group'		=> 2,
								'postSubmit' => true,	// download needs post submit (not Ajax) to work
							);
							if ($file['mime'] == 'message/rfc822')
							{
								self::document_mail_action($current_level[$prefix.$file['name']], $file);
							}
							else if ($editable_mimes[$file['mime']])
							{
								self::document_editable_action($current_level[$prefix.$file['name']], $file);
							}
							break;

						default:
							if(!is_array($current_level[$prefix.$name_arr[$count]]))
							{
								// create parent folder
								$current_level[$prefix.$name_arr[$count]] = array(
									'icon'		=> 'phpgwapi/foldertree_folder',
									'caption'	=> Api\Vfs::decodePath($name_arr[$count]),
									'group'		=> 2,
									'children'	=> array(),
								);
							}
							break;
					}
				}
			}
			else if (count($files) >= self::SHOW_DOCS_BY_MIME_LIMIT)
			{
				if (!isset($documents[$file['mime']]))
				{
					$documents[$file['mime']] = array(
						'icon' => Api\Vfs::mime_icon($file['mime']),
						'caption' => Api\MimeMagic::mime2label($file['mime']),
						'group' => 2,
						'children' => array(),
					);
				}
				$documents[$file['mime']]['children'][$prefix.$file['name']] = array(
					'caption' => Api\Vfs::decodePath($file['name']),
					'postSubmit' => true,	// download needs post submit (not Ajax) to work
				);
				if ($file['mime'] == 'message/rfc822')
				{
					self::document_mail_action($documents[$file['mime']]['children'][$prefix.$file['name']], $file);
				}
				else if ($editable_mimes[$file['mime']])
				{
					self::document_editable_action($documents[$file['mime']]['children'][$prefix.$file['name']], $file);
				}
			}
			else
			{
				$documents[$prefix.$file['name']] = array(
					'icon' => Api\Vfs::mime_icon($file['mime']),
					'caption' => Api\Vfs::decodePath($file['name']),
					'group' => 2,
					'postSubmit' => true,	// download needs post submit (not Ajax) to work
				);
				if ($file['mime'] == 'message/rfc822')
				{
					self::document_mail_action($documents[$prefix.$file['name']], $file);
				}
				else if ($editable_mimes[$file['mime']])
				{
					self::document_editable_action($documents[$prefix.$file['name']], $file);
				}
			}
		}

		return array(
			'icon' => 'etemplate/merge',
			'caption' => $caption,
			'children' => $documents,
			// disable action if no document or export completly forbidden for non-admins
			'enabled' => (boolean)$documents && (self::hasExportLimit($export_limit,'ISALLOWED') || self::is_export_limit_excepted()),
			'hideOnDisabled' => true,	// do not show 'Insert in document', if no documents defined or no export allowed
			'group' => $group,
		);
	}

	/**
	 * Set up a document action for an eml (email) document
	 *
	 * Email (.eml) documents get special action handling.  They don't send a file
	 * back to the client like the other documents.  Merging for a single selected
	 * contact opens a compose window, multiple contacts just sends.
	 *
	 * @param Array &$action Action to be modified for mail
	 * @param Array $file Array of information about the document from Api\Vfs::find
	 * @return void
	 */
	private static function document_mail_action(Array &$action, $file)
	{
		unset($action['postSubmit']);

		// Lots takes a while, confirm
		$action['confirm_multiple'] = lang('Do you want to send the message to all selected entries, WITHOUT further editing?');

		// These parameters trigger compose + merge - only if 1 row
		$extra = array(
			'from=merge',
			'document='.$file['path'],
			'merge='.get_called_class()
		);

		// egw.open() used if only 1 row selected
		$action['egw_open'] = 'edit-mail--'.implode('&',$extra);
		$action['target'] = 'compose_' .$file['path'];

		// long_task runs menuaction once for each selected row
		$action['nm_action'] = 'long_task';
		$action['popup'] = Api\Link::get_registry('mail', 'edit_popup');
		$action['message'] = lang('insert in %1',Api\Vfs::decodePath($file['name']));
		$action['menuaction'] = 'mail.mail_compose.ajax_merge&document='.$file['path'].'&merge='. get_called_class();
	}

	/**
	 * Set up a document action so the generated file is saved and opened in
	 * the collabora editor (if collabora is available)
	 *
	 * @param Array &$action Action to be modified for editor
	 * @param Array $file Array of information about the document from Api\Vfs::find
	 * @return void
	 */
	private static function document_editable_action(Array &$action, $file)
	{
		unset($action['postSubmit']);
		$action['nm_action'] = 'location';
		$action['url'] = urldecode(http_build_query(array(
				'menuaction' => 'collabora.EGroupware\\collabora\\Ui.merge_edit',
				'document'   => $file['path'],
				'merge'      => get_called_class(),
				'id'         => '$id',
				'select_all' => '$select_all'
		)));
		$action['target'] = '_blank';
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
					if (Api\Vfs::stat($dir.'/'.$document) && Api\Vfs::is_readable($dir.'/'.$document))
					{
						$document = $dir.'/'.$document;
						return false;
					}
				}
			}
		}
		elseif (Api\Vfs::stat($document) && Api\Vfs::is_readable($document))
		{
			return false;
		}
		//error_log(__METHOD__."('$document', dirs='$dirs') returning 'Document '$document' does not exist or is not readable for you!'");
		return lang("Document '%1' does not exist or is not readable for you!",$document);
	}

	/**
	 * Get a list of supported extentions
	 */
	public static function get_file_extensions()
	{
		return array('txt', 'rtf', 'odt', 'ods', 'docx', 'xml', 'eml');
	}

	/**
	 * Format a number according to user prefs with decimal and thousands separator
	 *
	 * Reimplemented from etemplate to NOT use user prefs for Excel 2003, which gives an xml error
	 *
	 * @param int|float|string $number
	 * @param int $num_decimal_places =2
	 * @param string $_mimetype =''
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
		return Api\Etemplate::number_format($number,$num_decimal_places);
	}
}
