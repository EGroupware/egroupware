<!-- BEGIN event_widget -->
{indent}<div class="calendar_calEventHeader{Small}" style="background-color: {bordercolor}; color: {headercolor};">
{indent}	{header}
{indent}	<div class="calendar_calEventIcons">{icons}</div>
{indent}</div>
{indent}<div class="calendar_calEventBody{Small}">
{indent}	<p style="margin: 0px;">
{indent}		<span class="calendar_calEventTitle">{title}</span>
{indent}		<br>{bodydescription}
{indent}	</p>
{indent}</div>
<!-- END event_widget -->

<!-- BEGIN event_widget_wholeday_on_top -->
{indent}<div class="calendar_calEventBody{Small}">
{indent}	{title}
{indent}</div>
<!-- END event_widget_wholeday_on_top -->

<!-- BEGIN event_tooltip -->
<div class="calendar_calEventTooltip {status_class}" style="border-color: {bordercolor}; background: {bodybackground};">
	<div class="calendar_calEventHeaderSmall" style="background-color: {bordercolor};">
		<font color="{headercolor}">{timespan}</font>
		<div  class="calendar_calEventIcons">{icons}</div>
	</div>
	<div class="calendar_calEventBodySmall">
		<p style="margin: 0px;">
		<span class="calendar_calEventTitle">{title}</span><br>
		{description}</p>
		<p style="margin: 2px 0px;">{times}
		{location}
		{category}
		{participants}</p>
	</div>
</div>
<!-- END event_tooltip -->

<!-- BEGIN planner_event -->
{icons} {title}
<!-- END planner_event -->
