<!-- BEGIN event_widget -->
{indent}<div class="calEventHeader{Small}" style="background-color: {bordercolor}; color: {headercolor};">
{indent}	{header}
{indent}	<div class="calEventIcons">{icons}</div>
{indent}</div>
{indent}<div class="calEventBody{Small}">{title}</div>
<!-- END event_widget -->

<!-- BEGIN event_widget_wholeday_on_top -->
{indent}<div class="calEventBody{Small}">
{indent}	{title}
{indent}</div>
<!-- END event_widget_wholeday_on_top -->

<!-- BEGIN event_tooltip -->
<div class="calEventTooltip {status_class}" style="border-color: {bordercolor}; background: {bodybackground};">
	<div class="calEventHeaderSmall" style="background-color: {bordercolor};">
		<font color="{headercolor}">{timespan}</font>
		<div  class="calEventIcons">{icons}</div>
	</div>
	<div class="calEventBodySmall">
		<p style="margin: 0px;">
		<span class="calEventTitle">{title}</span><br>
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
