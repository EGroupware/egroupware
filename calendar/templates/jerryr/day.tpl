<!-- $Id$ -->
<!-- BEGIN day -->
{printer_friendly}
<table border="0" width="100%" class="calDayViewShadowBox">
	<tr>
		<td valign="top" width="70%" class="calDayViewSideBoxes">
   			<table border="0" width=100%>
    				<tr>
     					<td class="calDayView">{date}&nbsp;&nbsp;{username}&nbsp;</td>
				</tr>{day_events}
			</table>
  			<p align="center">{print}</p>
		</td>
		<td width=5></td>
		<td align="center" valign="top" >
			<table class="calDayViewSideBoxes" width="100%"><tr><td>
				<table width="100%" cellpadding=0 cellspacing=0>
					<tr>
						<td align="center" class="calDayView">{date}</td>
					</tr>
					<tr>
						<td align="center">{small_calendar}</td>
					</tr>
				
				</table>
			</td></tr></table>
			<table height="5"><tr><td></td></tr></table>
			
			<table class="calDayViewSideBoxes" width=100%><tr><td>
				<table width="100%" cellpadding=0 cellspacing=0>
					<tr>
						<td align="center" class="calDayView">{lang_todos}</td>
					</tr>
					<tr>
						<td>{todos}</td>
					</tr>
				</table>
			</td></tr></table>
			</td>
		</tr>
	</table>
<!-- END day -->
<!-- BEGIN day_event -->
    <tr>
     <td>
{daily_events}
     </td>
    </tr>
<!-- END day_event -->

