/**
 * EGroupware Framework base object - TS declarations
 *
 * Framework base module which creates fw_base object and includes basic framework functionallity
 *
 * Generated via tsc --declaration (see ../egw_action/egw_action.d.ts), thought fw_ui had to be run through
 * doc/js2ts.php first, as TS does not understand old EGroupware inheritance.
 *
 * @package framework
 * @author Hadi Nategh <hn@egroupware.org>
 * @author Andreas Stoeckel <as@egroupware.org>
 * @copyright EGroupware GmbH 2014
 */
declare var fw_base: any;
declare var fw_browser: any;
/**
 * eGroupware JavaScript Framework - Non UI classes
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Andreas Stoeckel <as@egroupware.org>
 */
/**
 * application class constructor
 *
 * @param {type} _parentFw
 * @param {type} _appName
 * @param {type} _displayName
 * @param {type} _icon
 * @param {type} _indexUrl
 * @param {type} _sideboxWidth
 * @param {type} _baseUrl
 * @param {type} _internalName
 * @returns {egw_fw_class_application}
 */
declare function egw_fw_class_application(_parentFw: any, _appName: any, _displayName: any, _icon: any, _indexUrl: any, _sideboxWidth: any, _baseUrl: any, _internalName: any): egw_fw_class_application;
declare class egw_fw_class_application {
    /**
     * eGroupware JavaScript Framework - Non UI classes
     *
     * @link http://www.egroupware.org
     * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
     * @author Andreas Stoeckel <as@egroupware.org>
     */
    /**
     * application class constructor
     *
     * @param {type} _parentFw
     * @param {type} _appName
     * @param {type} _displayName
     * @param {type} _icon
     * @param {type} _indexUrl
     * @param {type} _sideboxWidth
     * @param {type} _baseUrl
     * @param {type} _internalName
     * @returns {egw_fw_class_application}
     */
    constructor(_parentFw: any, _appName: any, _displayName: any, _icon: any, _indexUrl: any, _sideboxWidth: any, _baseUrl: any, _internalName: any);
    appName: any;
    internalName: any;
    displayName: any;
    icon: any;
    indexUrl: any;
    sidebox_md5: string;
    baseUrl: any;
    website_title: string;
    app_header: string;
    sideboxWidth: any;
    parentFw: any;
    hasSideboxMenuContent: boolean;
    sidemenuEntry: any;
    tab: any;
    browser: any;
    getMenuaction(_fun: string, _ajax_exec_url: string): string;
    getBaseUrl(_force: boolean): any;
}
declare function egw_fw_getMenuaction(_fun: any): any;
declare function egw_fw_class_callback(_context: any, _proc: any): void;
declare class egw_fw_class_callback {
    constructor(_context: any, _proc: any);
    context: any;
    proc: any;
    call(...args: any[]): any;
}
/**
 * Class: egw_fw_ui_tab
 * The egw_fw_ui_tab represents a single tab "sheet" in the ui
 */
/**
 * The constructor of the egw_fw_ui_tab class.
 *
 * @param {object} _parent specifies the parent egw_fw_ui_tabs class
 * @param {object} _contHeaderDiv specifies the container "div" element, which should contain the headers
 * @param {object} _contDiv specifies the container "div" element, which should contain the contents of the tabs
 * @param {string} _icon specifies the icon which should be viewed besides the title of the tab
 * @param {function}(_sender) _callback specifies the function which should be called when the tab title is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {function}(_sender) _closeCallback specifies the function which should be called when the tab close button is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 * @param {int} _pos is the position where the tab will be inserted
 * @param {string} application status (e.g. status="5")
 */
