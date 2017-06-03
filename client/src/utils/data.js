export function generateNewId()
{
	return 'n' + (++generateNewId.n || (generateNewId.n = 0))
}

export function resolveIndex(index, size)
{
	return ((index % size) + size) % size
}
