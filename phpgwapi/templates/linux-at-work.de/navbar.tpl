<div class="main_menu" style="width : 150px ; height : 100% ; left : 0px ; top : 0px ;">
	<table summary="" style="width : 100%; ">
		<tr >
			<td class="main_menu">
					<a class="main_menu" href="{home_link}">{lang_welcome}</a>
			</td>
		</tr>
<!-- BEGIN preferences -->
		<tr >
			<td class="main_menu">
					<a class="main_menu" href="{preferences_link}">{lang_preferences}</a>
			</td>
		</tr>
<!-- END preferences -->
		<tr >
			<td class="main_menu">
					<a class="main_menu" href="{logout_link}">{lang_logout}</a>
			</td>
		</tr>
		<tr >
			<td class="main_menu">
					<a class="main_menu" href="{help_link}">{lang_help}</a>
			</td>
		</tr>
		<tr >
			<td>
					&nbsp;
			</td>
		</tr>
		<!-- applications supplies their own tr's and td's -->
		{applications}
	</table>
</div>

<div class="main_menu" style="width : 150px ; height : 60px ; left : 0px ; bottom : 0px ;">
	<table summary="" style="width : 100%; ">
		<tr>
			<td class="main_menu_bottom">
				{current_users}<br>
				{user_info_name}<br>
				{user_info_date}<br>
				{phpgw_version}
			</td>
		</tr>
	</table>
</div>

<!-- BEGIN app_header -->
<div style="position: absolute; padding: 0px; left:150px; top: 2px; font-weight: bold; color: #ffffff; font-size: 14px;">&nbsp;{current_app_header}<hr noshade></div>
<!-- END app_header -->
<div style="position: absolute; padding: 0px; left:150px; top: {message_top};">&nbsp;{messages}</div>

<div class="main_body" style="position: absolute; padding: 0px; height: 90%; left: 150px ; top: {app_top};
right:0px; overflow : auto">
<!-- END navbar -->
