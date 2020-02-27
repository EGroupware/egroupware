declare module eT2
{

}
declare var etemplate2 : any;
declare class et2_widget{
	destroy()
	getWidgetById(string) : et2_widget;
}
declare class et2_DOMWidget extends et2_widget{}
declare class et2_baseWidget extends et2_DOMWidget{}
declare class et2_valueWidget extends et2_baseWidget{}
declare class et2_inputWidget extends et2_valueWidget{
	getInputNode() : HTMLElement;
	public set_value(value: string | object | number);
	public getValue() : any;
}
declare class et2_tabbox extends et2_valueWidget {
	tabData : any;
	activateTab(et2_widget);
}
declare class et2_button extends et2_DOMWidget {
	click() : boolean;
	onclick: Function;
	set_disabled(b: boolean) : void;
}
declare var et2_surroundingsMgr : any;
declare var et2_arrayMgr : any;
declare var et2_readonlysArrayMgr : any;
declare var et2_container : any;
declare var et2_placeholder : any;
declare var et2_validTypes : string[];
declare var et2_typeDefaults : object;
//declare const et2_no_init : object;
declare class et2_editableWidget extends et2_inputWidget {
	public set_readonly(value : boolean);
}
/*declare var et2_IDOMNode : any;
declare var et2_IInput : any;
declare var et2_IResizeable : any;
declare var et2_IAligned : any;
declare var et2_ISubmitListener : any;
declare var et2_IDetachedDOM : any;
declare var et2_IPrint : any;*/
declare var et2_registry : {};
declare var et2_dataview : any;
declare var et2_dataview_controller : any;
declare var et2_dataview_selectionManager : any;
declare var et2_dataview_IInvalidatable : any;
declare var et2_dataview_IViewRange : any;
declare var et2_IDataProvider : any;
declare var et2_dataview_column : any;
declare var et2_dataview_columns : any;
declare var et2_dataview_container : any;
declare var et2_dataview_grid : any;
declare var et2_dataview_row : any;
declare var et2_dataview_rowProvider : any;
declare var et2_dataview_spacer : any;
declare var et2_dataview_tile : any;
declare class et2_customfields_list extends et2_valueWidget {
	constructor(_parent: any, _attrs: WidgetConfig, object: object);

