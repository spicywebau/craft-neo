export default {

	_stack: [ [] ],

	enter(segments, join = true)
	{
		if(typeof segments === 'string')
		{
			segments = this.fromFieldName(segments)
		}

		if(join)
		{
			const joined = this.getNamespace()
			joined.push(...segments)

			segments = joined
		}

		this._stack.push(segments)
	},

	enterByFieldName(fieldName, join = true)
	{
		this.enter(this.fromFieldName(fieldName), join)
	},

	leave()
	{
		return this._stack.length > 1 ?
			this._stack.pop() :
			this.getNamespace()
	},

	getNamespace()
	{
		return Array.from(this._stack[this._stack.length - 1])
	},

	parse(value)
	{
		if(typeof value === 'string')
		{
			if(value.indexOf('[') > -1)
			{
				return this.fromFieldName(value)
			}

			if(value.indexOf('-') > -1)
			{
				return value.split('-')
			}

			if(value.indexOf('.') > -1)
			{
				return value.split('.')
			}

			return value
		}

		return Array.from(value)
	},

	value(value, separator = '-')
	{
		const segments = this.getNamespace()
		segments.push(value)

		return segments.join(separator)
	},

	fieldName(fieldName = '')
	{
		const prefix = this.toFieldName()

		if(prefix)
		{
			return prefix + fieldName.replace(/([^'"\[\]]+)([^'"]*)/, '[$1]$2')
		}

		return fieldName
	},

	toString(separator = '-')
	{
		return this.getNamespace().join(separator)
	},

	toFieldName()
	{
		const segments = this.getNamespace()

		switch(segments.length)
		{
			case 0: return ''
			case 1: return segments[0]
		}

		return segments[0] + '[' + segments.slice(1).join('][') + ']'
	},

	fromFieldName(fieldName)
	{
		return fieldName.match(/[^\[\]\s]+/g) || []
	}
}
