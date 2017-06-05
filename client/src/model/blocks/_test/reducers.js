import assert from 'assert'
import blocksReducer from '../reducers'
import {
	ADD_BLOCK, REMOVE_BLOCK, MOVE_BLOCK,
	BLOCK_PARENT, BLOCK_PREV_SIBLING, BLOCK_NEXT_SIBLING,
} from '../constants'

function createDummyBlock(id, overrides={})
{
	return Object.assign({
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
	}, overrides)
}

describe(`Reducers`, function()
{
	describe(`blocksReducer()`, function()
	{
		it(`should not change the state after an invalid action`, function()
		{
			const action = {}

			const initialState = {
				collection: {},
				structure: [],
			}

			const expectedState = {
				collection: {},
				structure: [],
			}

			assert.deepEqual(blocksReducer(initialState, action), expectedState)
			assert.strictEqual(blocksReducer(initialState, action), initialState)
		})

		describe(`ADD_BLOCK`, function()
		{
			it(`should add a block to the store`, function()
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

			it(`should not add a block if it's ID already exists in the store`, function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: { block: createDummyBlock('1') },
				}

				const initialState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				const expectedState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
				assert.strictEqual(blocksReducer(initialState, action), initialState)
			})

			it(`should add a block with errors`, function()
			{
				const action = {
					type: ADD_BLOCK,
					payload: {
						block: createDummyBlock('1', {
							errors: [
								{
									type: 'FIELD_ERROR',
									fieldId: '1',
									message: `Message`,
									invalidProperty: true,
								},
							],
						}),
					},
				}

				const initialState = {
					collection: {},
					structure: [],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1', {
							errors: [
								{
									type: 'FIELD_ERROR',
									fieldId: '1',
									message: `Message`,
								},
							],
						}),
					},
					structure: [ { id: '1', level: 1 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block as the only child to another`, function()
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
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block as a child to another`, function()
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

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block before another`, function()
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
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '4', level: 1 },
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block before another under a parent`, function()
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
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '4', level: 2 },
						{ id: '2', level: 2 },
						{ id: '3', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block after another`, function()
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
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should add a block after another with descendants`, function()
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
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
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

		describe(`REMOVE_BLOCK`, function()
		{
			it(`should remove a block from the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK,
					payload: { blockId: '1' },
				}

				const initialState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				const expectedState = {
					collection: {},
					structure: [],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should change the state if the block ID doesn't exist in the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK,
					payload: { blockId: '1' },
				}

				const initialState = {
					collection: {},
					structure: [],
				}

				const expectedState = {
					collection: {},
					structure: [],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
				assert.strictEqual(blocksReducer(initialState, action), initialState)
			})

			it(`should remove a block and leave the rest`, function()
			{
				const action = {
					type: REMOVE_BLOCK,
					payload: { blockId: '2' },
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '3', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should remove a block and all of it's descendants`, function()
			{
				const action = {
					type: REMOVE_BLOCK,
					payload: { blockId: '2' },
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
						'5': createDummyBlock('5'),
						'6': createDummyBlock('6'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 2 },
						{ id: '4', level: 3 },
						{ id: '5', level: 2 },
						{ id: '6', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'6': createDummyBlock('6'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '6', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})
		})

		describe(`MOVE_BLOCK`, function()
		{
			it(`should not change the state if the block ID doesn't exist in the store`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_PARENT
					},
				}

				const initialState = {
					collection: { '2': createDummyBlock('2') },
					structure: [ { id: '2', level: 1 } ],
				}

				const expectedState = {
					collection: { '2': createDummyBlock('2') },
					structure: [ { id: '2', level: 1 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
				assert.strictEqual(blocksReducer(initialState, action), initialState)
			})

			it(`should not change the state if the related block ID doesn't exist in the store`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_PARENT
					},
				}

				const initialState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				const expectedState = {
					collection: { '1': createDummyBlock('1') },
					structure: [ { id: '1', level: 1 } ],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
				assert.strictEqual(blocksReducer(initialState, action), initialState)
			})

			it(`should move a block to be the only child of another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_PARENT,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '2', level: 1 },
						{ id: '1', level: 2 },
						{ id: '3', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block to be a child of another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_PARENT,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 2 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '2', level: 1 },
						{ id: '3', level: 2 },
						{ id: '1', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block before another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '3',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '3', level: 1 },
						{ id: '2', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block before another under a parent`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '3',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					},
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
						{ id: '3', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '3', level: 2 },
						{ id: '2', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block after another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '3',
						relatedBlockType: BLOCK_PREV_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
					},
					structure: [
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
						{ id: '1', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block after another with descendants`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_PREV_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 2 },
						{ id: '4', level: 2 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '2', level: 1 },
						{ id: '3', level: 2 },
						{ id: '4', level: 2 },
						{ id: '1', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants to be the only child of another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '4',
						relatedBlockType: BLOCK_PARENT,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 3 },
						{ id: '4', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '4', level: 1 },
						{ id: '1', level: 2 },
						{ id: '2', level: 3 },
						{ id: '3', level: 4 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants to be a child of another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '4',
						relatedBlockType: BLOCK_PARENT,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
						'5': createDummyBlock('5'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
						{ id: '5', level: 3 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
						'5': createDummyBlock('5'),
					},
					structure: [
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
						{ id: '5', level: 3 },
						{ id: '1', level: 3 },
						{ id: '2', level: 4 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants before another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '3',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 1 },
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
						{ id: '2', level: 1 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants before another under a parent`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '3',
						relatedBlockId: '2',
						relatedBlockType: BLOCK_NEXT_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '3', level: 2 },
						{ id: '4', level: 3 },
						{ id: '2', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants after another`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '4',
						relatedBlockType: BLOCK_PREV_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 3 },
						{ id: '4', level: 1 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
					},
					structure: [
						{ id: '4', level: 1 },
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 3 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})

			it(`should move a block with descendants after another with descendants`, function()
			{
				const action = {
					type: MOVE_BLOCK,
					payload: {
						blockId: '1',
						relatedBlockId: '3',
						relatedBlockType: BLOCK_PREV_SIBLING,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
						'5': createDummyBlock('5'),
					},
					structure: [
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
						{ id: '5', level: 3 },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlock('1'),
						'2': createDummyBlock('2'),
						'3': createDummyBlock('3'),
						'4': createDummyBlock('4'),
						'5': createDummyBlock('5'),
					},
					structure: [
						{ id: '3', level: 1 },
						{ id: '4', level: 2 },
						{ id: '5', level: 3 },
						{ id: '1', level: 1 },
						{ id: '2', level: 2 },
					],
				}

				assert.deepEqual(blocksReducer(initialState, action), expectedState)
			})
		})
	})
})
