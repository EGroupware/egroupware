import {Validator} from './Validator';

/**
 * @param {?} value
 */
const isString = value => typeof value === 'string';

export class IsString extends Validator {
	static get validatorName() {
		return 'IsString';
	}

	/**
	 * @param {?} value
	 */
	// eslint-disable-next-line class-methods-use-this
	execute(value) {
		let hasError = false;
		if (!isString(value)) {
			hasError = true;
		}
		return hasError;
	}
}

export class EqualsLength extends Validator {
	static get validatorName() {
		return 'EqualsLength';
	}


	execute(value, length = this.param) {
		let hasError = false;
		if (!isString(value) || value.length !== length) {
			hasError = true;
		}
		return hasError;
	}
}

export class MinLength extends Validator {
	static get validatorName() {
		return 'MinLength';
	}


	execute(value, min = this.param) {
		let hasError = false;
		if (!isString(value) || value.length < min) {
			hasError = true;
		}
		return hasError;
	}
}

export class MaxLength extends Validator {
	static get validatorName() {
		return 'MaxLength';
	}

	execute(value, max = this.param) {
		let hasError = false;
		if (!isString(value) || value.length > max) {
			hasError = true;
		}
		return hasError;
	}
}

export class MinMaxLength extends Validator {
	static get validatorName() {
		return 'MinMaxLength';
	}

	/**
	 * @param {?} value
	 */
	execute(value, { min = 0, max = 0 } = this.param) {
		let hasError = false;
		if (!isString(value) || value.length < min || value.length > max) {
			hasError = true;
		}
		return hasError;
	}
}

/**
 * @param {?} value
 * @param {RegExp} pattern
 */
const hasPattern = (value, pattern) => pattern.test(value);
export class Pattern extends Validator {
	static get validatorName() {
		return 'Pattern';
	}

	execute(value, pattern = this.param) {
		if (!(pattern instanceof RegExp)) {
			throw new Error(
				'Psst... Pattern validator expects RegExp object as parameter e.g, new Pattern(/#LionRocks/) or new Pattern(RegExp("#LionRocks")',
			);
		}
		let hasError = false;
		if (!isString(value) || !hasPattern(value, pattern)) {
			hasError = true;
		}

		return hasError;
	}
}
