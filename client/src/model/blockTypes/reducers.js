import {
	ADD_BLOCK_TYPE, REMOVE_BLOCK_TYPE, MOVE_BLOCK_TYPE,
	ADD_BLOCK_TYPE_GROUP, REMOVE_BLOCK_TYPE_GROUP, MOVE_BLOCK_TYPE_GROUP,
} from './constants'
import { generateNewId, resolveIndex } from '../../utils/data'

/*
 * The initial state for the reducer.
 */
const initialState = {
	collection: {},
	groups: {},
	structure: [],
}

/**
 * @param {Object} payload
 * @return {Object}
 */
function formatBlockType(payload)
{
	return {
		id: payload.id ? String(payload.id) : generateNewId(),
		name: String(payload.name || ''),
		handle: String(payload.handle || ''),
		max: Math.max(0, payload.max|0),
		topLevel: (typeof payload.topLevel === 'boolean') ? payload.topLevel : true,
		childrenIds: (payload.childrenIds instanceof Array) ? payload.childrenIds.map((id) => String(id)) : [],
		maxChildren: Math.max(0, payload.maxChildren|0),
		tabs: (payload.tabs instanceof Array) ? payload.tabs.map((tab) => ({
			name: String(tab.name),
			fieldIds: (tab.fieldIds instanceof Array) ? tab.fieldIds.map((id) => String(id)) : [],
		})) : [],
		errors: (payload.errors instanceof Array) ? payload.errors.map((e) => ({
			type: String(e.type || ''),
			message: String(e.message || ''),
		})) : [],
		template: {
			html: String((payload.template && payload.template.html) || ''),
			css: String((payload.template && payload.template.css) || ''),
			js: String((payload.template && payload.template.js) || ''),
		},
	}
}

/**
 * @param {Object} state
 * @param {Object} action
 * @return {Object}
 */
export default function blockTypesReducer(state=initialState, action)
{
	switch(action.type)
	{
		case ADD_BLOCK_TYPE:
		{

		}
		break
		case REMOVE_BLOCK_TYPE:
		{

		}
		break
		case MOVE_BLOCK_TYPE:
		{

		}
		break
		case ADD_BLOCK_TYPE_GROUP:
		{

		}
		break
		case REMOVE_BLOCK_TYPE_GROUP:
		{

		}
		break
		case MOVE_BLOCK_TYPE_GROUP:
		{

		}
		break
	}

	return state
}
