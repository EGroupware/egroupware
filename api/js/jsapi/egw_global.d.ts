/**
 * for now use "somehow" created global egw
 */
declare function egw_getFramework() : any;
//declare var window : Window & typeof globalThis;
declare var chrome : any;
declare var InstallTrigger : any;
//declare function egw(string, object) : object;
declare var egw : any;
declare var app : {classes: any};
declare var egw_globalObjectManager : any;
declare var framework : any;

declare var mailvelope : any;

declare function egw_refresh(_msg : string, app : string, id? : string|number, _type?, targetapp?, replace?, _with?, msgtype?);