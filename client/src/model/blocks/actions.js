import {
	ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK,
	BLOCK_PARENT, BLOCK_PREV_SIBLING, BLOCK_NEXT_SIBLING,
} from './constants'

export function addBlock(block, relatedBlockId=null, relatedBlockType=BLOCK_PARENT)
{
	return {
		type: ADD_BLOCK,
		payload: { block, relatedBlockId, relatedBlockType },
	}
}

export const addBlockTo = (block, parentBlockId) => addBlock(block, parentBlockId, BLOCK_PARENT)
export const addBlockBefore = (block, nextBlockId) => addBlock(block, nextBlockId, BLOCK_NEXT_SIBLING)
export const addBlockAfter = (block, prevBlockId) => addBlock(block, prevBlockId, BLOCK_PREV_SIBLING)

export function removeBlock(blockId)
{
	return {
		type: REMOVE_BLOCK,
		payload: { blockId },
	}
}

export function moveBlock(blockId, relatedBlockId=null, relatedBlockType=BLOCK_PARENT)
{
	return {
		type: MOVE_BLOCK,
		payload: { blockId, relatedBlockId, relatedBlockType },
	}
}

export const moveBlockTo = (blockId, parentBlockId) => moveBlock(blockId, parentBlockId, BLOCK_PARENT)
export const moveBlockBefore = (blockId, nextBlockId) => moveBlock(blockId, nextBlockId, BLOCK_NEXT_SIBLING)
export const moveBlockAfter = (blockId, prevBlockId) => moveBlock(blockId, prevBlockId, BLOCK_PREV_SIBLING)
