<!-- BEGIN top_part -->
<table width="100%" height="73" cellspacing="0" cellpadding="0">
	<tr class="top_top">
		<!-- top row back images are 58px high, but the row may be smaller than that -->
		<!-- row 2 images are 15 px high, so this table with these 2 rows is 58 plus 15 equals 73px high  -->
		<td width="154" valign="top">
			<img src="{logo_img}" border="0">
		</td>
		<td valign="bottom">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td width="33%" class="info">{user_info_name}</td>
					<td width="33%" class="info">{current_users}</td>
					<td width="33%" class="info" align="right">{user_info_date}</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr align="right" class="top_bottom">
		<td colspan="2">
			<!-- row 2 right nav buttons -->
			<table cellpadding="0" cellspacing="0">
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
<table cellspacing="0" cellpadding="0" class="left">
	<tr>
		<td valign="top">
			<!-- left nav table -->
			<table cellpadding="0" cellspacing="0">
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
<table cellspacing="0" cellpadding="0" class="bottom">
	<tr>
		<td align="center" class="info">{powered}</td>
	</tr>
</table>
<!-- END bottom_part -->