declare function egw_fw_ui_tab(_parent: any, _contHeaderDiv: any, _contDiv: any, _icon: string, _callback: any, _closeCallback: any, _tag: any, _pos: any, _status: any): void;
declare class egw_fw_ui_tab {
    /**
     * Class: egw_fw_ui_tab
     * The egw_fw_ui_tab represents a single tab "sheet" in the ui
     */
    /**
     * The constructor of the egw_fw_ui_tab class.
     *
     * @param {object} _parent specifies the parent egw_fw_ui_tabs class
     * @param {object} _contHeaderDiv specifies the container "div" element, which should contain the headers
     * @param {object} _contDiv specifies the container "div" element, which should contain the contents of the tabs
     * @param {string} _icon specifies the icon which should be viewed besides the title of the tab
     * @param {function}(_sender) _callback specifies the function which should be called when the tab title is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
     * @param {function}(_sender) _closeCallback specifies the function which should be called when the tab close button is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
     * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
     * @param {int} _pos is the position where the tab will be inserted
     * @param {string} application status (e.g. status="5")
     */
    constructor(_parent: any, _contHeaderDiv: any, _contDiv: any, _icon: string, _callback: any, _closeCallback: any, _tag: any, _pos: any, _status: any);
    parent: any;
    contHeaderDiv: any;
    contDiv: any;
    title: string;
    tag: any;
    closeable: boolean;
    callback: any;
    closeCallback: any;
    position: any;
    status: any;
    headerDiv: HTMLSpanElement;
    closeButton: HTMLSpanElement;
    headerH1: HTMLHeadingElement;
    contentDiv: HTMLDivElement;
    setTitle(_title: string): void;
    setContent(_content: string): void;
    show(): void;
    hide(): void;
    hideTabHeader(): void;
    remove(): void;
    setCloseable(_closeable: boolean): void;
}
/**
 * Class: egw_fw_ui_tabs
 * The egw_fw_ui_tabs class cares about displaying a set of tab sheets.
 */
/**
 * The constructor of the egw_fw_ui_sidemenu_tabs class. Two "divs" are created inside the specified container element, one for the tab headers and one for the tab contents.
 *
 * @param {object} _contDiv specifies "div" element the tab ui element should be displayed in.
 */
declare function egw_fw_ui_tabs(_contDiv: any): void;
declare class egw_fw_ui_tabs {
    /**
     * Class: egw_fw_ui_tabs
     * The egw_fw_ui_tabs class cares about displaying a set of tab sheets.
     */
    /**
     * The constructor of the egw_fw_ui_sidemenu_tabs class. Two "divs" are created inside the specified container element, one for the tab headers and one for the tab contents.
     *
     * @param {object} _contDiv specifies "div" element the tab ui element should be displayed in.
     */
    constructor(_contDiv: any);
    contDiv: any;
    contHeaderDiv: HTMLDivElement;
    appHeaderContainer: JQuery;
    appHeader: JQuery;
    tabs: any[];
    activeTab: any;
    tabHistory: any[];
    setAppHeader(_text: string, _msg_class: string): void;
    cleanHistory(): void;
    addTab(_icon: string, _callback: Function, _closeCallback: Function, _tag: any, _pos: any, _status: any): egw_fw_ui_tab;
    removeTab(_tab: any): void;
    showTab(_tab: any): void;
    setCloseable(_closeable: boolean): void;
    clean(): boolean;
    tabHistroy: any[];
    _isNotTheLastTab(): boolean;
}
/**
 * Class: egw_fw_ui_category
 * A class which manages and renderes a simple menu with categories, which can be opened and shown
 *
 * @param {object} _contDiv
 * @param {string} _name
 * @param {string} _title
 * @param {object} _content
 * @param {function} _callback
 * @param {function} _animationCallback
 * @param {object} _tag
 */
declare function egw_fw_ui_category(_contDiv: any, _name: string, _title: string, _content: any, _callback: Function, _animationCallback: Function, _tag: any): void;
declare class egw_fw_ui_category {
    /**
     * Class: egw_fw_ui_category
     * A class which manages and renderes a simple menu with categories, which can be opened and shown
     *
     * @param {object} _contDiv
     * @param {string} _name
     * @param {string} _title
     * @param {object} _content
     * @param {function} _callback
     * @param {function} _animationCallback
     * @param {object} _tag
     */
    constructor(_contDiv: any, _name: string, _title: string, _content: any, _callback: Function, _animationCallback: Function, _tag: any);
    contDiv: any;
    catName: string;
    callback: Function;
    animationCallback: Function;
    tag: any;
    headerDiv: HTMLDivElement;
    contentDiv: HTMLDivElement;
    open(_instantly: any): void;
    close(_instantly: any): void;
    remove(): void;
}
/**
 * egw_fw_ui_scrollarea class
 *
 * @param {object} _contDiv
 */
