/**
 * eGroupWare egw_action framework - egw action framework
 *
 * @link http://www.egroupware.org
 * @author Andreas Stöckel <as@stylite.de>
 * @copyright 2011 by Andreas Stöckel
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package egw_action
 * @version $Id$
 */

// XXX WARNING: UNTESTED, UNFINISHED, NOT (YET) WORKING CODE! XXX

/** egwActionHandler Interface **/

/**
 * Constructor for the egwActionHandler interface which (at least) should have the
 * execute function implemented.
 */
function egwActionHandler(_executeEvent)
{
	//Copy the executeEvent parameter
	this.execute = _executeEvent;
}


/** egwAction Object **/

/**
 * Associative array where action classes may register themselves
 */
var _egwActionClasses =
	{
		"default":
		{
			"actionConstructor": egwAction,
			"implementationConstructor": null
		}
	}

function egwAction(_id, _handler, _caption, _icon, _onExecute, _allowOnMultiple)
{
	//Default and check the values
	if (typeof _id != "string" || !_id)
		throw "egwAction _id must be a non-empty string!";
	if (typeof _handler == "undefined")
		this.handler = null;
	if (typeof _label == "undefined")
		_label = "";
	if (typeof _icon == "undefined")
		_icon = "";
	if (typeof _onExecute == "undefined")
		_onExecute = null;
	if (typeof _allowOnMultiple == "undefined")
		_allowOnMultiple = true;

	this.id = _id;
	this.caption = _caption;
	this.icon = _icon;
	this.allowOnMultiple = _allowOnMultiple;
	this.type = "default"; //All derived classes have to override this!

	this.execJSFnct = null;
	this.execHandler = false;
	this.set_onExecute(_onExecute);
}

/**
 * Executes this action by using the method specified in the onExecute setter.
 *
 * @param array_senders array with references to the objects which caused the action
 * //TODO: With DragDrop we don't only have senders but also one(!) target.
 */
egwAction.prototype.execute = function(_senders)
{
	if (this.execJSFnct && typeof this.execJSFnct == "function")
	{
		this.execJSFnct(this, _senders);
	}
	else if (this.execHandler)
	{
		this.handler.execute(this, _senders);
	}
}

/**
 * The set_onExecute function is the setter function for the onExecute event of
 * the egwAction object. There are three possible types the passed "_value" may
 * take:
 *	1. _value may be a string with the word "javaScript:" prefixed. The function
 *	   which is specified behind the colon and which has to be in the global scope
 *	   will be executed.
 *	2. _value may be a boolean, which specifies whether the external onExecute handler
 *	   (passed as "_handler" in the constructor) will be used.
 *	3. _value may be a JS functino which will then be called.
 * In all possible situation, the called function will get the following parameters:
 * 	1. A reference to this action
 * 	2. The senders, an array of all objects (JS)/object ids (PHP) which evoked the event
 */ 
egwAction.prototype.set_onExecute(_value)
{
	//Reset the onExecute handlers
	this.execJSFnct = null;
	this.execHandler = false;

	if (typeof _value == "string")
	{
		// Check whether the given string contains a javaScript function which
		// should be called upon executing the action
		if (_value.substr(0, 11) == "javaScript:")
		{
			//Check whether the given function exists
			var fnct = _value.substr(11);
			if (typeof window[fnct] == "function")
			{
				this.execJSFnct = window[fnct];
			}
		}
	}
	else if (typeof _value == "boolean")
	{
		// There is no direct reference to the PHP code which should be executed,
		// as the PHP code has knowledge about this.
		this.execHandler = _value;
	}
	else if (typeof _value == "function")
	{
		//The JS function has been passed directly
		this.execJSFnct = _value;
	}
}

egwAction.prototype.set_caption(_value)
{
	this.caption = _value;
}

egwAction.prototype.set_icon(_value)
{
	this.icon = _value;
}

egwAction.prototype.set_allowOnMultiple(_value)
{
	this.allowOnMultiple = _value;
}


/** egwActionManager Object **/

/**
 * egwActionManager manages a list of actions, provides functions to add new
 * actions or to update them via JSON.
 */
function egwActionManager(_handler)
{
	//Preset the handler parameter to null
	if (typeof _handler == "undefined")
		_handler = null;

	this.handler = _handler;
	this.actions = [];
}

