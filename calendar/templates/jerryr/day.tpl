<!-- $Id$ -->
<!-- BEGIN day -->
{printer_friendly}
<table border="0" width="100%" class="calDayViewShadowBox">
	<tr>
		<td valign="top" width="70%">
   			<table border="0" cellpadding=0 cellspacing=0 width=100%>
					<tr>
						<td width=8 class="calLtLtTitleBlue"></td>
						<td width=8 class="calLtMidTitleBlue"></td>
						<td  nowrap class="calDayTitleBlue" >{date}&nbsp;&nbsp;{username}&nbsp;</td>
						<td width=8 class="calRtMidTitleBlue" ></td>
						<td width=8 class="calRtRtTitleBlue" ></td>
					</tr>
					<tr>
						<td width=8 class="calLeftShadow"></td>
						<td valign="top" colspan=3 >
							<table width=100% cellpadding=0 cellspacing=0>{day_events}</table>
						</td>
						<td width=8 class="calRightShadow"></td>
					</tr>
					<tr class="calRowBottomShadow">
						<td width=8 class="calLtLtFoot"></td>
						<td width=8 class="calLtMidFoot"></td>
						<td>&nbsp;</td>
						<td width=8 class="calRtMidFoot"></td>
						<td width=8 class="calRtRtFoot"></td>
					</tr>	
				</table><p>{print}</p>
			</td>
			<td width=5></td>
			<td align="center" valign="top" >
				<table cellpadding=0 cellspacing=0 width="100%">
					<tr>
						<td>{small_calendar}</td>
					</tr>
				</table>
				<table height="5" border=0><tr><td></td></tr></table>
				<table width=100% cellspacing=0 cellpadding=0>
					<tr>
						<td>
							<table border=0 width="100%" cellpadding=0 cellspacing=0>
								<tr>
									<td width=8 class="calLtLtTitleRed"></td>
									<td width=8 class="calLtMidTitleRed"></td>
									<td  nowrap class="calDayTitleRed" >{lang_todos}</td>
									<td width=8 class="calRtMidTitleRed" ></td>
									<td width=8 class="calRtRtTitleRed" ></td>
								</tr>
								<tr>
									<td width=8 class="calLeftShadow"></td>
									<td valign="top" colspan=3 >	{todos}</td>
									<td width=8 class="calRightShadow"></td>
								</tr>
								<tr class="calRowBottomShadow">
									<td width=8 class="calLtLtFoot"></td>
									<td width=8 class="calLtMidFoot"></td>
									<td></td>
									<td width=8 class="calRtMidFoot"></td>
									<td width=8 class="calRtRtFoot"></td>
								</tr>	
							</table>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
<!-- END day -->
<!-- BEGIN day_event -->
    <tr>
     <td valign="top">
{daily_events}
     </td>
    </tr>
<!-- END day_event -->

