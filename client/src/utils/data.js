export function generateNewId()
{
	return 'n' + (++generateNewId.n || (generateNewId.n = 0))
}

export function resolveIndex(index, size)
{
	return ((index % size) + size) % size
}

export function formatObject(object, defaults)
{
	object = (typeof object === 'object') ? object : {}
	
	const newObject = {}

	for(let key of Object.keys(defaults))
	{
		if(typeof defaults[key] === 'object')
		{
			newObject[key] = formatObject(object[key], defaults[key])
		}
		else
		{
			newObject[key] = (key in object) ? object[key] : defaults[key]
		}
	}

	return newObject
}
