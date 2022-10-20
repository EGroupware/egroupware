import {Et2Date, parseDate} from "../../api/js/etemplate/Et2Date/Et2Date";
import {css} from "@lion/core";
import {CalendarApp} from "./app";

export class SidemenuDate extends Et2Date
{
	static get styles()
	{
		return [
			...super.styles,
			css`
			/** Hide input **/
			::slotted(input) {
				display: none;
			}
			/** Special sizing for headers **/
			.flatpickr-months > * {
				padding: 3px;
				height: 20px;
			}
			.flatpickr-current-month {
				height: 20px;
				font-size: 110%
			}
			div.flatpickr-calendar.inline .flatpickr-current-month .flatpickr-monthDropdown-months {
				width: 70%;
			}
			
			div.flatpickr-calendar.inline {
				width: 100% !important;
			}
			
			/** Responsive resize is in etemplate2.css since we can't reach that far inside **/
			`
		];
	}

	get slots()
	{
		return {
			...super.slots,
			input: () =>
			{
				// This element gets hidden and used for value - overridden from parent
				const text = document.createElement('input');
				text.type = "text";
				return text;
			}
		}
	}

	constructor()
	{
		super();

		this._onDayCreate = this._onDayCreate.bind(this);
		this._handleChange = this._handleChange.bind(this);
		this._handleDayHover = this._handleDayHover.bind(this);
		this._clearHover = this._clearHover.bind(this);
		this._handleHeaderChange = this._handleHeaderChange.bind(this);
	}

