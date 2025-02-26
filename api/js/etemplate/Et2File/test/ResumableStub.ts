class ResumableStub
{
	private options : {};
	public files : any[];
	private events : {};

	constructor(options = {})
	{
		this.options = options;
		this.files = [];
		this.events = {};
		this.upload = this.upload.bind(this);
	}

	assignBrowse()
	{
		// Simulates binding a file input
	}

	assignDrop()
	{
		// Simulates binding a drop target
	}

	addFile(file)
	{
		const resumableFile = {file: file, uniqueIdentifier: file.name, progress: () => 0};
		resumableFile.file.uniqueIdentifier = file.name;
		this.files.push(resumableFile);
		if(this.events['fileAdded'])
		{
			this.events['fileAdded'](resumableFile);
		}
	}

	removeFile(file)
	{
		this.files = this.files.filter(f => f.uniqueIdentifier !== file.uniqueIdentifier);
	}

	on(event, callback)
	{
		this.events[event] = callback;
	}

	upload()
	{
		this.files.forEach(file =>
		{
			if(this.events['fileProgress'])
			{
				file.progress = () => 0.5;
				this.events['fileProgress'](file);
			}
			setTimeout(() =>
			{
				if(this.events['fileSuccess'])
				{
					this.events['fileSuccess'](file, JSON.stringify({
						response: [{
							type: 'data',
							data: {tempName: file.uniqueIdentifier}
						}]
					}));
				}
			}, 100);
		});

		setTimeout(() =>
		{
			if(this.events['complete'])
			{
				this.events['complete']();
			}
		}, 200);
	}

	cancel()
	{
		this.files = [];
	}
}

export default ResumableStub;
export {ResumableStub, ResumableStub as Resumable};