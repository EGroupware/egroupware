/**
 * Popup manager
 *
 * Framework uses this to manage any popups we need to keep track of
 */
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";

export class EgwPopups
{

	// Keep track of open popups
	private _popups : Window | Et2Dialog[] = [];
	private _popupsGCInterval : number;

	public add(windowID)
	{
		this._popups.push(windowID);
		if(!this._popupsGCInterval)
		{
			// Check every 60s to make sure we didn't miss any
			this._popupsGCInterval = window.setInterval(() => this._garbage_collector(), 10000);
		}
	}

	/**
	 * get popups based on application name and regexp
	 * @param {string} _app app name
	 * @param {regexp|object} regex regular expression to check against location.href url or
	 * an object containing window property to be checked against
	 *
	 * @return {Window[]}
	 */
	public get(_app : string, param : RegExp | Object) : Window[]
	{
		const popups = [];
		for(let i = 0; i < this._popups.length; i++)
		{
			if(!this._popups[i].closed && this._popups[i].egw_appName == _app)
			{
				popups.push(this._popups[i]);
			}
		}
		if(param)
		{
			for(let j = 0; j < popups.length; j++)
			{
				if(typeof param === 'object' && param.constructor.name != 'RegExp')
				{
					const key = Object.keys(param)[0];
					if(!popups[j][key].match(new RegExp(param[key])))
					{
						delete (popups[j]);
					}
				}
				else
				{
					if(!popups[j].location.href.match(param))
					{
						delete (popups[j]);
					}
				}
			}
		}
		return popups.flat();
	}

	/**
	 * Check if given window is a "popup" alike, returning integer or undefined if not
	 *
	 * @param {Window} _wnd
	 * @returns {number|undefined}
	 */
	public findIndex(_wnd : Window) : number | undefined
	{
		return this._popups.findIndex(w => w === _wnd || w.$iFrame && $iFrame[0].contentWindow === _wnd) ?? undefined;
	}

	public close(_wnd : Window | Et2Dialog)
	{
		if(_wnd instanceof Et2Dialog)
		{
			this._popups.splice(this._popups.indexOf(_wnd), 1);
			return (<Et2Dialog>_wnd).hide();
		}
		else if(typeof _wnd.close === 'function')
		{
			_wnd.close();
		}
	}

	/**
	 * Collect and close all already closed windows
	 * egw.open_link expects it from the framework
	 */
	private _garbage_collector()
	{
		let i = this._popups.length;
		while(i--)
		{
			if(this._popups[i].closed)
			{
				this._popups.splice(i, 1);
			}
		}
		if(this._popups.length == 0 && this._popupsGCInterval)
		{
			window.clearInterval(this._popupsGCInterval);
			this._popupsGCInterval = null;
		}
	}
}