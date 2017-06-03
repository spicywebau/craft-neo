import { ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK } from './constants'
import { getBlockDescendants } from './selectors'
import { generateNewId } from '../../utils/data'

/**
 * @param {Object} payload
 * @return {Object}
 */
function formatBlock(payload)
{
	return {
		id: payload.id || generateNewId(),
		blockTypeId: payload.blockTypeId,
		enabled: (typeof payload.enabled === 'boolean') ? payload.enabled : true,
		data: (typeof payload.data === 'object') ? Object.assign({}, payload.data) : {},
		errors: ((payload.errors instanceof Array) ? payload.errors : []).map((e) => ({
			type: e.type || '',
			message: e.message || '',
		})),
		template: (typeof payload.template === 'object') ? {
			html: payload.template.html || '',
			css: payload.template.css || '',
			js: payload.template.js || '',
		} : null,
	}
}

/**
 * @param {Array} structure
 * @param {String} blockId
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Array}
 */
function addToStructure(structure, blockId, relatedBlockId, relatedBlockType)
{
	
}

/**
 * @param {Array} structure
 * @param {String} blockId
 * @return {Array}
 */
function removeFromStructure(structure, blockId)
{

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

}

/**
 * @param {Object} collection
 * @param {Array} structure
 * @return {Object}
 */
function alignCollectionWithStructure(collection, structure)
{
	const newCollection = {}

	for(let { id } of structure)
	{
		newCollection[id] = collection[id]
	}

	return newCollection
}

/*
 * The initial state for the reducer.
 */
const initialState = {
	collection: {},
	structure: [],
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
			const block = formatBlock(payload.block)
			const { relatedBlockId, relatedBlockType } = payload

			if(!(block.id in state.collection))
			{
				const collection = Object.assign({ [block.id]: block }, state.collection)
				const structure = addToStructure(state.structure, block.id, relatedBlockId, relatedBlockType)

				return { collection, structure }
			}
		}
		break
		case REMOVE_BLOCK:
		{
			const { blockId } = payload

			if(blockId in state.collection)
			{
				const structure = removeFromStructure(state.structure, blockId)
				const collection = alignCollectionWithStructure(state.collection, structure)

				return { collection, structure }
			}
		}
		break
		case MOVE_BLOCK:
		{
			const { blockId, relatedBlockId, relatedBlockType } = payload

			const hasBlock = state.structure.find(({ id }) => id == blockId)
			const hasRelatedBlock = state.structure.find(({ id }) => id == relatedBlockId)

			if(hasBlock && hasRelatedBlock)
			{
				const collection = state.collection
				const structure = moveInStructure(blockId, relatedBlockId, relatedBlockType)

				return { collection, structure }
			}
		}
		break
	}

	return state
}
