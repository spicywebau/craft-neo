import assert from 'assert'
import blockTypesReducer from '../reducers'
import {
	ADD_BLOCK_TYPE, REMOVE_BLOCK_TYPE, MOVE_BLOCK_TYPE,
	ADD_BLOCK_TYPE_GROUP, REMOVE_BLOCK_TYPE_GROUP, MOVE_BLOCK_TYPE_GROUP,
} from '../constants'

describe(`Reducers`, function()
{
	describe(`blockTypesReducer()`, function()
	{
		it(`should not change the state after an invalid action`, function()
		{
			const action = {}

			const initialState = {
				collection: {},
				group: {},
				structure: [],
			}

			const expectedState = {
				collection: {},
				group: {},
				structure: [],
			}

			assert.deepEqual(blockTypesReducer(initialState, action), expectedState)
			assert.strictEqual(blockTypesReducer(initialState, action), initialState)
		})

		describe('ADD_BLOCK_TYPE', function()
		{
			
		})

		describe('REMOVE_BLOCK_TYPE', function()
		{

		})

		describe('MOVE_BLOCK_TYPE', function()
		{

		})

		describe('ADD_BLOCK_TYPE_GROUP', function()
		{
			
		})

		describe('REMOVE_BLOCK_TYPE_GROUP', function()
		{

		})

		describe('MOVE_BLOCK_TYPE_GROUP', function()
		{

		})
	})
})
