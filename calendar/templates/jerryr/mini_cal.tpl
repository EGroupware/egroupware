<!-- $Id$ -->
<!-- BEGIN mini_cal -->
<table width=100%><tr>
						<td>
							<table border=0 width="100%" cellpadding=0 cellspacing=0>
								<tr>
									<td width=8 class="calLtLtTitleBlue"></td>
									<td width=8 class="calLtMidTitleBlue"></td>
									<td  nowrap class="calDayTitleBlue" >{month}</td>
									<td width=8 class="calRtMidTitleBlue" ></td>
									<td width=8 class="calRtRtTitleBlue" ></td>
								</tr>
								<tr>
									<td width=8 class="calLeftShadow"></td>
									<td valign="top" colspan=3 ><table border="0" width="100%" cellspacing="7" cellpadding="0" valign="top" cols="7">
    <tr>{daynames}    </tr>{display_monthweek}   </table></td>
									<td width=8 class="calRightShadow"></td>
								</tr>
								
								<tr class="calRowBottomShadow">
									<td class="calLtLtFoot"></td>
									<td class="calLtMidFoot"></td>
									<td></td>
									<td class="calRtMidFoot"></td>
									<td class="calRtRtFoot"></td>
								</tr>	
							</table>
						</td>
</tr></table>
<!-- END mini_cal -->
<!-- BEGIN mini_week -->
    <tr>{monthweek_day}
    </tr>
<!-- END mini_week -->
<!-- BEGIN mini_day -->
     <td align="center"{day_image}><font size="-2">{dayname}</font></td>
<!-- END mini_day -->
