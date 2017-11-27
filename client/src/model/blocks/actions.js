import {
	ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK,
	BLOCK_PARENT, BLOCK_PREV_SIBLING, BLOCK_NEXT_SIBLING,
} from './constants'

/**
 * @param {Object} block
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Object}
 */
export function addBlock(block, relatedBlockId=null, relatedBlockType=BLOCK_PARENT)
{
	return {
		type: ADD_BLOCK,
		payload: { block, relatedBlockId, relatedBlockType },
	}
}

/**
 * @param {Object} block
 * @param {String} parentBlockId
 * @return {Object}
 */
export function addBlockTo(block, parentBlockId)
{
	return addBlock(block, parentBlockId, BLOCK_PARENT)
}

/**
 * @param {Object} block
 * @param {String} nextBlockId
 * @return {Object}
 */
export function addBlockBefore(block, nextBlockId)
{
	return addBlock(block, nextBlockId, BLOCK_NEXT_SIBLING)
}

/**
 * @param {Object} block
 * @param {String} prevBlockId
 * @return {Object}
 */
export function addBlockAfter(block, prevBlockId)
{
	return addBlock(block, prevBlockId, BLOCK_PREV_SIBLING)
}

/**
 * @param {Object} block
 * @return {Object}
 */
export function updateBlock(block)
{
	return {
		type: UPDATE_BLOCK,
		payload: { block },
	}
}

/**
 * @param {String} blockId
 * @return {Object}
 */
export function removeBlock(blockId)
{
	return {
		type: REMOVE_BLOCK,
		payload: { blockId },
	}
}

/**
 * @param {String} blockId
 * @param {String} relatedBlockId
 * @param {String} relatedBlockType
 * @return {Object}
 */
export function moveBlock(blockId, relatedBlockId=null, relatedBlockType=BLOCK_PARENT)
{
	return {
		type: MOVE_BLOCK,
		payload: { blockId, relatedBlockId, relatedBlockType },
	}
}

/**
 * @param {String} blockId
 * @param {String} parentBlockId
 * @return {Object}
 */
export function moveBlockTo(blockId, parentBlockId)
{
	return moveBlock(blockId, parentBlockId, BLOCK_PARENT)
}

/**
 * @param {String} blockId
 * @param {String} nextBlockId
 * @return {Object}
 */
export function moveBlockBefore(blockId, nextBlockId)
{
	return moveBlock(blockId, nextBlockId, BLOCK_NEXT_SIBLING)
}

/**
 * @param {String} blockId
 * @param {String} prevBlockId
 * @return {Object}
 */
export function moveBlockAfter(blockId, prevBlockId)
{
	return moveBlock(blockId, prevBlockId, BLOCK_PREV_SIBLING)
}
