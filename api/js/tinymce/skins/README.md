# TinyMCE skins
These are custom egroupware skins for tinyMce
## creation
The skins were created following the tutorial from [tinyMceWebsite](https://www.tiny.cloud/docs/tinymce/latest/creating-a-skin/)
The additional sources to generate the skin are in [light](./src/egw) and [dark](./src/egw-dark)
## setup to change the skin
- clone the tinyMCE repository `git clone git@github.com:tinymce/tinymce.git`
- create symlinks to [light](./src/egw) and [dark](./src/egw-dark) in the `/modules/oxide/src/less/skins/ui` folder
- use `bun run build` to generate the skin or 
- use `bun run start` to start the local tinyMce server to see the changes you are making to the skin
- move `/modules/oxide/build/skins/ui/egw(-dark)` to [light](./ui/egw) or [dark](./ui/egw-dark)