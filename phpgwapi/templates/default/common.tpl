<!-- BEGIN optionlist -->
		<option {selected} VALUE="{value}">{text}</option>
<!-- END optionlist -->

<!-- BEGIN submit_button -->
		<input tabindex="{button_tabindex}" type="submit" value="{button_value}" name="{button_name}">
<!-- END submit_button -->

<!-- BEGIN msgbox_start -->
<CENTER><TABLE border=1 bgcolor="{row_on}">
<!-- END msgbox_start -->

<!-- BEGIN msgbox_row -->
<TR><TD bgcolor="{msgbox_row_color}"><img src="{msgbox_img}" ALT="{msgbox_img_alt}"> {msgbox_text}</TD></TR>
<!-- END msgbox_row -->

<!-- BEGIN msgbox_end -->
</TABLE></CENTER>
<!-- END msgbox_end -->

<!-- BEGIN border_top_nohead -->
	<table border="0" cellspacing="2" cellpadding="2" width="{border_top_width}">
		<tr>
			<td bgcolor="{row_on}">
<!-- END border_top_nohead -->

<!-- BEGIN border_top -->
	<table border="0" cellspacing="0" cellpadding="0" width="{border_top_width}">
		<tr>
			<td bgcolor="{table_bg}">{border_header_text}</td>
		</tr>
		<tr>
			<td bgcolor="{row_on}">
<!-- END border_top -->

<!-- BEGIN border_bottom -->
			</td>
		</tr>
	</table>
<!-- END border_bottom -->
<!-- BEGIN randomdata -->
{data}
<!-- END randomdata -->

