## Notes on automatic documentation

This is a project in itself. Here's how the pieces fit together:

+ `build:dev` package script calls `/doc/scripts/build.mjs` which is responsible for calling the individual pieces. We
  pass files to the subprocesses, but options are set in a separate config file.
+ `/doc/scripts/metadata.mjs` extracts the component information
  using [CEM](https://custom-elements-manifest.open-wc.org/), and stores it to `/doc/dist/custom-elements.json`
+ `/doc/scripts/etemplate2/eleventy.config.cjs` uses [11ty](11ty.dev) to build a documentation site, from the
  subdirectories in `/doc/etemplate2`, and stores it to `/doc/dist/site`

If a component doesn't show up, it's probably not in the manifest.