egwActionManager.prototype.addAction = function(_type, _id, _caption, _icon,
	_onExecute, _allowOnMultiple)
{
	//Get the constructor for the given action type
	if (!_type)
		_type = "default";
	var constructor = _egwActionClasses[_type].actionConstructor;

	if (typeof constructor == "function")
	{
		var action = new constructor(_id, this.handler, _caption, _icon, _onExecute,
			_allowOnMultiple);
		this.actions.push[action];

		return action;
	}
	else
		throw "Given action type not registered.";
}

egwActionManager.prototype.updateActions = function(_actions)
{
	for (var i = 0 ; i < _actions.length; i++)
	{
		//Check whether the given action is already part of this action manager instance
		var elem = _actions[i];
		if (typeof elem == "object" && typeof elem.id == "string" && elem.id)
		{
			//Check whether the action already exists, and if no, add it to the
			//actions list
			var action = this.getAction(elem.id);
			if (!action)
			{
				if (typeof elem.type == "undefined")
					elem.type = "default";

				var constructor = _egwActionClasses[elem.type].actionConstructor;

				if (typeof constructor == "function")
					action = new constructor(elem.id, this.handler);
				else
					throw "Given action type not registered.";

				this.actions.push(action);
			}

			//Update the actions by calling the corresponding setter functions
			//TODO: Hirachical actions will need a reference to their parent -
			//      this parent is has to be translated to a js object
			//TODO: Maby the setter, JSON, update stuff should somehow be moved
			//      to a own base class.
			egwActionStoreJSON(elem, action, true);
		}
	}
}

egwActionManager.prototype.getAction = function(_id)
{
	for (var i = 0; i < this.actions.length; i++)
	{
		if (this.actions[i].id == _id)
			return this.actions[i];
	}

	return null;
}


/** egwActionImplementation Interface **/

/**
 * Abstract interface for the egwActionImplementation object. The egwActionImplementation
 * object is responsible for inserting the actual action representation (context menu,
 * drag-drop code) into the DOM Tree by using the egwActionObjectInterface object
 * supplied by the object.
 * To write a "class" which derives from this object, simply write a own constructor,
 * which replaces "this" with a "new egwActionImplementation" and implement your
 * code in "doRegisterAction" und "doUnregisterAction".
 * Register your own implementation within the _egwActionClasses object.
 *
 * @param object _action is the parent egwAction object for that instance.
 */
function egwActionImplementation(_action)
{
	this.action = _action;

	this.doRegisterAction = null;
	this.doUnregisterAction = null;
}

/**
 * Injects the implementation code into the DOM tree by using the supplied 
 * actionObjectInterface.
 *
 * @returns true if the Action had been successfully registered, false if it
 * 	had not.
 */
egwActionImplementation.registerAction = function(_actionObjectInterface)
{
	if (this.doRegisterAction == null)
	{
		throw "Abstract function call: registerAction";
	}
	else
	{
		return this.doRegisterAction(_action, _actionObjectInterface);
	}
}

/**
 * Unregister action will be called before an actionObjectInterface is destroyed,
 * which gives the egwActionImplementation the opportunity to remove the previously
 * injected code.
 *
 * @returns true if the Action had been successfully unregistered, false if it
 * 	had not.
 */
egwActionImplementation.unregisterAction = function(_actionObjectInterface)
{
	if (this.doUnregisterAction == null)
	{
		throw "Abstract function call: unregisterAction";
	}
	else
	{
		return this.doUnregisterAction(_action, _actionObjectInterface);
	}
}


/** egwActionLink Object **/

/**
 * The egwActionLink is used to interconnect egwActionObjects and egwActions.
 * This gives each action object the possibility to decide, whether the action
 * should be active in this context or not.
 *
 * @param _manager is a reference to the egwActionManager whic contains the action
 * 	the object wants to link to.
 */
function egwActionLink(_manager)
{
	this.enabled = true;
	this.actionId = "";
	this.actionObj = null;
	this.manager = _manager;
}

egwActionLink.prototype.updateLink = function (_data)
{
	egwActionStoreJSON(_data, this, true);
}

egwActionLink.prototype.set_enabled = function(_value)
{
	this.enabled = _value;
}

