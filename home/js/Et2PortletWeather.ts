import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {classMap, css, html, nothing, repeat, TemplateResult} from "@lion/core";
import shoelace from "../../api/js/etemplate/Styles/shoelace";
import {SelectOption} from "../../api/js/etemplate/Et2Select/FindSelectOptions";

/**
 * Show current and forecast weather
 */
export class Et2PortletWeather extends Et2Portlet
{
	static get styles()
	{
		return [
			...shoelace,
			...(super.styles || []),
			css`
			  .portlet--weather {
				display: flex;
			  }

			  .day__forecast {
				width: fit-content;
				min-width: 12ex;
				max-width: 20ex;
			  }

			  .temperature__day-label {
				text-align: center;
				font-size: 120%;
				padding-bottom: var(--sl-spacing-medium);
			  }

			  .temperature {
				font-size: 160%;
			  }

			  .temperature__high_low {
			  }

			  sl-icon {
				font-size: 32px;
			  }

			  .portlet--weather .temperature__current {
				/* Make current day a little bigger */
				font-size: 180%;
				padding: var(--sl-spacing-large);
			  }

			  .temperature__current .day__forecast {
				padding: var(--sl-spacing-medium) 0px;
			  }

			  :host([style*="span 1"]) .temperature__current {
				/* No padding if portlet is small */
				padding: 0px;
			  }

			  .portlet--weather .temperature__current sl-icon {
				font-size: 250%;
			  }

			  .temperature__day-list {
				flex: 1 1 auto;
				display: grid;
				gap: var(--sl-spacing-x-large) var(--sl-spacing-medium);
				grid-template-columns: repeat(auto-fill, minmax(12ex, 1fr));
				padding-top: var(--sl-spacing-large);
			  }

			  .temperature__day-list .weather__day-forecast {
				min-height: 12ex;
			  }
			`
		]
	}

	/**
	 * Get a list of user-configurable properties
	 * @returns {[{name : string, type : string, select_options? : [SelectOption]}]}
	 */
	get portletProperties() : { name : string, type : string, label : string, select_options? : SelectOption[] }[]
	{
		return [
			...super.portletProperties,
			{
				'name': 'city',
				'type': 'et2-textbox',
				'label': this.egw().lang('Location'),
			}
			/* Use size to control what we show
			{
				name: "display", label: "Display", type: "et2-select", select_options: [
					{'value': 'today', 'label': this.egw().lang('today')},
					{'value': '3', 'label': this.egw().lang('%1 day', 3)},
					{'value': '10', 'label': this.egw().lang('%1 day', 10)},
				]
			}
			*/
		];
	}

	/**
	 * Template for one day of the forecast
	 * @param day
	 * @protected
	 */
	protected forecastDayTemplate(day)
	{
		return html`
            <div class="weather__day-forecast">
                <et2-description class="temperature__day-label" value="${day.day}"></et2-description>
                <et2-hbox part="day" class="day__forecast">
                    <sl-icon class="weather_icon" name="${day.weather[0].icon}"></sl-icon>
                    ${(typeof day.temp?.temp != "undefined") ? html`
                        <et2-hbox class="temperature">
                            <span>${day.temp.temp}</span>
                        </et2-hbox>` : nothing
                    }
                    <et2-vbox class="temperature__high_low">
                        <span class="temperature__max">${day.temp.max}</span>
                        <span class="temperature__min">${day.temp.min}</span>
                    </et2-vbox>
                </et2-hbox>
            </div>`;
	}

	bodyTemplate() : TemplateResult
	{
		const doList = parseInt(getComputedStyle(this).width) > 300;
		const current = this.settings?.weather?.current || {weather: [{icon: "question-lg"}], temp: {temp: "?"}};

		// Get the forecast, excluding today
		let list = this.settings.weather && (Object.values(this.settings?.weather?.list || {}).slice(1) || []) || [];
		
		return html`
            <div
                    part="base"
                    class=${classMap({
                        portlet: true,
                        "portlet--weather": true
                    })}
            >
                <div part="current" class="temperature__current">
                    ${this.forecastDayTemplate({
                        ...{
                            day: 'Today',
                            // Current has a different data format
                            temp: {
                                min: current.temp.temp_min,
                                max: current.temp.temp_max
                            }, ...current
                        }
                    })}
                </div>
                ${doList ? html`
                    <div part="list" class="temperature__day-list">
                        ${repeat(list, (item, index) =>
                        {
                            return this.forecastDayTemplate(item);
                        })}
                    </div>` : nothing
                }
            </div>
		`;
	}

}

if(!customElements.get("et2-portlet-weather"))
{
	customElements.define("et2-portlet-weather", Et2PortletWeather);
}