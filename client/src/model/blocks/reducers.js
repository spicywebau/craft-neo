import {
	ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK,
	BLOCK_PARENT, BLOCK_PREV_SIBLING, BLOCK_NEXT_SIBLING,
} from './constants'
import { getDescendants, getPrevSiblings, getNextSiblings } from './selectors/structure'
import { generateNewId } from '../../utils/data'

/*
 * The initial state for the reducer.
 */
const initialState = {
	collection: {},
	structure: [],
}

/**
 * @param {Object} payload
 * @return {Object}
 */
function formatBlock(payload)
{
	return {
		id: payload.id ? String(payload.id) : generateNewId(),
		blockTypeId: payload.blockTypeId ? String(payload.blockTypeId) : null,
		enabled: (typeof payload.enabled === 'boolean') ? payload.enabled : true,
		data: (typeof payload.data === 'object') ? Object.assign({}, payload.data) : {},
		errors: (payload.errors instanceof Array) ? payload.errors.map((e) => ({
			type: String(e.type || ''),
			fieldId: e.fieldId ? String(e.fieldId) : null,
			message: String(e.message || ''),
		})) : [],
		template: (typeof payload.template === 'object') ? {
			html: String(payload.template.html || ''),
			css: String(payload.template.css || ''),
			js: String(payload.template.js || ''),
		} : false,
	}
}

/**
 * @param {Array} structure
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Object}
 */
function getBlockInsertionData(structure, relatedBlockId, relatedBlockType)
{
	let index = structure.length
	let level = 1

	if (relatedBlockId)
	{
		const findRelated = ({ id }) => (id === relatedBlockId)
		const relatedItem = structure.find(findRelated)
		const relatedIndex = structure.findIndex(findRelated)

		switch(relatedBlockType)
		{
			case BLOCK_PARENT:
			{
				const descendants = getDescendants(structure, relatedBlockId)
				const lastDescendant = descendants[descendants.length - 1]

				index = lastDescendant ? (lastDescendant.index + 1) : (relatedIndex + 1)
				level = relatedItem.level + 1
			}
			break
			case BLOCK_PREV_SIBLING:
			{
				const descendants = getDescendants(structure, relatedBlockId)
				const lastDescendant = descendants[descendants.length - 1]

				index = lastDescendant ? (lastDescendant.index + 1) : (relatedIndex + 1)
				level = relatedItem.level
			}
			break
			case BLOCK_NEXT_SIBLING:
			{
				index = relatedIndex
				level = relatedItem.level
			}
			break
		}
	}

	return { index, level }
}

/**
 * @param {Array} structure
 * @param {String} blockId
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Array}
 */
function addToStructure(structure, blockId, relatedBlockId=null, relatedBlockType=null)
{
	const { index, level } = getBlockInsertionData(structure, relatedBlockId, relatedBlockType)
	const addItem = { id: blockId, level }

	return [ ...structure.slice(0, index), addItem, ...structure.slice(index) ]
}

/**
 * @param {Array} structure
 * @param {String} blockId
 * @return {Array}
 */
function removeFromStructure(structure, blockId)
{
	const descendants = getDescendants(structure, blockId)
	const removedIdMap = descendants.reduce((map, { id }) => (map[id] = 1, map), { [blockId]: 1 })

	return structure.filter(({ id }) => !(id in removedIdMap))
}

/**
 * @param {Array} structure
 * @param {String} blockId
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Array}
 */
function moveInStructure(structure, blockId, relatedBlockId, relatedBlockType)
{
	const descendants = getDescendants(structure, blockId)
	const item = structure.find(({ id }) => id === blockId)
	
	structure = removeFromStructure(structure, blockId)

	const { index, level } = getBlockInsertionData(structure, relatedBlockId, relatedBlockType)
	const newStructure = structure.slice(0, index)

	newStructure.push({ id: blockId, level })

	descendants.forEach((descendantItem) => newStructure.push({
		id: descendantItem.id,
		level: level + (descendantItem.level - item.level),
	}))

	newStructure.push(...structure.slice(index))

	return newStructure
}

/**
 * @param {Object} collection
 * @param {Array} structure
 * @return {Object}
 */
function alignCollectionWithStructure(collection, structure)
{
	const newCollection = {}

	for (let { id } of structure)
	{
		newCollection[id] = collection[id]
	}

	return newCollection
}

/**
 * @param {Object} state
 * @param {Object} action
 * @return {Object}
 */
export default function blocksReducer(state=initialState, action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			const block = formatBlock(action.payload.block)
			const { relatedBlockId, relatedBlockType } = action.payload

			if (!(block.id in state.collection))
			{
				const collection = Object.assign({ [block.id]: block }, state.collection)
				const structure = addToStructure(state.structure, block.id, relatedBlockId, relatedBlockType)

				state = { collection, structure }
			}
		}
		break
		case REMOVE_BLOCK:
		{
			const { blockId } = action.payload

			if (blockId in state.collection)
			{
				const structure = removeFromStructure(state.structure, blockId)
				const collection = alignCollectionWithStructure(state.collection, structure)

				state = { collection, structure }
			}
		}
		break
		case MOVE_BLOCK:
		{
			const { blockId, relatedBlockId, relatedBlockType } = action.payload

			const hasBlock = state.structure.find(({ id }) => id === blockId)
			const hasRelatedBlock = state.structure.find(({ id }) => id === relatedBlockId)

			if (hasBlock && hasRelatedBlock)
			{
				const collection = state.collection
				const structure = moveInStructure(state.structure, blockId, relatedBlockId, relatedBlockType)

				state = { collection, structure }
			}
		}
		break
	}

	return state
}
