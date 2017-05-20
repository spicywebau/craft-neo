export default const state = {

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
					template: {
						html: '',
						css: '',
						js: ''
					}
				}
			]
		}
	*/ },

	blockTypeGroups: { /*
		id: { name: "Group" },
	*/ },

	blockTypeStructure: [ /*
		{ type: BLOCK_TYPE, id: 'id' },
		{ type: BLOCK_TYPE_GROUP, id: 'id' }
	*/ ],

	blocks: { /*
		id: {
			blockType: 'id',
			enabled: true,
			errors: [],
			template: {
				html: '',
				css: '',
				js: ''
			}
		}
	*/ },

	blockStructure: [ /*
		{ id: 'id', level: 1 }
	*/ ],
}
