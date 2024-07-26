export class Validator
{
	/**
	 *
	 * @param {?} [param]
	 * @param {Object.<string,?>} [config]
	 */
	constructor(param?, config?)
	{

		/** @type {?} */
		this.__param = param;

		/** @type {Object.<string,?>} */
		this.__config = config || {};
		this.type = (config && config.type) || 'error'; // Default type supported by ValidateMixin
	}

	static get validatorName()
	{
		return '';
	}

	/**
	 * @desc The function that returns a Boolean
	 * @param {?} [modelValue]
	 * @param {?} [param]
	 * @param {{}} [config]
	 * @returns {Boolean|Promise<Boolean>}
	 */
	// eslint-disable-next-line no-unused-vars, class-methods-use-this
	execute(modelValue, param, config) : boolean | Promise<boolean>
	{
		const ctor = /** @type {typeof Validator} */ (this.constructor);
		if(!ctor.validatorName)
		{
			throw new Error(
				'A validator needs to have a name! Please set it via "static get validatorName() { return \'IsCat\'; }"',
			);
		}
		return true;
	}

	set param(p)
	{
		this.__param = p;
		if(this.dispatchEvent)
		{
			this.dispatchEvent(new Event('param-changed'));
		}
	}

	get param()
	{
		return this.__param;
	}

	set config(c)
	{
		this.__config = c;
		if(this.dispatchEvent)
		{
			this.dispatchEvent(new Event('config-changed'));
		}
	}

	get config()
	{
		return this.__config;
	}

	/**
	 * @overridable
	 * @param {MessageData} [data]
	 * @returns {Promise<string|Node>}
	 * @protected
	 */
	async _getMessage(data)
	{
		const ctor = /** @type {typeof Validator} */ (this.constructor);
		const composedData = {
			name: ctor.validatorName,
			type: this.type,
			params: this.param,
			config: this.config,
			...data,
		};
		if(this.config.getMessage)
		{
			if(typeof this.config.getMessage === 'function')
			{
				return this.config.getMessage(composedData);
			}
			throw new Error(
				`You must provide a value for getMessage of type 'function', you provided a value of type: ${typeof this
					.config.getMessage}`,
			);
		}
		return ctor.getMessage(composedData);
	}

}