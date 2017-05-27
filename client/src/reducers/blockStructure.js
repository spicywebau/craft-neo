import { ADD_BLOCK, REMOVE_BLOCK, REORDER_BLOCK } from '../constants/actions'
import { resolveIndex } from '../utils/data'

export default function blockStructureReducer(state=[], action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			const { block, parentBlockId } = action.payload

			// Non-strict inequality matches both null and undefined
			if(parentBlockId == null)
			{
				return [ ...state, { id: block.id, level: 1 } ]
			}

			const parentItem = state.find((item) => item.id === parentBlockId)
			const parentIndex = state.indexOf(parentItem)

			if(parentItem && parentIndex > -1)
			{
				const nextItem = state.find((item, index) => index > parentIndex && item.level <= parentItem.level)
				const nextIndex = nextItem ? state.indexOf(nextItem) : -1

				const newItem = { id: block.id, level: parentItem.level + 1 }
				const newState = [ ...state ]

				if(nextIndex > -1)
				{
					newState.splice(nextIndex, 0, newItem)
				}
				else
				{
					newState.push(newItem)
				}

				return newState
			}
		}
		break
		case REMOVE_BLOCK:
		{
			const { blockId } = action.payload

			if(state.find((item) => item.id === block.id))
			{
				return state.filter((item) => item.id !== blockId)
			}
		}
		break
	}

	return state
}
