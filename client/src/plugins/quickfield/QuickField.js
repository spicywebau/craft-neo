const newFields = {}
const newGroups = {}
const _history = []
let _trackHistory = true

export default window.QuickField
  ? window.QuickField.extend({

      addField (field, elementSelector) {
        this.base(field, elementSelector)
        newFields[field.id] = { field, elementSelector }

        if (_trackHistory) {
          this._addToHistory('addField', [field, elementSelector])
        }
      },

      removeField (id) {
        this.base(id)
        delete newFields[id]

        if (_trackHistory) {
          this._addToHistory('removeField', [id])
        }
      },

      resetField (field, elementSelector) {
        this.base(field, elementSelector)
        newFields[field.id] = { field, elementSelector }

        if (_trackHistory) {
          this._addToHistory('resetField', [field, elementSelector])
        }
      },

      addGroup (group, resetFldGroups) {
        this.base(group, resetFldGroups)
        newGroups[group.id] = { name: group.name }

        if (_trackHistory) {
          this._addToHistory('addGroup', [group, resetFldGroups])
        }
      },

      renameGroup (group, oldName) {
        this.base(group, oldName)
        newGroups[group.id] = { name: group.name }

        if (_trackHistory) {
          this._addToHistory('renameGroup', [group, oldName])
        }
      },

      removeGroup (id) {
        this.base(id)
        delete newGroups[id]

        if (_trackHistory) {
          this._addToHistory('removeGroup', [id])
        }
      },

      _addToHistory (action, data) {
        const len = _history.length

        if (!len || _history[len - 1].action !== action || !this._equalHistory(action, _history[len - 1].data, data)) {
          _history.push({ action, data })
        }
      },

      _equalHistory (action, data1, data2) {
        if (data1.length !== data2.length) {
          return false
        }

        const equalityCheck = (a, b) => action.startsWith('remove')
          ? a[0] === b[0]
          : a[0].id === b[0].id && a[1] === b[1]

        return equalityCheck(data1, data2)
      },

      applyHistory () {
        // Make sure applying the history won't add to the history
        _trackHistory = false
        _history.forEach(historyItem => this[historyItem.action](...historyItem.data))
        _trackHistory = true
      }

    }, {

      /**
       * @deprecated in 2.13.15; use `applyHistory()` instead
       */
      getNewFields () {
        return Object.assign({}, newFields)
      },

      /**
       * @deprecated in 2.13.15; use `applyHistory()` instead
       */
      getNewGroups () {
        return Object.assign({}, newGroups)
      }
    })
  : false
