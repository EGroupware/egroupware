<!-- $Id$ -->

<!-- BEGIN mini_cal -->

	<table id="calendar_minical_table" class="calendar_minical_table" border=0 cellspacing="0" cellpadding="0">
		<tr>
			<td style="padding-left: 8px; font-weight: bold; text-align: left;">
				{month}
			</td>
			<td align="right">
				<!-- why empty? -->
				{prevmonth}&nbsp;&nbsp;{nextmonth}
			</td>
		</tr>
		<tr>
			<td  style="padding-left: 8px; text-align: left;">
<!--				<img src="{cal_img_root}" width="90%" height="1"> -->
					<hr class="calendar_minical_hrule">
			</td>
		</tr>
		<tr valign="top">
			<td>
				<table id="calendar_minical_daytable" class="calendar_minical_daytable" cellspacing="5" cellpadding="0">
					<tr>{daynames}</tr>
						{display_monthweek}
				</table>

			</td>
	 	</tr>
	</table>
<!-- END mini_cal -->

<!-- BEGIN mini_week -->
	<tr>
		{monthweek_day}
	</tr>
<!-- END mini_week -->

<!-- BEGIN mini_day -->
		<td class="calendar_minical_dayname" {day_image}>
			{dayname}
		</td>
<!-- END mini_day -->
