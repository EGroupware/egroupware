import fs from 'fs';
import {playwrightLauncher} from '@web/test-runner-playwright';
import {esbuildPlugin} from '@web/dev-server-esbuild';

// Get tests for web components (in their own directory)
const webComponents = fs.readdirSync('api/js/etemplate')
	.filter(
		dir => fs.statSync(`api/js/etemplate/${dir}`).isDirectory() && fs.existsSync(`api/js/etemplate/${dir}/test`),
	)
	.map(dir => `api/js/etemplate/${dir}/test`);

// Add any test files in app/js/test/
const appJS = fs.readdirSync('.')
	.filter(
		dir => fs.existsSync(`${dir}/js`) && fs.existsSync(`${dir}/js/test`) && fs.statSync(`${dir}/js/test`).isDirectory(),
	)


export default {
	nodeResolve: true,
	coverageConfig: {
		report: true,
		reportDir: 'coverage',
		threshold: {
			statements: 90,
			branches: 65,
			functions: 80,
			lines: 90,
		},
	},
	testFramework: {
		config: {
			timeout: '3000',
		},
	},
	browsers: [
		playwrightLauncher({product: 'firefox', concurrency: 1}),
		playwrightLauncher({product: 'chromium'}),
		// Dependant on specific versions of shared libraries (libicuuc.so.66, latest is .67)
		//playwrightLauncher({ product: 'webkit' }),
	],
	groups:
		webComponents.map(pkg =>
		{
			return {
				name: `${pkg}`,
				files: `${pkg}/*.test.ts`
			};
		}).concat(
			appJS.map(app =>
				{
					return {
						name: app,
						files: `${app}/js/**/*.test.ts`
					}
				}
			))
	,

	plugins: [
		// Handles typescript
		esbuildPlugin({ts: true})
	],
};
