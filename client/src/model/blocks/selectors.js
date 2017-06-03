import { createSelector } from 'reselect'
import { createSelectorFactory } from '../../utils/selector'

const getCollection = (state) => state.collection
const getStructure = (state) => state.structure

/**
 * @param {Object} state
 * @return {Array}
 */
export const getBlocksHierarchy = createSelector(
	[ getCollection, getStructure ],
	function(collection, structure)
	{
		const tree = []
		const parentStack = []

		for(let item of structure)
		{
			const block = collection[item.id]
			const treeItem = { block, level: item.level, children: [] }
			let peekItem

			// Remove parent blocks until either empty, or a parent block is only one level below this one -
			// consequently, it'll be the parent of the block
			while((peekItem = parentStack[parentStack.length - 1]) && item.level <= peekItem.level)
			{
				parentStack.pop()
			}

			// If there are no blocks in the stack, it must be a root level block, otherwise, the block at the top of
			// the stack will be the parent
			peekItem = parentStack[parentStack.length - 1]
			const branch = peekItem ? peekItem.children : tree
			branch.push(treeItem)

			// The current block may potentially be a parent block as well, so save it to the stack
			parentStack.push(treeItem)
		}

		return tree
	}
)

/**
 * @param {String} blockId
 * @param {Number} depth
 * @return {Function}
 */
export const getBlockDescendantsFactory = (blockId, depth=null) => createSelector(
	[ getCollection, getStructure ],
	function(collection, structure)
	{
		const descendants = []
		let foundBlock = false
		let blockLevel

		for(let item of structure)
		{
			if(foundBlock)
			{
				if(item.level > blockLevel)
				{
					if(!depth || item.level - blockLevel <= depth)
					{
						const block = collection[item.id]
						const descendantItem = { block, level: item.level }

						descendants.push = [ descendantItem ]
					}
				}
				else
				{
					break
				}
			}
			else if(blockId === item.id)
			{
				foundBlock = true
				blockLevel = item.level
			}
		}

		return descendants
	}
)

/**
 * @param {Object} state
 * @param {String} blockId
 * @param {Number} depth
 * @return {Array}
 */
export function getBlockDescendants(state, blockId, depth=null)
{
	return getBlockDescendantsFactory(blockId, depth)(state)
}

/**
 * @param {Object} state
 * @param {String} blockId
 * @return {Array}
 */
export function getBlockChildren(state, blockId)
{
	return getBlockDescendantsFactory(blockId, 1)(state)
}
