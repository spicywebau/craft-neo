import assert from 'assert'
import { generateNewId, resolveIndex } from '../data'

describe(`Data`, function()
{
	describe(`generateNewId()`, function()
	{
		it(`should return a string`, function()
		{
			assert.equal(typeof generateNewId(), 'string')
		})

		it(`should generate all unique values among the functions range`, function()
		{
			const ids = new Set()
			for(let i = 0; i < 10**4; i++)
			{
				const newId = generateNewId()
				assert.ok(!ids.has(newId))
				ids.add(newId)
			}
		})
	})

	describe(`resolveIndex()`, function()
	{
		it(`should return the same index when in range`, function()
		{
			assert.equal(resolveIndex(5, 10), 5)
		})

		it(`should return zero when zero is provided as the index`, function()
		{
			assert.equal(resolveIndex(0, 10), 0)
		})

		it(`should return zero when the index is equal to the range`, function()
		{
			assert.equal(resolveIndex(10, 10), 0)
		})

		it(`should return zero when the index is some multiple of the range`, function()
		{
			assert.equal(resolveIndex(30, 10), 0)
		})

		it(`should wrap the index if significantly greater than the range`, function()
		{
			assert.equal(resolveIndex(25, 10), 5)
		})

		it(`should return one less than the limit when negative one is provided as the index`, function()
		{
			assert.equal(resolveIndex(-1, 10), 9)
		})

		it(`should return zero when the index is the negative of the range`, function()
		{
			assert.equal(resolveIndex(-10, 10), 0)
		})

		it(`should wrap the index if significantly less than the negative of the range`, function()
		{
			assert.equal(resolveIndex(-25, 10), 5)
		})
	})
})