	async connectedCallback()
	{
		super.connectedCallback();

		this.removeEventListener("change", this._oldChange);
		this.addEventListener("change", this._handleChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();

		this.removeEventListener("change", this._handleChange);
		if(this._instance && this._instance.daysContainer !== undefined)
		{
			this._instance.weekNumbers.removeEventListener("mouseover", this._handleDayHover);
			this._instance.weekNumbers.removeEventListener("mouseout", this._clearHover);
		}
	}

	/**
	 * Initialze flatpickr, and bind to any internal elements we're interested in
	 *
	 * Normal pre-creation config goes in this.getOptions()
	 *
	 * @returns {Promise<void>}
	 */
	async init()
	{
		await super.init();

		// This needs to wait until after everything is created
		if(this._instance.daysContainer !== undefined)
		{
			this._instance.weekNumbers.addEventListener("mouseover", this._handleDayHover);
			this._instance.weekNumbers.addEventListener("mouseout", this._clearHover);
		}
	}

	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	public getOptions()
	{
		let options = super.getOptions();

		options.allowInput = false;
		options.inline = true;
		options.dateFormat = "Y-m-dT00:00:00\\Z";

		options.onMonthChange = this._handleHeaderChange;
		options.onYearChange = this._handleHeaderChange;
		options.wrap = false;

		return options
	}

	protected _buttonPlugin()
	{
		// No buttons
		return null;
	}

	/**
	 * Override from parent - This is the node we tell flatpickr to use
	 * It must be an <input>, flatpickr doesn't understand anything else
	 * @returns {any}
	 */
	findInputField() : HTMLInputElement
	{
		return <HTMLInputElement>this._inputNode;
	}

	set_value(value)
	{
		if(typeof value !== "string" && value.length == 8)
		{
			super.set_value(parseDate(value, "Ymd"));
		}
		else
		{
			super.set_value(value);
		}
	}

	/**
	 * Handler for change events.  Re-bound to be able to cancel month changes, since it's an input and emits them
	 *
	 * @param dates
	 * @param {string} dateString
	 * @param instance
	 * @protected
	 */
	protected _handleChange(_ev)
	{
		if(_ev.target == this._instance.monthsDropdownContainer)
		{
			_ev.preventDefault();
			_ev.stopPropagation();
			return false;
		}
		let view_change = app.calendar.sidebox_changes_views.indexOf(app.calendar.state.view);
		let update = {date: this.getValue()};

		if(view_change >= 0)
		{
			update.view = app.calendar.sidebox_changes_views[view_change ? view_change - 1 : view_change];
		}
		else if(app.calendar.state.view == 'listview')
		{
			update.filter = 'after';
		}
		else if(app.calendar.state.view == 'planner')
		{
			update.planner_view = 'day';
		}
		app.calendar.update_state(update);

	}

	/**
	 * Handle click on shortcut button(s) like "Today"
	 *
	 * @param button_index
	 * @param fp Flatpickr instance
	 */
	public _handleShortcutButtonClick(button_index, fp)
	{
		// This just changes the calendar to today
		super._handleShortcutButtonClick(button_index, fp);

		let temp_date = new Date("" + this._instance.currentYear + "-" + (this._instance.currentMonth + 1) + "-" + (this._instance.selectedDates[0].getDate() || "01"));

		// Go directly
		let update = {date: temp_date};
		app.calendar.update_state(update);
	}

	_handleClick(_ev : MouseEvent) : boolean
	{
		//@ts-ignore
		if(this._instance.weekNumbers.contains(_ev.target))
		{
			return this._handleWeekClick(_ev);
		}

		return super._handleClick(_ev);
	}

	/**
	 * Handle clicking on the week number
	 *
	 * @param {MouseEvent} _ev
	 * @returns {boolean}
	 */
	_handleWeekClick(_ev : MouseEvent)
	{
		let view = app.calendar.state.view;
		let days = app.calendar.state.days;
		let fp = this._instance;

		// Avoid a full state update, we just want the calendar to update
		// Directly update to avoid change event from the sidebox calendar

		let week_index = Array.prototype.indexOf.call(fp.weekNumbers.children, _ev.target);
		if(week_index == -1)
		{
			return false;
		}
		let weekStartDay = fp.days.childNodes[7 * week_index].dateObj;
		fp.setDate(weekStartDay);

		let date = new Date(weekStartDay);
		date.setUTCMinutes(date.getUTCMinutes() - date.getTimezoneOffset());
		date = app.calendar.date.toString(date);

		// Set to week view, if in one of the views where we change view
		if(app.calendar.sidebox_changes_views.indexOf(view) >= 0)
		{
			app.calendar.update_state({view: 'week', date: date, days: days});
		}
		else if(view == 'planner')
		{
			// Clicked a week, show just a week
			app.calendar.update_state({date: date, planner_view: 'week'});
		}
		else if(view == 'listview')
		{
			app.calendar.update_state({
				date: date,
				end_date: app.calendar.date.toString(CalendarApp.views.week.end_date({date: date})),
				filter: 'week'
			});
		}
		else
		{
			app.calendar.update_state({date: date});
		}
		return true;
	}

	/**
	 * Handle a hover over a day
	 * @param {MouseEvent} _ev
	 * @returns {boolean}
	 */
	_handleDayHover(_ev : MouseEvent)
	{
		if(this._instance.weekNumbers.contains(_ev.target))
		{
			return this._highlightWeek(_ev.target);
		}
	}

	/**
	 * Highlight a week based on the given week number HTMLElement
	 */
	_highlightWeek(weekElement)
	{
		let fp = this._instance;

		let week_index = Array.prototype.indexOf.call(fp.weekNumbers.children, weekElement);
		if(week_index == -1)
		{
			return false;
		}

		fp.weekStartDay = fp.days.childNodes[7 * week_index].dateObj;
		fp.weekEndDay = fp.days.childNodes[7 * (week_index + 1) - 1].dateObj;

		let days = fp.days.childNodes;
		for(let i = days.length; i--;)
		{
			let date = days[i].dateObj;
			if(date >= fp.weekStartDay && date <= fp.weekEndDay)
			{
				days[i].classList.add("inRange");
			}
			else
			{
				days[i].classList.remove("inRange")
			}
		}
	}

	_clearHover()
	{
		let days = this._instance.days.childNodes;
		for(var i = days.length; i--;)
			days[i].classList.remove("inRange");
	}

	get _headerNode()
	{
		return this._instance?.monthNav;
	}

	/**
	 * Year or month changed
	 *
	 * @protected
	 */
	protected _handleHeaderChange()
	{
		const maxDays = new Date(this._instance.currentYear, this._instance.currentMonth + 1, 0).getDate();

		let temp_date = new Date("" + this._instance.currentYear + "-" +
			(this._instance.currentMonth + 1) + "-" +
			("" + Math.min(maxDays, new Date(this.value).getUTCDate())).padStart(2, "0")
		);
		temp_date.setUTCMinutes(temp_date.getUTCMinutes() + temp_date.getTimezoneOffset());

		// Go directly
		let update = {date: temp_date};
		app.calendar.update_state(update);
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("calendar-date", SidemenuDate);