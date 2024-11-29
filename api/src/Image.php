<?php
/**
 * EGroupware API: Finding template specific images
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage image
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * Finding template specific images
 *
 * Images availability is cached on instance level, cache can be invalidated by
 * calling Admin >> Delete cache and register hooks.
 */
class Image
{
	/**
	 * Global lookup table mapping logic image-names to selected bootstrap icons or existing icons:
	 * - edit --> pencil-fill
	 * - save --> api/save as bootstrap's save does not match, but shadows api/save, if not remapped here
	 *
	 * @var string[]
	 */
	static $global2bootstrap = [
		'5_day_view'	=> '5-square',
		'7_day_view'	=> '7-square',
		//'about'	=> 'about',
		'accept_call'	=> 'telephone',
		'add'	=> 'plus-circle',
		'agt_action_fail'	=> 'slash-circle',
		'agt_action_success'	=> 'check-lg',
		'agt_reload'	=> 'arrow-clockwise',
		//'alarm'	=> 'alarm',
		'apply'	=> 'floppy',
		'apps'	=> 'grid-3x3-gap',
		//'archive'	=> 'archive',
		'arrow_down'	=> 'caret-down-fill',
		'arrow_left'	=> 'caret-left-fill',
		'arrow_right'	=> 'caret-right-fill',
		'arrow_up'	=> 'caret-up-fill',
		'attach'	=> 'paperclip',
		'back'	=> 'arrow-bar-left',
		'bullet'	=> 'record',
		'cake'	=> 'cake2',
		'calendar'	=> 'calendar3',
		'call'	=> 'telephone',
		'cancel'	=> 'x-square',
		'cancelled'	=> 'x-lg',
		'check'	=> 'check-lg',
		'close'	=> 'x-lg',
		'configure'	=> 'gear',
		'continue'	=> 'arrow-bar-right',
		//'copy'	=> 'copy',
		'cti_phone'	=> 'telephone',
		'cursor_editable'	=> 'copy',
		'datepopup'	=> 'calendar3',
		'delete'	=> 'trash3',
		'deleted'	=> 'trash3',
		'dialog_error'	=> 'exclamation-circle',
		'dialog_help'	=> 'question-circle',
		'dialog_info'	=> 'info-circle',
		'dialog_warning'	=> 'exclamation-triangle',
		'discard'	=> 'arrow-counterclockwise',
		'done'	=> 'check-lg',
		'dots'	=> 'three-dots-vertical',
		'down'	=> 'arrow-bar-down',
		//'download'	=> 'download',
		'drop'	=> 'paperclip',
		'edit'	=> 'pencil-square',
		'edit_leaf'	=> 'pencil-square',
		'editpaste'	=> 'clipboard2-data',
		'email'     => 'envelope',
		'export'	=> 'box-arrow-up',
		'fav_filter'	=> 'star',
		'favorites'	=> 'star-fill',
		'filesave'	=> 'folder-symlink',
		'filter'	=> 'funnel',
		'folder'	=> 'folder2',
		'folder_management'	=> 'folder-check',
		'generate_password'	=> 'key',
		'goup'	=> 'arrow-bar-up',
		'group'	=> 'people',
		'hangup'	=> 'telephone-minus',
		'help'	=> 'question-circle',
		'home'	=> 'house-door',
		'ical'	=> 'box-arrow-down',
		'import'	=> 'box-arrow-in-down',
		'infolog_task'	=> 'file-earmark-font',
		'internet'	=> 'globe2',
		'landscape'	=> 'tablet-landscape',
		'language'	=> 'translate',
		'leaf'	=> 'file-earmark',
		'link'	=> 'link-45deg',
		//'list'	=> 'list',
		'list_alt'	=> 'list',
		//'lock'	=> 'lock',
		'logout'	=> 'power',
		//used in mobile: 'menu_active'	=> 'arrow-bar-left',
		'mail'      => 'envelope',
		'menu_list'	=> 'list-task',
		'milestone'	=> 'check2-circle',
		'mime128_directory'	=> 'folder2',
		'mime128_unknown' => 'file-earmark',
		'mime128_application_octet-stream' => 'file-earmark-binary',
		'mime128_message_rfc822' => 'envelope-at',
		'mime128_text_plain' => 'file-earmark-text',
		'mime128_text_html' => 'filetype-html',
		'mime128_text_css' => 'filetype-css',
		'mime128_text_csv' => 'filetype-csv',
		'mime128_text_x-python' => 'filetype-py',
		'mime128_text_x-markdown' => 'filetype-md',
		'mime128_text_x-vcard' => 'bi-filetype-vcs',
		'mime128_text_calendar' => 'bi-filetype-ics',
		'mime128_application_pdf'   => 'filetype-pdf',
		'mime128_application_javascript'    => 'filetype-js',
		'mime128_application_rtf'   => 'file-earmark-richtext',
		'mime128_application_xml'   => 'filetype-xml',
		'mime128_application_x-egroupware-etemplate' => 'file-earmark-code',
		'mime128_application_msword' => 'filetype-doc',
		'mime128_application_zip' => 'file-earmark-zip',
		'mime128_application_x-gtar' => 'file-earmark-zip',
		'mime128_application_x-gzip' => 'file-earmark-zip',
		'mime128_application_x-tar' => 'file-earmark-zip',
		'mime128_application_x-bzip2' => 'file-earmark-zip',
		'mime128_application_x-7z-compressed' => 'file-earmark-zip',
		'mime128_application_x-rar-compressed' => 'file-earmark-zip',
		'mime128_application_x-httpd-php' => 'filetype-php',
		'mime128_application_json' => 'filetype-json',
		'mime128_application_yaml' => 'filetype-yml',
		'mime128_application_postscript' => 'filetype-ai',
		'mime128_application_vnd.ms-excel' => 'filetype-xls',
		'mime128_application_vnd.ms-powerpoint' => 'filetype-ppt',
		'mime128_application_vnd.oasis.opendocument.presentation' => 'bi-filetype-odp',
		'mime128_application_vnd.oasis.opendocument.spreadsheet' => 'bi-filetype-ods',
		'mime128_application_vnd.oasis.opendocument.text' => 'bi-filetype-odt',
		'mime128_application_vnd.oasis.opendocument.graphics' => 'bi-filetype-odg',
		//'mime128_application/vnd.oasis.opendocument.text-template' => 'bi-filetype-ott',
		//'mime128_application/vnd.oasis.opendocument.text-web' => 'bi-filetype-oth',
		//'mime128_application/vnd.oasis.opendocument.text-master' => 'bi-filetype-odm',
		//'mime128_application/vnd.oasis.opendocument.spreadsheet-template' => 'bi-filetype-ots',
		//'mime128_application/vnd.oasis.opendocument.chart' => 'bi-filetype-odc',
		//'mime128_application/vnd.oasis.opendocument.presentation-template' => 'bi-filetype-otp',
		//'mime128_application/vnd.oasis.opendocument.graphics-template' => 'bi-filetype-otg',
		//'mime128_application/vnd.oasis.opendocument.formula' => 'bi-filetype-odf',
		//'mime128_application/vnd.oasis.opendocument.database' => 'bi-filetype-odb',
		//'mime128_application/vnd.oasis.opendocument.image' => 'bi-filetype-odi',
		'mime128_application_vnd.openxmlformats-officedocument.presentationml.presentation' => 'filetype-pptx',
		//'mime128_application_vnd.openxmlformats-officedocument.presentationml.slideshow' => '',
		'mime128_application_vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'filetype-xlsx',
		'mime128_application_vnd.openxmlformats-officedocument.wordprocessingml.document' => 'filetype-docx',
		'mime128_application_x-sh' => 'filetype-sh',
		'mime128_application_x-sql' => 'filetype-sql',
		'mime128_video' => 'file-earmark-play',
		'mime128_video_mp4' => 'filetype-mp4',
		'mime128_video_mov' => 'filetype-mov',
		'mime128_video_webm' => 'bi-filetype-webm',
		'mime128_video_x-youtube' => 'youtube',
		'mime128_video_x-livefeedback' => 'smallpart/navbar',
		//'mime128_video_ogg' => '',
		'mime128_image' => 'file-earmark-image',
		'mime128_image_bmp' => 'filetype-bmp',
		'mime128_image_jpeg' => 'filetype-jpg',
		'mime128_image_png' => 'filetype-png',
		'mime128_image_gif' => 'filetype-gif',
		'mime128_image_svg' => 'filetype-svg',
		'mime128_image_tiff' => 'filetype-tiff',
		'mime128_image_vnd.adobe.photoshop' => 'filetype-psd',
		'mime128_image_vnd.adobe.illustrator' => 'filetype-ai',
		'mime128_audio' => 'file-earmark-music',
		'mime128_audio_mp4' => 'filetype-mp4',
		'mime128_audio_x-wav' => 'filetype-wav',
		'minus'	=> 'dash-lg',
		'month'	=> 'calendar-month',
		'mouse_scroll_lr'	=> 'arrows',
		'mouse_scroll_ud'	=> 'arrow-down-up',
		'move'	=> 'scissors',
		'navbar'	=> 'app-indicator',
		'new'	=> 'file-earmark-plus',
		'new_leaf'	=> 'file-earmark-plus',
		'next'	=> 'arrow-bar-right',
		'notification_message'	=> 'bell',
		'notification_message_active'	=> 'bell',
		'offer'	=> 'question-square',
		'password'	=> 'key',
		'password_old'	=> 'key-fill',
		'personal'	=> 'person',
		'phone'	=> 'telephone',
		'planner'	=> 'people',
		'planner_category'	=> 'tags',
		'plus'	=> 'plus-lg',
		'portrail'	=> 'tablet',
		'preferences'	=> 'gear',
		'previous'	=> 'arrow-bar-left',
		'print'	=> 'printer',
		'prio_high'	=> 'arrow-up-circle',
		'prio_low'	=> 'arrow-down-circle',
		'priority'	=> 'dash-circle',
		'private'	=> 'key',
		'reload'	=> 'arrow-clockwise',
		'revert'    => 'recycle',
		'save'      => 'api/bi-save',   // composition of floppy=apply and x-square=cancel
		'save_new'  => 'api/bi-save-new',
		'save_zip'	=> 'file-zip',
		//'search'	=> 'search',
		'security-update'	=> 'shield-exclamation',
		'setup'	=> 'gear',
		//'share'	=> 'share',
		'single'	=> 'person',
		'symlink'	=> 'reply',
		'tag_message'	=> 'tag',
		'tentative'	=> 'clock-history',
		'timestamp'	=> 'magic',
		//'unlock'	=> 'unlock',
		'up'	=> 'arrow-bar-up',
		'update'	=> 'shield-exclamation',
		'upload'	=> 'box-arrow-in-up',
		'url'	=> 'link-45deg',
		'users'	=> 'people',
		'view'	=> 'search',
		'visibility'	=> 'eye',
		'visibility_off'	=> 'eye-slash',
		'addressbook/accounts'	=> 'person-gear',
		'addressbook/advanced-search'	=> 'zoom-in',
		'addressbook/group'	=> 'people',
		'addressbook/private'	=> 'key',
		'calendar/1_day_view'	=> '1-square',
		'calendar/4_day_view'	=> '4-square',
		'calendar/accepted'	=> 'check-lg',
		'calendar/day'	=> '1-square',
		'calendar/list_view'	=> 'list',
		'calendar/month_view'	=> 'calendar/bi-31-square',
		'calendar/multiweek_view'	=> 'calendar/bi-card-list',
		'calendar/needs-action'	=> 'question-circle',
		'calendar/next'	=> 'arrow-bar-right',
		'calendar/nonblocking'	=> 'ban',
		'calendar/planner_category_view'	=> 'tags',
		'calendar/planner_view'	=> 'people',
		'calendar/previous'	=> 'arrow-bar-left',
		'calendar/private'	=> 'key',
		'calendar/recur'	=> 'arrow-clockwise',
		'calendar/rejected'	=> 'x-circle',
		'calendar/single'	=> 'person',
		'calendar/tentative'	=> 'clock-history',
		'calendar/today'	=> 'calendar-event',
		'calendar/videoconference'	=> 'camera-video',
		'calendar/week_view'	=> '7-square',
        'calendar/year_view'	=> 'calendar/bi-12-square',
		'collabora/curly_brackets_icon'	=> 'braces-asterisk',
		'dhtmlxtree/close'	=> 'caret-down',
		'dhtmlxtree/folderClosed'	=> 'folder2',
		'dhtmlxtree/folderOpen'	=> 'folder2-open',
		'dhtmlxtree/kfm_home'	=> 'download',
		'dhtmlxtree/MailFolderClosed'	=> 'folder2',
		'dhtmlxtree/MailFolderDrafts'	=> 'pencil-square',
		'dhtmlxtree/MailFolderHam'	=> 'envelope-check',
		'dhtmlxtree/MailFolderJunk'	=> 'exclamation-octagon',
		'dhtmlxtree/MailFolderOutbox'	=> 'upload',
		'dhtmlxtree/MailFolderPlain'	=> 'envelope',
		'dhtmlxtree/MailFolderSent'	=> 'send',
		'dhtmlxtree/MailFolderTemplates'	=> 'file-earmark-text',
		'dhtmlxtree/MailFolderTrash'	=> 'trash',
		'dhtmlxtree/open'	=> 'caret-up',
		'dhtmlxtree/thunderbird'	=> 'house-door',
		'filemanager/button_createdir'	=> 'folder-plus',
		'filemanager/editpaste'	=> 'clipboard-check',
		'filemanager/folder_closed'	=> 'folder2',
		'filemanager/folder_closed_big'	=> 'folder2',
		'filemanager/folder_open'	=> 'folder2-open',
		'filemanager/gohome'	=> 'house-door',
		'filemanager/list_row'	=> 'list',
		'filemanager/list_tile'	=> 'grid-3x3-gap',
		'filemanager/linkpaste' => 'filemanager/bi-linkpaste',
		'filemanager/mailpaste'	=> 'envelope-check',
		'filemanager/upload'	=> 'box-arrow-in-up',
		'images/blocks'	=> 'boxes',
		'images/books'	=> 'journals',
		'images/charts'	=> 'bar-chart-line',
		'images/clipboard'	=> 'clipboard-check',
		'images/communications'	=> 'pc-display',
		'images/configure'	=> 'tools',
		'images/connect'	=> 'plug',
		'images/finance'	=> 'bank',
		'images/gear'	=> 'gear',
		'images/hardware'	=> 'motherboard',
		'images/help'	=> 'question-circle',
		'images/idea'	=> 'lightbulb',
		'images/important'	=> 'exclamation-triangle',
		'images/info'	=> 'info-circle',
		'images/linux'	=> 'ubuntu',
		'images/mac'	=> 'apple',
		'images/open_book'	=> 'book',
		'images/open_folder'	=> 'folder2-open',
		'images/people'	=> 'people',
		'images/person'	=> 'person',
		'images/screen'	=> 'display',
		'images/security'	=> 'lock',
		'images/star'	=> 'star',
		'images/stats'	=> 'calculator',
		'images/table'	=> 'table',
		'images/winclose'	=> 'x-square',
		'images/windows'	=> 'windows',
		'images/world'	=> 'globe-europe-africa',
		'importexport/export'	=> 'box-arrow-up',
		'importexport/import'	=> 'box-arrow-in-down',
		'infolog/archive'	=> 'archive',
		'infolog/billed'	=> 'currency-euro',
		'infolog/call'	=> 'telephone',
		'infolog/cancelled'	=> 'x-square',
		'infolog/deleted'	=> 'trash3',
		'infolog/done'	=> 'check-lg',
		'infolog/done_all'	=> 'check-all',
		'infolog/email'	=> 'envelope',
		'infolog/nonactive'	=> 'slash-circle',
		'infolog/not-started'	=> 'pause-circle',
		'infolog/note'	=> 'pencil-square',
		'infolog/phone'	=> 'phone-vibrate',
		'infolog/status'	=> 'question-square',
		'infolog/task'	=> 'check-square',
		'infolog/template'	=> 'file-earmark-text',
		'infolog/will-call'	=> 'telephone-plus',
		'mail/attach'	=> 'paperclip',
		'mail/certified_message'	=> 'envelope-check',
		'mail/fileexport'	=> 'floppy',
		'mail/filter'	=> 'funnel',
		'mail/htmlmode'	=> 'code-slash',
		'mail/kmmsgdel'	=> 'x-lg',
		'mail/kmmsgnew'	=> 'envelope-plus',
		'mail/kmmsgread'	=> 'envelope-open',
		'mail/kmmsgunseen'	=> 'envelope',
		'mail/mail_forward'	=> 'mail/bi-forward',
		'mail/mail_forward_attach'	=> 'envelope-arrow-up',
		'mail/mail_label1'	=> 'lightning',
		'mail/mail_label2'	=> 'key',
		'mail/mail_label3'	=> 'person',
		'mail/mail_label4'	=> 'gear',
		'mail/mail_label5'	=> 'clock',
		'mail/mail_reply'	=> 'reply',
		'mail/mail_replyall'	=> 'reply-all',
		'mail/mail_send'	=> 'send',
		'mail/notification'	=> 'bell',
		'mail/prio_high'	=> 'exclamation-diamond',
		'mail/prio_low'	=> 'arrow-down-square',
		'mail/read_flagged_small'	=> 'flag',
		//'mail/smime_encrypt'	=> 'building-lock', // old icons are better/easier to understand, need to be adapted to new BI style
		//'mail/smime_sign'	=> 'database-lock',
		'mail/source'	=> 'code',
		'mail/spam_list_domain_add'	=> 'house-add',
		'mail/spam_list_domain_remove'	=> 'house-dash',
		'mail/spam_list_remove'	=> 'envelope-dash',
		'mail/spam_list_add'	=> 'envelope-plus',
		'mail/tag_message'	=> 'tag',
		'mail/textmode'	=> 'fonts',
		'mail/unread_flagged_small'	=> 'flag-fill',
		'mail/whitelist'	=> 'envelope-check',
		'mail/blacklist'	=> 'envelope-exclamation',
		'projectmanager/download'	=> 'download',
		'projectmanager/milestone'	=> 'check2-circle',
		'projectmanager/pricelist'	=> 'currency-euro',
		'status/videoconference'	=> 'camera-video',
		'status/videoconference_call'	=> 'camera-video',
		'status/videoconference_join'	=> 'node-plus',
		'timesheet/pause'	=> 'pause-fill',
		'timesheet/pause-orange'	=> 'pause-fill',
		'timesheet/play'	=> 'play-fill',
		'timesheet/play-blue'	=> 'play-fill',
		'timesheet/stop'	=> 'stop-fill',
		/* not used
		'topmenu_items/access'	=> 'lock',
		'topmenu_items/category'	=> 'tag',
		'topmenu_items/home'	=> 'house-door',
		'topmenu_items/logout'	=> 'power',
		'topmenu_items/mobile/back'	=> 'arrow-left',
		'topmenu_items/mobile/cancelled'	=> 'x-lg',
		'topmenu_items/mobile/check'	=> 'check-lg',
		'topmenu_items/mobile/checkbox'	=> 'square',
		'topmenu_items/mobile/menu'	=> 'list',
		'topmenu_items/mobile/menu_active'	=> 'arrow-left',
		'topmenu_items/mobile/notify_off'	=> 'bell-slash',
		'topmenu_items/mobile/notify_on'	=> 'bell',
		'topmenu_items/mobile/plus_white'	=> 'plus-lg',
		'topmenu_items/mobile/save'	=> 'floppy',
		'topmenu_items/mobile/search'	=> 'search',
		'topmenu_items/mobile/star'	=> 'star',
		'topmenu_items/password'	=> 'key',
		'topmenu_items/search'	=> 'search',
		'topmenu_items/setup'	=> 'gear',
		*/
	];
	/**
	 * Searches a appname, template and maybe language and type-specific image
	 *
	 * @param string $app
	 * @param string|array $image one or more image-name in order of precedence
	 * @param string $extension ='' extension to $image, makes sense only with an array
	 * @param boolean $add_cachebuster =false true: add a cachebuster to the returnd url
	 *
	 * @return string url of image or null if not found
	 */
	static function find($app,$image,$extension='',$add_cachebuster=false)
	{
		$image_map = self::map(null);

		// array of images in descending precedence
		if (is_array($image))
		{
			foreach($image as $img)
			{
				if (($url = self::find($app, $img, $extension, $add_cachebuster)))
				{
					return $url;
				}
			}
			//error_log(__METHOD__."('$app', ".array2string($image).", '$extension') NONE found!");
			return null;
		}
		if (!is_string($image))
		{
			return null;
		}

		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];

