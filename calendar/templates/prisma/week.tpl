<!-- $Id$ -->

{printer_friendly}

<!-- head table with mini calendars -->

<table id="calendar_week_minical_table" class="calendar_week_minical_table">
	<tr>
		<td id="small_calendar_prev" align="left" valign="top" width="20%">
			{small_calendar_prev}
		</td>
		<td  id="prev_week_link"  align="center" valign="middle" width="20%">
			<b>{prev_week_link}</b>
		</td>
		<td  id="small_calendar_this"  align="center" valign="top" width="20%">
			{small_calendar_this}
		</td>
		<td  id="next_week_link"  align="center" valign="middle" width="20%">
			<b>{next_week_link}</b>
		</td>
		<td  id="small_calendar_right"  align="right" valign="top" width="20%">
			{small_calendar_next}
		</td>
	</tr>
</table>
<br />
<table id="calendar_week_identifier_table" class="calendar_week_identifier_table">
	<tr>
		<td align="center">
			<span class="calendar_week_identifier">{week_identifier}&nbsp;&nbsp;</span>
			<span class="calendar_user_identifier">::&nbsp;{username}&nbsp;::</span>
		</td>
	</tr>
</table>
<!-- from month_day.tpl -->
{week_display}

<div class="calendar_link_print">
<br />
{print}
</div>