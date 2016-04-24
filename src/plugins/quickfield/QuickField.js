const newFields = {}
const newGroups = {}

export default window.QuickField ? QuickField.extend({

	addField(id, name, groupName)
	{
		this.base(id, name, groupName)
		newFields[id] = {name: name, groupName: groupName}
	},

	removeField(id)
	{
		this.base(id)
		delete newFields[id]
	},

	resetField: function(id, groupName, name)
	{
		this.base(id, groupName, name)
		newFields[id] = {name: name, groupName: groupName}
	},

	addGroup(id, name)
	{
		this.base(id, name)
		newGroups[id] = {name: name}
	}

}, {

	getNewFields()
	{
		return Object.assign({}, newFields)
	},

	getNewGroups()
	{
		return Object.assign({}, newGroups)
	}
}) : false
