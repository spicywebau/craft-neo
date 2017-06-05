import assert from 'assert'
import blocksReducer from '../reducers'
import {
	ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK,
	BLOCK_PARENT, BLOCK_PREV_SIBLING, BLOCK_NEXT_SIBLING,
} from '../constants'

function createDummyBlock(id)
{
	return {
		id: String(id),
		blockTypeId: '1',
		enabled: true,
		data: {},
		errors: [],
		template: {
			html: '',
			css: '',
			js: '',
		},
	}
}

describe('Reducers', function()
{
	describe('blocksReducer()', function()
	{
		describe('ADD_BLOCK', function()
		{
			it('should add a block to the store', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: { block: createDummyBlock('1') },
				}

				const initialState = {
					collection: {},
					structure: [],
				}

				const expectedState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block as the only child to another', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('2'),
						relatedBlockId: '1',
						relatedBlockType: BLOCK_PARENT,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
					},
					structure: [
						{ id: '1', level: 1 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '2': createDummyBlock('2') }, initialState.collection),
					structure: [ ...initialState.structure, { id: '2', level: 2 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block as a child to another', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('3'),
						relatedBlockId: '1',
						relatedBlockType: BLOCK_PARENT,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '3': createDummyBlock('3') }, initialState.collection),
					structure: [ ...initialState.structure, { id: '3', level: 2 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block before another', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('4'),
						relatedBlockId: '1',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '4': createDummyBlock('4') }, initialState.collection),
					structure: [
						{ id: '4', level: 1 },
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block before another under a parent', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('4'),
						relatedBlockId: '2',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '4': createDummyBlock('4') }, initialState.collection),
					structure: [
						{ id: '1', level: 1 },
						{ id: '4', level: 2 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block after another', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('2'),
						relatedBlockId: '1',
						relatedBlockType: BLOCK_PREV_SIBLING,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
					},
					structure: [
						{ id: '1', level: 1 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '2': createDummyBlock('2') }, initialState.collection),
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it('should add a block after another with descendants', function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('4'),
						relatedBlockId: '1',
						relatedBlockType: BLOCK_PREV_SIBLING,
					}
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				const expectedState = {
					collection: Object.assign({ '4': createDummyBlock('4') }, initialState.collection),
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
						{ id: '4', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})
		})
	})
})
