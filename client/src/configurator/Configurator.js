import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'
import { v4 as uuidv4 } from 'uuid'
import NS from '../namespace'

import BlockType from './BlockType'
import BlockTypeSettings from './BlockTypeSettings'
import BlockTypeFieldLayout from './BlockTypeFieldLayout'
import Group from './Group'
import GroupSettings from './GroupSettings'
import './styles/configurator.scss'

const _defaults = {
  namespace: [],
  blockTypes: [],
  groups: [],
  fieldLayoutHtml: ''
}

export default Garnish.Base.extend({

  _templateNs: [],
  _items: [],

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    const inputIdPrefix = Craft.formatInputId(settings.namespace)
    const $field = $(`#${inputIdPrefix}-neo-configurator`)
    const $input = $field.children('.field').children('.input')

    this._templateNs = NS.parse(settings.namespace)
    this._fieldLayoutHtml = settings.fieldLayoutHtml
    this._items = []

    this.$container = this._generateConfigurator()
    $input.append(this.$container)

    const $neo = this.$container.find('[data-neo]')
    this.$mainContainer = $neo.filter('[data-neo="container.main"]')
    this.$sidebarContainer = $neo.filter('[data-neo="container.sidebar"]')
    this.$blockTypesContainer = $neo.filter('[data-neo="container.blockTypes"]')
    this.$settingsContainer = $neo.filter('[data-neo="container.settings"]')
    this.$fieldLayoutContainer = $neo.filter('[data-neo="container.fieldLayout"]')
    this.$blockTypeButton = $neo.filter('[data-neo="button.blockType"]')
    this.$groupButton = $neo.filter('[data-neo="button.group"]')
    this.$settingsButton = $neo.filter('[data-neo="button.settings"]')
    this.$fieldLayoutButton = $neo.filter('[data-neo="button.fieldLayout"]')

    this._itemSort = new Garnish.DragSort(null, {
      container: this.$blockTypeItemsContainer,
      handle: '[data-neo-bt="button.move"], [data-neo-g="button.move"]',
      axis: 'y',
      onSortChange: () => this._updateItemOrder()
    })

    // Add the existing block types and groups
    const existingItems = []
    const btNamespace = [...this._templateNs, 'blockTypes']
    const gNamespace = [...this._templateNs, 'groups']

    for (const btInfo of settings.blockTypes) {
      const btSettings = new BlockTypeSettings({
        namespace: [...btNamespace, btInfo.id],
        sortOrder: btInfo.sortOrder,
        id: btInfo.id,
        name: btInfo.name,
        handle: btInfo.handle,
        maxBlocks: btInfo.maxBlocks,
        maxSiblingBlocks: btInfo.maxSiblingBlocks,
        maxChildBlocks: btInfo.maxChildBlocks,
        topLevel: btInfo.topLevel,
        errors: btInfo.errors,
        childBlockTypes: existingItems.filter(item => item instanceof BlockType)
      })

      const btFieldLayout = new BlockTypeFieldLayout({
        namespace: [...btNamespace, btInfo.id],
        html: btInfo.fieldLayoutHtml,
        id: btInfo.fieldLayoutId,
        blockTypeId: btInfo.id
      })

      const blockType = new BlockType({
        namespace: btNamespace,
        settings: btSettings,
        fieldLayout: btFieldLayout
      })

      if (window.localStorage.getItem('neo:copyBlockType')) {
        blockType.$actionsMenu.find('[data-action="paste"]').parent().removeClass('disabled')
      }

      blockType.on('copy.configurator', () => this._copyBlockType(blockType))
      blockType.on('paste.configurator', () => this._pasteBlockType())
      blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType))
      existingItems.push(blockType)
    }

    for (const gInfo of settings.groups) {
      const gSettings = new GroupSettings({
        namespace: [...gNamespace, gInfo.id],
        sortOrder: gInfo.sortOrder,
        id: gInfo.id,
        name: gInfo.name
      })

      const group = new Group({
        namespace: gNamespace,
        settings: gSettings
      })

      existingItems.push(group)
    }

    for (const item of existingItems.sort((a, b) => a.getSettings().getSortOrder() - b.getSettings().getSortOrder())) {
      this.addItem(item)
    }

    for (const blockType of this.getBlockTypes()) {
      const btSettings = blockType.getSettings()
      const info = settings.blockTypes.find(i => i.handle === btSettings.getHandle())

      btSettings.setChildBlocks(info.childBlocks)
    }

    this.selectTab('settings')

    this.addListener(this.$blockTypeButton, 'click', '@newBlockType')
    this.addListener(this.$groupButton, 'click', '@newGroup')
    this.addListener(this.$settingsButton, 'click', () => this.selectTab('settings'))
    this.addListener(this.$fieldLayoutButton, 'click', () => this.selectTab('fieldLayout'))
  },

  _generateConfigurator () {
    return $(`
      <div class="nc_sidebar" data-neo="container.sidebar">
        <div class="nc_sidebar_title">${Craft.t('neo', 'Block Types')}</div>
        <div class="nc_sidebar_list" data-neo="container.blockTypes"></div>
        <div class="nc_sidebar_buttons btngroup">
          <a class="btn add icon" role="button" data-neo="button.blockType">${Craft.t('neo', 'Block type')}</a>
          <a class="btn type-heading" role="button" data-neo="button.group">${Craft.t('neo', 'Group')}</a>
        </div>
      </div>
      <div class="nc_main" data-neo="container.main">
        <div class="nc_main_tabs">
          <a class="nc_main_tabs_tab is-selected" role="button" data-neo="button.settings">${Craft.t('neo', 'Settings')}</a>
          <a class="nc_main_tabs_tab" role="button" data-neo="button.fieldLayout">${Craft.t('neo', 'Field Layout')}</a>
        </div>
        <div class="nc_main_content" data-neo="container.settings"></div>
        <div class="nc_main_content" data-neo="container.fieldLayout"></div>
      </div>`)
  },

  addItem (item, index = -1) {
    const settings = item.getSettings()

    this._insertAt(item.$container, index)
    this._itemSort.addItems(item.$container)

    if (settings) this.$settingsContainer.append(settings.$container)

    this.$mainContainer.removeClass('hidden')

    this.addListener(item.$container, 'click', '@selectItem')
    item.on('destroy.configurator', () => this.removeItem(item, false))

    if (item instanceof BlockType) {
      const fieldLayout = item.getFieldLayout()
      if (fieldLayout) this.$fieldLayoutContainer.append(fieldLayout.$container)
    }

    this._items.push(item)
    this._updateItemOrder()

    if (item instanceof BlockType) {
      for (const blockType of this.getBlockTypes()) {
        const btSettings = blockType.getSettings()
        if (btSettings) btSettings.addChildBlockType(item)
      }
    }

    this.trigger('addItem', {
      item: item,
      index: index
    })
  },

  removeItem (item, showConfirm) {
    showConfirm = (typeof showConfirm === 'boolean' ? showConfirm : false)

    if (showConfirm) {
      const message = Craft.t('neo', 'Are you sure you want to delete this {type}?', {
        type:
        item instanceof BlockType
          ? 'block type'
          : item instanceof Group
            ? 'group'
            : 'item'
      })

      if (window.confirm(message)) {
        this.removeItem(item, false)
      }
    } else {
      const settings = item.getSettings()

      this._itemSort.removeItems(item.$container)

      item.$container.remove()
      if (settings) settings.$container.remove()

      if (item instanceof BlockType) {
        const fieldLayout = item.getFieldLayout()
        if (fieldLayout) fieldLayout.$container.remove()
      }

      this.removeListener(item.$container, 'click')
      item.off('.configurator')

      this._updateItemOrder()

      if (this._items.length === 0) {
        this.$mainContainer.addClass('hidden')
      }

      this.trigger('removeItem', {
        item: item
      })
    }
  },

  getItems () {
    return Array.from(this._items)
  },

  getItemByElement ($element) {
    return this._items.find(item => item.$container.is($element))
  },

  getSelectedItem () {
    return this._items.find(item => item.isSelected())
  },

  selectItem (item, focusInput) {
    focusInput = (typeof focusInput === 'boolean' ? focusInput : true)

    const settings = item ? item.getSettings() : null

    for (const i of this._items) {
      const thisIsTheItem = i === item
      i.toggleSelect(thisIsTheItem)

      if (thisIsTheItem) {
        this.$fieldLayoutButton.toggleClass('hidden', !(i instanceof BlockType))
      }
    }

    this.selectTab('settings')

    if (focusInput && settings && !Garnish.isMobileBrowser()) {
      setTimeout(() => settings.getFocusInput().focus(), 100)
    }
  },

  getBlockTypes () {
    return this._items.filter(item => item instanceof BlockType)
  },

  getGroups () {
    return this._items.filter(item => item instanceof Group)
  },

  selectTab (tab) {
    this.$settingsContainer.toggleClass('hidden', tab !== 'settings')
    this.$fieldLayoutContainer.toggleClass('hidden', tab !== 'fieldLayout')

    this.$settingsButton.toggleClass('is-selected', tab === 'settings')
    this.$fieldLayoutButton.toggleClass('is-selected', tab === 'fieldLayout')
  },

  _getNewFieldLayoutHtml () {
    return this._fieldLayoutHtml.replace(
      /<input type="hidden" name="fieldLayout" value="{&quot;uid&quot;:&quot;([a-f0-9-]+)&quot;}" data-config-input>/,
      `<input type="hidden" name="fieldLayout" value="{&quot;uid&quot;:&quot;${uuidv4()}&quot;}" data-config-input>`
    )
  },

  _updateItemOrder () {
    const items = []

    this._itemSort.$items.each((index, element) => {
      const item = this.getItemByElement(element)

      if (item) {
        const settings = item.getSettings()
        if (settings) settings.setSortOrder(index + 1)

        items.push(item)
      }
    })

    this._items = items
  },

  _createBlockTypeFrom (oldBlockType) {
    const namespace = [...this._templateNs, 'blockTypes']
    const id = BlockTypeSettings.getNewId()
    const selectedItem = this.getSelectedItem()
    const selectedIndex = selectedItem ? selectedItem.getSettings().getSortOrder() : -1

    if (oldBlockType === null) {
      const settings = new BlockTypeSettings({
        childBlockTypes: this.getBlockTypes(),
        id: id,
        namespace: [...namespace, id],
        sortOrder: this._items.length
      })
      const fieldLayout = new BlockTypeFieldLayout({
        blockTypeId: id,
        html: this._getNewFieldLayoutHtml(),
        namespace: [...namespace, id]
      })

      this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
    } else {
      const oldSettings = oldBlockType.getSettings()
      const settings = new BlockTypeSettings({
        childBlocks: oldSettings.getChildBlocks(),
        childBlockTypes: this.getBlockTypes(),
        // Set a timestamp on the handle so it doesn't clash with the old one
        handle: `${oldSettings.getHandle()}_${Date.now()}`,
        id: id,
        maxBlocks: oldSettings.getMaxBlocks(),
        maxChildBlocks: oldSettings.getMaxChildBlocks(),
        maxSiblingBlocks: oldSettings.getMaxSiblingBlocks(),
        name: oldSettings.getName(),
        namespace: [...namespace, id],
        sortOrder: this._items.length,
        topLevel: oldSettings.getTopLevel()
      })
      const config = oldBlockType.getFieldLayout().getConfig()

      if (config.tabs.length > 0) {
        const $spinner = $('<div class="nc_sidebar_list_item type-spinner"><span class="spinner"></span></div>')
        this._insertAt($spinner, selectedIndex)

        Craft.postActionRequest('neo/configurator/render-field-layout', { layout: config }, e => {
          const fieldLayout = new BlockTypeFieldLayout({
            blockTypeId: id,
            html: e.success ? e.html : this._getNewFieldLayoutHtml(),
            namespace: [...namespace, id]
          })

          this.$blockTypesContainer.find('.type-spinner').remove()
          this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
        })
      } else {
        const fieldLayout = new BlockTypeFieldLayout({
          blockTypeId: id,
          html: this._getNewFieldLayoutHtml(),
          namespace: [...namespace, id]
        })

        this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
      }
    }
  },

  _initBlockType (namespace, settings, fieldLayout, index) {
    const blockType = new BlockType({ namespace, settings, fieldLayout })

    this.addItem(blockType, index)
    this.selectItem(blockType)

    blockType.on('copy.configurator', () => this._copyBlockType(blockType))
    blockType.on('paste.configurator', () => this._pasteBlockType())
    blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType))
  },

  _copyBlockType (blockType) {
    const settings = blockType.getSettings()
    const data = {
      childBlocks: settings.getChildBlocks(),
      handle: settings.getHandle(),
      layout: blockType.getFieldLayout().getConfig(),
      maxBlocks: settings.getMaxBlocks(),
      maxChildBlocks: settings.getMaxChildBlocks(),
      maxSiblingBlocks: settings.getMaxSiblingBlocks(),
      name: settings.getName(),
      topLevel: settings.getTopLevel()
    }

    window.localStorage.setItem('neo:copyBlockType', JSON.stringify(data))
    this.getBlockTypes().forEach(bt => bt.$actionsMenu.find('[data-action="paste"]').parent().removeClass('disabled'))
  },

  _pasteBlockType () {
    const encodedData = window.localStorage.getItem('neo:copyBlockType')

    if (!encodedData) {
      return
    }

    const data = JSON.parse(encodedData)
    const blockTypeHandles = this.getBlockTypes().map(bt => bt.getSettings().getHandle())
    const childBlocks = Array.isArray(data.childBlocks)
      ? data.childBlocks.filter(cb => blockTypeHandles.includes(cb))
      : (data.childBlocks ? true : [])
    const settings = new BlockTypeSettings({
      childBlocks: childBlocks,
      childBlockTypes: this.getBlockTypes(),
      handle: data.handle,
      maxBlocks: data.maxBlocks,
      maxChildBlocks: data.maxChildBlocks,
      maxSiblingBlocks: data.maxSiblingBlocks,
      name: data.name,
      topLevel: data.topLevel
    })

    const fieldLayout = new BlockTypeFieldLayout({
      html: this._getNewFieldLayoutHtml(),
      layout: data.layout
    })

    const blockType = new BlockType({
      settings: settings,
      fieldLayout: fieldLayout
    })

    this._createBlockTypeFrom(blockType)
  },

  _insertAt (element, index) {
    const $element = $(element)

    if (index >= 0 && index < this._items.length) {
      $element.insertAt(index, this.$blockTypesContainer)
    } else {
      this.$blockTypesContainer.append($element)
    }
  },

  '@newBlockType' () {
    this._createBlockTypeFrom(null)
  },

  '@newGroup' () {
    const namespace = [...this._templateNs, 'groups']
    const id = GroupSettings.getNewId()

    const settings = new GroupSettings({
      namespace: [...namespace, id],
      sortOrder: this._items.length,
      id: id
    })

    const group = new Group({
      namespace: namespace,
      settings: settings
    })

    const selected = this.getSelectedItem()
    const index = selected ? selected.getSettings().getSortOrder() : -1

    this.addItem(group, index)
    this.selectItem(group)
  },

  '@selectItem' (e) {
    const item = this.getItemByElement(e.currentTarget)

    this.selectItem(item)
  }
})
