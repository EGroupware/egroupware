 marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" rightmargin="0" leftmargin="0" border="0" 
 onLoad="MM_preloadImages('{app_images}',
'{img_root_roll}/log_out_over2.gif',
'{img_root_roll}/preferences_over2.gif',
'{img_root_roll}/question_mark_over2.gif',
'{img_root_roll}/welcome_over2.gif');"
 background="{img_root}/content_spacer_middle.gif">
  <!-- the above is the continuation and finishing if the < body > element started in head.tpl
  the margin items could be merged into head, as head already supplies some
  the variables for onLoad are set in navbar -->

<table border="0" width="100%" height="73" cellspacing="0" cellpadding="0">
<tr>
	<!-- top row back images are 58px high, but the row may be smaller than that -->
	<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
	<td width="154" height="58" align="left" valign="top" background="{img_root}/em.gif">
		<img src="{img_root}/logo2.gif">
	</td>
	<td width="100%" align="right" background="{img_root}/em.gif">
		<table width="100%" height="28" cellpadding="0" cellspacing="0" border="0" valign="top">
		<tr>
			<!-- <td width="100%" align="right" valign="top"><font size="{powered_by_size}" color="{powered_by_color}">{powered_by}&nbsp;{current_users}</font></td> -->
			<td width="50%" align="left" valign="top">
				<font size="{powered_by_size}" color="{powered_by_color}">{current_users}</font>
			</td>
			<td width="50%" align="right" valign="top">
				<font size="{powered_by_size}" color="{powered_by_color}">{powered_by}&nbsp;</font>
			</td>
		</tr>
		</table>
		<table width="100%" height="29" cellpadding="0" cellspacing="0" border="0" valign="bottom">
		<tr>
			<td width="50%" align="left" valign="bottom">
				<font size="{user_info_size}" color="{user_info_color}">
				<strong>{user_info_name}</strong></font>
			</td>
			<td width="50%" align="right" valign="bottom">
				<font size="{user_info_size}" color="{user_info_color}">
				<strong>{user_info_date}&nbsp;</strong></font>
			</td>
		</tr>
		</table>
	</td>
</tr>
<tr>
	<td colspan="2" width="100%" height="15" align="right" valign="top" background="{img_root}/top_spacer_middle2.gif">
		<!-- row 2 right nav buttons -->
		<table cellpadding="0" cellspacing="0" border="0">
		<tr>
			<td><a href="{home_link}" onMouseOver="nine.src='{img_root}/rollover/welcome_over2.gif'" onMouseOut="nine.src='{img_root}/welcome2.gif'"><img src="{img_root}/welcome2.gif" border="0" name="nine"></a></td>
			<td><a href="{preferences_link}" onMouseOver="ten.src='{img_root}/rollover/preferences_over2.gif'" onMouseOut="ten.src='{img_root}/preferences2.gif'"><img src="{img_root}/preferences2.gif" border="0" name="ten"></a></td>
			<td><a href="{logout_link}" onMouseOver="eleven.src='{img_root}/rollover/log_out_over2.gif'" onMouseOut="eleven.src='{img_root}/log_out2.gif'"><img src="{img_root}/log_out2.gif" border="0" name="eleven"></a></td>
			<td><a href="{help_link}" onMouseOver="help.src='{img_root}/rollover/question_mark_over2.gif'" onMouseOut="help.src='{img_root}/question_mark2.gif'"><img src="{img_root}/question_mark2.gif" border="0" name="help"></a></td>
		</tr>
		</table>
	</td>
</tr>
</table>
<table border="0" width="100%" height="100%" cellspacing="0" cellpadding="0">
<tr>
	<td width="154" align="left" valign="top" background="{img_root}/nav_bar_left_spacer.gif">
		<!-- left nav table -->
		<table border="0" cellpadding="0" cellspacing="0">
		<!-- applications supplies their own tr's and td's -->
		{applications}
		<tr>
			<td><img src="{img_root}/nav_bar_left_top_bg.gif"></td>
		</tr>
		</table>
	</td>
	<td width="100%" align="left" valign="top">
		<!-- this TD background image moved to body element -->
		<br>
<!-- END navbar -->
