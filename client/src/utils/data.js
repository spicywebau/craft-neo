/**
 * Generates a new ID string.
 * Only guaranteed to be unique among calls of this function.
 * 
 * @return {String} The ID
 */
export function generateNewId()
{
	return 'n' + (++generateNewId.n || (generateNewId.n = 0))
}

/**
 * Resolves the true index value to some array or list.
 * Allows negative indices which are wrapped around to the end of the list.
 * Allows indices greater than the size of the list, which are wrapped around to the start of the list.
 * 
 * @param {Number} index
 * @param {Number} size
 * @return {Number} The resolved index
 */
export function resolveIndex(index, size)
{
	return ((index % size) + size) % size
}
