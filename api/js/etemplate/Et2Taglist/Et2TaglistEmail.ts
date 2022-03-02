
export class Et2TaglistEmail extends Et2Taglist
{
	static get taglist_option () {return TaglistOptionEmail};

	_setOptionTemplate()
	{
		return html`
            ${this.get_select_options().map(entry => html` <taglist-option .choiceValue="${entry.value}">${entry.label}</taglist-option> `)}
		`;
	}

	get_select_options(): any
	{
		//@todo: override it accordingly
		return super.get_select_options();
	}
}
customElements.define('et2-taglist-email', Et2TaglistEmail);