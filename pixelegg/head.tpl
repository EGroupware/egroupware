<!-- BEGIN head --><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="{lang_code}" xmlns="http://www.w3.org/1999/xhtml"{dir_code}>
	<head>
		<title>{website_title}</title>
		<meta http-equiv="content-type" content="text/html; charset={charset}" />
		<meta name="keywords" content="EGroupware" />
		<meta name="description" content="EGroupware" />
		<meta name="keywords" content="EGroupware" />
		<meta name="copyright" content="Stylite AG 2013, see http://www.stylite.de/EPL" />
		<meta name="language" content="{lang_code}" />
		<meta name="author" content="Stylite AG www.stylite.de" />
		{pngfix}
		{meta_robots}
		<link rel="icon" href="{img_icon}" type="image/x-ico" />
		<link rel="shortcut icon" href="{img_shortcut}" />
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

						<div id="egw_fw_topmenu_items">{topmenu_items}</div>

					</div>
			</div>


			<div id="egw_fw_topmenu_addons">
					<div id="egw_fw_topmenu_info_items">{topmenu_info_items}</div>
					<div id="egw_fw_logout" title="{title_logout}" data-logout-url="{link_logout}"></div>
					<div id="egwpopup_fw_notifications" title="">0</div>
					<div id="egw_fw_print" title="{title_print}"></div>
			</div>
			<div id="egw_fw_sidebar">
				<div id="egw_fw_sidemenu"></div>
				<div id="egw_fw_splitter"></div>
			</div>
			<div id="egw_fw_main">

				<div id="egw_fw_tabs"><div id="egw_fw_topmenu_slide" class="slideup"><span id="slidetoggle"></span></div>

				</div>

			</div>
		</div>
		<div id="egw_fw_footer">{powered_by}</div>


<!-- END framework -->
