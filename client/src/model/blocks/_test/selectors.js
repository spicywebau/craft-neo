import assert from 'assert'
import { getTree, getDescendants, getAncestors, getPrevSiblings, getNextSiblings } from '../selectors/structure'

describe(`Selectors`, function()
{
	describe(`Structure`, function()
	{
		describe(`getTree()`, function()
		{
			it(`should return an empty array from empty structure`, function()
			{
				assert.deepEqual(getTree([]), [])
			})

			it(`should return a single level array from non-nested structure`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 1 },
					{ id: '3', level: 1 },
				]

				const expectedState = [
					{ id: '1', level: 1, index: 0, children: [] },
					{ id: '2', level: 1, index: 1, children: [] },
					{ id: '3', level: 1, index: 2, children: [] },
				]

				assert.deepEqual(getTree(initialState), expectedState)
			})

			it(`should return a multi-level array from nested structure`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 2 },
					{ id: '5', level: 1 },
				]

				const expectedState = [
					{ id: '1', level: 1, index: 0, children: [
						{ id: '2', level: 2, index: 1, children: [
							{ id: '3', level: 3, index: 2, children: [] },
						] },
						{ id: '4', level: 2, index: 3, children: [] },
					] },
					{ id: '5', level: 1, index: 4, children: [] },
				]

				assert.deepEqual(getTree(initialState), expectedState)
			})
		})

		describe(`getDescendants()`, function()
		{
			it(`should return an empty array from empty structure`, function()
			{
				assert.deepEqual(getDescendants([]), [])
			})
			
			it(`should return the same structure without specifying an ID or depth`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 2 },
					{ id: '5', level: 1 },
				]

				const expectedState = [
					{ id: '1', level: 1, index: 0 },
					{ id: '2', level: 2, index: 1 },
					{ id: '3', level: 3, index: 2 },
					{ id: '4', level: 2, index: 3 },
					{ id: '5', level: 1, index: 4 },
				]

				assert.deepEqual(getDescendants(initialState), expectedState)
			})

			it(`should return the first two levels only`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 2 },
					{ id: '5', level: 3 },
					{ id: '6', level: 4 },
					{ id: '7', level: 1 },
				]

				const expectedState = [
					{ id: '1', level: 1, index: 0 },
					{ id: '2', level: 2, index: 1 },
					{ id: '4', level: 2, index: 3 },
					{ id: '7', level: 1, index: 6 },
				]

				assert.deepEqual(getDescendants(initialState, null, 2), expectedState)
			})

			it(`should return an empty array from an item with no descendants`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
				]

				const expectedState = []

				assert.deepEqual(getDescendants(initialState, '1'), expectedState)
			})

			it(`should return all descendants of one item`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 1 },
					{ id: '5', level: 2 },
				]

				const expectedState = [
					{ id: '2', level: 2, index: 1 },
					{ id: '3', level: 3, index: 2 },
				]

				assert.deepEqual(getDescendants(initialState, '1'), expectedState)
			})

			it(`should return all children (first level descendants) of one item`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 1 },
					{ id: '5', level: 2 },
				]

				const expectedState = [
					{ id: '2', level: 2, index: 1 },
				]

				assert.deepEqual(getDescendants(initialState, '1', 1), expectedState)
			})
		})

		describe(`getAncestors()`, function()
		{
			it(`should return an empty array from an item with no ancestors`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
				]

				const expectedState = []

				assert.deepEqual(getAncestors(initialState, '1'), expectedState)
			})

			it(`should return all ancestors of one item`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 1 },
					{ id: '4', level: 2 },
					{ id: '5', level: 3 },
				]

				const expectedState = [
					{ id: '4', level: 2, index: 3 },
					{ id: '3', level: 1, index: 2 },
				]

				assert.deepEqual(getAncestors(initialState, '5'), expectedState)
			})

			it(`should return the parent (first ancestor) of one item`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 1 },
					{ id: '4', level: 2 },
					{ id: '5', level: 3 },
				]

				const expectedState = [
					{ id: '4', level: 2, index: 3 },
				]

				assert.deepEqual(getAncestors(initialState, '5', 1), expectedState)
			})
		})

		describe(`getPrevSiblings()`, function()
		{
			it(`should return an empty array for an item with no previous siblings`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
				]

				const expectedState = []

				assert.deepEqual(getPrevSiblings(initialState, '1'), expectedState)
			})

			it(`should return all previous siblings`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 1 },
					{ id: '3', level: 1 },
				]

				const expectedState = [
					{ id: '1', level: 1, index: 0 },
					{ id: '2', level: 1, index: 1 },
				]

				assert.deepEqual(getPrevSiblings(initialState, '3'), expectedState)
			})

			it(`should return all previous siblings within the same level`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 1 },
					{ id: '5', level: 2 },
					{ id: '6', level: 3 },
					{ id: '7', level: 3 },
					{ id: '8', level: 4 },
					{ id: '9', level: 3 },
				]

				const expectedState = [
					{ id: '6', level: 3, index: 5 },
					{ id: '7', level: 3, index: 6 },
				]

				assert.deepEqual(getPrevSiblings(initialState, '9'), expectedState)
			})
		})

		describe(`getNextSiblings()`, function()
		{
			it(`should return an empty array for an item with no next siblings`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
				]

				const expectedState = []

				assert.deepEqual(getNextSiblings(initialState, '1'), expectedState)
			})

			it(`should return all next siblings`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 1 },
					{ id: '3', level: 1 },
				]

				const expectedState = [
					{ id: '2', level: 1, index: 1 },
					{ id: '3', level: 1, index: 2 },
				]

				assert.deepEqual(getNextSiblings(initialState, '1'), expectedState)
			})

			it(`should return all next siblings within the same level`, function()
			{
				const initialState = [
					{ id: '1', level: 1 },
					{ id: '2', level: 2 },
					{ id: '3', level: 3 },
					{ id: '4', level: 1 },
					{ id: '5', level: 2 },
					{ id: '6', level: 3 },
					{ id: '7', level: 3 },
					{ id: '8', level: 4 },
					{ id: '9', level: 3 },
				]

				const expectedState = [
					{ id: '7', level: 3, index: 6 },
					{ id: '9', level: 3, index: 8 },
				]

				assert.deepEqual(getNextSiblings(initialState, '6'), expectedState)
			})
		})
	})
})
