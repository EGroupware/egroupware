/**
 * tsconfig.json's moduleResolution is "node" (classic), which doesn't understand package.json
 * "exports" maps at all - openpgp's own types are only exposed via its "./lightweight" export
 * entry, so `import('openpgp/lightweight')` fails to resolve for TYPE-checking purposes even
 * though Rollup's own (exports-map-aware) resolver bundles it at build time without issue. `any`
 * is an acceptable loss of type safety here: this module is only ever consulted through
 * MailJmap.loadOpenpgp()'s own single call site, itself already typed loosely for the same reason.
 */
declare module "openpgp/lightweight"
{
	const openpgp : any;
	export = openpgp;
}
