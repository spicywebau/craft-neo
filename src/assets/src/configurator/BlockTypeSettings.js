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
  enabled: true,
  ignorePermissions: true,
  minBlocks: 0,
  maxBlocks: 0,
  minSiblingBlocks: 0,
  maxSiblingBlocks: 0,
  minChildBlocks: 0,
  maxChildBlocks: 0,
  topLevel: true,
  childBlocks: null,
  childBlockTypes: [],
  html: null,
  js: null,
  errors: {}
}

export default Settings.extend({

  _templateNs: [],
  _childBlockTypes: [],
  _initialised: false,

  $container: null,
  $nameInput: new $(),
  $handleInput: new $(),
  $descriptionInput: new $(),
  $minBlocksInput: new $(),
  $maxBlocksInput: new $(),
  $minSiblingBlocksInput: new $(),
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
    this._settingsChildBlockTypes = settings.childBlockTypes
    this._afterCreateContainer = () => {
      this.setName(settings.name)
      this.setHandle(settings.handle)
      this.setDescription(settings.description)
      this._setIconId(settings.iconId)
      this.setEnabled(settings.enabled)
      this.setIgnorePermissions(settings.ignorePermissions)
      this.setMinBlocks(settings.minBlocks)
      this.setMaxBlocks(settings.maxBlocks)
      this.setMinSiblingBlocks(settings.minSiblingBlocks)
      this.setMaxSiblingBlocks(settings.maxSiblingBlocks)
      this.setMinChildBlocks(settings.minChildBlocks)
      this.setMaxChildBlocks(settings.maxChildBlocks)
      this.setTopLevel(settings.topLevel)
    }

    if (settings.html !== null) {
      this.createContainer({
        html: settings.html,
        js: settings.js
      })
    }
  },

  createContainer (containerData) {
    // Only create it if it doesn't already exist
    if (this.$container !== null) {
      return
    }

    this.$container = $(containerData.html)
    this._js = containerData.js ?? ''

    const $neo = this.$container.find('[data-neo-bts]')
    this.$nameInput = $neo.filter('[data-neo-bts="input.name"]')
    this.$handleInput = $neo.filter('[data-neo-bts="input.handle"]')
    this.$descriptionInput = $neo.filter('[data-neo-bts="input.description"]')
    this.$iconIdContainer = $neo.filter('[data-neo-bts="container.iconId"]')
    this.$enabledInput = $neo.filter('[data-neo-bts="input.enabled"]')
    this.$enabledContainer = $neo.filter('[data-neo-bts="container.enabled"]')
    this.$ignorePermissionsInput = $neo.filter('[data-neo-bts="input.ignorePermissions"]')
    this.$ignorePermissionsContainer = $neo.filter('[data-neo-bts="container.ignorePermissions"]')
    this.$minBlocksInput = $neo.filter('[data-neo-bts="input.minBlocks"]')
    this.$maxBlocksInput = $neo.filter('[data-neo-bts="input.maxBlocks"]')
    this.$minSiblingBlocksInput = $neo.filter('[data-neo-bts="input.minSiblingBlocks"]')
    this.$maxSiblingBlocksInput = $neo.filter('[data-neo-bts="input.maxSiblingBlocks"]')
    this.$minChildBlocksInput = $neo.filter('[data-neo-bts="input.minChildBlocks"]')
    this.$minChildBlocksContainer = $neo.filter('[data-neo-bts="container.minChildBlocks"]')
    this.$maxChildBlocksInput = $neo.filter('[data-neo-bts="input.maxChildBlocks"]')
    this.$maxChildBlocksContainer = $neo.filter('[data-neo-bts="container.maxChildBlocks"]')
    this.$topLevelInput = $neo.filter('[data-neo-bts="input.topLevel"]')
    this.$topLevelContainer = $neo.filter('[data-neo-bts="container.topLevel"]')
    this.$groupChildBlockTypesInput = $neo.filter('[data-neo-bts="input.groupChildBlockTypes"]')
    this.$groupChildBlockTypesContainer = $neo.filter('[data-neo-bts="container.groupChildBlockTypes"]')
    this.$childBlocksInput = $neo.filter('[data-neo-bts="input.childBlocks"]')
    this.$childBlocksContainer = $neo.filter('[data-neo-bts="container.childBlocks"]')
    this.$deleteButton = $neo.filter('[data-neo-bts="button.delete"]')

    this._afterCreateContainer()
  },

  initUi () {
    if (this._initialised) {
      return
    }

    this.$foot = $(this._js)
    Garnish.$bod.append(this.$foot)

    Craft.initUiElements(this.$container)

    this._childBlocksSelect = this.$childBlocksInput.data('checkboxSelect')
    this._enabledLightswitch = this.$enabledInput.data('lightswitch')
    this._ignorePermissionsLightswitch = this.$ignorePermissionsInput.data('lightswitch')
    this._topLevelLightswitch = this.$topLevelInput.data('lightswitch')
    this._groupChildBlockTypesLightswitch = this.$groupChildBlockTypesInput.data('lightswitch')
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
    this.addListener(this.$iconIdContainer, 'change', () => {
      setTimeout(
        () => {
          const $iconIdInput = this.$iconIdInput
          const iconId = $iconIdInput.length > 0 ? $iconIdInput.val() : null
          this._setIconId(iconId)
        },
        500
      )
    })
    this.addListener(this._enabledLightswitch, 'change', () => this.setEnabled(this._enabledLightswitch.on))
    this.addListener(this._ignorePermissionsLightswitch, 'change', () => this.setIgnorePermissions(this._ignorePermissionsLightswitch.on))
    this.addListener(this.$minBlocksInput, 'keyup change', () => this.setMinBlocks(this.$minBlocksInput.val()))
    this.addListener(this.$maxBlocksInput, 'keyup change', () => this.setMaxBlocks(this.$maxBlocksInput.val()))
    this.addListener(this.$minSiblingBlocksInput, 'keyup change', () => this.setMinSiblingBlocks(this.$minSiblingBlocksInput.val()))
    this.addListener(this.$maxSiblingBlocksInput, 'keyup change', () => this.setMaxSiblingBlocks(this.$maxSiblingBlocksInput.val()))
    this.addListener(this.$minChildBlocksInput, 'keyup change', () => this.setMinChildBlocks(this.$minChildBlocksInput.val()))
    this.addListener(this.$maxChildBlocksInput, 'keyup change', () => this.setMaxChildBlocks(this.$maxChildBlocksInput.val()))
    this.addListener(this.$topLevelInput, 'change', () => this.setTopLevel(this._topLevelLightswitch.on))
    this.addListener(this.$groupChildBlockTypesInput, 'change', () => this.setTopLevel(this._groupChildBlockTypesLightswitch.on))
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
        <input type="checkbox" value="${settings.getHandle()}" id="${id}" class="checkbox" name="${name}[]" data-neo-btsc="input.${settings.getId()}">
        <label for="${id}" data-neo-btsc="text.label">${settings.getName()}</label>
      </div>`)
  },

  get $iconIdInput () {
    return this.$iconIdContainer.find('input[type="hidden"]')
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

  /**
   * @deprecated in 3.8.0
   */
  setSortOrder (_) {
    console.warn('BlockTypeSettings.setSortOrder() is deprecated and no longer used.')
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

  getIconId () { return this._iconId },
  _setIconId (iconId) {
    if (iconId !== this._iconId) {
      const oldIconId = this._iconId
      this._iconId = iconId

      if (this.$iconIdInput.val() !== this._iconId) {
        // TODO
        // This is normally where we would reset the input value, but since the icon ID setting is an asset field, we
        // would also need to update the element HTML. This would be good to implement in the future, and then this
        // method could be made public.
      }

      this.trigger('change', {
        property: 'iconId',
        oldValue: oldIconId,
        newValue: this._iconId
      })
    }
  },

  getEnabled () { return this._enabled },
  setEnabled (enabled) { this._setLightswitchField('enabled', enabled) },

  getIgnorePermissions () { return this._ignorePermissions },
  setIgnorePermissions (ignore) { this._setLightswitchField('ignorePermissions', ignore) },

  getMinBlocks () { return this._minBlocks },
  setMinBlocks (minBlocks) { this._setBlocksConstraint('minBlocks', minBlocks) },

  getMaxBlocks () { return this._maxBlocks },
  setMaxBlocks (maxBlocks) { this._setBlocksConstraint('maxBlocks', maxBlocks) },

  getMinSiblingBlocks () { return this._minSiblingBlocks },
  setMinSiblingBlocks (minSiblingBlocks) { this._setBlocksConstraint('minSiblingBlocks', minSiblingBlocks) },

  getMaxSiblingBlocks () { return this._maxSiblingBlocks },
  setMaxSiblingBlocks (maxSiblingBlocks) { this._setBlocksConstraint('maxSiblingBlocks', maxSiblingBlocks) },

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
  setTopLevel (topLevel) { this._setLightswitchField('topLevel', topLevel) },

  _setLightswitchField (property, value) {
    const privateProp = `_${property}`
    const lightswitchProp = `${privateProp}Lightswitch`
    const oldValue = this[privateProp]
    const newValue = !!value

    if (oldValue !== newValue) {
      this[privateProp] = newValue

      if (this[lightswitchProp] && this[lightswitchProp].on !== this[privateProp]) {
        this[lightswitchProp].on = this[privateProp]
        this[lightswitchProp].toggle()
      }

      this.trigger('change', {
        property,
        oldValue,
        newValue
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
      const $existingCheckbox = this.$childBlocksContainer.find(`[data-neo-btsc="input.${settings.getId()}"]`)
      const $checkbox = $existingCheckbox.length > 0
        ? $existingCheckbox
        : this._generateChildBlocksCheckbox(settings)

      this._childBlockTypes.push(blockType)

      if ($existingCheckbox.length === 0) {
        this.$childBlocksContainer.append($checkbox)
        this._refreshChildBlocks()
      }

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

  getConditions () {
    NS.enter(this._templateNs)
    const baseInputName = NS.toFieldName().replaceAll('\\', '\\\\')
    NS.leave()
    const baseConditionInputNameWithExtraSlash = `${baseInputName}[conditions]`
    const baseConditionInputName = baseConditionInputNameWithExtraSlash.replaceAll('\\\\', '\\')
    const conditionInputNames = this.$container
      .find(`[name^="${baseConditionInputNameWithExtraSlash}"]`)
      .get()
      .map((condition) => condition.name)

    const allFormData = new window.FormData(this.$container.closest('form').get(0))
    const conditionsData = {}

    conditionInputNames.forEach((conditionInputName) => {
      let conditionsSubData = conditionsData
      const conditionsCurrentPath = [baseConditionInputName]
      const conditionsDataPath = conditionInputName
        .replace(baseConditionInputName, '')
        .slice(1, -1)
        .split('][')

      conditionsDataPath.forEach((pathStep, i) => {
        conditionsCurrentPath.push(`[${pathStep}]`)

        if (pathStep !== '' && !(pathStep in conditionsSubData)) {
          if (pathStep === 'values') {
            conditionsSubData[pathStep] = []
          } else if (i < conditionsDataPath.length - 1) {
            conditionsSubData[pathStep] = {}
          } else {
            conditionsSubData[pathStep] = allFormData.get(conditionsCurrentPath.join(''))
          }
        } else if (pathStep === '') {
          conditionsSubData.push(...allFormData.getAll(conditionsCurrentPath.join('')))
        }

        conditionsSubData = conditionsSubData[pathStep]
      })
    })

    return conditionsData
  },

  _refreshChildBlocks () {
    const blockTypes = Array.from(this._childBlockTypes)
    const $options = this.$childBlocksContainer.children()

    const getOption = blockType => $options.get(blockTypes.indexOf(blockType))

    this._childBlockTypes = this._childBlockTypes.sort((a, b) => a.getSortOrder() - b.getSortOrder())
    $options.remove()

    for (const blockType of this._childBlockTypes) {
      const $option = getOption(blockType)
      this.$childBlocksContainer.append($option)
    }
  },

  _refreshChildBlockSettings (animate) {
    const showSettings = !!this.getChildBlocks()
    this._refreshSetting(this.$minChildBlocksContainer, showSettings, animate)
    this._refreshSetting(this.$maxChildBlocksContainer, showSettings, animate)
    this._refreshSetting(this.$groupChildBlockTypesContainer, showSettings, animate)
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