		// instance specific images have the highest precedence
		if (isset($image_map['vfs'][$image.$extension]))
		{
			$url = $webserver_url.$image_map['vfs'][$image.$extension];
		}
		// then our globals lookup table $global2bootstrap, but not for $app/navbar
		elseif(($image !== 'navbar' || $app === 'api' || !isset($image_map[$app][$image])) &&
			(isset($image_map['global'][$app.'/'.$image]) || isset($image_map['global'][$image])))
		{
			$image = $image_map['global'][$app.'/'.$image] ?? $image_map['global'][$image];
			// allow redirects like "calendar/yearview" --> "calendar/bi-12-square"
			if (strpos($image, '/'))
			{
				list($app, $image) = explode('/', $image, 2);
			}
		}
		// then app specific ones
		elseif(isset($image_map[$app][$image.$extension]))
		{
			$url = $webserver_url.$image_map[$app][$image.$extension];
		}
		if (isset($url))
		{
			// keep it
		}
		// then bootstrap icons
		elseif(isset($image_map['bootstrap'][$image]))
		{
			$url = $webserver_url.$image_map['bootstrap'][$image];
		}
		// then api
		elseif(isset($image_map['api'][$image.$extension]))
		{
			$url = $webserver_url.$image_map['api'][$image.$extension];
		}

