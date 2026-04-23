/**
 * EGroupware clientside egw tail
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage jsapi
 * @link https://www.egroupware.org
 * @author Hadi Nategh (as AT stylite.de)
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 */

import './egw_json.js';

document.addEventListener('DOMContentLoaded', function() {
	"use strict";

	let log_tail_start = 0;
	const filenameEl = document.querySelector('pre[id^="log"]');

	let filename = null;
	if (filenameEl) {
		filename = filenameEl.getAttribute('data-filename');
	}

	function button_log(buttonId) {
		if (buttonId !== "clear_log") {
			egw.json("api.EGroupware\\Api\\Json\\Tail.ajax_delete", [filename, buttonId === "empty_log"])
				.sendRequest(true);
		}
		const log = document.querySelector("#log");
		if (log) {
			log.textContent = "";
		}
	}

	function refresh_log() {
		egw.json("api.EGroupware\\Api\\Json\\Tail.ajax_chunk", [filename, log_tail_start], function(_data) {
			if (_data.length) {
				log_tail_start = _data.next;
				const log = document.querySelector("#log");
				if (log) {
					log.textContent = log.textContent + _data.content.replace(/</g, "&lt;");
					log.scrollTop = log.scrollHeight;
				}
			}

			if (_data.size === false) {
				const downloadLog = document.querySelector("#download_log");
				if (downloadLog) downloadLog.style.display = "none";
			} else {
				const downloadLog = document.querySelector("#download_log");
				if (downloadLog) {
					downloadLog.style.display = "block";
					downloadLog.setAttribute("title", egw(window).lang('Size') + _data.size);
				}
			}

			if (_data.writable === false) {
				const purgeLog = document.querySelector("#purge_log");
				const emptyLog = document.querySelector("#empty_log");
				if (purgeLog) purgeLog.style.display = "none";
				if (emptyLog) emptyLog.style.display = "none";
			} else {
				const purgeLog = document.querySelector("#purge_log");
				const emptyLog = document.querySelector("#empty_log");
				if (purgeLog) purgeLog.style.display = "block";
				if (emptyLog) emptyLog.style.display = "block";
			}

			window.setTimeout(refresh_log, _data.length ? 200 : 2000);
		}).sendRequest(true);
	}

	function resize_log() {
		const log = document.querySelector("#log");
		if (log) {
			log.style.width = (egw_getWindowInnerWidth() - egw_getWindowInnerWidth() * 0.02) + "px";
			log.style.height = (egw_getWindowInnerHeight() * 0.92) + "px";
		}
	}

	const clearLogBtn = document.querySelector('et2-button[id^="clear_log"]');
	const purgeLogBtn = document.querySelector('et2-button[id^="purge_log"]');
	const emptyLogBtn = document.querySelector('et2-button[id^="empty_log"]');
	const downloadLogBtn = document.querySelector('et2-button[id^="download_log"]');

	if (clearLogBtn) clearLogBtn.onclick = function () { button_log(this.id); };
	if (purgeLogBtn) purgeLogBtn.onclick = function () { button_log(this.id); };
	if (emptyLogBtn) emptyLogBtn.onclick = function () { button_log(this.id); };
	if (downloadLogBtn) {
		downloadLogBtn.onclick = function () {
			egw(window).open_link('/index.php?menuaction=api.EGroupware\\Api\\Json\\Tail.download&filename=' + encodeURIComponent(filename));
		};
	}

	resize_log();
	window.addEventListener('resize', resize_log);

	if (filename !== null && filename.length > 0) {
		refresh_log();
	}
});
