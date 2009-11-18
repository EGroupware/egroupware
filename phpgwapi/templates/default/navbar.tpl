<!-- BEGIN navbar_header -->
<div style="position:relative"><div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" title="{logo_title}" alt="EGroupware"/></a></div></div>
<script language="javascript">
	var currentapp = '{currentapp}';
</script>

<!-- BEGIN app_extra_icons_div -->
<script language="javascript">
	new ypSlideOutMenu("menu1", "down", 10, 114, 160, 200,'right');
</script>

<div id="menu1Container">
	<div id="menu1Content" style="position: relative; left: 0; text-align: left;">
		<div id="extraIcons">
			<table cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr>
					<td colspan="2" nowrap="nowrap" align="right" style="background-color:#dddddd; padding:1px;">
						<a href="#" {show_menu_event}="ypSlideOutMenu.hide('menu1')" title="{lang_close}"><img style="" border="0" src="{img_root}/close.png"/></a>
					</td>
				</tr>
<!-- BEGIN app_extra_block -->
				<tr>
					<td class="extraIconsRow"><a href="{url}" {target}><img src="{icon}" alt="{title}" title="{title}" width="16" border="0" /></a></td>
					<td align="left" class="extraIconsRow"><a href="{url}" {target}>{title}</a></td>
				</tr>
<!-- END app_extra_block -->
			</table>
		</div>
	</div>
</div>
<!-- END app_extra_icons_div -->

<div id="divMain">
	<div id="divUpperTabs">
		<ul>
<!-- BEGIN upper_tab_block -->
			<li><a href="{url}">{title}</a></li>
<!-- END upper_tab_block -->
		</ul>
	</div>
	<div id="divAppIconBar">
		<table width="100%" border="0" cellspacing="0" cellpadding="0" style="padding-top: 7px">
			<tr>
				<td width="200"></td>
				<td valign="bottom">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
<!-- BEGIN app_icon_block -->
							<td width="{tdwidth}%" align="center" style="text-align:center"><a href="{url}" {target}><img src="{icon}" alt="{title}" title="{title}" border="0" /></a></td>
<!-- END app_icon_block -->
<!-- BEGIN app_extra_icons_icon -->
							<td width="26" valign="top" align="right" style="padding-right:3px; padding-top:20px;">
								<a title="{lang_show_more_apps}" href="#" {show_menu_event}="ypSlideOutMenu.showMenu('menu1')"><img src="{img_root}/extra_icons.png" border="0" /></a>
							</td>
<!-- END app_extra_icons_icon -->
						</tr>
						<tr>
<!-- BEGIN app_title_block -->
							<td align="center" valign="top" class="appTitles" style="text-align:center"><a href="{url}" {target}>{title}</a></td>
<!-- END app_title_block -->
						</tr>
					</table>
				</td>
			</tr>
		</table>
	</div>
	<div id="divStatusBar"><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td width="33%" align="left" id="user_info">{user_info}</td>
		<td align="center" id="admin_info">{current_users}</td>
		<td width="33%"  align="right" id="quick_add">{quick_add}</td>
	</tr></table></div>
<!-- END navbar_header -->


<!-- BEGIN appbox -->
<!--[if lte IE 6.5]></div><iframe frameborder="0" framespacing="0" src="{img_root}/dragarea_right.png" class="iframeforselectbox"></iframe></div><![endif]-->
	<div id="divSubContainer">
		<table width="100%" cellspacing="0" cellpadding="0">
		<tr>
		<!-- Sidebox Column -->
{sideboxcolstart}
<!-- END appbox -->


<!-- BEGIN sidebox_hide_header -->
	<script language="javascript">
		new ypSlideOutMenu("menu2", "right", 0, 105, 100, 200)
	</script>

	<div id="sideboxdragarea" style="z-index:100;position:absolute;left:0px;top:105px">
		<a href="#" {show_menu_event}="ypSlideOutMenu.showMenu('menu2')" title="{lang_show_menu}"><img src="{img_root}/dragarea_right.png" /></a>
	</div>

	<div id="menu2Container">
	<!--[if lte IE 6.5]><div class="selectbg" id="dd3"><div class="bdforselection"><![endif]-->
		<div id="menu2Content" style="position: relative; left: 0; text-align: left;">
			<table cellspacing="0" cellpadding="0">
				<tr><td>
					<div style="background-color:#ffffff;border: #9c9c9c 1px solid;padding:5px;width:203px;">
<!-- END sidebox_hide_header -->


<!-- BEGIN sidebox_hide_footer -->
					</div>
				</td><td style="padding-top:10px" valign="top">
					<a href="#" onclick="ypSlideOutMenu.hide('menu2')" ><img src="{img_root}/dragarea_left.png" align="right" /></a>
				</td>
			</tr></table>
		</div>
	</div>
<!-- END sidebox_hide_footer -->


<!-- BEGIN navbar_footer -->
{sideboxcolend}
		<!-- End Sidebox Column -->

		<!-- Applicationbox Column -->
		<td id="tdAppbox" valign="top" {remove_padding}>
			<div id="divAppboxHeader">{current_app_title}</div>
			<div id="divAppbox">
				<table width="100%" cellpadding="0" cellspacing="0">
				<tr><td>
<!-- END navbar_footer -->


<!-- BEGIN extra_blocks_header -->
<div class="divSidebox">
	<div class="divSideboxHeader"><span>{lang_title}</span></div>
	<div>
		<table width="100%" cellspacing="0" cellpadding="0">
<!-- END extra_blocks_header -->


<!-- BEGIN extra_block_row -->
		<tr class="divSideboxEntry">
			<td width="20" align="center" valign="middle" class="textSidebox">{icon_or_star}</td><td class="textSidebox"><a class="textSidebox" href="{item_link}"{target}>{lang_item}</a></td>
		</tr>
<!-- END extra_block_row -->


<!-- BEGIN extra_block_row_raw -->
		<tr class="divSideboxEntry">
			<td colspan="2" class="textSidebox">{lang_item}</td>
		</tr>
<!-- END extra_block_row_raw -->


<!-- BEGIN extra_block_row_no_link -->
		<tr class="divSideboxEntry">
			<td width="20" align="center" valign="middle" class="textSidebox">{icon_or_star}</td><td class="textSidebox">{lang_item}</td>
		</tr>
<!-- END extra_block_row_no_link -->


<!-- BEGIN extra_block_spacer -->
		<tr class="divSideboxEntry">
			<td colspan="2" height="8" class="textSidebox">&nbsp;</td>
		</tr>
<!-- END extra_block_spacer -->


<!-- BEGIN extra_blocks_footer -->
		</table>
	</div>
</div>

<div class="sideboxSpace"></div>
<!-- END extra_blocks_footer -->






