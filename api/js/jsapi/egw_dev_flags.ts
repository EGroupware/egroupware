/**
 * Temporary development-only scaffolding for the jsapi TypeScript migration.
 *
 * While a single egw_*.ts module is mid-port, its file can import both the
 * new factory and a throwaway `egw_x.legacy.js` copy of its pre-port content,
 * and use useLegacyJsapiModule() to pick which one registers via
 * egw.extend() - flip with `?jsapi_legacy=<module>` (or "all") in the URL, or
 * `localStorage.setItem('egw:jsapi:legacy', '<module>')`, then reload. No
 * rebuild needed, since both implementations are already compiled into the
 * one bundle.
 *
 * This is per-module, temporary scaffolding, not a permanent feature-flag
 * system - once a module's new implementation is trusted, delete its
 * if/else and its .legacy.js copy. Once every module has migrated, delete
 * this file too.
 */
export function useLegacyJsapiModule(name : string) : boolean
{
	const flag = new URLSearchParams(location.search).get('jsapi_legacy')
		?? localStorage.getItem('egw:jsapi:legacy') ?? '';
	return flag === 'all' || flag.split(',').includes(name);
}
