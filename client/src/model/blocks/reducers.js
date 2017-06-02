import { ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK } from './constants'
import { formatObject, generateNewId } from '../../utils/data'

export default function blocksReducer(state={}, action)
{
	switch(action.type)
	{
		case ADD_BLOCK:
		{
			const block = formatObject(payload.block, {
				id: generateNewId(),
				blockType: null,
				enabled: true,
				data: {},
				errors: [],
				template: {
					html: '',
					css: '',
					js: '',
				},
			})
		}
		break
		case REMOVE_BLOCK:
		{

		}
		break
		case MOVE_BLOCK:
		{

		}
		break
	}

	return state
}