declare function egw_fw_ui_scrollarea(_contDiv: any): void;
declare class egw_fw_ui_scrollarea {
    /**
     * egw_fw_ui_scrollarea class
     *
     * @param {object} _contDiv
     */
    constructor(_contDiv: any);
    startScrollSpeed: number;
    endScrollSpeed: number;
    scrollSpeedAccel: number;
    timerInterval: number;
    contDiv: any;
    contHeight: number;
    boxHeight: number;
    scrollPos: number;
    buttonScrollOffs: number;
    maxScrollPos: number;
    buttonsVisible: boolean;
    mouseOver: boolean;
    scrollTime: any;
    btnUpEnabled: boolean;
    btnDownEnabled: boolean;
    scrollDiv: HTMLDivElement;
    outerDiv: HTMLDivElement;
    contentDiv: any;
    btnUp: HTMLSpanElement;
    btnDown: HTMLSpanElement;
    setScrollPos(_pos: any): void;
    scrollDelta(_delta: any): void;
    toggleButtons(_visible: any): void;
    buttonHeight: number;
    update(): void;
    getScrollDelta(_timeGap: any): number;
    mouseOverCallback(_context: any): void;
    mouseOverToggle(_over: any, _dir: any): void;
    dir: any;
}
declare function egw_fw_ui_splitter(_contDiv: any, _orientation: any, _resizeCallback: any, _constraints: any, _tag: any): void;
declare class egw_fw_ui_splitter {
    constructor(_contDiv: any, _orientation: any, _resizeCallback: any, _constraints: any, _tag: any);
    tag: any;
    contDiv: any;
    orientation: any;
    resizeCallback: any;
    startPos: number;
    constraints: {
        "size": number;
        "minsize": number;
        "maxsize": number;
    }[];
    splitterDiv: HTMLDivElement;
    clipDelta(_delta: any): any;
    dragStartHandler(event: any, ui: any): void;
    dragHandler(event: any, ui: any): void;
    dragStopHandler(event: any, ui: any): void;
    set_disable(_state: any): void;
}
/**
 * Constructor for toggleSidebar UI object
 *
 * @param {type} _contentDiv sidemenu div
 * @param {function} _toggleCallback callback function to set toggle prefernces and resize handling
 * @param {object} _callbackContext context of the toggleCallback
 * @returns {egw_fw_ui_toggleSidebar}
 */
declare function egw_fw_ui_toggleSidebar(_contentDiv: any, _toggleCallback: Function, _callbackContext: any): egw_fw_ui_toggleSidebar;
declare class egw_fw_ui_toggleSidebar {
    /**
     * Constructor for toggleSidebar UI object
     *
     * @param {type} _contentDiv sidemenu div
     * @param {function} _toggleCallback callback function to set toggle prefernces and resize handling
     * @param {object} _callbackContext context of the toggleCallback
     * @returns {egw_fw_ui_toggleSidebar}
     */
    constructor(_contentDiv: any, _toggleCallback: Function, _callbackContext: any);
    toggleCallback: Function;
    toggleDiv: JQuery;
    toggleAudio: JQuery;
    contDiv: JQuery;
    onToggle(_callbackContext: any): void;
    set_toggle(_state: string, _toggleCallback: any, _context: any): void;
}
/**
 * eGroupware Framework ui object
 * @package framework
 * @author Hadi Nategh <hn@stylite.de>
 * @author Andreas Stoeckel <as@stylite.de>
 * @copyright Stylite AG 2014
 * @description Framework ui object, is implementation of UI class
 */
/**
 * ui siemenu entry class
 * Basic sidebar menu implementation
 *
 * @type @exp;Class@call;extend
 */
