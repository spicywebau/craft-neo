import { createStore, combineReducers } from 'redux'

import blocksReducer from './reducers/blocks'
import blockStructureReducer from './reducers/blockStructure'
// import blockTypesReducer from './reducers/blockTypes'
// import blockTypeGroupsReducer from './reducers/blockTypeGroups'
// import blockTypeStructureReducer from './reducers/blockTypeStructure'

import { addBlock, removeBlock } from './actions/blocks'

import { getBlocksHierarchy } from './selectors/blocks'

const reducer = combineReducers({
	blocks: blocksReducer,
	blockStructure: blockStructureReducer,
	// blockTypes: blockTypesReducer,
	// blockTypeGroups: blockTypeGroupsReducer,
	// blockTypeStructure: blockTypeStructureReducer,
})

const store = createStore(reducer)

store.subscribe(() => console.log(store.getState()))

store.dispatch(addBlock({
	id: 4,
	blockType: 'bt1',
}))

store.dispatch(addBlock({
	id: 5,
	blockType: 'bt2',
}))

store.dispatch(addBlock({
	id: 6,
	blockType: 'bt3',
}, 5))

store.dispatch(addBlock({
	id: 7,
	blockType: 'bt4',
}, 6))

store.dispatch(addBlock({
	id: 53,
	blockType: 'bt5',
}, 6))

store.dispatch(addBlock({
	id: 25,
	blockType: 'b7',
}, 5))

console.log(getBlocksHierarchy(store.getState()))
