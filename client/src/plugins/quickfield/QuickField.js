const newFields = {}
const newGroups = {}

export default window.QuickField
  ? window.QuickField.extend({

      addField (field, elementSelector) {
        this.base(field, elementSelector)
        newFields[field.id] = { field, elementSelector }
      },

      removeField (id) {
        this.base(id)
        delete newFields[id]
      },

      resetField (field, elementSelector) {
        this.base(field, elementSelector)
        newFields[field.id] = { field, elementSelector }
      },

      addGroup (group, resetFldGroups) {
        this.base(group, resetFldGroups)
        newGroups[group.id] = { name: group.name }
      },

      renameGroup (group, oldName) {
        this.base(group, oldName)
        newGroups[group.id] = { name: group.name }
      },

      removeGroup (id) {
        this.base(id)
        delete newGroups[id]
      }

    }, {

      getNewFields () {
        return Object.assign({}, newFields)
      },

      getNewGroups () {
        return Object.assign({}, newGroups)
      }
    })
  : false
