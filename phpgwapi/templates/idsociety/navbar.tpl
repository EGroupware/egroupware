 marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" rightmargin="0" leftmargin="0" border="0" 
 onLoad="MM_preloadImages('{app_images}',
'{img_root_roll}/log_out_over.gif',
'{img_root_roll}/preferences_over.gif',
'{img_root_roll}/help-over.gif',
'{img_root_roll}/welcome_over.gif')"
 background="{img_root}/content_spacer_middle.gif">
  <!-- the above is the continuation and finishing if the < body > element started in head.tpl
  the margin items could be merged into head, as head already supplies some
  the variables for onLoad are set in navbar -->

<table width="100%" height="100%" cellspacing="0" cellpadding="0" border="0">
<tr>
	<td width="154" valign="top" background="{img_root}/nav_bar_left_spacer.gif">
		<!-- left nav table -->
		<table border="0" cellpadding="0" cellspacing="0" width="154">
 		<tr>
			<td valign="top" width="154" background="{img_root}/em.gif">
				<img src="{img_root}/logo.gif">
			</td>
		</tr>
		<tr>
			<td>
				<img src="{img_root}/right_nav_bar.gif" border="0" name="one">
			</td>
		</tr>
		<!-- applications supplies their own tr's and td's -->
		{applications}
		<tr>
			<td><img src="{img_root}/nav_bar_left_top_bg.gif"></td>
		</tr>
		</table>
	</td>
	<td width="100%" valign="top">
		<!-- top right table -->
		<!-- this TD background image moved to body element -->
		<table border="0" width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td background="{img_root}/nav_top_spacer.gif" align="left" height="30">
				<font size="{user_info_size}" color="{user_info_color}">
				<strong>{user_info_name}<br>{user_info_date}</strong></font>
			</td>
			<td background="{img_root}/nav_top_spacer.gif" align="right">
				<font size="{powered_by_size}" color="{powered_by_color}">{powered_by}&nbsp;<br>{current_users}&nbsp;</font>
			</td>
		</tr>
		<tr>
			<td colspan="2" background="{img_root}/top_spacer_middle.gif" align="right" width="100%">
				<!-- right top nav table -->
				<table cellpadding="0" cellspacing="0" border="0" height="15">
				<tr>
					<td><a href="{home_link}" onMouseOver="nine.src='{img_root}/rollover/welcome_over.gif'" onMouseOut="nine.src='{img_root}/welcome.gif'"><img src="{img_root}/welcome.gif" border="0" name="nine"></a></td>
					<td><a href="{preferences_link}" onMouseOver="ten.src='{img_root}/rollover/preferences_over.gif'" onMouseOut="ten.src='{img_root}/preferences.gif'"><img src="{img_root}/preferences.gif" border="0" name="ten"></a></td>
					<td><a href="{logout_link}" onMouseOver="eleven.src='{img_root}/rollover/log_out_over.gif'" onMouseOut="eleven.src='{img_root}/log_out.gif'"><img src="{img_root}/log_out.gif" border="0" name="eleven"></a></td>
					<td><a href="{help_link}" onMouseOver="help.src='{img_root}/rollover/help-over.gif'" onMouseOut="help.src='{img_root}/question_mark.gif'"><img src="{img_root}/question_mark.gif" border="0" name="help"></a></td>
				</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td colspan="2">
				<br>
<!-- END navbar -->
