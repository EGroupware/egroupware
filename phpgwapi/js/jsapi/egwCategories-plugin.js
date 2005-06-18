  /***************************************************************************\
  * eGroupWare - Javascript API                                               *
  * http://www.egroupware.org                                                 *
  * Written by:                                                               *
  *  - Raphael Derosso Pereira <raphaelpereira@users.sourceforge.net>         *
  *  - Vinicius Cubas Brand <viniciuscb@users.sourceforge.net>                *
  *  sponsored by Think.e - http://www.think-e.com.br                         *
  * ------------------------------------------------------------------------- *
  *  This program is free software; you can redistribute it and/or modify it  *
  *  under the terms of the GNU General Public License as published by the    *
  *  Free Software Foundation; either version 2 of the License, or (at your   *
  *  option) any later version.                                               *
  \***************************************************************************/

	/*
	 * eGroupWare Global API - Categories Handling Floating Window
	 *
	 */

	function egwCategoriesPlugin()
	{
		this.window = null;
		this.selectedCategories = new Array();
		this.categories = new Array();
		this.postURL = GLOBALS['serverRoot']+'/xmlrpc.php';
	
		//user-defined method to execute when window closes
		this.onCloseHandler = null;

		//user-defined method to execute when window changes
		this.onChangeHandler = null;

		this.DOM = new Object();

		// Initialization
		this.DOM.egw_categories = Element("egw_categories");
		this.DOM.egw_categories_change = Element("egw_categories_change");
		this.DOM.egw_categories_w_form = Element("egw_categories_w_form");
		this.DOM.egw_categories_wcontent = Element("egw_categories_wcontent");
		this.DOM.egw_categories_title = Element("egw_categories_title");
		
        this.window = new dJSWin(
		{			'id': 'egwCategories',
					'content_id': 'egw_categories_wcontent',
					'win_class': 'row_off',
					'width': '200px',
					'height': '120px',
					'title_color': '#3978d6',
					'title': this.DOM.egw_categories_title.value,
					'title_text_color': 'white',
					'button_x_img': GLOBALS['egw_img_dir']+'/winclose.gif',
					'border': true});
	}

	//receives an object with keys=keys of select, vals=vals to show
	egwCategoriesPlugin.prototype.populate = function(population)
	{
		this.categories = population;
		clearSelectBox(this.DOM.egw_categories,0);
		fillSelectBox(this.DOM.egw_categories,this.categories);
		this.selectedCategories = new Array();
	}

	//receives array or object with values=categories ids, fetch categories
	//from egw via rpc call
	egwCategoriesPlugin.prototype.setCategories = function(categories)
	{

	}
	
	egwCategoriesPlugin.prototype.selectCategories = function(selected)
	{
		this.selectedCategories = selected;
		selectOptions(this.DOM.egw_categories,this.selectedCategories);
		
	}

	//gets all selected categories values
	egwCategoriesPlugin.prototype.getSelectedIDs = function()
	{
		return this.selectedCategories;
	}

	//gets all selected categories values
	egwCategoriesPlugin.prototype.getSelectedNames = function()
	{
		var ret = new Array();
		for (var i in this.selectedCategories)
		{
			ret.push(this.categories[this.selectedCategories[i]]);
		}

		return ret;
	}
	
	//gets all selected categories values
	egwCategoriesPlugin.prototype.getSelectedCategories = function()
	{
		var ret = new Object();
		for (var i in this.selectedCategories)
		{
			ret[this.selectedCategories[i]] = (this.categories[this.selectedCategories[i]]);
		}

		return ret;
	}	

	egwCategoriesPlugin.prototype.close = function ()
	{
		this.window.close();
	}

	egwCategoriesPlugin.prototype.open = function ()
	{
		this.window.open();
	}
	
	egwCategoriesPlugin.prototype.setOnChangeHandler = function(func)
	{
		this.onChangeHandler = func;	
	}

	/*********************************************************************\
	 *                          Private Methods                          *
	\*********************************************************************/


	//method to execute when window closes without saving
	egwCategoriesPlugin.prototype._onClose = function()
	{
		if (typeof(this.onCloseHandler) == 'function')
		{
			this.onCloseHandler();
		}
	}

	//method to execute when window changes its value
	egwCategoriesPlugin.prototype._changeCategories = function(handler)
	{
		this.selectedCategories = getSelectedOptions(this.DOM.egw_categories);
		if (typeof(this.onChangeHandler) == 'function')
		{
			this.onChangeHandler();
		}
		this.window.close();
	}

	egwCategoriesPlugin.prototype._clearAll = function ()
	{
		// Clear information container 
		this.selectedCategories = new Array();

		// Clear Fields 
		this.DOM.egw_categories_w_form.reset();
	}
		
	egwCategoriesPlugin.prototype._disableAll = function ()
	{
	}

	//just in the end of tpl
	//egwCategories = new egwCategoriesPlugin();
	

