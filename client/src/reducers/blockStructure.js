import { ADD_BLOCK, REMOVE_BLOCK, REORDER_BLOCK } from '../constants/actions'
import { resolveIndex } from '../utils/data'

export default function blockStructureReducer(state=[], action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			let { block, index } = action.payload
			index = resolveIndex(Number.isInteger(index) ? index : -1, state.length + 1)

			if(!state.includes(block.id))
			{
				if(index === state.length)
				{
					return [ ...state, block.id ]
				}
				else
				{
					return state.reduce((acc, val, i) =>
					{
						if(i === index) acc.push(block.id)
						acc.push(val)
						return acc
					}, [])
				} 
			}
		}
		break
		case REMOVE_BLOCK:
		{
			let { blockId } = action.payload

			if(state.includes(blockId))
			{
				return state.filter((id) => id !== blockId)
			}
		}
		break
		case REORDER_BLOCK:
		{
			let { blockId, index } = action.payload
			index = resolveIndex(index, state.length)

			if(state.includes(blockId))
			{
				return state.reduce((acc, val, i) =>
				{
					if(i === index) acc.push(block.id)
					acc.push(val)
					return acc
				}, [])
			}
		}
	}

	return state
}
