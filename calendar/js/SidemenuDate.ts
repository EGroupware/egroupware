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

	constructor()
	{
		super();

		this._onDayCreate = this._onDayCreate.bind(this);
		this._handleChange = this._handleChange.bind(this);
		this._handleDayHover = this._handleDayHover.bind(this);
		this._clearHover = this._clearHover.bind(this);
		this._updateGoButton = this._updateGoButton.bind(this);
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
		if(this._instance.daysContainer !== undefined)
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

		// Customise next / prev buttons
		this.querySelector('.flatpickr-next-month').classList.add("et2_button", "et2_button_text");
		this.egw().tooltipBind(this.querySelector('.flatpickr-next-month'), this.egw().lang("next"));
		this.querySelector('.flatpickr-prev-month').classList.add("et2_button", "et2_button_text");
		this.egw().tooltipBind(this.querySelector('.flatpickr-prev-month'), this.egw().lang("prev"));

		// Move buttons into header
		if(this._goButton && this._headerNode)
		{
			this._headerNode.append(this._goButton);
		}
		if(this._todayButton && this._headerNode)
		{
			this._headerNode.append(this._todayButton);
		}
		this._updateGoButton();
	}

	/**
	 * Override some flatpickr defaults to get things how we like it
	 *
	 * @see https://flatpickr.js.org/options/
	 * @returns {any}
	 */
	protected getOptions()
	{
		let options = super.getOptions();

		options.inline = true;
		options.dateFormat = "Y-m-dT00:00:00\\Z";
		options.shorthandCurrentMonth = true;

		options.onMonthChange = this._updateGoButton;
		options.onYearChange = this._updateGoButton;

		options.nextArrow = "";
		options.prevArrow = "";

		return options
	}

	set_value(value)
	{
		if(!value || value == 0 || value == "0" || typeof value !== "string")
		{
			return super.set_value(value);
		}
		if(value.length == 8)
		{
			super.set_value(parseDate(value, "Ymd"));
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
		this._oldChange(_ev);
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

	get _goButton()
	{
		return this.querySelector("et2-button[id*='go']");
	}

	get _todayButton()
	{
		return this.querySelector("et2-button[id*='today']");
	}

	/**
	 * Update the go button
	 *
	 * @protected
	 */
	protected _updateGoButton()
	{
		if(!this._goButton)
		{
			return;
		}
		let temp_date = new Date("" + this._instance.currentYear + "-" + (this._instance.currentMonth + 1) + "-01");
		temp_date.setUTCMinutes(temp_date.getUTCMinutes() + temp_date.getTimezoneOffset());

		this._goButton.setAttribute('title', egw.lang(this._instance.formatDate(temp_date, "F")));
		// Store current _displayed_ date in date button for clicking
		temp_date.setUTCMinutes(temp_date.getUTCMinutes() - temp_date.getTimezoneOffset());
		this._goButton.setAttribute('data-date', temp_date.toJSON());
	}
}

// @ts-ignore TypeScript is not recognizing that Et2Date is a LitElement
customElements.define("calendar-date", SidemenuDate);