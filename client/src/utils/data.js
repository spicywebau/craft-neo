export function generateNewId()
{
	return 'n' + (++generateNewId.n || (generateNewId.n = 0))
}

export function copyObjectKeys(keys=[], from)
{
	return keys.reduce((to, k) => Object.assign(to, { [k]: from[k] }), {})
}

export function resolveIndex(index, size)
{
	return ((index % size) + size) % size
}
