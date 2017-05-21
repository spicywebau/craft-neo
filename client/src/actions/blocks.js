import { ADD_BLOCK, REMOVE_BLOCK, REORDER_BLOCK } from '../constants/actions'
import { generateNewId } from '../utils/data'

export function addBlock(block, index=-1)
{
	block = Object.assign({ id: generateNewId() }, block)

	return {
		type: ADD_BLOCK,
		payload: { block, index },
	}
}

export function removeBlock(blockId)
{
	return {
		type: REMOVE_BLOCK,
		payload: { blockId },
	}
}

export function reorderBlock(blockId, index)
{
	return {
		type: REORDER_BLOCK,
		payload: { blockId, index },
	}
}
