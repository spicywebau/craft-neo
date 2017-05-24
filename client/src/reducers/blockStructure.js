import { ADD_BLOCK, REMOVE_BLOCK, REORDER_BLOCK } from '../constants/actions'
import { resolveIndex } from '../utils/data'

export default function blockStructureReducer(state=[], action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			let { block, level } = action.payload
			const item = { id: block.id, level }

			if(!state.find((item) => item.id === block.id))
			{
				return [ ...state, item ]
			}
		}
		break
		case REMOVE_BLOCK:
		{
			let { blockId } = action.payload

			if(state.find((item) => item.id === block.id))
			{
				return state.filter((item) => item.id !== blockId)
			}
		}
		break
	}

	return state
}
