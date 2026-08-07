/**
 * Circular dependancy resolution file
 * Here we force the order of includes
 */

import "../../../vendor/bower-asset/jquery/dist/jquery.min.js";
import "../jquery/jquery.noconflict.js";

import "./egw.js";
import "./egw_core";
import "./egw_debug";
import "./egw_preferences";
import "./egw_lang";
import "./egw_links";
import "./egw_open";
import "./egw_user";
import "./egw_config";
import "./egw_images";
import "./egw_jsonq";
import "./egw_files";
import "./egw_json";
import "./egw_store";
import "./egw_tooltip.js";
import "./egw_css";
import "./egw_calendar";
import "./egw_ready";
import "./egw_data";
import "./egw_tail.js";
import "./egw_inheritance.js";
import "./egw_message";
import "./egw_notification";
import "./egw_timer.js";
import "./jsapi.js";