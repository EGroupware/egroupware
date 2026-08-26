/**
 * Importing a stylesheet yields its text, so it can be handed to lit's unsafeCSS().
 *
 * The build side of this: rollup.config.js has a `load` hook for .css, and
 * web-test-runner.config.mjs passes esbuild a "text" loader for .css.
 */
declare module "*.css"
{
	const css : string;
	export default css;
}
