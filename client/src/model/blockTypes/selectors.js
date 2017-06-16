import { createSelector } from 'reselect'

const getCollection = (state) => state.collection
const getGroups = (state) => state.groups
const getStructure = (state) => state.structure

/**
 * @param {Array} state
 * @return {Array}
 */
export const getBlockTypeTree = createSelector(
	[ getCollection, getGroups, getStructure ],
	function(collection, groups, structure)
	{
		const blockTypeTree = []

		return blockTypeTree
	}
)

/**
 * @param {Array} state
 * @return {Array}
 */
export const getTopBlockTypes = createSelector(
	[ getCollection, getGroups, getStructure ],
	function(collection, groups, structure)
	{
		const topBlockTypes = []

		return topBlockTypes
	}
)

/**
 * @param {String} blockTypeGroupId
 * @return {Function}
 */
export const getGroupedBlockTypesFactory = (blockTypeGroupId) => createSelector(
	[ getCollection, getGroups, getStructure ],
	function(collection, groups, structure)
	{
		const groupedBlockTypes = []

		return groupedBlockTypes
	}
)

/**
 * @param {Array} state
 * @param {String} blockTypeGroupId
 * @return {Array}
 */
export function getGroupedBlockTypes(state, blockTypeGroupId)
{
	return getGroupedBlockTypesFactory(blockTypeGroupId)(state)
}
