<!-- BEGIN optionlist -->
		<option {selected} VALUE="{value}">{text}</option>
<!-- END optionlist -->

<!-- BEGIN submit_button -->
		<input tabindex="{button_tabindex}" type="submit" value="{button_value}" name="{button_name}">
<!-- END submit_button -->

<!-- BEGIN msgbox_start -->
<CENTER><TABLE border=1 bgcolor="{row_on}">
<!-- END msgbox_start -->

<!-- BEGIN msgbox_row_good -->
<TR><TD bgcolor="{msgbox_row_color}">[<font color="green">O</font>] {msgbox_text}</TD></TR>
<!-- END msgbox_row_good -->

<!-- BEGIN msgbox_row_bad -->
<TR><TD bgcolor="{msgbox_row_color}">[<font color="red">X</font>] {msgbox_text}</TD></TR>
<!-- END msgbox_row_bad -->

<!-- BEGIN msgbox_end -->
</TABLE></CENTER>
<!-- END msgbox_end -->

<!-- BEGIN border_top_nohead -->
				<table border="0" cellspacing="2" cellpadding="2" width="100%">
					<tr>
						<td bgcolor="{row_on}">
<!-- END border_top_nohead -->

<!-- BEGIN border_top -->
	<table border="0" cellspacing="0" cellpadding="0" width="{border_top_width}">
		<tr>
			<td width="5" height="5"><img src="/images/borders/topleft.png"></td>
			<td background="/images/borders/top.png"><img src="/images/borders/pixel.gif"></td>
			<td width="5" height="5"><img src="/images/borders/topright.png"></td>
		</tr>
		<tr>
			<td width="5" background="/images/borders/leftside.png"><img src="/images/borders/pixel.gif"></td>
			<td bgcolor="{grey1}">
				<table border="0" cellspacing="2" cellpadding="2" width="100%">
					<tr>
						<td bgcolor="{headerB}" class="phpgw_whitetext">{border_header_text}</td>
					</tr>
					<tr>
						<td bgcolor="{row_on}">
<!-- END border_top -->

<!-- BEGIN border_bottom -->
						</td>
					</tr>
				</table>
			</td>
			<td width="5" background="/images/borders/rightside.png"><img src="/images/borders/pixel.gif"></td>
		</tr>
		<tr>
			<td width="5" height="5"><img src="/images/borders/bottomleft.png"></td>
			<td background="/images/borders/bottom.png"><img src="/images/borders/pixel.gif"></td>
			<td width="5" height="5"><img src="/images/borders/bottomright.png"></td>
		</tr>
	</table>
<!-- END border_bottom -->
<!-- BEGIN randomdata -->
						{data}
<!-- END randomdata -->

