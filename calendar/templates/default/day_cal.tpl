<!-- $Id$ -->
<!-- BEGIN day -->
<table width="100%" cellspacing="0" cellpadding="0">
{row}
</table>
<!-- END day -->
<!-- BEGIN day_row -->
    <tr>{time}{event}
    </tr>
<!-- END day_row -->
<!-- BEGIN day_event_on -->
     <td class="event-on"{extras}>&nbsp;{event}</td>
<!-- END day_event_on -->
<!-- BEGIN day_event_off -->
     <td class="event-off"{extras}>&nbsp;{event}</td>
<!-- END day_event_off -->
<!-- BEGIN day_event_holiday -->
     <td class="event-holiday"{extras}>&nbsp;{event}</td>
<!-- END day_event_holiday -->
<!-- BEGIN day_time -->
     <td class="time" nowrap>{open_link}{time}{close_link}</td>
<!-- END day_time -->