declare class fw_ui_sidemenu_entry {
    /**
     * Framework ui sidemenu entry class constructor
     *
     * @param {object} _parent specifies the parent egw_fw_ui_sidemenu
     * @param {object} _baseDiv specifies "div" element the entries should be appended to.
     * @param {object} _elemDiv
     * @param {string} _name specifies the title of the entry in the side menu
     * @param {string} _icon specifies the icon which should be viewd besides the title in the side menu
     * @param {function}(_sender) _callback specifies the function which should be called when the entry is clicked. The _sender parameter passed is a reference to this egw_fw_ui_sidemenu_entry element.
     * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
     * @param {string} _app application name
     */
    constructor(_parent: any, _baseDiv: any, _elemDiv: any, _name: any, _icon: any, _callback: any, _tag: any, _app: any);
    /**
     * setContent replaces the content of the sidemenu entry with the content given by _content.
     * @param {string} _content HTML/Text which should be displayed.
     */
    setContent(_content: any): void;
    /**
     * open openes this sidemenu_entry and displays the content.
     */
    open(): void;
    /**
     * close closes this sidemenu_entry and hides the content.
     */
    close(): void;
    /**
     * egw_fw_ui_sidemenu_entry_header_active
     * showAjaxLoader shows the AjaxLoader animation which should be displayed when
     * the content of the sidemenu entry is just being loaded.
     */
    showAjaxLoader(): void;
    /**
     * showAjaxLoader hides the AjaxLoader animation
     */
    hideAjaxLoader(): void;
    /**
     * Removes this entry.
     */
    remove(): void;
}
/**
 *
 * @type @exp;Class@call;extend
 */
declare class fw_ui_sidemenu {
    /**
     * The constructor of the egw_fw_ui_sidemenu.
     *
     * @param {object} _baseDiv specifies the "div" in which all entries added by the addEntry function should be displayed.
     */
    constuctor(_baseDiv);
}
/**
 * Class: egw_fw_ui_tab
 * The egw_fw_ui_tab represents a single tab "sheet" in the ui
 */
/**
 * The constructor of the egw_fw_ui_tab class.
 *
 * @param {object} _parent specifies the parent egw_fw_ui_tabs class
 * @param {object} _contHeaderDiv specifies the container "div" element, which should contain the headers
 * @param {object} _contDiv specifies the container "div" element, which should contain the contents of the tabs
 * @param {string} _icon specifies the icon which should be viewed besides the title of the tab
 * @param {function}(_sender) _callback specifies the function which should be called when the tab title is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {function}(_sender) _closeCallback specifies the function which should be called when the tab close button is clicked. The _sender parameter passed is a reference to this egw_fw_ui_tab element.
 * @param {object} _tag can be used to attach any user data to the object. Inside egw_fw _tag is used to attach an egw_fw_class_application to each sidemenu entry.
 * @param {int} _pos is the position where the tab will be inserted
 * @param {string} application status (e.g. status="5")
 */
declare function egw_fw_ui_tab(_parent: any, _contHeaderDiv: any, _contDiv: any, _icon: any, _callback: any, _closeCallback: any, _tag: any, _pos: any, _status: any): void;
/**
 * Class: egw_fw_ui_tabs
 * The egw_fw_ui_tabs class cares about displaying a set of tab sheets.
 */
/**
 * The constructor of the egw_fw_ui_sidemenu_tabs class. Two "divs" are created inside the specified container element, one for the tab headers and one for the tab contents.
 *
 * @param {object} _contDiv specifies "div" element the tab ui element should be displayed in.
 */
declare function egw_fw_ui_tabs(_contDiv: any): void;
/**
 * Class: egw_fw_ui_category
 * A class which manages and renderes a simple menu with categories, which can be opened and shown
 *
 * @param {object} _contDiv
 * @param {string} _name
 * @param {string} _title
 * @param {object} _content
 * @param {function} _callback
 * @param {function} _animationCallback
 * @param {object} _tag
 */
declare function egw_fw_ui_category(_contDiv: any, _name: any, _title: any, _content: any, _callback: any, _animationCallback: any, _tag: any): void;
/**
 * egw_fw_ui_scrollarea class
 *
 * @param {object} _contDiv
 */
declare function egw_fw_ui_scrollarea(_contDiv: any): void;
/**
 * egw_fw_ui_splitter class
 */
declare var EGW_SPLITTER_HORIZONTAL: number;
declare var EGW_SPLITTER_VERTICAL: number;
declare function egw_fw_ui_splitter(_contDiv: any, _orientation: any, _resizeCallback: any, _constraints: any, _tag: any): void;
/**
 * Constructor for toggleSidebar UI object
 *
 * @param {type} _contentDiv sidemenu div
 * @param {function} _toggleCallback callback function to set toggle prefernces and resize handling
 * @param {object} _callbackContext context of the toggleCallback
 * @returns {egw_fw_ui_toggleSidebar}
 */
declare function egw_fw_ui_toggleSidebar(_contentDiv: any, _toggleCallback: any, _callbackContext: any): void;
