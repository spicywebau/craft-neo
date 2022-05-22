import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'
import NS from '../namespace'
import Settings from './Settings'

const _defaults = {
  namespace: [],
  id: null,
  sortOrder: 0,
  name: '',
  handle: '',
  description: '',
  icon: '',
  maxBlocks: 0,
  maxSiblingBlocks: 0,
  maxChildBlocks: 0,
  topLevel: true,
  childBlocks: null,
  childBlockTypes: [],
  errors: {}
}

export default Settings.extend({

  _templateNs: [],
  _childBlockTypes: [],

  $sortOrderInput: new $(),
  $nameInput: new $(),
  $handleInput: new $(),
  $descriptionInput: new $(),
  $iconInput: new $(),
  $maxBlocksInput: new $(),
  $maxSiblingBlocksInput: new $(),
  $maxChildBlocksInput: new $(),

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._childBlockTypes = []
    this._id = settings.id
    this._errors = settings.errors
    this.setSortOrder(settings.sortOrder)
    this.setName(settings.name)
    this.setHandle(settings.handle)
    this.setDescription(settings.description)
    this.setIcon(settings.icon)
    this.setMaxBlocks(settings.maxBlocks)
    this.setMaxSiblingBlocks(settings.maxSiblingBlocks)
    this.setMaxChildBlocks(settings.maxChildBlocks)
    this.setTopLevel(settings.topLevel)

    this.$container = this._generateBlockTypeSettings()

    const $neo = this.$container.find('[data-neo-bts]')
    this.$sortOrderInput = $neo.filter('[data-neo-bts="input.sortOrder"]')
    this.$nameInput = $neo.filter('[data-neo-bts="input.name"]')
    this.$handleInput = $neo.filter('[data-neo-bts="input.handle"]')
    this.$descriptionInput = $neo.filter('[data-neo-bts="input.description"]')
    this.$iconInput = $neo.filter('[data-neo-bts="input.icon"]')
    this.$maxBlocksInput = $neo.filter('[data-neo-bts="input.maxBlocks"]')
    this.$maxSiblingBlocksInput = $neo.filter('[data-neo-bts="input.maxSiblingBlocks"]')
    this.$maxChildBlocksInput = $neo.filter('[data-neo-bts="input.maxChildBlocks"]')
    this.$maxChildBlocksContainer = $neo.filter('[data-neo-bts="container.maxChildBlocks"]')
    this.$topLevelInput = $neo.filter('[data-neo-bts="input.topLevel"]')
    this.$topLevelContainer = $neo.filter('[data-neo-bts="container.topLevel"]')
    this.$childBlocksInput = $neo.filter('[data-neo-bts="input.childBlocks"]')
    this.$childBlocksContainer = $neo.filter('[data-neo-bts="container.childBlocks"]')
    this.$deleteButton = $neo.filter('[data-neo-bts="button.delete"]')

    Craft.initUiElements(this.$container)

    this._childBlocksSelect = this.$childBlocksInput.data('checkboxSelect')
    this._topLevelLightswitch = this.$topLevelInput.data('lightswitch')
    this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)

    // Ensure that an existing block type's handle will not be changed if the name is edited first.
    if (this.getHandle() !== '') {
      this._handleGenerator.stopListening()
    }

    for (const blockType of settings.childBlockTypes) {
      this.addChildBlockType(blockType)
    }

    this.setChildBlocks(settings.childBlocks)

    // LightSwitch accidentally overrides the `on()` method by using `on` as a property...
    Garnish.Base.prototype.on.call(this._topLevelLightswitch, 'change', () => this.setTopLevel(this._topLevelLightswitch.on))

    this.addListener(this.$nameInput, 'keyup change', () => {
      this.setName(this.$nameInput.val())

      if (this._handleGenerator.listening) {
        // Wait for the handle to be auto-updated
        setTimeout(() => this.setHandle(this.$handleInput.val()), 200)
      }
    })

    this.addListener(this.$handleInput, 'keyup change textchange', () => this.setHandle(this.$handleInput.val()))
    this.addListener(this.$descriptionInput, 'keyup change textchange', () => this.setDescription(this.$descriptionInput.val()))
    this.addListener(this.$iconInput, 'keyup change textchange', () => this.setIcon(this.$iconInput.val()))
    this.addListener(this.$maxBlocksInput, 'keyup change', () => this.setMaxBlocks(this.$maxBlocksInput.val()))
    this.addListener(this.$maxSiblingBlocksInput, 'keyup change', () => this.setMaxSiblingBlocks(this.$maxSiblingBlocksInput.val()))
    this.addListener(this.$maxChildBlocksInput, 'keyup change', () => this.setMaxChildBlocks(this.$maxChildBlocksInput.val()))
    this.addListener(this.$deleteButton, 'click', () => {
      if (window.confirm(Craft.t('neo', 'Are you sure you want to delete this block type?'))) {
        this.destroy()
      }
    })

    this.$childBlocksInput.on('change', 'input', () => this._refreshMaxChildBlocks())
  },

  _generateBlockTypeSettings () {
    const errors = this.getErrors()
    const maxBlocks = this.getMaxBlocks()
    const maxSiblingBlocks = this.getMaxSiblingBlocks()
    const maxChildBlocks = this.getMaxChildBlocks()
    NS.enter(this._templateNs)
    const sortOrderName = NS.fieldName('sortOrder')
    const nameInputId = NS.value('name', '-')
    const nameInputName = NS.fieldName('name')
    const handleInputId = NS.value('handle', '-')
    const handleInputName = NS.fieldName('handle')
    const descriptionInputId = NS.value('description', '-')
    const descriptionInputName = NS.fieldName('description')
    const iconInputId = NS.value('icon', '-')
    const iconInputName = NS.fieldName('icon')
    const maxBlocksInputId = NS.value('maxBlocks', '-')
    const maxBlocksInputName = NS.fieldName('maxBlocks')
    const maxSiblingBlocksInputId = NS.value('maxSiblingBlocks', '-')
    const maxSiblingBlocksInputName = NS.fieldName('maxSiblingBlocks')
    const childBlocksInputId = NS.value('childBlocks', '-')
    const childBlocksInputName = NS.fieldName('childBlocks')
    const maxChildBlocksInputId = NS.value('maxChildBlocks', '-')
    const maxChildBlocksInputName = NS.fieldName('maxChildBlocks')
    const topLevelInputId = NS.value('topLevel', '-')
    const topLevelInputName = NS.fieldName('topLevel')
    NS.leave()

    const $nameInput = Craft.ui.createTextField({
      type: 'text',
      id: nameInputId,
      name: nameInputName,
      label: Craft.t('neo', 'Name'),
      instructions: Craft.t('neo', 'What this block type will be called in the CP.'),
      required: true,
      value: this.getName(),
      errors: errors.name
    })
    $nameInput.find('input').attr('data-neo-bts', 'input.name')

    const $handleInput = Craft.ui.createTextField({
      type: 'text',
      id: handleInputId,
      name: handleInputName,
      label: Craft.t('neo', 'Handle'),
      instructions: Craft.t('neo', 'How you’ll refer to this block type in the templates.'),
      required: true,
      class: 'code',
      value: this.getHandle(),
      errors: errors.handle
    })
    $handleInput.find('input').attr('data-neo-bts', 'input.handle')

    const $descriptionInput = Craft.ui.createTextareaField({
      type: 'text',
      id: descriptionInputId,
      name: descriptionInputName,
      label: Craft.t('neo', 'Description'),
      required: false,
      value: this.getDescription(),
      errors: errors.handle
    })
    $descriptionInput.find('input').attr('data-neo-bts', 'input.description')

    const $iconInput = Craft.ui.createTextField({
      type: 'text',
      id: iconInputId,
      name: iconInputName,
      label: Craft.t('neo', 'Icon'),
      instructions: Craft.t('neo', 'Public URL to a svg icon which represents this block.'),
      required: false,
      value: this.getIcon(),
      errors: errors.icon
    })
    $iconInput.find('input').attr('data-neo-bts', 'input.icon')

    const $maxBlocksInput = Craft.ui.createTextField({
      type: 'number',
      id: maxBlocksInputId,
      name: maxBlocksInputName,
      label: Craft.t('neo', 'Max Blocks'),
      instructions: Craft.t('neo', 'The maximum number of blocks of this type the field is allowed to have.'),
      value: maxBlocks > 0 ? maxBlocks : null,
      min: 0,
      errors: errors.maxBlocks
    })
    $maxBlocksInput.find('input')
      .removeClass('fullwidth')
      .css('width', '80px')
      .attr('data-neo-bts', 'input.maxBlocks')

    const $maxSiblingBlocksInput = Craft.ui.createTextField({
      type: 'number',
      id: maxSiblingBlocksInputId,
      name: maxSiblingBlocksInputName,
      label: Craft.t('neo', 'Max Sibling Blocks of This Type'),
      instructions: Craft.t('neo', 'The maximum number of blocks of this type allowed under one parent block or at the top level.'),
      value: maxSiblingBlocks > 0 ? maxSiblingBlocks : null,
      min: 0,
      errors: errors.maxSiblingBlocks
    })
    $maxSiblingBlocksInput.find('input')
      .removeClass('fullwidth')
      .css('width', '80px')
      .attr('data-neo-bts', 'input.maxSiblingBlocks')

    const $childBlocksInput = Craft.ui.createField(
      $(`
        <fieldset class="checkbox-select" data-neo-bts="input.childBlocks">
          <div>
            <input type="hidden" name="${childBlocksInputName}">
            <input type="checkbox" value="*" id="${childBlocksInputId}" class="all checkbox" name="${childBlocksInputName}">
            <label for="${childBlocksInputId}"><strong>${Craft.t('neo', 'All')}</strong></label>
          </div>
          <div data-neo-bts="container.childBlocks"></div>
        </fieldset>`),
      {
        id: childBlocksInputId,
        label: Craft.t('neo', 'Child Blocks'),
        instructions: Craft.t('neo', 'Which block types do you want to allow as children?')
      }
    )

    const $maxChildBlocksInput = Craft.ui.createTextField({
      type: 'number',
      id: maxChildBlocksInputId,
      name: maxChildBlocksInputName,
      label: Craft.t('neo', 'Max Child Blocks'),
      instructions: Craft.t('neo', 'The maximum number of child blocks this block type is allowed to have.'),
      value: maxChildBlocks > 0 ? maxChildBlocks : null,
      min: 0,
      errors: errors.maxChildBlocks,
      attributes: {
        style: 'width: 80px;',
        'data-neo-bts': 'input.maxChildBlocks'
      }
    })
    $maxChildBlocksInput.find('input')
      .removeClass('fullwidth')
      .css('width', '80px')
      .attr('data-neo-bts', 'input.maxChildBlocks')

    const $topLevelInput = Craft.ui.createField(
      $(`
        <div class="lightswitch${this.getTopLevel() ? ' on' : ''}" tabindex="0" data-neo-bts="input.topLevel">
          <div class="lightswitch-container">
            <div class="label on"></div>
            <div class="handle"></div>
            <div class="label off"></div>
          </div>
          <input type="hidden" name="${topLevelInputName}" value="${this.getTopLevel() ? '1' : ''}">
        </div>`),
      {
        id: topLevelInputId,
        label: Craft.t('neo', 'Top Level'),
        instructions: Craft.t('neo', 'Will this block type be allowed at the top level?')
      }
    )

    return $(`
      <div>
        <input type="hidden" name="${sortOrderName}" value="${this.getSortOrder()}" data-neo-bts="input.sortOrder">
        <div>
          ${$('<div class="field"/>').append($nameInput).html()}
          ${$('<div class="field"/>').append($handleInput).html()}
          ${$('<div class="field"/>').append($descriptionInput).html()}
          ${$('<div class="field"/>').append($iconInput).html()}
          ${$('<div class="field"/>').append($maxBlocksInput).html()}
          ${$('<div class="field"/>').append($maxSiblingBlocksInput).html()}
          ${$('<div class="field"/>').append($childBlocksInput).html()}
          <div data-neo-bts="container.maxChildBlocks">
            ${$('<div class="field"/>').append($maxChildBlocksInput).html()}
          </div>
          <div data-neo-bts="container.topLevel">
            ${$('<div class="field"/>').append($topLevelInput).html()}
          </div>
        </div>
        <hr>
        <a class="error delete" data-neo-bts="button.delete">${Craft.t('neo', 'Delete block type')}</a>
      </div>`)
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

  getIcon () { return this._icon },
  setIcon (icon) {
    if (icon !== this._icon) {
      const oldIcon = this._icon
      this._icon = icon

      if (this.$iconInput.val() !== this._icon) {
        this.$iconInput.val(this._icon)
      }

      this.trigger('change', {
        property: 'icon',
        oldValue: oldIcon,
        newValue: this._icon
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

  getMaxChildBlocks () { return this._maxChildBlocks },
  setMaxChildBlocks (maxChildBlocks) {
    const oldMaxChildBlocks = this._maxChildBlocks
    const newMaxChildBlocks = Math.max(0, maxChildBlocks | 0)

    if (newMaxChildBlocks === 0) {
      this.$maxChildBlocksInput.val(null)
    }

    if (oldMaxChildBlocks !== newMaxChildBlocks) {
      this._maxChildBlocks = newMaxChildBlocks

      if (this._maxChildBlocks > 0 && parseInt(this.$maxChildBlocksInput.val()) !== this._maxChildBlocks) {
        this.$maxChildBlocksInput.val(this._maxChildBlocks)
      }

      this.trigger('change', {
        property: 'maxChildBlocks',
        oldValue: oldMaxChildBlocks,
        newValue: this._maxChildBlocks
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

    this._refreshMaxChildBlocks(false)
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

  _refreshMaxChildBlocks (animate) {
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