	public static readonly prefix : string;
	public customfields : any;
	set_visible(visible : boolean);
}
declare class et2_nextmatch extends et2_DOMWidget {

}
declare var et2_nextmatch_header_bar : any;
declare var et2_nextmatch_header : any;
declare var et2_nextmatch_customfields : any;
declare var et2_nextmatch_controller : any;
declare class et2_dynheight {
	constructor(_outerNode, _innerNode, _minHeight);
	outerNode : any;
	update : any;
	free : any;
}
declare class et2_nextmatch_rowProvider {}
declare var et2_nextmatch_rowWidget : any;
declare var et2_nextmatch_rowTemplateWidget : any;
declare var et2_ajaxSelect : any;
declare var et2_ajaxSelect_ro : any;
declare var et2_barcode : any;
declare var et2_box : any;
declare var et2_details : any;
declare var et2_checkbox : any;
declare var et2_checkbox_ro : any;
declare var et2_color : any;
declare var et2_color_ro : any;
declare var et2_date : any;
declare var et2_date_duration : any;
declare var et2_date_duration_ro : any;
declare var et2_date_ro : any;
declare var et2_date_range : any;
declare var et2_description : any;
declare var et2_dialog : any;
declare var et2_diff : any;
declare var et2_dropdown_button : any;
declare var et2_entry : any;
declare var et2_favorites : any;
declare class et2_file extends et2_widget {}
declare var et2_grid : any;
declare var et2_groupbox : any;
declare var et2_groupbox_legend : any;
declare var et2_hbox : any;
declare var et2_historylog : any;
declare var et2_hrule : any;
declare var et2_html : any;
declare var et2_htmlarea : any;
declare class et2_iframe extends et2_valueWidget {
	public set_src(string);
}
declare var et2_image : any;
declare var et2_appicon : any;
declare var et2_avatar : any;
declare var et2_avatar_ro : any;
declare var et2_lavatar : any;
declare var et2_itempicker : any;
declare var et2_link_to : any;
declare var et2_link_apps : any;
declare var et2_link_entry : any;
declare var et2_link_string : any;
declare var et2_link_list : any;
declare var et2_link_add : any;
declare var et2_number : any;
declare var et2_number_ro : any;
declare var et2_portlet : any;
declare var et2_progress : any;
declare var et2_radiobox : any;
declare var et2_radiobox_ro : any;
declare var et2_radioGroup : any;
declare var et2_script : any;
declare var et2_selectAccount : any;
declare var et2_selectAccount_ro : any;
declare class et2_selectbox extends et2_inputWidget {
	protected options : any;
	public createInputWidget();
	public set_multiple(boolean);
	public set_select_options(options: any);
}
declare var et2_selectbox_ro : any;
declare var et2_menulist : any;
declare var et2_split : any;
declare var et2_styles : any;
declare class et2_taglist extends et2_selectbox {
	protected div : JQuery;
}
declare var et2_taglist_account : any;
declare var et2_taglist_email : any;
declare var et2_taglist_category : any;
declare var et2_taglist_thumbnail : any;
declare var et2_taglist_state : any;
declare var et2_taglist_ro : any;
declare var et2_template : any;
declare var et2_textbox : any;
declare var et2_textbox_ro : any;
declare var et2_searchbox : any;
declare var et2_timestamper : any;
declare class et2_toolbar extends et2_DOMWidget {}
declare var et2_tree : any;
declare var et2_url : any;
declare var et2_url_ro : any;
declare var et2_vfs : any;
declare var et2_vfsName : any;
declare var et2_vfsPath : any;
declare var et2_vfsName_ro : any;
declare var et2_vfsMime : any;
declare var et2_vfsSize : any;
declare var et2_vfsMode : any;
declare var et2_vfsUid : any;
declare var et2_vfsUpload : any;
declare var et2_vfsSelect : any;
declare var et2_video : any;
declare var tinymce : any;
declare var date : any;
declare var tinyMCE : any;
declare class et2_nextmatch_sortheader extends et2_nextmatch_header {}
declare class et2_nextmatch_filterheader extends et2_nextmatch_header {}
declare class et2_nextmatch_accountfilterheader extends et2_nextmatch_header {}
declare class et2_nextmatch_taglistheader  extends et2_nextmatch_header {}
declare class et2_nextmatch_entryheader  extends et2_nextmatch_header {}
declare class et2_nextmatch_customfilter extends et2_nextmatch_filterheader {}
declare function et2_createWidget(type : string, params? : {}, parent? : any) : any;
declare function nm_action(_action : {}, _senders : [], _target? : any, _ids? : any) : void;
declare function et2_compileLegacyJS(_code : string, _widget : et2_widget, _context? : HTMLElement) : Function;
// et2_core_xml.js
declare function et2_loadXMLFromURL(_url : string, _callback : Function, _context? : object, _fail_callback? : Function) : void;
declare function et2_directChildrenByTagName(_node, _tagName);
declare function et2_filteredNodeIterator(_node, _callback, _context);
declare function et2_readAttrWithDefault(_node, _name, _default?);
declare function sprintf(format : string, ...args : any) : string;
declare function fetchAll(ids, nextmatch, callback : Function) : boolean;
declare function doLongTask(idsArr : string[], all : boolean, _action : any, nextmatch : any) : boolean;
declare function nm_compare_field(_action, _senders, _target) : boolean;
declare function nm_open_popup(_action, _selected) : void;
declare function nm_submit_popup(button) : void;
declare function nm_hide_popup(element, div_id) : false;
declare function nm_activate_link(_action, _senders) : void;
declare function egw_seperateJavaScript(_html) : void;
declare class Resumable {
	constructor(asyncOptions: any);
}
declare class dhtmlXTreeObject {
	constructor(options : any);
}
declare function expose(widget:any) : any;