		if (!empty($url))
		{
			if ($add_cachebuster)
			{
				$url .= '?'.filemtime(EGW_SERVER_ROOT.substr($url, strlen($webserver_url)));
			}
			return $url;
		}

		// if image not found, check if it has an extension and try without
		if (strpos($image, '.') !== false)
		{
			$name = null;
			self::get_extension($image, $name);
			return self::find($app, $name, $extension, $add_cachebuster);
		}
		error_log(__METHOD__."('$app', '$image') image NOT found!");
		return null;
	}

	/**
	 * Get extension (and optional basename without extension) of a given path
	 *
	 * @param string $path
	 * @param string &$name on return basename without extension
	 * @return string extension without dot, eg. 'php'
	 */
	protected static function get_extension($path, &$name=null)
	{
		$parts = explode('.', Vfs::basename($path));
		$ext = array_pop($parts);
		$name = implode('.', $parts);
		return $ext;
	}

	/**
	 * Scan filesystem for images of all apps
	 *
	 * For each application and image-name (without extension) one full path is returned.
	 * The path takes template-set and image-type-priority (now fixed to: png, jpg, gif, ico) into account.
	 *
	 * VFS image directory is treated like an application named 'vfs'.
	 *
	 * @param string $template_set =null 'default', 'idots', 'jerryr', default is template-set from user prefs
	 *
	 * @return array of application => image-name => full path
	 */
	public static function map($template_set=null)
	{
		if (is_null($template_set))
		{
			$template_set = $GLOBALS['egw_info']['server']['template_set'];
		}

		$cache_name = 'image_map_'.$template_set.'_svg'.(Header\UserAgent::mobile() ? '_mobile' : '');
		if (($map = Cache::getInstance(__CLASS__, $cache_name)))
		{
			return $map;
		}
		//$starttime = microtime(true);

		// priority: : SVG->PNG->JPG->GIF->ICO
		$img_types = array('svg','png','jpg','gif','ico');

		$map = ['global' => self::$global2bootstrap];
		foreach(scandir(EGW_SERVER_ROOT) as $app)
		{
			if ($app[0] === '.' || !is_dir(EGW_SERVER_ROOT.'/'.$app) ||
				!file_exists(EGW_SERVER_ROOT.'/'.$app.'/templates') && $app !== 'node_modules')
			{
				continue;
			}
			$app_map =& $map[$app];
			if (true) $app_map = array();
			$imagedirs = array();
			if (Header\UserAgent::mobile() && $app !== 'node_modules')
			{
				$imagedirs[] = '/'.$app.'/templates/mobile/images';
			}
			if ($app == 'api')
			{
				$imagedirs[] = $GLOBALS['egw']->framework->template_dir.'/images';
			}
			elseif ($app === 'node_modules')
			{
				unset($map[$app]);
				$app_map =& $map[$app='bootstrap'];
				$imagedirs[] = '/node_modules/bootstrap-icons/icons';
			}
			else
			{
				$imagedirs[] = '/'.$app.'/templates/'.$template_set.'/images';
			}
			if ($app !== 'bootstrap')
			{
				if ($template_set != 'idots') $imagedirs[] = '/'.$app.'/templates/idots/images';
				$imagedirs[] = '/'.$app.'/templates/default/images';
			}

			foreach($imagedirs as $imagedir)
			{
				if (!file_exists($dir = EGW_SERVER_ROOT.$imagedir) || !is_readable($dir)) continue;

				foreach(scandir($dir) as $img)
				{
					if ($img[0] == '.') continue;

					$subdir = null;
					foreach(is_dir($dir.'/'.$img) ? scandir($dir.'/'.($subdir=$img)) : (array) $img as $img)
					{
						$name = null;
						if (!in_array($ext = self::get_extension($img, $name), $img_types) || empty($name)) continue;

						if (isset($subdir)) $name = $subdir.'/'.$name;

						if (!isset($app_map[$name]) || array_search($ext, $img_types) < array_search(self::get_extension($app_map[$name]), $img_types))
						{
							$app_map[$name] = $imagedir.'/'.$name.'.'.$ext;
						}
					}
				}
			}
		}
		$app_map =& $map['vfs'];
		if (true) $app_map = array();
		if (!empty($dir = $GLOBALS['egw_info']['server']['vfs_image_dir']) && Vfs::file_exists($dir) && Vfs::is_readable($dir))
		{
			foreach(Vfs::find($dir) as $img)
			{
				if (!in_array($ext = self::get_extension($img, $name), $img_types) || empty($name)) continue;

				if (!isset($app_map[$name]) || array_search($ext, $img_types) < array_search(self::get_extension($app_map[$name]), $img_types))
				{
					$app_map[$name] = Vfs::download_url($img);
				}
			}
		}
		else if ($dir)
		{
			return $map;
		}
		//error_log(__METHOD__."('$template_set') took ".(microtime(true)-$starttime).' secs');
		Cache::setInstance(__CLASS__, $cache_name, $map, 86400);	// cache for one day
		//echo "<p>template_set=".array2string($template_set)."</p>\n"; _debug_array($map);
		return $map;
	}

	/**
	 * Delete image map cache for ALL template sets
	 */
	public static function invalidate()
	{
		$templates = array('idots', 'jerryr', 'jdots', 'pixelegg');
		if (($template_set = $GLOBALS['egw_info']['user']['preferences']['common']['template_set']) && !in_array($template_set, $templates))
		{
			$templates[] = $template_set;
		}
		//error_log(__METHOD__."() for templates ".array2string($templates));
		foreach($templates as $template_set)
		{
			Cache::unsetInstance(__CLASS__, 'image_map_'.$template_set);
			Cache::unsetInstance(__CLASS__, 'image_map_'.$template_set.'_svg');
		}
	}
}