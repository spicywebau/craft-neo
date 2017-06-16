import assert from 'assert'
import blockTypesReducer from '../reducers'
import {
	BLOCK_TYPE, BLOCK_TYPE_GROUP,
	ADD_BLOCK_TYPE, REMOVE_BLOCK_TYPE, MOVE_BLOCK_TYPE,
	ADD_BLOCK_TYPE_GROUP, REMOVE_BLOCK_TYPE_GROUP, MOVE_BLOCK_TYPE_GROUP,
} from '../constants'

function createDummyBlockType(id, overrides={})
{
	return Object.assign({
		id: String(id),
		name: `Block type`,
		handle: 'blockType',
		max: 0,
		topLevel: true,
		childrenIds: [],
		maxChildren: 0,
		tabs: [],
		errors: [],
		template: {
			html: '',
			css: '',
			js: '',
		},
	}, overrides)
}

function createDummyBlockTypeGroup(id, overrides={})
{
	return Object.assign({
		id: String(id),
		name: `Block type group`,
	}, overrides)
}

describe(`Reducers`, function()
{
	describe(`blockTypesReducer()`, function()
	{
		it(`should not change the state after an invalid action`, function()
		{
			const action = {}

			const initialState = {
				collection: {},
				groups: {},
				structure: [],
			}

			const expectedState = {
				collection: {},
				groups: {},
				structure: [],
			}

			assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
			assert.strictEqual(blockTypesReducer(initialState, action), initialState)
		})

		describe('ADD_BLOCK_TYPE', function()
		{
			it(`should add a block type to the store`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE,
					payload: { blockType: createDummyBlockType('1') },
				}

				const initialState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const expectedState = {
					collection: { '1': createDummyBlockType('1') },
					groups: {},
					structure: [ { type: BLOCK_TYPE, id: '1' } ],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
				assert.strictEqual(actualState.groups, initialState.groups)
			})

			it(`should add a block type at the requested index`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE,
					payload: {
						blockType: createDummyBlockType('4'),
						index: 1,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
						'4': createDummyBlockType('4'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '4' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})

			it(`should add a block type at the requested negative index`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE,
					payload: {
						blockType: createDummyBlockType('4'),
						index: -1,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
						'4': createDummyBlockType('4'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
						{ type: BLOCK_TYPE, id: '4' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})
		})

		describe('REMOVE_BLOCK_TYPE', function()
		{
			it(`should remove a block type from the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE,
					payload: { blockTypeId: '1' },
				}

				const initialState = {
					collection: { '1': createDummyBlockType('1') },
					groups: {},
					structure: [ { type: BLOCK_TYPE, id: '1' } ],
				}

				const expectedState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
				assert.strictEqual(actualState.groups, initialState.groups)
			})

			it(`should not change the state if the block type ID doesn't exist in the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE,
					payload: { blockTypeId: '1' },
				}

				const initialState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const expectedState = {
					collection: {},
					groups: {},
					structure: [],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
				assert.strictEqual(blockTypesReducer(initialState, action), initialState)
			})

			it(`should remove a block type and leave the rest`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE,
					payload: { blockTypeId: '2' },
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
			})
		})

		describe('MOVE_BLOCK_TYPE', function()
		{
			it(`should not change the state if the block type ID doesn't exist in the store`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE,
					payload: {
						blockTypeId: '1',
						index: 1,
					},
				}

				const initialState = {
					collection: { '2': createDummyBlockType('2') },
					groups: {},
					structure: [ { type: BLOCK_TYPE, id: '2' } ],
				}

				const expectedState = {
					collection: { '2': createDummyBlockType('2') },
					groups: {},
					structure: [ { type: BLOCK_TYPE, id: '2' } ],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
				assert.strictEqual(blockTypesReducer(initialState, action), initialState)
			})

			it(`should move the block type to the requested index`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE,
					payload: {
						blockTypeId: '3',
						index: 1,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '3' },
						{ type: BLOCK_TYPE, id: '2' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})

			it(`should move the block type to the requested negative index`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE,
					payload: {
						blockTypeId: '1',
						index: -1,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
						'3': createDummyBlockType('3'),
					},
					groups: {},
					structure: [
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE, id: '3' },
						{ type: BLOCK_TYPE, id: '1' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})
		})

		describe('ADD_BLOCK_TYPE_GROUP', function()
		{
			it(`should add a block type group to the store`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE_GROUP,
					payload: { blockTypeGroup: createDummyBlockTypeGroup('1') },
				}

				const initialState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const expectedState = {
					collection: {},
					groups: { '1': createDummyBlockTypeGroup('1') },
					structure: [ { type: BLOCK_TYPE_GROUP, id: '1' } ],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
				assert.strictEqual(actualState.collection, initialState.collection)
			})

			it(`should add a block type group at the requested index`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE_GROUP,
					payload: {
						blockTypeGroup: createDummyBlockTypeGroup('4'),
						index: 3,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
						'4': createDummyBlockTypeGroup('4'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '4' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})

			it(`should add a block type group at the requested negative index`, function()
			{
				const action = {
					type: ADD_BLOCK_TYPE_GROUP,
					payload: {
						blockTypeGroup: createDummyBlockTypeGroup('4'),
						index: -2,
					},
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
						'4': createDummyBlockTypeGroup('4'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '4' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})
		})

		describe('REMOVE_BLOCK_TYPE_GROUP', function()
		{
			it(`should remove a block type group from the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE_GROUP,
					payload: { blockTypeGroupId: '1' },
				}

				const initialState = {
					collection: {},
					groups: { '1': createDummyBlockTypeGroup('1') },
					structure: [ { type: BLOCK_TYPE_GROUP, id: '1' } ],
				}

				const expectedState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
				assert.strictEqual(actualState.collection, initialState.collection)
			})

			it(`should not change the state if the block type group ID doesn't exist in the store`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE_GROUP,
					payload: { blockTypeGroupId: '1' },
				}

				const initialState = {
					collection: {},
					groups: {},
					structure: [],
				}

				const expectedState = {
					collection: {},
					groups: {},
					structure: [],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
				assert.strictEqual(blockTypesReducer(initialState, action), initialState)
			})

			it(`should remove a block type group and leave the rest`, function()
			{
				const action = {
					type: REMOVE_BLOCK_TYPE_GROUP,
					payload: { blockTypeGroupId: '2' },
				}

				const initialState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const expectedState = {
					collection: {
						'1': createDummyBlockType('1'),
						'2': createDummyBlockType('2'),
					},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE, id: '1' },
						{ type: BLOCK_TYPE, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
			})
		})

		describe('MOVE_BLOCK_TYPE_GROUP', function()
		{
			it(`should not change the state if the block type group ID doesn't exist in the store`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE_GROUP,
					payload: {
						blockTypeGroupId: '1',
						index: 1,
					},
				}

				const initialState = {
					collection: {},
					groups: { '2': createDummyBlockTypeGroup('2') },
					structure: [ { type: BLOCK_TYPE_GROUP, id: '2' } ],
				}

				const expectedState = {
					collection: {},
					groups: { '2': createDummyBlockTypeGroup('2') },
					structure: [ { type: BLOCK_TYPE_GROUP, id: '2' } ],
				}

				assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
				assert.strictEqual(blockTypesReducer(initialState, action), initialState)
			})

			it(`should move the block type group to the requested index`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE_GROUP,
					payload: {
						blockTypeGroupId: '3',
						index: 1,
					},
				}

				const initialState = {
					collection: {},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const expectedState = {
					collection: {},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})

			it(`should move the block type group to the requested negative index`, function()
			{
				const action = {
					type: MOVE_BLOCK_TYPE_GROUP,
					payload: {
						blockTypeGroupId: '1',
						index: -1,
					},
				}

				const initialState = {
					collection: {},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '1' },
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
					],
				}

				const expectedState = {
					collection: {},
					groups: {
						'1': createDummyBlockTypeGroup('1'),
						'2': createDummyBlockTypeGroup('2'),
						'3': createDummyBlockTypeGroup('3'),
					},
					structure: [
						{ type: BLOCK_TYPE_GROUP, id: '2' },
						{ type: BLOCK_TYPE_GROUP, id: '3' },
						{ type: BLOCK_TYPE_GROUP, id: '1' },
					],
				}

				const actualState = blockTypesReducer(initialState, action)

				assert.deepEqual(actualState, expectedState)
			})
		})
	})
})
