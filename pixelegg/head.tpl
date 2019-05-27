<!-- BEGIN head --><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="{lang_code}" xmlns="http://www.w3.org/1999/xhtml"{dir_code}>
	<head>
		<title>{website_title}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="EGroupware" />
		<meta name="description" content="EGroupware" />
		<meta name="keywords" content="EGroupware" />
		<meta name="copyright" content="EGroupware GmbH http://www.egroupware.org (c) 2018" />
		<meta name="language" content="{lang_code}" />
		<meta name="author" content="EGroupware GmbH http://www.egroupware.org" />
		{pngfix}
		{meta_robots}
		<link rel="icon" href="{img_icon}" type="image/x-ico" />
		<link rel="shortcut icon" href="{img_shortcut}" />
		<link rel="stylesheet" href="{webserver_url}/api/js/offline/themes/offline-theme-slide.css">
		<link rel="stylesheet" href="{webserver_url}/api/js/offline/themes/offline-language-{lang_code}.css">
		<script src="{webserver_url}/api/js/offline/offline.min.js"></script>
		{css_file}
		<style type="text/css">
			{app_css}
		</style>
		{java_script}
	</head>
	<body {body_tags}>
		{include_wz_tooltip}
		<div id="divAppboxHeader" class="onlyPrint">{app_header}</div>
<!-- END head -->
<!-- BEGIN framework -->
		{hook_after_navbar}
		<div id="egw_fw_basecontainer">
			<div id="egw_fw_header">
				<div id="egw_divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_header}" title="{logo_title}" alt="EGroupware"/></a></div>
				<div id="egw_fw_topmenu">
					<div id="egw_fw_topmenu_items">
						{topmenu_items}
						{powered_by}
					</div>
				</div>
				<div id="egw_fw_topmenu_info_items">
					{topmenu_info_items}
				</div>
			</div>
			<div id="egw_fw_sidebar">
				<div id="egw_fw_sidemenu"></div>
				<div id="egw_fw_splitter"></div>
			</div>
			<div id="egw_fw_main">
				<div id="egw_fw_tabs">
				</div>
			</div>
			<div id="egw_fw_sidebar_r"></div>
		</div>
<!-- END framework -->
