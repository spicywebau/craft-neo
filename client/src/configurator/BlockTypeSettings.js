import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'
import NS from '../namespace'
import Settings from './Settings'

const _defaults = {
  namespace: [],
  id: null,
  sortOrder: 0,
  fieldLayoutId: null,
  fieldLayoutConfig: null,
  name: '',
  handle: '',
  description: '',
  maxBlocks: 0,
  maxSiblingBlocks: 0,
  minChildBlocks: 0,
  maxChildBlocks: 0,
  topLevel: true,
  childBlocks: null,
  childBlockTypes: [],
  html: '',
  js: '',
  errors: {}
}

export default Settings.extend({

  _templateNs: [],
  _childBlockTypes: [],
  _initialised: false,

  $sortOrderInput: new $(),
  $nameInput: new $(),
  $handleInput: new $(),
  $descriptionInput: new $(),
  $maxBlocksInput: new $(),
  $maxSiblingBlocksInput: new $(),
  $minChildBlocksInput: new $(),
  $maxChildBlocksInput: new $(),

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._childBlockTypes = []
    this._childBlocks = settings.childBlocks
    this._id = settings.id
    this._fieldLayoutId = settings.fieldLayoutId
    this._fieldLayoutConfig = settings.fieldLayoutConfig
    this._errors = settings.errors
    this._js = settings.js
    this._settingsChildBlockTypes = settings.childBlockTypes
    this.$container = $(settings.html)

    const $neo = this.$container.find('[data-neo-bts]')
    this.$sortOrderInput = $neo.filter('[data-neo-bts="input.sortOrder"]')
    this.$nameInput = $neo.filter('[data-neo-bts="input.name"]')
    this.$handleInput = $neo.filter('[data-neo-bts="input.handle"]')
    this.$descriptionInput = $neo.filter('[data-neo-bts="input.description"]')
    this.$maxBlocksInput = $neo.filter('[data-neo-bts="input.maxBlocks"]')
    this.$maxSiblingBlocksInput = $neo.filter('[data-neo-bts="input.maxSiblingBlocks"]')
    this.$minChildBlocksInput = $neo.filter('[data-neo-bts="input.minChildBlocks"]')
    this.$minChildBlocksContainer = $neo.filter('[data-neo-bts="container.minChildBlocks"]')
    this.$maxChildBlocksInput = $neo.filter('[data-neo-bts="input.maxChildBlocks"]')
    this.$maxChildBlocksContainer = $neo.filter('[data-neo-bts="container.maxChildBlocks"]')
    this.$topLevelInput = $neo.filter('[data-neo-bts="input.topLevel"]')
    this.$topLevelContainer = $neo.filter('[data-neo-bts="container.topLevel"]')
    this.$childBlocksInput = $neo.filter('[data-neo-bts="input.childBlocks"]')
    this.$childBlocksContainer = $neo.filter('[data-neo-bts="container.childBlocks"]')
    this.$deleteButton = $neo.filter('[data-neo-bts="button.delete"]')

    this.setSortOrder(settings.sortOrder)
    this.setName(settings.name)
    this.setHandle(settings.handle)
    this.setDescription(settings.description)
    this.setMaxBlocks(settings.maxBlocks)
    this.setMaxSiblingBlocks(settings.maxSiblingBlocks)
    this.setMinChildBlocks(settings.minChildBlocks)
    this.setMaxChildBlocks(settings.maxChildBlocks)
    this.setTopLevel(settings.topLevel)
  },

  initUi () {
    if (this._initialised) {
      return
    }

    this.$foot = $(this._js)
    Garnish.$bod.append(this.$foot)

    Craft.initUiElements(this.$container)

    this._childBlocksSelect = this.$childBlocksInput.data('checkboxSelect')
    this._topLevelLightswitch = this.$topLevelInput.data('lightswitch')
    this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)

    // Ensure that an existing block type's handle will not be changed if the name is edited first.
    if (this.getHandle() !== '') {
      this._handleGenerator.stopListening()
    }

    for (const blockType of this._settingsChildBlockTypes) {
      this.addChildBlockType(blockType)
    }

    this.setChildBlocks(this._childBlocks)

    this.addListener(this.$nameInput, 'keyup change', () => {
      this.setName(this.$nameInput.val())

      if (this._handleGenerator.listening) {
        // Wait for the handle to be auto-updated
        setTimeout(() => this.setHandle(this.$handleInput.val()), 200)
      }
    })

    this.addListener(this.$handleInput, 'keyup change textchange', () => this.setHandle(this.$handleInput.val()))
    this.addListener(this.$descriptionInput, 'keyup change textchange', () => this.setDescription(this.$descriptionInput.val()))
    this.addListener(this.$maxBlocksInput, 'keyup change', () => this.setMaxBlocks(this.$maxBlocksInput.val()))
    this.addListener(this.$maxSiblingBlocksInput, 'keyup change', () => this.setMaxSiblingBlocks(this.$maxSiblingBlocksInput.val()))
    this.addListener(this.$minChildBlocksInput, 'keyup change', () => this.setMinChildBlocks(this.$minChildBlocksInput.val()))
    this.addListener(this.$maxChildBlocksInput, 'keyup change', () => this.setMaxChildBlocks(this.$maxChildBlocksInput.val()))
    this.addListener(this._topLevelLightswitch, 'change', () => this.setTopLevel(this._topLevelLightswitch.on))
    this.addListener(this.$deleteButton, 'click', () => {
      if (window.confirm(Craft.t('neo', 'Are you sure you want to delete this block type?'))) {
        this.destroy()
      }
    })

    this.$childBlocksInput.on('change', 'input', () => this._refreshChildBlockSettings())

    this._initialised = true
  },

  _generateChildBlocksCheckbox (settings) {
    NS.enter(this._templateNs)
    const id = NS.value('childBlock-' + settings.getId(), '-')
    const name = NS.fieldName('childBlocks')
    NS.leave()

    return $(`
      <div>
        <input type="checkbox" value="${settings.getHandle()}" id="${id}" class="checkbox" name="${name}[]" data-neo-btsc="input">
        <label for="${id}" data-neo-btsc="text.label">${settings.getName()}</label>
      </div>`)
  },

  getFocusInput () {
    return this.$nameInput
  },

  getId () {
    return this._id
  },

  getFieldLayoutId () {
    return this._fieldLayoutId
  },

  getFieldLayoutConfig () {
    return Object.assign({}, this._fieldLayoutConfig)
  },

  isNew () {
    return /^new/.test(this.getId())
  },

  getErrors () {
    return this._errors
  },

  setSortOrder (sortOrder) {
    this.base(sortOrder)

    this.$sortOrderInput.val(this.getSortOrder())
  },

  getName () { return this._name },
  setName (name) {
    if (name !== this._name) {
      const oldName = this._name
      this._name = name

      if (this.$nameInput.val() !== this._name) {
        this.$nameInput.val(this._name)
      }

      this.trigger('change', {
        property: 'name',
        oldValue: oldName,
        newValue: this._name
      })
    }
  },

  getHandle () { return this._handle },
  setHandle (handle) {
    if (handle !== this._handle) {
      const oldHandle = this._handle
      this._handle = handle

      if (this.$handleInput.val() !== this._handle) {
        this.$handleInput.val(this._handle)
      }

      this.trigger('change', {
        property: 'handle',
        oldValue: oldHandle,
        newValue: this._handle
      })
    }
  },

  getDescription () { return this._description },
  setDescription (description) {
    if (description !== this._description) {
      const oldDescription = this._description
      this._description = description

      if (this.$descriptionInput.val() !== this._description) {
        this.$descriptionInput.val(this._description)
      }

      this.trigger('change', {
        property: 'description',
        oldValue: oldDescription,
        newValue: this._description
      })
    }
  },

  getMaxBlocks () { return this._maxBlocks },
  setMaxBlocks (maxBlocks) {
    const oldMaxBlocks = this._maxBlocks
    const newMaxBlocks = Math.max(0, maxBlocks | 0)

    if (newMaxBlocks === 0) {
      this.$maxBlocksInput.val(null)
    }

    if (oldMaxBlocks !== newMaxBlocks) {
      this._maxBlocks = newMaxBlocks

      if (this._maxBlocks > 0 && parseInt(this.$maxBlocksInput.val()) !== this._maxBlocks) {
        this.$maxBlocksInput.val(this._maxBlocks)
      }

      this.trigger('change', {
        property: 'maxBlocks',
        oldValue: oldMaxBlocks,
        newValue: this._maxBlocks
      })
    }
  },

  getMaxSiblingBlocks () { return this._maxSiblingBlocks },
  setMaxSiblingBlocks (maxSiblingBlocks) {
    const oldMaxSiblingBlocks = this._maxSiblingBlocks
    const newMaxSiblingBlocks = Math.max(0, maxSiblingBlocks | 0)

    if (newMaxSiblingBlocks === 0) {
      this.$maxSiblingBlocksInput.val(null)
    }

    if (oldMaxSiblingBlocks !== newMaxSiblingBlocks) {
      this._maxSiblingBlocks = newMaxSiblingBlocks

      if (this._maxSiblingBlocks > 0 && parseInt(this.$maxSiblingBlocksInput.val()) !== this._maxSiblingBlocks) {
        this.$maxSiblingBlocksInput.val(this._maxSiblingBlocks)
      }

      this.trigger('change', {
        property: 'maxSiblingBlocks',
        oldValue: oldMaxSiblingBlocks,
        newValue: this._maxSiblingBlocks
      })
    }
  },

  getMinChildBlocks () { return this._minChildBlocks },
  getMaxChildBlocks () { return this._maxChildBlocks },
  setMinChildBlocks (minChildBlocks) { this._setBlocksConstraint('minChildBlocks', minChildBlocks) },
  setMaxChildBlocks (maxChildBlocks) { this._setBlocksConstraint('maxChildBlocks', maxChildBlocks) },
  _setBlocksConstraint (mode, value) {
    const privateProp = `_${mode}`
    const jqueryProp = `$${mode}Input`
    const oldValue = this[privateProp]
    const newValue = Math.max(0, value | 0)

    if (newValue === 0) {
      this[jqueryProp].val(null)
    }

    if (oldValue !== newValue) {
      this[privateProp] = newValue

      if (this[privateProp] > 0 && parseInt(this[jqueryProp].val()) !== this[privateProp]) {
        this[jqueryProp].val(this[privateProp])
      }

      this.trigger('change', {
        property: mode,
        oldValue,
        newValue: this[privateProp]
      })
    }
  },

  getTopLevel () { return this._topLevel },
  setTopLevel (topLevel) {
    const oldTopLevel = this._topLevel
    const newTopLevel = !!topLevel

    if (oldTopLevel !== newTopLevel) {
      this._topLevel = newTopLevel

      if (this._topLevelLightswitch && this._topLevelLightswitch.on !== this._topLevel) {
        this._topLevelLightswitch.on = this._topLevel
        this._topLevelLightswitch.toggle()
      }

      this.trigger('change', {
        property: 'topLevel',
        oldValue: oldTopLevel,
        newValue: this._topLevel
      })
    }
  },

  getChildBlocks () {
    const select = this._childBlocksSelect
    const childBlocks = []

    if (typeof select === 'undefined') {
      return this._childBlocks === true ? true : Array.from(this._childBlocks ?? [])
    }

    if (select.$all.prop('checked')) {
      return true
    }

    select.$options.each(function (index) {
      const $option = $(this)

      if ($option.prop('checked')) {
        childBlocks.push($option.val())
      }
    })

    return childBlocks.length > 0 ? childBlocks : false
  },

  setChildBlocks (childBlocks) {
    const select = this._childBlocksSelect

    if (childBlocks === true || childBlocks === '*') {
      select.$all.prop('checked', true)
      select.onAllChange()
    } else if (Array.isArray(childBlocks)) {
      select.$all.prop('checked', false)

      for (const handle of childBlocks) {
        select.$options.filter(`[value="${handle}"]`).prop('checked', true)
      }
    } else {
      select.$all.prop('checked', false)
      select.$options.prop('checked', false)
    }

    this._refreshChildBlockSettings(false)
  },

  addChildBlockType (blockType) {
    if (!this._childBlockTypes.includes(blockType)) {
      const settings = blockType.getSettings()
      const $checkbox = this._generateChildBlocksCheckbox(settings)

      this._childBlockTypes.push(blockType)
      this.$childBlocksContainer.append($checkbox)

      this._refreshChildBlocks()

      const select = this._childBlocksSelect
      const allChecked = select.$all.prop('checked')
      select.$options = select.$options.add($checkbox.find('input'))
      if (allChecked) select.onAllChange()

      const eventNs = '.childBlock' + this.getId()
      settings.on('change' + eventNs, e => this['@onChildBlockTypeChange'](e, blockType, $checkbox))
      settings.on('destroy' + eventNs, e => this.removeChildBlockType(blockType))
    }
  },

  removeChildBlockType (blockType) {
    const index = this._childBlockTypes.indexOf(blockType)
    if (index >= 0) {
      this._childBlockTypes.splice(index, 1)

      const settings = blockType.getSettings()
      const $checkbox = this.$childBlocksContainer.children().eq(index)

      $checkbox.remove()

      const select = this._childBlocksSelect
      select.$options = select.$options.remove($checkbox.find('input'))

      const eventNs = '.childBlock' + this.getId()
      settings.off(eventNs)

      this._refreshChildBlocks()
    }
  },

  _refreshChildBlocks () {
    const blockTypes = Array.from(this._childBlockTypes)
    const $options = this.$childBlocksContainer.children()

    const getOption = blockType => $options.get(blockTypes.indexOf(blockType))

    this._childBlockTypes = this._childBlockTypes.sort((a, b) => a.getSettings().getSortOrder() - b.getSettings().getSortOrder())
    $options.remove()

    for (const blockType of this._childBlockTypes) {
      const $option = getOption(blockType)
      this.$childBlocksContainer.append($option)
    }
  },

  _refreshChildBlockSettings (animate) {
    this._refreshSetting(this.$minChildBlocksContainer, !!this.getChildBlocks(), animate)
    this._refreshSetting(this.$maxChildBlocksContainer, !!this.getChildBlocks(), animate)
  },

  '@onChildBlockTypeChange' (e, blockType, $checkbox) {
    const $neo = $checkbox.find('[data-neo-btsc]')
    const $input = $neo.filter('[data-neo-btsc="input"]')
    const $labelText = $neo.filter('[data-neo-btsc="text.label"]')

    switch (e.property) {
      case 'name':
        $labelText.text(e.newValue)
        break

      case 'handle':
        $input.val(e.newValue)
        break

      case 'sortOrder':
        this._refreshChildBlocks()
        break
    }
  }
},
{
  _totalNewBlockTypes: 0,

  getNewId () {
    return `new${this._totalNewBlockTypes++}`
  }
})
