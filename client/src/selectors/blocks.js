import { createSelector } from 'reselect'

const getBlocks = (state) => state.blocks
const getBlockStructure = (state) => state.blockStructure

export const getBlocksAsTree = createSelector(
	[ getBlocks, getBlockStructure ],
	function(blocks, blockStructure)
	{
		const tree = []
		const parentStack = []

		for(let item of blockStructure)
		{
			const block = blocks[item.id]
			const treeItem = { block, level: item.level, children: [] }
			let peekItem

			// Remove parent blocks until either empty, or a parent block is only one level below this one -
			// consequently, it'll be the parent of the block
			while((peekItem = parentStack[parentStack.length - 1]) && item.level <= peekItem.level)
			{
				parentStack.pop()
			}

			// If there are no blocks in the stack, it must be a root level block, otherwise, the block at the top of
			// the stack will be the parent
			peekItem = parentStack[parentStack.length - 1]
			const branch = peekItem ? peekItem.children : tree
			branch.push(treeItem)

			// The current block may potentially be a parent block as well, so save it to the stack
			parentStack.push(treeItem)
		}

		return tree
	}
)
