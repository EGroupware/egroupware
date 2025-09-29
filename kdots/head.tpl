<!-- BEGIN head --><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
		"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="{lang_code}" lang="{lang_code}" xmlns="http://www.w3.org/1999/xhtml"{dir_code} {theme}
	  class="{kdots_theme}">
<head>
	<title>{website_title}</title>
	<meta http-equiv="content-type" content="text/html; charset={charset}"/>
	<meta name="keywords" content="EGroupware"/>
	<meta name="description" content="EGroupware"/>
	<meta name="keywords" content="EGroupware"/>
	<meta name="copyright" content="EGroupware GmbH https://www.egroupware.org (c) 2020"/>
	<meta name="language" content="{lang_code}"/>
	<meta name="author" content="EGroupware GmbH https://www.egroupware.org"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
    {meta_robots}
	<link rel="manifest" href="{webserver_url}/manifest.json"/>
	<link rel="icon" href="{img_icon}" type="image/x-ico"/>
	<link rel="shortcut icon" href="{img_shortcut}"/>
	<link rel="stylesheet" href="{webserver_url}/api/js/offline/themes/offline-theme-slide.css">
	<link rel="stylesheet" href="{webserver_url}/api/js/offline/themes/offline-language-{lang_code}.css">
	<script src="{webserver_url}/api/js/offline/offline.min.js"></script>
    {css_file}
	<style type="text/css">
		{app_css}
	</style>
	<style type="text/css">
		{firstload_animation_style}
	</style>
    {java_script}
</head>
<body {body_tags} lang="{lang_code}">
{include_wz_tooltip}
<!-- END head -->
<!-- BEGIN framework -->
<egw-framework id="egw_fw_basecontainer"
			   application-list="{application-list}"
			   class="{sl_theme}"
>
	<a slot="logo" href="{logo_url}" target="_blank"><img src="{logo_header}" title="{logo_title}" alt="Site logo"/></a>
	<div slot="header-right" id="egw_fw_topmenu_info_items">
        {topmenu_info_items}
	</div>
	<!-- Currently open app -->
    {open_app}
</egw-framework>


{hook_after_navbar}

<div id="egw_fw_firstload">
    {firstload_animation}
</div>
<!-- END framework -->