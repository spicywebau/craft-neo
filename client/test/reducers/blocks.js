import assert from 'assert'
import reducer from '../../src/reducers/blocks'
import { ADD_BLOCK, REMOVE_BLOCK } from '../../src/constants/actions'

describe('blocksReducer()', function()
{
	describe('ADD_BLOCK', function()
	{
		it('should add a block to the store', function()
		{
			assert.deepEqual(
				reducer({}, {
					type: ADD_BLOCK,
					payload: {
						block: {
							id: 'id',
							blockType: 'blockType',
						}
					}
				}),
				{
					id: {
						id: 'id',
						blockType: 'blockType',
						enabled: true,
						errors: [],
						html: '',
						css: '',
						js: '',
					}
				}
			)
		})
	})
})
