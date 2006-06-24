<!-- BEGIN event_widget -->
{indent}<div class="calEventHeader{Small}" style="background-color: {bordercolor};">
{indent}	{header}
{indent}	<div class="calEventIcons">{icons}</div>
{indent}</div>
{indent}<div class="calEventBody{Small}">{title}</div>
<!-- END event_widget -->

<!-- BEGIN event_tooltip -->
<div class="calEventTooltip" style="border-color: {bordercolor}; background: {bodybackground};">
	<div class="calEventHeaderSmall" style="background-color: {bordercolor};">
		<font color="white"><b>{timespan}</b></font>
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
</dir>
<!-- END event_tooltip -->

<!-- BEGIN planner_event -->
{icons} {title}
<!-- END planner_event -->
