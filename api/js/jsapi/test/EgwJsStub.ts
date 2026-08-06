/**
 * Stub for egw.js / egw_json.js, used via an import map in EgwDataHarness.
 *
 * egw_data.js has side-effect-only imports of both files, just to guarantee
 * `window.egw` (and egw.registerJSONPlugin) exist before it calls
 * egw.extend(...). The test harness already guarantees that itself (via
 * egw_core.js plus a synthetic stand-in for the 'json' module), so the real
 * egw.js/egw_json.js - which pull in jQuery, DOM script-tag attributes and
 * websocket/popup handling that has nothing to do with data storage - are
 * redirected to this empty module instead.
 */
export {};