egwActionLink.prototype.set_actionId = function(_value)
{
	this.actionId = _value;
	this.actionObj = this.manager.getAction(_value);

	if (!this.actionObj)
		throw "Given action object does not exist!"
}


/** egwActionObject Object **/

/**
 * The egwActionObject represents an abstract object to which actions may be
 * applied. Communication with the DOM tree is established by using the
 * egwActionObjectInterface (AOI), which is passed in the constructor.
 * egwActionObjects are organized in a tree structure.
 *
 * @param string _id is the identifier of the object which
 * @param object _parent is the parent object in the hirachy. This may be set to NULL
 * @param object _manager is the action manager this object is connected to
 * @param object _interaction is the egwActionObjectInterface which connects
 * 	this object to the DOM tree.
 */
function egwActionObject(_id, _parent, _manager, _interaction)
{
	this.id = _id;
	this.parent = _parent;
	this.interaction = _interaction;
	this.children = [];
	this.actionLinks = [];
	this.manager = _manager;
}

/**
 * Updates the actionLinks of the given ActionObject.
 *
 * @param array _actionLinks contains the information about the actionLinks which
 * 	should be updated as an array of objects. Example
 * 	[
 * 		{
 * 			"actionId": "file_delete",
 * 			"enabled": true
 * 		}
 * 	]
 * 	If an supplied link doesn't exist yet, it will be created (if _doCreate is true)
 * 	and added to the list. Otherwise the information will just be updated.
 * @param boolean _recursive If true, the settings will be applied to all child
 * 	object (default false)
 * @param boolean _doCreate If true, not yet existing links will be created (default true)
 */
egwActionObject.prototype.updateActionLinks = function(_actionLinks, _recursive, _doCreate)
{
	if (typeof _recursive == "undefined")
		_recursive = false;
	if (typeof _doCreate == "undefined")
		_doCreate = true;

	for (var i = 0; i < _actionLinks.length; i++)
	{
		var elem = _actionLinks[i];
		if (typeof elem.actionId != "undefined" && elem.actionId)
		{
			//Get the action link object, if it doesn't exists yet, create it
			var actionLink = this.getActionLink(elem.actionId);
			if (!actionLink && _doCreate)
			{
				actionLink = new egwActionLink(this.manager);
			}

			//Set the supplied data
			if (actionLink)
			{
				actionLink.updateLink(elem);
			}
		}
	}

	if (_recursive)
	{
		for (var i = 0; i < this.children.length; i++)
		{
			this.children[i].updateActionLinks(_actionLinks, true, _doCreate);
		}
	}
}

/**
 * Returns the action link, which contains the association to the action with
 * the given actionId.
 *
 * @param string _actionId name of the action associated to the link
 */
egwActionObject.prototype.getActionLink = function(_actionId)
{
	for (var i = 0; i < this.actionLinks.length; i++)
	{
		if (this.actionLinks[i].actionObj.id == _actionId)
		{
			return this.actionLinks[i];
		}
	}

	return null;
}

/**
 * Returns all actions associated to the object tree, grouped by type.
 *
 * @param function _test gets an egwActionObject and should return, whether the
 * 	actions of this object are added to the result. Defaults to a "always true"
 * 	function.
 * @param object _groups is an internally used parameter, may be omitted.
 */
egwActionObject.prototype.getActionImplementationGroups = function(_test, _groups)
{
	// If the _groups parameter hasn't been given preset it to an empty object
	// (associative array).
	if (typeof _groups == "undefined")
		_groups = {};
	if (typeof _test == "undefined")
		_test = function(_obj) {return true};

	for (var i = 0; i < this.actionLinks.length; i++)
	{
		var action = this.actionsLink[i].actionObj;
		if (typeof action != "undefined" && _test(this))
		{
			if (typeof _groups[action.type] == "undefined")
			{
				_groups[action.type] = [];
			}

			_groups[action.type].push(
				{
					"object": this,
					"link": this.actionLinks[i]
				}
			);
		}
	}

	// Recursively add the actions of the children to the result (as _groups is)
	// a object, only the reference is passed.
	for (var i = 0; i < this.children.length; i++)
	{
		this.children[i].getActionImplementationGroups(_test, _groups);
	}

	return _groups;
}

