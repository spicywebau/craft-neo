import { createSelector } from 'reselect'
import { getTree, getDescendants } from './structure'

const getCollection = (state) => state.collection
const getStructure = (state) => state.structure

/**
 * @param {Object} state
 * @return {Array}
 */
export const getBlockTree = createSelector(
	[ getCollection, getStructure ],
	function(collection, structure)
	{
		return getTree(structure, (item) => (item.block = collection[item.id], item))
	}
)

/**
 * @param {String} blockId
 * @param {Number} depth
 * @return {Function}
 */
export const getBlockDescendantsFactory = (blockId=null, depth=null) => createSelector(
	[ getCollection, getStructure ],
	function(collection, structure)
	{
		const descendants = getDescendants(structure, blockId, depth)

		// Join block models to structural items for convenient reference
		descendants.forEach((item) => item.block = collection[item.id])

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

/**
 * @param {Object} state
 * @param {Number} depth
 * @return {Array}
 */
export function getTopBlocks(state, depth=1)
{
	return getBlockDescendantsFactory(null, depth)(state)
}
