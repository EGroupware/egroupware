<!-- BEGIN event_widget -->
<table style="width: 100%; height: 100%; margin: 0px; padding: 0px;" cellpadding="0" cellspacing="0" border="0" align="center" {tooltip}>

	<tr style="height: {header_height};" valign="top">
		<td valign="middle" class="calEventHeader{Small}" style="height: {header_height}; border-top: {border}px solid {bordercolor}; background-color: {headerbgcolor};">
			{icons} {timespan}
		</td>
	</tr>

	<tr valign="top" style="height: 100%;">
		<td class="calEventBody{Small}" style="background: {bodybackground}; border-bottom: {border}px solid {bordercolor}; border-left: {border}px solid {bordercolor}; border-right: {border}px solid {bordercolor};">
			{title}
		</td>
	</tr>
</table>
<!-- END event_widget -->

<!-- BEGIN event_tooltip -->
<table style="width: 100%; margin: 0px; padding: 0px;" cellpadding="0" cellspacing="0" border="0" align="center">
	<tr style="height: {header_height};" valign="top">
		<td valign="middle" class="calEventHeaderSmall" style="height: {header_height}; border-top: {border}px solid {bordercolor}; background-color: {headerbgcolor};">
			{icons} {timespan}
		</td>
	</tr>
	<tr valign="top">
		<td class="calEventBodySmall" style="background: {bodybackground}; border-bottom: {border}px solid {bordercolor}; border-left: {border}px solid {bordercolor}; border-right: {border}px solid {bordercolor};">
			<p style="margin: 0px;">
			<span class="calEventTitle">{title}</span><br>
			{description}</p>
			<p style="margin: 2px 0px;">{times}
			{location}
			{category}
			{participants}</p>
		</td>
	</tr>
</table>
<!-- END event_tooltip -->

<!-- BEGIN planner_event -->
{icons} {title}
<!-- END planner_event -->
