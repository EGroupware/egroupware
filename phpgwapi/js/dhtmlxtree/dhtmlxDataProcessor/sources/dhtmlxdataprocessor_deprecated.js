//v.2.6 build 100722

/*
Copyright DHTMLX LTD. http://www.dhtmlx.com
You allowed to use this component or parts of it under GPL terms
To use it on other terms or get Professional edition of the component please contact us at sales@dhtmlx.com
*/

	/**
	*     @desc: set function called after row updated
	*     @param: func - event handling function (or its name)
	*     @type: deprecated
	*     @topic: 10
	*	  @event: onAfterUpdate
	*     @eventdesc: Event raised after row updated on server side
	*     @eventparam:  ID of clicked row
	*     @eventparam:  type of command
	*     @eventparam:  new Id, for _insert_ command
	*/
	dataProcessor.prototype.setOnAfterUpdate = function(ev){
		this.attachEvent("onAfterUpdate",ev);
	}
	
	/**
	* 	@desc: enable/disable debuging
	*	@param: mode - true/false
	*   @type: deprecated
	*/
	dataProcessor.prototype.enableDebug = function(mode){
	}
	
/**
*     @desc: set function called before server request sent ( can be used for including custom client server transport system)
*     @param: func - event handling function
*     @type: public
*     @topic: 0
*     @event: onBeforeUpdate
*     @eventdesc:  Event occured in moment before data sent to server
*     @eventparam: ID of item which need to be updated
*     @eventparam: type of operation
*     @eventreturns: false to block default sending routine
*/
	dataProcessor.prototype.setOnBeforeUpdateHandler=function(func){  
		this.attachEvent("onBeforeDataSending",func);
	};