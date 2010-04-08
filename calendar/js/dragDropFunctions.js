/**
 * Calendar - dragNdrop
 *
 * @link http://www.egroupware.org
 * @author Christian Binder <christian.binder@freakmail.de>
 * @package calendar
 * @copyright (c) 2006 by Christian Binder <christian.binder@freakmail.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

function dragEvent()
{
	if(minOffset())
	{
		// set a fixed width
		dd.obj.css.width = dd.obj.w + dd.px;

		// make a snapshot of the old (original) innerHTML of the dragged event
		if(!dd.obj.oldInnerHTML)
		{
			dd.obj.oldInnerHTML = dd.obj.div.innerHTML;
		}

		if(dropTarget = dd.obj.getEltBelow())
		{
			var datetime = dropTarget.my_datetime;
		}

		// we just allow to drop within the users own calendar
		// and not crossing into another calendar ATM
		if(datetime && (dd.obj.my_calendarOwner == dropTarget.my_owner ))
		{
			dd.obj.div.innerHTML = '<div style="font-size: 1.1em; font-weight: bold; text-align: center;">' + datetime.substr(9,2) + ":" + datetime.substr(11,2) + '</div>';
		} else {

			dd.obj.div.innerHTML = '<div style="background-color: red; height: 100%; width: 100%; text-align: center;">' + dd.obj.my_errorImage + '</div>';
		}
	}
}

function dropEvent()
{
	// minimum requirements for ajax call
	if(	minOffset() &&
		(dropTarget = dd.obj.getEltBelow()) &&
		(dropTarget.my_datetime) &&
		(dd.obj.my_calendarOwner == dropTarget.my_owner)
	)
	{
		dd.obj.div.innerHTML = '<div style="height: 100%; width: 100%; text-align: center;">' + dd.obj.my_loaderImage + '</div>';

		xajax_doXMLHTTP(
			'calendar.calendar_ajax.moveEvent',
			dd.obj.my_eventId,
			dd.obj.my_calendarOwner,
			dropTarget.my_datetime,
			dropTarget.my_owner
		);
	}
	else
	{
		// abort - move to old position and restore old innerHTML
		if(dd.obj.oldInnerHTML)
		{
			dd.obj.div.innerHTML = dd.obj.oldInnerHTML;
		}

		// restore old width (calEvent width = 100%)
		dd.obj.div.style.width = "100%";

		dd.obj.moveTo(dd.obj.defx,dd.obj.defy);
	}
}

function minOffset()
{
	var offsetX = Math.abs(dd.obj.defx - dd.obj.x);
	var offsetY = Math.abs(dd.obj.defy - dd.obj.y);

	if(offsetX > 5 || offsetY > 5) { return true; }
	return false;
}
