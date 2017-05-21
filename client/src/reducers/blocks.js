import { ADD_BLOCK, REMOVE_BLOCK } from '../constants/actions'
import { copyObjectKeys } from '../utils/data'

export default function blocksReducer(state={}, action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			let { block } = action.payload
			block = {
				id: block.id,
				blockType: block.blockType,
				enabled: typeof block.enabled === 'boolean' ? block.enabled : true,
				errors: block.errors || [],
				html: block.html || '',
				css: block.css || '',
				js: block.js || '',
			}

			if(!(block.id in state))
			{
				return Object.assign({ [block.id]: block }, state)
			}
		}
		break
		case REMOVE_BLOCK:
		{
			let { blockId } = action.payload

			if(blockId in state)
			{
				const newState = Object.assign({}, state)
				delete newState[blockId]
				
				return newState
			}
		}
		break
	}

	return state
}
