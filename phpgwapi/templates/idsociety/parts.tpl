<!-- BEGIN top_part -->
<table border="0" width="100%" height="73" cellspacing="0" cellpadding="0">
	<tr>
		<!-- top row back images are 58px high, but the row may be smaller than that -->
		<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
		<td width="5%" height="58" align="left" valign="top" background="{em_img}" nowrap>
			<img src="{logo_img}">
		</td>
		<td width="100%" align="right" background="{em_img}">
			<table width="100%" height="28" cellpadding="0" cellspacing="0" border="0" valign="top">
				<tr>
					<!-- <td width="100%" align="right" valign="top"><font size="{powered_by_size}" color="{powered_by_color}">{powered_by}&nbsp;{current_users}</font></td> -->
					<td width="50%" align="left" valign="top">
						<font size="{powered_by_size}" color="{powered_by_color}">{current_users}&nbsp;</font>
					</td>
					<td width="50%" align="right" valign="top">
						<font size="{powered_by_size}" color="{powered_by_color}">&nbsp;{powered_by}</font>
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
		<td colspan="2" width="100%" height="15" align="right" valign="top" background="{top_spacer_middle_img}">
			<!-- row 2 right nav buttons -->
			<table cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td><a href="{home_link}" onMouseOver="nine.src='{welcome_img_hover}'" onMouseOut="nine.src='{welcome_img}'"><img src="{welcome_img}" border="0" name="nine"></a></td>
					<td><a href="{preferences_link}" onMouseOver="ten.src='{preferences_img_hover}'" onMouseOut="ten.src='{preferences_img}'"><img src="{preferences_img}" border="0" name="ten"></a></td>
					<td><a href="{logout_link}" onMouseOver="eleven.src='{logout_img_hover}'" onMouseOut="eleven.src='{logout_img}'"><img src="{logout_img}" border="0" name="eleven"></a></td>
					<td><a href="{help_link}" onMouseOver="help.src='{about_img_hover}'" onMouseOut="help.src='{about_img}'"><img src="{about_img}" border="0" name="help"></a></td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<!-- END top_part -->

<!-- BEGIN left_part -->
<table border="0" width="154" height="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td width="154" align="left" valign="top" background="{nav_bar_left_spacer_img}">
			<!-- left nav table -->
			<table border="0" cellpadding="0" cellspacing="0">
				<!-- applications supplies their own tr's and td's -->
				{applications}
				<tr>
					<td><img src="{nav_bar_left_top_bg_img}"></td>
				</tr>
			</table>
		</td>
	</tr>
</table>
<!-- END left_part -->


<!-- BEGIN bottom_part -->
<table border="0" width="100%" height="100%" cellspacing="0" cellpadding="0">
	<tr>
		<td align="center" background="{em_bottom_img}">
			<font color="{power_textcolor}">{powered}</font>
		</td>
	</tr>
</table>
<!-- END bottom_part -->
