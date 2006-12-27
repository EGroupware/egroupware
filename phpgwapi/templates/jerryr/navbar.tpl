<!-- BEGIN navbar_header -->
<div id="divLogo"><a href="{logo_url}" target="_blank"><img src="{logo_file}" border="0" title="{logo_title}" alt="eGroupWare"/></a></div>

{app_extra_icons_div}

<div class="appIconSpacer"></div>

<div id="divMain">
	<div id="divUpperTabs">
		<ul>
{upper_tabs}
		</ul>
	</div>

<div class="divAppIconBlock">

	<div id="divAppIconBar">
		<table class="appIconTbl" width="100%" border="0" cellspacing="0" cellpadding="0" >
			<tr>
				<td width="85" class="iconBackFirstCell"></td>
				<td valign="top">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
{app_icons}
						</tr>
						<tr>
{app_titles}
						</tr>
					</table>
				</td>
{app_extra_icons_icon}
			</tr>
		</table>
	</div>

</div>

	<div id="divStatusBar"><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td width="33%">
			<table cellpadding=0 cellspacing=0>
				<tr>
					<td class="userinfolt"></td>
					<td id="user_info" class="userinfomid">{user_info}</td>
					<td class="userinfort"></td>
				</tr>
			</table>
		</td>
		<td align="center" id="admin_info">{current_users}</td>
		<td width="33%"  align="right" id="quick_add">{quick_add}</td>
	</tr></table></div>
<!-- END navbar_header -->


<!-- BEGIN upper_tab_block -->
			<li><a href="{url}">{title}</a></li>
<!-- END upper_tab_block -->


<!-- BEGIN app_icon_block -->
							<td width="{tdwidth}%" align="center" class="iconBack" onmouseover="this.className='iconBackHover'" onmouseout="this.className='iconBack'"><a href="{url}" {target}><img src="{icon}" alt="{title}" title="{title}" border="0" /></a></td>
<!-- END app_icon_block -->


<!-- BEGIN app_title_block -->
							<td align="center" valign="top" class="appTitles" style="text-align:center"><a href="{url}" {target}>{title}</a></td>
<!-- END app_title_block -->


<!-- BEGIN appbox -->	
	<div id="divSubContainer">
		<table width="100%" cellspacing="0" cellpadding="0">
		<tr>
		<!-- Sidebox Column -->
{sideboxcolstart}
<!-- END appbox -->

<!-- BEGIN sidebox_hide_header -->
	<script language="javascript">
		new ypSlideOutMenu("menu2", "right", 0, 28, 160, 200)
	</script>

	<div id="sideboxdragarea" style="position:absolute;left:0px;top:38px; z-index: 52;">
		<a href="#" {show_menu_event}="ypSlideOutMenu.showMenu('menu2')" onmouseover="//ypSlideOutMenu.showMenu('menu2')" title="{lang_show_menu}"><img src="{img_root}/dragarea_right.png" /></a>
	</div>

	<div id="menu2Container">
		<div id="menu2Content" style="position: relative; left: 0; text-align: left;">
			<table cellspacing="0" cellpadding="0">
				<tr><td>
					<div class="slideoutBack">
<!-- END sidebox_hide_header -->


<!-- BEGIN sidebox_hide_footer -->
					</div>
				</td><td style="padding-top:10px" valign="top">
					<a href="#" onClick="ypSlideOutMenu.hide('menu2')" ><img src="{img_root}/dragarea_left.png" align="right" /></a>
				</td></tr>
			</table>
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
<div class="menuOneBoxTwo">
	<div class="divSideboxHeader"><span>{lang_title}</span></div>
	<div>
		<table class="sideboxDrop" width="100%" cellspacing="0" cellpadding="0">
<!-- END extra_blocks_header -->


<!-- BEGIN extra_blocks_footer -->
		</table>	
	</div>
</div>
</div>
<div class="sideboxSpace"></div>
<!-- END extra_blocks_footer -->


<!-- BEGIN extra_block_row -->
		<tr class="divSideboxEntry">
			<td width="20" align="center" valign="middle" class="textSideboxStar"></td><td class="textSidebox" onmouseover="this.className='textSideboxHover'" onmouseout="this.className='textSidebox'"><a class="textSidebox" href="{item_link}"{target}>{lang_item}</a></td>
		</tr>
<!-- END extra_block_row -->


<!-- BEGIN extra_block_row_raw -->
		<tr class="divSideboxEntry">
			<td colspan="2">{lang_item}</td>
		</tr>
<!-- END extra_block_row_raw -->


<!-- BEGIN extra_block_row_no_link -->
		<tr class="divSideboxEntry">
			<td width="20" align="center" valign="middle" class="textSideboxStar"></td><td class="textSideboxSpacer">{lang_item}</td>
		</tr>
<!-- END extra_block_row_no_link -->


<!-- BEGIN extra_block_spacer -->
		<tr class="divSideboxEntry">
			<td width="20" align="center" valign="middle" class="textSideboxStar"></td><td height="8" class="textSideboxSpacer">&nbsp;</td>
		</tr>
<!-- END extra_block_spacer -->


<!-- BEGIN app_extra_icons_div -->
<script language="javascript">
	new ypSlideOutMenu("menu1", "down", 45, 5, 188, 300,'right');
</script>
<div id="menu1Container">
	<div id="menu1Content" class="menuOneBox">
		<div id="extraIcons">
			<table cellspacing="0" cellpadding="0" border="0" width="100%">
			{app_extra_icons}
			<tr><td colspan="2" class="extraIconsHeader" nowrap="nowrap" align="right">
                                <a href="#" {show_menu_event}="ypSlideOutMenu.hide('menu1')" title="{lang_close}"><img style="" border="0" src="{img_root}/up.button.png"/></a>
                        </td></tr>

			</table>
		</div>
	</div>
</div>
<!-- END app_extra_icons_div -->


<!-- BEGIN app_extra_block -->
				<tr>
					<td class="extraIconsRowIcon"><a href="{url}" {target}><img src="{icon}" alt="{title}" title="{title}" width="16" border="0" /></a></td>
					<td align="left" class="extraIconsRow"><a href="{url}" {target}>{title}</a></td>
				</tr>
<!-- END app_extra_block -->


<!-- BEGIN app_extra_icons_icon -->
	<td width="32" valign="top" align="center" style="padding-top:13px;">
		<a title="{lang_show_more_apps}" href="#" {show_menu_event}="ypSlideOutMenu.showMenu('menu1')"><img src="{img_root}/extra_icons.png" border="0" /></a>
	</td>
<!-- END app_extra_icons_icon -->
