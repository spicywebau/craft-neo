import {
	ADD_BLOCK_TYPE, REMOVE_BLOCK_TYPE, MOVE_BLOCK_TYPE,
	ADD_BLOCK_TYPE_GROUP, REMOVE_BLOCK_TYPE_GROUP, MOVE_BLOCK_TYPE_GROUP,
} from './constants'

export function addBlockType(blockType, index=-1)
{
	return {
		type: ADD_BLOCK_TYPE,
		payload: { blockType, index },
	}
}

export function removeBlockType(blockTypeId)
{
	return {
		type: REMOVE_BLOCK_TYPE,
		payload: { blockTypeId },
	}
}

export function moveBlockType(blockTypeId, index)
{
	return {
		type: MOVE_BLOCK_TYPE,
		payload: { blockTypeId, index },
	}
}

export function addBlockTypeGroup(blockTypeGroup, index=-1)
{
	return {
		type: ADD_BLOCK_TYPE_GROUP,
		payload: { blockTypeGroup, index },
	}
}

export function removeBlockTypeGroup(blockTypeGroupId)
{
	return {
		type: REMOVE_BLOCK_GROUP_TYPE,
		payload: { blockTypeGroupId },
	}
}

export function moveBlockTypeGroup(blockTypeGroupId, index)
{
	return {
		type: MOVE_BLOCK_GROUP_TYPE,
		payload: { blockTypeGroupId, index },
	}
}
