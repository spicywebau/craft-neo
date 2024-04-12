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
  blockTypeSettingsHtml: '',
  blockTypeSettingsJs: '',
  fieldLayoutHtml: '',
  groupSettingsHtml: ''
}

export default Garnish.Base.extend({

  _templateNs: [],
  _items: [],

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    const inputIdPrefix = Craft.formatInputId(settings.namespace)
    const $field = $(`#${inputIdPrefix}-neo-configurator`)
    this.$container = $field.children('.field').children('.input')

    this._templateNs = NS.parse(settings.namespace)
    this._blockTypeSettingsHtml = settings.blockTypeSettingsHtml
    this._blockTypeSettingsJs = settings.blockTypeSettingsJs
    this._fieldLayoutHtml = settings.fieldLayoutHtml
    this._blockTypeGroupSettingsHtml = settings.blockTypeGroupSettingsHtml
    this._items = []

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
      container: this.$blockTypesContainer,
      handle: '[data-neo-bt="button.move"], [data-neo-g="button.move"]',
      axis: 'y',
      onSortChange: () => this._updateItemOrder()
    })

    // Add the existing block types and groups
    const existingItems = []
    const btNamespace = [...this._templateNs, 'items', 'blockTypes']
    const gNamespace = [...this._templateNs, 'items', 'groups']

    for (const btInfo of settings.blockTypes) {
      const btSettings = new BlockTypeSettings({
        namespace: [...btNamespace, btInfo.id],
        sortOrder: btInfo.sortOrder,
        id: btInfo.id,
        name: btInfo.name,
        handle: btInfo.handle,
        description: btInfo.description,
        iconId: btInfo.iconId,
        enabled: btInfo.enabled,
        ignorePermissions: btInfo.ignorePermissions,
        minBlocks: btInfo.minBlocks,
        maxBlocks: btInfo.maxBlocks,
        minSiblingBlocks: btInfo.minSiblingBlocks,
        maxSiblingBlocks: btInfo.maxSiblingBlocks,
        minChildBlocks: btInfo.minChildBlocks,
        maxChildBlocks: btInfo.maxChildBlocks,
        topLevel: btInfo.topLevel,
        html: btInfo.settingsHtml,
        js: btInfo.settingsJs,
        errors: btInfo.errors,
        fieldLayoutId: btInfo.fieldLayoutId,
        fieldLayoutConfig: btInfo.fieldLayoutConfig,
        childBlocks: btInfo.childBlocks,
        childBlockTypes: existingItems.filter(item => item instanceof BlockType)
      })

      const blockType = new BlockType({
        namespace: btNamespace,
        field: this,
        settings: btSettings
      })

      blockType.on('copy.configurator', () => this._copyBlockType(blockType))
      blockType.on('paste.configurator', () => this._pasteBlockType())
      blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType.getConfig()))
      blockType.on('beforeLoad.configurator', () => {
        this.$fieldLayoutContainer.append(
          $('<span class="spinner"/></span>')
        )
        this.$settingsContainer.append(
          $('<span class="spinner"/></span>')
        )
      })
      blockType.on('afterLoad.configurator', () => {
        this.$fieldLayoutContainer.children('.spinner').remove()
        this.$settingsContainer.children('.spinner').remove()
        const blockTypeSettings = blockType.getSettings()
        blockTypeSettings?.refreshChildBlockTypes(this.getBlockTypes())
        blockTypeSettings?.setChildBlocks()
      })
      existingItems.push(blockType)
    }

    for (const gInfo of settings.groups) {
      const gSettings = new GroupSettings({
        namespace: [...gNamespace, gInfo.id],
        sortOrder: gInfo.sortOrder,
        id: gInfo.id,
        name: gInfo.name,
        alwaysShowDropdown: gInfo.alwaysShowDropdown,
        defaultAlwaysShowGroupDropdowns: settings.defaultAlwaysShowGroupDropdowns
      })

      const group = new Group({
        namespace: gNamespace,
        field: this,
        settings: gSettings
      })

      group.on('beforeLoad.configurator', () => this.$settingsContainer.append(
        $('<span class="spinner"/></span>')
      ))
      group.on('afterLoad.configurator', () => {
        this.$settingsContainer.children('.spinner').remove()
        this.addItem(group)
      })
      existingItems.push(group)
    }

    for (const item of existingItems.sort((a, b) => a.getSortOrder() - b.getSortOrder())) {
      this.addItem(item)
    }

    for (const blockType of this.getBlockTypes()) {
      const btSettings = blockType.getSettings()

      if (btSettings?.$container) {
        const info = settings.blockTypes.find(i => i.handle === btSettings.getHandle())
        btSettings.setChildBlocks(info.childBlocks)
      }
    }

    this.selectTab('settings')
    this.addListener(this.$blockTypeButton, 'click', '@newBlockType')
    this.addListener(this.$groupButton, 'click', '@newGroup')
    this.addListener(this.$settingsButton, 'click', () => this.selectTab('settings'))
    this.addListener(this.$fieldLayoutButton, 'click', () => this.selectTab('fieldLayout'))
  },

  addItem (item, index = -1) {
    const settings = item.getSettings()

    if (!document.contains(item.$container[0])) {
      this._insertAt(item.$container, index)
    }

    if (this._itemSort.$items.filter(item.$container).length === 0) {
      this._itemSort.addItems(item.$container)
    }

    if (settings?.$container) {
      this.$settingsContainer.append(settings.$container)

      if (item instanceof BlockType) {
        settings.initUi()
      }
    }

    this.$mainContainer.removeClass('hidden')

    this.addListener(item.$container, 'click', '@selectItem')
    item.on('destroy.configurator', () => this.removeItem(item, false))

    if (item instanceof BlockType) {
      this._addFieldLayout(item.getFieldLayout())
    }

    this._items.push(item)

    // Only bother updating the item order if the item wasn't just being appended
    if (index >= 0 && index < this._items.length - 1) {
      this._updateItemOrder()
    }

    if (item instanceof BlockType) {
      for (const blockType of this.getBlockTypes()) {
        const btSettings = blockType.getSettings()
        if (btSettings?.$container) {
          btSettings.addChildBlockType(item)
        }
      }
    }

    this.trigger('addItem', {
      item,
      index
    })
  },

  _addFieldLayout (fieldLayout) {
    if (fieldLayout) {
      this.$fieldLayoutContainer.append(fieldLayout.$container)
    }
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

      if (settings?.$container) {
        settings.$container.remove()
      }

      if (item instanceof BlockType) {
        const fieldLayout = item.getFieldLayout()
        if (fieldLayout) fieldLayout.$container.remove()
      }

      this.removeListener(item.$container, 'click')
      item.off('.configurator')

      this._items = this._items.filter((oldItem) => oldItem !== item)

      if (this._items.length === 0) {
        this.$mainContainer.addClass('hidden')
      }

      this.trigger('removeItem', {
        item
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
        const itemIsGroup = !(i instanceof BlockType)
        this.$fieldLayoutButton.toggleClass('hidden', itemIsGroup)

        if (itemIsGroup) {
          this.selectTab('settings')
        }
      }
    }

    Craft.ElementThumbLoader.retryAll()

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

  _getNewBlockTypeSettingsHtml (blockTypeId) {
    return this._blockTypeSettingsHtml.replace(/__NEOBLOCKTYPE_ID__/g, blockTypeId)
  },

  _getNewBlockTypeSettingsJs (blockTypeId) {
    return this._blockTypeSettingsJs.replace(/__NEOBLOCKTYPE_ID__/g, blockTypeId)
  },

  _getNewFieldLayoutHtml () {
    return this._fieldLayoutHtml.replace(
      /&quot;uid&quot;:&quot;([a-f0-9-]+)&quot;/,
      `&quot;uid&quot;:&quot;${uuidv4()}&quot;`
    )
  },

  _getNewBlockTypeGroupSettingsHtml (groupId) {
    return this._blockTypeGroupSettingsHtml.replace(/__NEOBLOCKTYPEGROUP_ID__/g, groupId)
  },

  _updateItemOrder () {
    const items = []

    this._itemSort.$items.each((index, element) => {
      const item = this.getItemByElement(element)

      if (item) {
        items.push(item)

        if (item instanceof BlockType) {
          item.getSettings().refreshChildBlockTypes()
        }
      }
    })

    this._items = items
  },

  _createBlockTypeFrom (config) {
    const namespace = [...this._templateNs, 'items', 'blockTypes']
    let id
    do {
      id = BlockTypeSettings.getNewId()
    } while (this.$blockTypesContainer.find(`[data-neo-bt="container.${id}"]`).length > 0)

    const selectedItem = this.getSelectedItem()
    const selectedIndex = selectedItem ? selectedItem.getSortOrder() : -1

    if (config === null) {
      // Creating a new block type
      const settings = new BlockTypeSettings({
        childBlockTypes: this.getBlockTypes(),
        id,
        namespace: [...namespace, id],
        sortOrder: this._items.length,
        html: this._getNewBlockTypeSettingsHtml(id),
        js: this._getNewBlockTypeSettingsJs(id)
      })
      const fieldLayout = new BlockTypeFieldLayout({
        blockTypeId: id,
        html: this._getNewFieldLayoutHtml(),
        namespace: [...namespace, id]
      })

      this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
    } else {
      // Cloning or pasting a copy of a block type
      const $spinner = $('<div class="nc_sidebar_list_item type-spinner"><span class="spinner"></span></div>')
      this._insertAt($spinner, selectedIndex)
      const settingsObj = Object.assign({}, config.settings, {
        // Set a timestamp on the handle so it doesn't clash with the old one
        handle: `${config.settings.handle}_${Date.now()}`,
        id,
        sortOrder: this._items.length
      })
      const settings = new BlockTypeSettings({
        ...settingsObj,
        childBlockTypes: this.getBlockTypes(),
        namespace: [...namespace, id]
      })
      const fieldLayoutConfig = config.fieldLayout
      const data = {
        settings: settingsObj,
        fieldLayout: fieldLayoutConfig.tabs.length > 0 ? fieldLayoutConfig : null
      }

      Craft.queue.push(() => new Promise((resolve, reject) => {
        Craft.sendActionRequest('POST', 'neo/configurator/render-block-type', { data })
          .then(response => {
            const fieldLayout = new BlockTypeFieldLayout({
              blockTypeId: id,
              html: response.data.fieldLayoutHtml,
              namespace: [...namespace, id]
            })
            settings.createContainer({
              html: response.data.settingsHtml.replace(/__NEOBLOCKTYPE_ID__/g, id),
              js: response.data.settingsJs.replace(/__NEOBLOCKTYPE_ID__/g, id)
            })

            this._initBlockType(namespace, settings, fieldLayout, selectedIndex, true)
            resolve()
          })
          .catch((err) => {
            reject(err)
            console.error(err)
            Craft.cp.displayError(Craft.t('neo', 'Couldn’t create new block type.'))
          })
          .finally(() => this.$blockTypesContainer.find('.type-spinner').remove())
      }))
    }
  },

  _initBlockType (namespace, settings, fieldLayout, index, alreadyLoaded = false) {
    const blockType = new BlockType({
      namespace,
      field: this,
      settings,
      fieldLayout,
      alreadyLoaded
    })

    this.addItem(blockType, index)
    this.selectItem(blockType)
    this.selectTab('settings')

    blockType.on('copy.configurator', () => this._copyBlockType(blockType))
    blockType.on('paste.configurator', () => this._pasteBlockType())
    blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType.getConfig()))
  },

  _copyBlockType (blockType) {
    blockType.load()
      .then(() => {
        window.localStorage.setItem('neo:copyBlockType', JSON.stringify(blockType.getConfig()))
        this.getBlockTypes().forEach(bt => bt.$actionsMenu?.find('[data-action="paste"]').parent().removeClass('disabled'))
      })
      .catch((e) => {
        console.error(e)
        Craft.cp.displayError(Craft.t('neo', 'Couldn’t copy block type.'))
      })
  },

  _pasteBlockType () {
    const encodedData = window.localStorage.getItem('neo:copyBlockType')

    if (!encodedData) {
      return
    }

    this._createBlockTypeFrom(JSON.parse(encodedData))
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
    const namespace = [...this._templateNs, 'items', 'groups']
    let id
    do {
      id = GroupSettings.getNewId()
    } while (this.$blockTypesContainer.find(`[data-neo-g="container.${id}"]`).length > 0)

    const settings = new GroupSettings({
      namespace: [...namespace, id],
      html: this._getNewBlockTypeGroupSettingsHtml(id),
      sortOrder: this._items.length,
      id
    })

    const group = new Group({
      namespace,
      field: this,
      settings
    })

    const selected = this.getSelectedItem()
    const index = selected ? selected.getSortOrder() : -1

    this.addItem(group, index)
    this.selectItem(group)
  },

  '@selectItem' (e) {
    const item = this.getItemByElement(e.currentTarget)

    this.selectItem(item)
  }
})
