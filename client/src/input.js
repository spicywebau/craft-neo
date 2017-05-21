import { createStore, combineReducers } from 'redux'
import { createSelector } from 'reselect'

import blocksReducer from './reducers/blocks'
import blockStructureReducer from './reducers/blockStructure'

import { addBlock, removeBlock } from './actions/blocks'

const reducer = combineReducers({
	blocks: blocksReducer,
	blockStructure: blockStructureReducer,
})

const store = createStore(reducer)
