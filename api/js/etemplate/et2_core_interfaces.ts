/**
 * EGroupware eTemplate2 - File which contains all interfaces
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Andreas Stöckel
 */

/**
 * Checks if an object / et2_widget implements given methods
 *
 * @param obj
 * @param methods
 */
function implements_methods(obj : et2_widget, methods : string[]) : boolean
{
	for(let i=0; i < methods.length; ++i)
	{
		if (typeof obj[methods[i]] !== 'function')
		{
			return false;
		}
	}
	return true;
}

/**
 * Interface for all widget classes, which are based on a DOM node.
 */
interface et2_IDOMNode
{
	/**
	 * Returns the DOM-Node of the current widget. The return value has to be
	 * a plain DOM node. If you want to return an jQuery object as you receive
	 * it with
	 *
	 * 	obj = jQuery(node);
	 *
	 * simply return obj[0];
	 *
	 * @param _sender The _sender parameter defines which widget is asking for
	 * 	the DOMNode. Depending on that, the widget may return different nodes.
	 * 	This is used in the grid. Normally the _sender parameter can be omitted
	 * 	in most implementations of the getDOMNode function.
	 * 	However, you should always define the _sender parameter when calling
	 * 	getDOMNode!
	 */
	getDOMNode(_sender? : et2_widget) : HTMLElement
}
var et2_IDOMNode = "et2_IDOMNode";
function implements_et2_IDOMNode(obj : et2_widget)
{
	return implements_methods(obj, ["getDOMNode"]);
}

/**
 * Interface for all widgets which support returning a value
 */
interface et2_IInput
{
	/**
	 * getValue has to return the value of the input widget
	 */
	getValue() : any

	/**
	 * Is dirty returns true if the value of the widget has changed since it
	 * was loaded.
	 */
	isDirty() : boolean

	/**
	 * Causes the dirty flag to be reseted.
	 */
	resetDirty() : void

	/**
	 * Checks the data to see if it is valid, as far as the client side can tell.
	 * Return true if it's not possible to tell on the client side, because the server
	 * will have the chance to validate also.
	 *
	 * The messages array is to be populated with everything wrong with the data,
	 * so don't stop checking after the first problem unless it really makes sense
	 * to ignore other problems.
	 *
	 * @param {String[]} messages List of messages explaining the failure(s).
	 *	messages should be fairly short, and already translated.
	 *
	 * @return {boolean} True if the value is valid (enough), false to fail
	 */
	isValid(messages) : boolean
}
var et2_IInput = "et2_IInput";
function implements_et2_IInput(obj : et2_widget)
{
	return implements_methods(obj, ["getValue", "isDirty", "resetDirty", "isValid"]);
}

/**
 * Interface for widgets which should be automatically resized
 */
interface et2_IResizeable
{
	/**
	 * Called whenever the window is resized
	 */
	resize(number) : void
}
var et2_IResizeable = "et2_IResizeable";
function implements_et2_IResizeable(obj : et2_widget)
{
	return implements_methods(obj, ["resize"]);
}

/**
 * Interface for widgets which have the align attribute
 */
interface et2_IAligned
{
	get_align() : string
}
var et2_IAligned = "et2_IAligned";
function implements_et2_IAligned(obj : et2_widget)
{
	return implements_methods(obj, ["get_align"]);
}

/**
 * Interface for widgets which want to e.g. perform clientside validation before
 * the form is submitted.
 */
interface et2_ISubmitListener
{
	/**
	 * Called whenever the template gets submitted. Return false if you want to
	 * stop submission.
	 *
	 * @param _values contains the values which will be sent to the server.
	 * 	Listeners may change these values before they get submitted.
	 */
	submit(_values) : void
}
var et2_ISubmitListener = "et2_ISubmitListener";
function implements_et2_ISubmitListener(obj : et2_widget)
{
	return implements_methods(obj, ["submit"]);
}

/**
 * Interface all widgets must support which can operate on given DOM-Nodes. This
 * is used in grid lists, when only a single row gets really stored in the widget
 * tree and this instance has to work on multiple copies of the DOM-Tree elements.
 */
interface et2_IDetachedDOM
{
	/**
	 * Creates a list of attributes which can be set when working in the
	 * "detached" mode. The result is stored in the _attrs array which is provided
	 * by the calling code.
	 *
	 * @param {array} _attrs
	 */
	getDetachedAttributes(_attrs : string[]) : void

	/**
	 * Returns an array of DOM nodes. The (relatively) same DOM-Nodes have to be
	 * passed to the "setDetachedAttributes" function in the same order.
	 */
	getDetachedNodes() : HTMLElement[]

	/**
	 * Sets the given associative attribute->value array and applies the
	 * attributes to the given DOM-Node.
	 *
	 * @param _nodes is an array of nodes which have to be in the same order as
	 * 	the nodes returned by "getDetachedNodes"
	 * @param _values is an associative array which contains a subset of attributes
	 * 	returned by the "getDetachedAttributes" function and sets them to the
	 * 	given values.
	 */
	setDetachedAttributes(_nodes : HTMLElement[], _values : object, _data?) : void
}
var et2_IDetachedDOM = "et2_IDetachedDOM";
function implements_et2_IDetachedDOM(obj : et2_widget)
{
	return implements_methods(obj, ["getDetachedAttributes", "getDetachedNodes", "setDetachedAttributes"]);
}

/**
 * Interface for widgets that need to do something special before printing
 */
interface et2_IPrint
{
	/**
	 * Set up for printing
	 *
	 * @return {undefined|Deferred} Return a jQuery Deferred object if not done setting up
	 *  (waiting for data)
	 */
	beforePrint() : JQueryPromise<any> | void

	/**
	 * Reset after printing
	 */
	afterPrint() : void
}
var et2_IPrint = "et2_IPrint";
function implements_et2_IPrint(obj : et2_widget)
{
	return implements_methods(obj, ["beforePrint", "afterPrint"]);
}

/**
 * Interface all exposed widget must support in order to getMedia for the blueimp Gallery.
 */
interface et2_IExposable
{
	/**
	 * get media an array of media objects to pass to blueimp Gallery
	 * @param {array} _attrs
	 */
	getMedia(_attrs) : void;
}
var et2_IExposable = "et2_IExposable";
function implements_et2_IExposable(obj : et2_widget)
{
	return implements_methods(obj, ["getMedia"]);
}