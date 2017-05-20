export default const state = {

	fields: { /*
		id: {
			name: "Field",
			handle: 'field'
		}
	*/ },

	blockTypes: { /*
		id: {
			name: "Block Type",
			handle: 'blockType',
			max: 10,
			top: true,
			children: [ 'id' ],
			maxChildren: 0,
			tabs: [
				{
					name: "Tab 1",
					fields: [
						{ id: 'id', required: false }
					]
				}
			]
		}
	*/ },

	blockTypeGroups: { /*
		id: {
			name: "Group",
			children: [ 'id' ]
		},
	*/ },

	blockTypeStructure: [ /*
		{ type: BLOCK_TYPE, id: 'id' },
		{ type: GROUP, id: 'id' }
	*/ ],
}
