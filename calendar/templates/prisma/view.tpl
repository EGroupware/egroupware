<!-- $Id$ -->

<!-- BEGIN view_event -->
<table id="calendar_view_event" border="0" width="98%" align="center">
	{row}
	<tr>
		<td>
			<table id="calendar_viewevent_button_left" cellspacing="5">
				<tr>
					{button_left}
				</tr>
			</table>
		</td>
	   <td align="center">
			<table id="calendar_viewevent_button_center" cellspacing="5">
				<tr>
					{button_center}
				</tr>
			</table>
		</td>
		<td align="right">
			<table id="calendar_viewevent_button_right" cellspacing="5">
				<tr>
					{button_right}
				</tr>
			</table>
		</td>
	</tr>
</table>
<!-- END view_event -->

<!-- BEGIN list -->
	<tr bgcolor="{tr_color}">
		<td valign="top" width="30%" align="right">&nbsp;<b>{field}&nbsp;:&nbsp;</b></td>
		<td colspan="2" valign="top" width="70%" align="left">{data}</td>
	</tr>
<!-- END list -->

<!-- BEGIN hr -->
	<tr>
		<td colspan="3" bgcolor="{th_bg}" align="center">
			<b>{hr_text}</b>
		</td>
	</tr>
<!-- END hr -->
