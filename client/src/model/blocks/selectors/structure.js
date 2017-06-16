/**
 * @param {Array} structure
 * @param {Function} middleware
 * @return {Array}
 */
export function getTree(structure, middleware=(item)=>item)
{
	const tree = []
	const parentStack = []

	for(let i = 0; i < structure.length; i++)
	{
		const item = structure[i]

		// Use a separate stacking item so middleware can't affect the algorithm
		const stackItem = { level: item.level, children: [] }
		const treeItem = middleware({ id: item.id, level: item.level, index: i, children: stackItem.children })
		let peekItem

		// Remove parent items until either empty, or a parent item is only one level below this one - consequently,
		// it'll be the parent of the item
		while((peekItem = parentStack[parentStack.length - 1]) && item.level <= peekItem.level)
		{
			parentStack.pop()
		}

		// If there are no items in the stack, it must be a root level item, otherwise, the utem at the top of the stack
		// will be the parent
		peekItem = parentStack[parentStack.length - 1]
		const branch = peekItem ? peekItem.children : tree
		branch.push(treeItem)

		// The current block may potentially be a parent block as well, so save it to the stack
		parentStack.push(stackItem)
	}

	return tree
}

/**
 * @param {Array} structure
 * @param {String} parentId
 * @param {Number} depth
 * @return {Array}
 */
export function getDescendants(structure, parentId=null, depth=null)
{
	const descendants = []
	let foundParent = !parentId
	let parentLevel = 0

	for(let i = 0; i < structure.length; i++)
	{
		const item = structure[i]

		if(foundParent)
		{
			if(item.level > parentLevel)
			{
				if(!depth || item.level - parentLevel <= depth)
				{
					const descendantItem = { id: item.id, index: i, level: item.level }
					descendants.push(descendantItem)
				}
			}
			else
			{
				break
			}
		}
		else if(parentId === item.id)
		{
			foundParent = true
			parentLevel = item.level
		}
	}

	return descendants
}

/**
 * @param {Array} structure
 * @param {String} itemId
 * @param {Number} height
 * @return {Array}
 */
export function getAncestors(structure, itemId, height=null)
{
	const ancestors = []
	let foundItem = false
	let itemLevel
	let levelCount = 0

	for(let i = structure.length - 1; i >= 0; i--)
	{
		const item = structure[i]

		if(height && levelCount >= height)
		{
			break
		}

		if(foundItem)
		{
			if(item.level === itemLevel - levelCount - 1)
			{
				const ancestorItem = { id: item.id, index: i, level: item.level }
				ancestors.push(ancestorItem)

				levelCount++
			}
		}
		else if(itemId === item.id)
		{
			foundItem = true
			itemLevel = item.level
		}
	}

	return ancestors
}

/**
 * @param {Array} structure
 * @param {String} itemId
 * @param {Number} direction
 * @return {Array}
 */
function getSiblingsInDirection(structure, itemId, direction)
{
	const siblings = []
	let foundItem = false
	let itemLevel

	for(let k = 0; k < structure.length; k++)
	{
		const i = (direction < 0) ? (structure.length - k - 1) : k
		const item = structure[i]

		if(foundItem)
		{
			if(item.level < itemLevel)
			{
				break
			}

			if(item.level === itemLevel)
			{
				const siblingItem = { id: item.id, index: i, level: item.level }
				siblings.push(siblingItem)
			}
		}
		else if(itemId === item.id)
		{
			foundItem = true
			itemLevel = item.level
		}
	}

	return siblings
}

/**
 * @param {Array} structure
 * @param {String} itemId
 * @return {Array}
 */
export function getPrevSiblings(structure, itemId)
{
	return getSiblingsInDirection(structure, itemId, -1)
}

/**
 * @param {Array} structure
 * @param {String} itemId
 * @return {Array}
 */
export function getNextSiblings(structure, itemId)
{
	return getSiblingsInDirection(structure, itemId, 1)
}

/**
 * @param {Array} structure
 * @param {String} itemId
 * @return {Array}
 */
export function getSiblings(structure, itemId)
{
	return [
		...getPrevSiblings(structure, itemId).reverse(),
		...getNextSiblings(structure, itemId),
	]
}
