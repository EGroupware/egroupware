<!-- $Id$ -->
<!-- BEGIN mini_cal -->
<table width=100%><tr>
						<td>
							<table align="center" border=0 width="200" cellpadding=0 cellspacing=0 bgcolor="#faf8f3">
								<tr>
									<td width=8 class="calLtLtTitleBeige"></td>
									<td width=8 class="calLtMidTitleBeige"></td>
									<td  nowrap class="calDayTitleBeige" >{month}</td>
									<td width=8 class="calRtMidTitleBeige" ></td>
									<td width=8 class="calRtRtTitleBeige" ></td>
								</tr>
								<tr>
									<td width=8 class="calLeftShadow"></td>
									<td valign="top" colspan=3 ><table border="0" width="100%" cellspacing="7" cellpadding="0" valign="top" cols="7">
    <tr>{daynames}    </tr>{display_monthweek}   </table></td>
									<td width=8 class="calRightShadow"></td>
								</tr>
								
								<tr class="calRowBottomShadow">
									<td width=8 class="calLtLtFoot"></td>
									<td width=8 class="calLtMidFoot"></td>
									<td>&nbsp;</td>
									<td width=8 class="calRtMidFoot"></td>
									<td width=8 class="calRtRtFoot"></td>
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
