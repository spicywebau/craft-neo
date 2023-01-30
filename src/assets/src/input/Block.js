import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import { addFieldLinks } from '../plugins/cpfieldinspect/main'

const _defaults = {
  namespace: [],
  blockType: null,
  id: null,
  level: 1,
  buttons: null,
  enabled: true,
  collapsed: false,
  modified: true,
  showButtons: true,
  showBlockTypeHandle: false
}

const _resources = {}

const _escapeMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;'
}

function _resourceFilter () {
  let url = this.href || this.src

  if (url) {
    const paramIndex = url.indexOf('?')

    url = (paramIndex < 0 ? url : url.substr(0, paramIndex))

    const isNew = !Object.prototype.hasOwnProperty.call(_resources, url)
    _resources[url] = 1

    return isNew
  }

  return true
}

function _escapeHTML (str) {
  return str ? str.replace(/[&<>"'/]/g, s => _escapeMap[s]) : ''
}

function _limit (s, l = 40) {
  s = s || ''
  return s.length > l ? s.slice(0, l - 3) + '...' : s
}

export default Garnish.Base.extend({

  _templateNs: [],
  _field: null,
  _blockType: null,
  _initialised: false,
  _expanded: true,
  _enabled: true,
  _modified: true,
  _initialState: null,
  _forceModified: false,

  init (settings = {}, generateElement = false) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._field = settings.field
    this._blockType = settings.blockType
    this._id = settings.id
    this._buttons = settings.buttons
    this._enabled = settings.enabled && this._blockType.getEnabled()
    this._initialEnabled = settings.enabled
    this._modified = settings.modified
    this._showButtons = settings.showButtons
    this._renderOldChildBlocksContainer = !settings.blockType.hasChildBlocksUiElement()
    this.$container = generateElement
      ? this._generateElement(settings.showBlockTypeHandle)
      : $(`[data-neo-b-id=${this._id}]`)

    const $neo = this.$container.find('[data-neo-b]')
    this.$bodyContainer = $neo.filter(`[data-neo-b="${this._id}.container.body"]`)
    this.$contentContainer = $neo.filter(`[data-neo-b="${this._id}.container.content"]`)
    this.$childrenContainer = $neo.filter(`[data-neo-b="${this._id}.container.children"]`)
    this.$childrenWarningsContainer = $neo.filter(`[data-neo-b="${this._id}.container.childrenWarnings"]`)
    this.$collapsedChildrenContainer = $neo.filter(`[data-neo-b="${this._id}.container.collapsedChildren"]`)
    this.$blocksContainer = $neo.filter(`[data-neo-b="${this._id}.container.blocks"]`)
    this.$buttonsContainer = $neo.filter(`[data-neo-b="${this._id}.container.buttons"]`)
    this.$topbarContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbar"]`)
    this.$topbarLeftContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbarLeft"]`)
    this.$topbarRightContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbarRight"]`)
    this.$handleContainer = $neo.filter(`[data-neo-b="${this._id}.container.handle"]`)
    this.$tabContainer = this.$contentContainer.children('[data-layout-tab]')
    this.$menuContainer = $neo.filter(`[data-neo-b="${this._id}.container.menu"]`)
    this.$previewContainer = $neo.filter(`[data-neo-b="${this._id}.container.preview"]`)
    this.$settingsButton = $neo.filter(`[data-neo-b="${this._id}.button.actions"]`)
    this.$togglerButton = $neo.filter(`[data-neo-b="${this._id}.button.toggler"]`)
    this.$tabsButton = $neo.filter(`[data-neo-b="${this._id}.button.tabs"]`)
    this.$enabledInput = $neo.filter(`[data-neo-b="${this._id}.input.enabled"]`)
    this.$levelInput = $neo.filter(`[data-neo-b="${this._id}.input.level"]`)
    this.$collapsedInput = $neo.filter(`[data-neo-b="${this._id}.input.collapsed"]`)
    this.$status = $neo.filter(`[data-neo-b="${this._id}.status"]`)
    this.$sortOrder = $neo.filter(`[data-neo-b="${this._id}.sortOrder"]`)
    this.$form = this.$container.closest('form')

    if (this._buttons) {
      this._buttons.on('newBlock', e => this.trigger('newBlock', Object.assign(e, { level: this.getLevel() + 1 })))
      this.$buttonsContainer.append(this._buttons.$container)

      if (this._buttons.$ownerContainer === null) {
        this._buttons.$ownerContainer = this.$container
      }
    }

    let hasErrors = false
    if (this._blockType) {
      for (const tab of this._blockType.getTabs()) {
        const selector = `[data-neo-b-info="${tab.getName()}"]`

        if (this.$tabContainer.filter(selector).find('ul.errors').length > 0) {
          hasErrors = true
          this.$tabButton.filter(selector).addClass('error')
        }
      }
    }

    this.setLevel(settings.level)
    this.toggleExpansion(hasErrors ? true : !settings.collapsed, false, false)
    this.toggleShowButtons(this._showButtons)

    this.addListener(this.$topbarContainer, 'dblclick', '@doubleClickTitle')
    this.$container.data('block', this)
  },

  _generateElement (showHandle = false) {
    NS.enter(this._templateNs)
    const baseInputName = NS.toFieldName()
    const baseInputId = NS.toString('-')
    NS.leave()

    const type = this._blockType
    const typeTabs = type.getTabs()
    const hasTabs = typeTabs.length > 0
    const isParent = type.isParent()
    const actionBtnLabel = `${type.getName()} ${Craft.t('neo', 'Actions')}`
    const actionMenuId = `neoblock-action-menu-${this._id}`
    const tabsBtnLabel = `${type.getName()} ${Craft.t('neo', 'Tabs')}`
    const tabsMenuId = `neoblock-tabs-menu-${this._id}`
    const sortOrderName = `${this._templateNs[0]}[${this._templateNs.slice(1, this._templateNs.length - 2).join('][')}][sortOrder]`
    const elementHtml = []
    elementHtml.push(`
      <div class="ni_block ni_block--${type.getHandle()} is-${this._collapsed ? 'collapsed' : 'expanded'} ${!hasTabs && !isParent ? 'is-empty' : ''} ${isParent ? 'is-parent' : ''}" data-neo-b-id="${this._id}" data-neo-b-name="${type.getName()}">
        <input type="hidden" name="${baseInputName}[type]" value="${type.getHandle()}">
        <input type="hidden" name="${baseInputName}[enabled]" value="${this._enabled ? '1' : ''}" data-neo-b="${this._id}.input.enabled">
        <input type="hidden" name="${baseInputName}[level]" value="${this._level}" data-neo-b="${this._id}.input.level">
        <input type="hidden" name="${sortOrderName}[]" value="${this._id}" data-neo-b="${this._id}.input.sortOrder">`)

    if (isNaN(parseInt(this._id))) {
      elementHtml.push(`
        <input type="hidden" name="${baseInputName}[collapsed]" value="${!this._expanded ? '1' : ''}" data-neo-b="${this._id}.input.collapsed">`)
    }

    elementHtml.push(`
        <div class="ni_block_topbar" data-neo-b="${this._id}.container.topbar">
          <div class="ni_block_topbar_left" data-neo-b="${this._id}.container.topbarLeft">
            <div class="ni_block_topbar_item" data-neo-b="${this._id}.select">
              <div class="checkbox block-checkbox" title="${Craft.t('neo', 'Select')} aria-label="${Craft.t('neo', 'Select')}"></div>
            </div>
            <div class="ni_block_topbar_item title">
              <span class="blocktype" data-neo-b="${this._id}.select">${type.getName()}</span>
            </div>
            <div class="ni_block_topbar_item preview-container clip-text">
              <span class="preview" data-neo-b="${this._id}.container.preview">&nbsp;</span>
            </div>
          </div>
          <div class="ni_block_topbar_right" data-neo-b="${this._id}.container.topbarRight">
            <div class="ni_block_topbar_item size-full tabs">`)

    if (hasTabs || isParent) {
      elementHtml.push(`
              <div class="tabs_trigger" data-neo-b="${this._id}.button.toggler"></div>`)
    }

    if (typeTabs.length > 1) {
      elementHtml.push(`
              <div class="tabs_inner" data-neo-b="${this._id}.container.tabs">`)

      for (let i = 0; i < typeTabs.length; i++) {
        const tabName = typeTabs[i].getName()
        elementHtml.push(`
                <a class="tab ${!i ? 'is-selected' : ''}" data-neo-b="${this._id}.button.tab" data-neo-b-info="${tabName}">${tabName}</a>`)
      }

      elementHtml.push(`
              </div>
              <div>
                <button type="button" role="button" title=${Craft.t('neo', 'Tabs')} aria-controls="${tabsMenuId}" aria-label="${tabsBtnLabel}" data-disclosure-trigger data-neo-b="${this._id}.button.tabs" class="tabs_btn menubtn">
                  ${typeTabs[0].getName()}
                </button>
                <div id="${tabsMenuId}" class="neo_block_tabs-menu menu menu--disclosure">
                  <ul>`)

      for (let i = 0; i < typeTabs.length; i++) {
        const tabName = typeTabs[i].getName()
        elementHtml.push(`
                    <li>
                      <a${!i ? ' class="is-selected"' : ''} href="#" type="button" role="button" aria-label="${tabName}" data-neo-b="${this._id}.button.tab" data-neo-b-info="${tabName}">${tabName}</a>
                    </li>`)
      }

      elementHtml.push(`
                  </ul>
                </div>
              </div>`)
    }

    elementHtml.push(`
            </div>
            <div class="ni_block_topbar_item hidden" data-neo-b="${this._id}.status">
              <div class="status off" title="${Craft.t('neo', 'Disabled')}"></div>
            </div>
            <div class="ni_block_topbar_item block-settings">
              <div>
                <button class="btn settings icon menubtn" type="button" role="button" title="${Craft.t('neo', 'Actions')}" aria-controls="${actionMenuId}" aria-label="${actionBtnLabel}" data-disclosure-trigger data-neo-b="${this._id}.button.actions"></button>
                <div id="${actionMenuId}" class="menu menu--disclosure" data-neo-b="${this._id}.container.menu">
                  <ul class="padded">`)

    if (hasTabs || isParent) {
      elementHtml.push(`
                    <li><a data-icon="collapse" data-action="collapse" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Collapse')}">${Craft.t('neo', 'Collapse')}</a></li>
                    <li class="hidden"><a data-icon="expand" data-action="expand" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Expand')}">${Craft.t('neo', 'Expand')}</a></li>`)
    }

    elementHtml.push(`
                    <li><a data-icon="disabled" data-action="disable" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Disable')}">${Craft.t('neo', 'Disable')}</a></li>
                    <li class="hidden"><a data-icon="enabled" data-action="enable" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Enable')}">${Craft.t('neo', 'Enable')}</a></li>
                    <li class="hidden"><a data-icon="uarr" data-action="moveUp" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Move up')}">${Craft.t('neo', 'Move up')}</a></li>
                    <li class="hidden"><a data-icon="darr" data-action="moveDown" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Move down')}">${Craft.t('neo', 'Move down')}</a></li>
                  </ul>
                  <hr>
                  <ul class="padded">
                    <li><a data-icon="plus" data-action="add" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Add block above')}">${Craft.t('neo', 'Add block above')}</a></li>
                    <li><a data-icon="field" data-action="copy" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Copy')}">${Craft.t('neo', 'Copy')}</a></li>
                    <li><a data-icon="brush" data-action="paste" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Paste')}">${Craft.t('neo', 'Paste')}</a></li>
                    <li><a data-icon="share" data-action="duplicate" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Clone')}">${Craft.t('neo', 'Clone')}</a></li>
                  </ul>`)

    if (type.isDeletableByUser()) {
      elementHtml.push(`
                  <hr>
                  <ul class="padded">
                    <li><a class="error" data-icon="remove" data-action="delete" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Delete')}">${Craft.t('neo', 'Delete')}</a></li>
                  </ul>`)
    }

    elementHtml.push(`
                </div>
              </div>
            </div>
            <div class="ni_block_topbar_item block-reorder">
              <a class="move icon" title="${Craft.t('neo', 'Reorder')}" aria-label="${Craft.t('neo', 'Reorder')}" role="button" data-neo-b="${this._id}.button.move"></a>
            </div>
          </div>
        </div>`)

    if (hasTabs || isParent) {
      elementHtml.push(`
        <div class="ni_block_body" data-neo-b="${this._id}.container.body">`)

      if (hasTabs) {
        elementHtml.push(`
          <div class="ni_block_content" data-neo-b="${this._id}.container.content">
            ${type.getHtml(this._id)}
          </div>`)
      }

      if (isParent && this._renderOldChildBlocksContainer) {
        elementHtml.push(`
          <div class="ni_block_children" data-neo-b="${this._id}.container.children">
            <div class="ni_blocks" data-neo-b="${this._id}.container.blocks">
            </div>
            <div data-neo-b="${this._id}.container.buttons" class="hidden"></div>
            <div data-neo-b="${this._id}.container.childrenWarnings" class="hidden">
              <p class="first warning with-icon">${Craft.t('neo', "This Neo field's maximum number of levels has been reached, so no child blocks can be added here.")}</p>
            </div>
          </div>`)
      }

      elementHtml.push(`
        </div>`)
    }

    if (isParent) {
      elementHtml.push(`
        <div class="ni_block_collapsed-children" data-neo-b="${this._id}.container.collapsedChildren"></div>`)
    }

    elementHtml.push(`
      <div data-neo="container.buttons"></div>`)

    const $elementHtml = $(elementHtml.join(''))

    if (showHandle) {
      $('<div/>')
        .addClass('ni_block_topbar_item handle')
        .prop('data-neo-b', `${this._id}.container.handle`)
        .append(Craft.ui.createCopyTextBtn({
          id: `${baseInputId}-${type.getHandle()}-attribute`,
          class: ['code', 'small', 'light'],
          value: type.getHandle()
        }))
        .insertAfter($elementHtml.find('.ni_block_topbar_item.title'))
    }

    return $elementHtml
  },

  initUi (callInitUiElements = true) {
    if (this._initialised) {
      // Nothing to do here
      return
    }

    this.$foot = $(this._blockType.getJs(this._id)).filter(_resourceFilter)
    Garnish.$bod.append(this.$foot)

    if (callInitUiElements) {
      Craft.initUiElements(this.$contentContainer)
    }

    this.$form = this.$container.closest('form')
    this.initTabButtons()

    this._settingsMenu = this.$settingsButton.data('trigger') || new Garnish.DisclosureMenu(this.$settingsButton)
    this._settingsMenu.on('show', () => {
      // Make sure all other blocks in the field have their settings menus closed
      this._field
        .getBlocks()
        .filter((block) => block.$container.hasClass('active'))
        .forEach((block) => block.toggleSettingsMenu(false))
      this.$container.addClass('active')
    })
    this._settingsMenu.on('hide', () => this.$container.removeClass('active'))

    this.$menuContainer = this._settingsMenu.$container
    this.addListener(this.$menuContainer.find('[data-action]'), 'click', this._handleActionClick)
    this.addListener(this.$menuContainer.find('[data-action]'), 'keydown', this._handleActionKeydown)

    this.toggleEnabled(this._initialEnabled)

    this._initialised = true
    this._buttons?.initUi()

    Garnish.requestAnimationFrame(() => this.updateResponsiveness())

    // For Matrix blocks inside a Neo block, this listener adds a class name to the block for Neo to style.
    // Neo applies its own styles to Matrix blocks in an effort to improve the visibility of them, however
    // when dragging a Matrix block these styles get lost (since a dragged Matrix block loses its context of
    // being inside a Neo block). Adding this class name to blocks before they are dragged means that the
    // dragged Matrix block can still have the Neo-specific styles.
    this.$container.on('mousedown', '.matrixblock', function (e) {
      $(this).addClass('neo-matrixblock')
    })

    // If this block has errors and is nested somewhere in a child blocks UI element, set errors on ancestors' tabs
    if (this.$container.hasClass('has-errors')) {
      this.$container.parents('.ni_child-blocks-ui-element').each((_, cbuiElement) => {
        const $tabContent = $(cbuiElement).parent()
        const parentBlock = $tabContent.closest('.ni_block').data('block')
        const tabIndex = $tabContent.index()
        parentBlock.$tabButton.filter('.tab').eq(tabIndex) // Desktop tab buttons
          .add(parentBlock.$tabButton.filter(':not(.tab)').eq(tabIndex)) // Mobile tab buttons
          .add(parentBlock.$container.find('> .ni_block_topbar .tabs_btn')) // Mobile tab dropdown button
          .addClass('has-errors')
          .append(`<span data-icon="alert" aria-label="${Craft.t('neo', 'Error')}"></span>`)
      })
    }

    // Setting up field and block property watching
    if (!this.isNew()) {
      this._initialState = {
        enabled: this._enabled,
        level: this._level,
        content: this._getPostData()
      }

      const detectChange = () => this._detectChange()
      const observer = new window.MutationObserver(() => {
        setTimeout(detectChange, 200)

        // Ensure blocks that are supposed to be non-editable by the user remain so
        if (!this.getBlockType().isEditableByUser() && !this.$container.hasClass('is-disabled-for-user')) {
          this.$container.addClass('is-disabled-for-user')
        }
      })

      observer.observe(this.$container[0], {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true
      })

      this.$contentContainer.on('propertychange change click', 'input, textarea, select, div.redactor-in', detectChange)
      this.$contentContainer.on('paste input keyup', 'input:not([type="hidden"]), textarea, div.redactor-in', detectChange)

      this._detectChangeObserver = observer

      // Hide the copy/paste/clone options if the block type is disabled
      this.$menuContainer
        .find('[data-action="copy"], [data-action="paste"], [data-action="duplicate"]')
        .parent()
        .toggleClass('hidden', !this._blockType.getEnabled())
    }

    addFieldLinks(this.$contentContainer)

    this.trigger('initUi')
  },

  /**
   * @public
   * @since 3.7.0
   */
  initTabButtons () {
    const $neo = this.$container.find('[data-neo-b]')
    this.$tabsContainer = $neo.filter(`[data-neo-b="${this._id}.container.tabs"]`)
    this.$tabButton = $neo.filter(`[data-neo-b="${this._id}.button.tab"]`)

    this._tabsMenu = this.$tabsButton.data('trigger') || new Garnish.DisclosureMenu(this.$tabsButton)
    this._tabsMenu.on('show', () => this.$container.addClass('active'))
    this._tabsMenu.on('hide', () => this.$container.removeClass('active'))

    this.$tabButton = this.$tabButton.add(this._tabsMenu.$container.find(`[data-neo-b="${this._id}.button.tab"]`))
    this.addListener(this.$tabButton, 'click', this['@setTab'])
    this.addListener(this.$tabButton, 'keydown', this._handleTabKeydown)
  },

  destroy () {
    if (this._initialised) {
      this.$foot.remove()

      clearInterval(this._detectChangeInterval)

      if (this._detectChangeObserver) {
        this._detectChangeObserver.disconnect()
      }

      this.trigger('destroy')
    }
  },

  getBlockType () {
    return this._blockType
  },

  getId () {
    return this._id
  },

  /**
   * @public
   * @returns the ID of the duplicate block, or the ID of this block if it hasn't been duplicated
   * @since 3.7.0
   */
  getDuplicatedBlockId () {
    return this.$form.data('elementEditor')?.duplicatedElements[this._id] ?? this._id
  },

  isTopLevel () {
    return this._level === 1
  },

  getLevel () {
    return this._level
  },

  setLevel (level) {
    this._level = level | 0

    this.$levelInput.val(`0${this._level}`)
    this.$container.toggleClass('is-level-odd', !!(this._level % 2))
    this.$container.toggleClass('is-level-even', !(this._level % 2))
  },

  setModified (isModified) {
    this._modified = isModified
  },

  getButtons () {
    return this._buttons
  },

  getSiteId () {
    if (!this._siteId) {
      const $siteId = this.$form.find('input[name="siteId"]')
      this._siteId = $siteId.val()
    }

    return this._siteId
  },

  getContent () {
    const rawContent = this._getPostData()
    const content = {}

    const setValue = (keys, value) => {
      let currentSet = content

      for (let i = 0; i < keys.length - 1; i++) {
        const key = keys[i]

        if (!$.isPlainObject(currentSet[key]) && !Array.isArray(currentSet[key])) {
          currentSet[key] = {}
        }

        currentSet = currentSet[key]
      }

      const key = keys[keys.length - 1]
      currentSet[key] = value
    }

    for (const rawName of Object.keys(rawContent)) {
      const fullName = NS.parse(rawName)
      const name = fullName.slice(this._templateNs.length + 1) // Adding 1 because content is NS'd under [fields]

      // Make sure empty arrays (which can happen with level, enabled, etc. when using the child blocks UI element) are ignored
      if (!name.length) {
        continue
      }

      const value = rawContent[rawName]

      setValue(name, value)
    }

    return content
  },

  getParent (blocks = null) {
    blocks ??= this._field.getBlocks()
    const level = this.getLevel()
    let index = blocks.indexOf(this)
    let blockParent = null

    if (index >= 0 && level > 1) {
      while (blockParent === null && index > 0) {
        const currentBlock = blocks[--index]
        const currentLevel = currentBlock.getLevel()

        if (currentLevel === level - 1) {
          blockParent = currentBlock
        }
      }
    }

    return blockParent
  },

  getChildren (blocks = null, descendants = null) {
    blocks ??= this._field.getBlocks()
    const level = this.getLevel()
    let index = blocks.indexOf(this)
    const childBlocks = []

    if (index >= 0) {
      let currentBlock = blocks[++index]

      while (currentBlock && currentBlock.getLevel() > level) {
        const currentLevel = currentBlock.getLevel()

        if (descendants ? currentLevel > level : currentLevel === level + 1) {
          childBlocks.push(currentBlock)
        }

        currentBlock = blocks[++index]
      }
    }

    return childBlocks
  },

  getSiblings (blocks = null) {
    blocks ??= this._field.getBlocks()

    return this.isTopLevel() ? blocks.filter(b => b.isTopLevel()) : this.getParent(blocks).getChildren(blocks)
  },

  getField () {
    return this._field
  },

  updatePreview (condensed = null) {
    condensed = typeof condensed === 'boolean' ? condensed : false

    const $childFields = this.$childrenContainer.find('.field')
    const $fields = this.$contentContainer.find('.field').add($childFields)
    const previewText = []

    $fields.each(function () {
      const $field = $(this)
      const $input = $field.children('.input')
      const fieldType = $field.data('type')
      const label = $field.children('.heading').children('label').text()

      // We rely on knowing the field type to know how to generate its preview, so if we don't know, skip it.
      if (fieldType === null) {
        return
      }

      let value = false

      switch (fieldType) {
        case 'craft\\fields\\Assets':
          {
            const values = []
            const $assets = $input.find('.element')

            $assets.each(function () {
              const $asset = $(this)
              const $thumbContainer = $asset.find('.elementthumb')
              const $thumb = $thumbContainer.children('img')
              let srcset = $thumb.prop('srcset')

              if (!srcset) {
                srcset = $thumbContainer.data('srcset')
              }

              values.push(`<img sizes="30px" srcset="${srcset}">`)

              if (!condensed && $assets.length === 1) {
                const title = $asset.find('.title').text()

                values.push(_escapeHTML(_limit(title)))
              }
            })

            value = values.join(' ')
          }
          break
        case 'craft\\fields\\Categories':
        case 'craft\\fields\\Entries':
        case 'craft\\fields\\Tags':
        case 'craft\\fields\\Users':
          {
            const values = []

            $input.find('.element').each(function () {
              const title = $(this).find('.title, .label').eq(0).text()
              values.push(_escapeHTML(_limit(title)))
            })

            value = values.join(', ')
          }
          break
        case 'craft\\fields\\Checkboxes':
          {
            const values = []

            $input.find('input[type="checkbox"]').each(function () {
              if (this.checked) {
                const id = $(this).prop('id')
                const label = $input.find(`label[for="${id}"]`).text()
                values.push(_escapeHTML(_limit(label)))
              }
            })

            value = values.join(', ')
          }
          break
        case 'craft\\fields\\Color':
          {
            const color = $input.find('input[type="color"]').val()
            const colorText = $input.find('input[type="text"]').val()
            const colorRev = $input.find('div.colorhex').text()
            let background = null

            if (color && colorText) {
              // Set the selected color.  `colorText` must also be checked, even though it's not used, because
              // the color type field may still store a color value even if the text field has been cleared.
              background = `background-color: ${color}`
            } else if (!color && colorText) {
              // When a block is initially collapsed, the color type field will not have been set, so the text
              // field value will need to be used.
              background = `background-color: ${colorText}`
            } else if (colorRev) {
              // Entry revisions will hav a div rather than an input, so use that.
              background = `background-color: ${colorRev}`
            } else {
              // No color value has been set for the field.
              background = 'background-image: repeating-linear-gradient(-45deg, transparent, transparent 2px, #777 2px, #777 3px)'
            }

            value = `<div class="preview_color" style="${background}"></div>`
          }
          break
        case 'craft\\fields\\Date':
          {
            const date = _escapeHTML($input.find('.datewrapper input').val())
            const time = _escapeHTML($input.find('.timewrapper input').val())

            value = date && time ? (date + ' ' + time) : (date || time)
          }
          break
        case 'craft\\fields\\Dropdown':
          {
            const $selected = $input.find('select').children(':selected')

            value = _escapeHTML(_limit($selected.text()))
          }
          break
        case 'craft\\fields\\Email':
          value = _escapeHTML(_limit($input.children('input[type="email"]').val()))
          break
        case 'craft\\fields\\Lightswitch':
          {
            const enabled = !!$input.find('input').val()

            value = `<span class="status${enabled ? ' live' : ''}"></span>` + _escapeHTML(_limit(label))
          }
          break
        case 'craft\\fields\\MultiSelect':
        case 'ttempleton\\categorygroupsfield\\fields\\CategoryGroupsField':
          {
            const values = []
            const $selected = $input.find('select').children(':selected')

            $selected.each(function () {
              values.push($(this).text())
            })

            value = _escapeHTML(_limit(values.join(', ')))
          }
          break
        case 'craft\\fields\\Number':
        case 'craft\\fields\\PlainText':
          value = _escapeHTML(_limit($input.children('input[type="text"], textarea').val()))
          break
        case 'craft\\fields\\RadioButtons':
          {
            const $checked = $input.find('input[type="radio"]:checked')
            const label = $checked.closest('label').text()

            value = _escapeHTML(_limit(label))
          }
          break
        case 'craft\\redactor\\Field':
        case 'spicyweb\\tinymce\\fields\\TinyMCE':
          value = _escapeHTML(_limit(Craft.getText($input.find('textarea').val())))
          break
        case 'craft\\ckeditor\\Field':
          value = _escapeHTML(_limit(Craft.getText($input.find('[role="textbox"]').html())))
          break
        case 'craft\\fields\\Url':
          value = _escapeHTML(_limit($input.children('input[type="url"]').val()))
          break
        case 'craft\\fields\\Matrix':
        case 'verbb\\supertable\\fields\\SuperTableField':
          {
            const $subFields = $field.find('.field')
            const $subInputs = $subFields.find('input[type!="hidden"], select, textarea, .label')

            const values = []

            $subInputs.each(function () {
              const $subInput = $(this)
              let subValue = null

              if ($subInput.is('input, textarea')) {
                subValue = Craft.getText(Garnish.getInputPostVal($subInput))
              } else if ($subInput.is('select')) {
                subValue = $subInput.find('option:selected').text()
              } else if ($subInput.hasClass('label')) {
                // TODO check for lightswitch maybe?
                subValue = $subInput.text()
              }

              if (subValue) {
                values.push(_limit(subValue))
              }
            })

            value = _escapeHTML(values.join(', '))
          }
          break
        case 'typedlinkfield\\fields\\LinkField':
        case 'presseddigital\\linkit\\fields\\LinkitField':
          {
            const values = []
            const $selectedType = $input.find('select').children(':selected').first()
            const $visibleOption = $input.find('.linkfield--typeOption:not(.hidden), [class^="linkit--"]:not(.hidden)')
            const visibleInputVal = $visibleOption.find('input[type!="hidden"]').val()
            const $visibleElement = $visibleOption.find('.element')
            const customText = $input.find('.field[id*="customText"] input, .linkit--customText input').val()

            values.push(_limit($selectedType.text()))

            if (visibleInputVal) {
              values.push(_limit(visibleInputVal))
            }

            if ($visibleElement.length > 0) {
              const title = $visibleElement.find('.title, .label').eq(0).text()

              values.push(_limit(title))
            }

            if (customText) {
              values.push(_limit(customText))
            }

            value = _escapeHTML(values.join(', '))
          }
          break
        case 'luwes\\codemirror\\fields\\CodeMirrorField':
        {
          const lines = []

          $field.find('.CodeMirror-line > span').each(function () {
            lines.push($(this).text())
          })

          value = _escapeHTML(lines.join(' '))
          break
        }
        case 'rias\\positionfieldtype\\fields\\Position':
        {
          const $selected = $input.find('.btn.active')

          value = _escapeHTML($selected.prop('title'))
          break
        }
        case 'wrav\\oembed\\fields\\OembedField':
          value = _escapeHTML(_limit($input.children('input').val()))
      }

      if (value && previewText.length < 10) {
        previewText.push('<span class="preview_section">', value, '</span>')
      }
    })

    this.$previewContainer.html(previewText.join(''))
  },

  isNew () {
    return /^new/.test(this.getId())
  },

  isSelected () {
    return this.$container.hasClass('is-selected')
  },

  collapse (save, animate) {
    this.toggleExpansion(false, save, animate)
  },

  expand (save, animate) {
    this.toggleExpansion(true, save, animate)
  },

  toggleExpansion (expand, save, animate) {
    expand = typeof expand === 'boolean' ? expand : !this._expanded
    save = typeof save === 'boolean' ? save : true
    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)

    if (expand !== this._expanded) {
      this._expanded = expand

      if (!this._expanded) {
        this.updatePreview()
      }

      const expandContainer = this.$menuContainer.find('[data-action="expand"]').parent()
      const collapseContainer = this.$menuContainer.find('[data-action="collapse"]').parent()

      this.$collapsedInput.val(!this._expanded ? '1' : '')
      this.$container
        .toggleClass('is-expanded', this._expanded)
        .toggleClass('is-collapsed', !this._expanded)

      expandContainer.toggleClass('hidden', this._expanded)
      collapseContainer.toggleClass('hidden', !this._expanded)
      this.$previewContainer.toggleClass('hidden', this._expanded)

      const contentHeight = this.$contentContainer.outerHeight() | 0
      const childrenHeight = this.$childrenContainer.outerHeight() | 0

      const expandedCss = {
        opacity: 1,
        height: contentHeight + childrenHeight
      }
      const collapsedCss = {
        opacity: 0,
        height: 0
      }
      const clearCss = {
        opacity: '',
        height: ''
      }

      if (animate) {
        this.$bodyContainer
          .css(this._expanded ? collapsedCss : expandedCss)
          .velocity(this._expanded ? expandedCss : collapsedCss, 'fast', e => {
            if (this._expanded) {
              this.$bodyContainer.css(clearCss)
            }
          })
      } else {
        this.$bodyContainer.css(this._expanded ? clearCss : collapsedCss)
      }

      if (save) {
        this.saveExpansion()
      }

      this.trigger('toggleExpansion', {
        expanded: this._expanded
      })
    }
  },

  isExpanded () {
    return this._expanded
  },

  saveExpansion () {
    if (!this.isNew()) {
      // Use the duplicated block ID if we're on a new provisional draft
      // The server-side code will also apply the new state to the canonical block
      const sentBlockId = this.$form.data('elementEditor')?.settings.isProvisionalDraft
        ? this.getDuplicatedBlockId()
        : this.getId()
      const data = {
        expanded: this.isExpanded() ? 1 : 0,
        blockId: sentBlockId,
        siteId: this.getSiteId()
      }

      Craft.queue.push(() => new Promise((resolve, reject) => {
        Craft.sendActionRequest('POST', 'neo/input/save-expansion', { data }).then(resolve).catch(reject)
      }))
    }
  },

  disable () {
    this.toggleEnabled(false)
  },

  enable () {
    this.toggleEnabled(true)
  },

  toggleEnabled (enable = !this._enabled) {
    const triggerEvent = this._enabled !== enable
    this._enabled = enable

    const blockTypeEnabled = this._blockType.getEnabled()
    const actuallyEnabled = this._enabled && blockTypeEnabled
    const enableContainer = this.$menuContainer.find('[data-action="enable"]').parent()
    const disableContainer = this.$menuContainer.find('[data-action="disable"]').parent()

    this.$container
      .toggleClass('is-enabled', actuallyEnabled)
      .toggleClass('is-disabled', !actuallyEnabled)

    this.$status.toggleClass('hidden', actuallyEnabled)

    enableContainer.toggleClass('hidden', this._enabled || !blockTypeEnabled)
    disableContainer.toggleClass('hidden', !this._enabled || !blockTypeEnabled)

    this.$enabledInput.val(this._enabled ? '1' : '')

    if (triggerEvent) {
      this.trigger('toggleEnabled', {
        enabled: this._enabled
      })
    }
  },

  isEnabled () {
    return this._enabled
  },

  toggleShowButtons (show = !this._showButtons) {
    this.$buttonsContainer.toggleClass('hidden', !show)
    this.$childrenWarningsContainer.toggleClass('hidden', show)
  },

  selectTab (tabName) {
    this.$tabButton.removeClass('is-selected')
    this.$tabContainer.addClass('hidden')
    const $tabButton = this.$tabButton.filter(`[data-neo-b-info="${tabName}"]`).addClass('is-selected')
    const $tabContainer = this.$tabContainer.eq($tabButton.index()).removeClass('hidden')
    this.$tabsButton.text(tabName)
    Craft.ElementThumbLoader.retryAll()

    this.trigger('selectTab', { tabName, $tabButton, $tabContainer })
  },

  updateResponsiveness () {
    const isMobileBrowser = Garnish.isMobileBrowser()
    this._topbarLeftWidth ??= this.$topbarLeftContainer.width() -
      (this._expanded ? 0 : this.$previewContainer.width()) -
      (isMobileBrowser ? this.$handleContainer.width() : 0)
    this._topbarRightWidth ??= this.$topbarRightContainer.width()
    const hasRoomForIndividualTabs = this.$topbarContainer.width() < this._topbarLeftWidth + this._topbarRightWidth

    this.$handleContainer.toggleClass('hidden', isMobileBrowser)
    this.$tabsContainer.toggleClass('invisible', hasRoomForIndividualTabs)
    this.$tabsButton.toggleClass('invisible', !hasRoomForIndividualTabs)
  },

  updateActionsMenu () {
    const blocks = this._field.getBlocks()
    const parentBlockType = this.getParent()?.getBlockType()
    let allowedBlockTypes = parentBlockType?.getChildBlocks() ?? this._field.getBlockTypes(true)

    if (allowedBlockTypes === true || allowedBlockTypes === '*') {
      allowedBlockTypes = this._field.getBlockTypes(false)
    } else if (Array.isArray(allowedBlockTypes)) {
      allowedBlockTypes = allowedBlockTypes
        .map(bt => typeof bt === 'string' ? this._field.getBlockTypeByHandle(bt) : bt)
        // In case any otherwise valid block types are being filtered out by the event or conditions
        .filter(bt => typeof bt !== 'undefined')
    }

    this.updateMenuStates(
      this._field.getName(),
      blocks,
      this._field.getMaxBlocks(),
      true,
      allowedBlockTypes,
      this._level === 1 ? this._field.getMaxTopBlocks() : 0
    )
  },

  // Deprecated in 3.0.4; use `updateActionsMenu()` instead
  updateMenuStates (field, blocks = [], maxBlocks = 0, additionalCheck = null, allowedBlockTypes = false, maxTopBlocks = 0) {
    additionalCheck = typeof additionalCheck === 'boolean' ? additionalCheck : true

    const blockType = this.getBlockType()
    const blocksOfType = blocks.filter(b => b.getBlockType().getHandle() === blockType.getHandle())
    const maxBlockTypes = blockType.getMaxBlocks()
    const siblingBlocks = this.getSiblings(blocks)

    const totalTopBlocks = blocks.filter(block => block.isTopLevel()).length

    const maxBlocksMet = maxBlocks > 0 && blocks.length >= maxBlocks
    const maxTopBlocksMet = maxTopBlocks > 0 && totalTopBlocks >= maxTopBlocks

    const allDisabled = maxBlocksMet || maxTopBlocksMet || !additionalCheck
    const typeDisabled = maxBlockTypes > 0 && blocksOfType.length >= maxBlockTypes
    let cloneDisabled = allDisabled || typeDisabled

    const pasteData = JSON.parse(window.localStorage.getItem(`neo:copy:${field}`) || '{}')
    let pasteDisabled = allDisabled || !pasteData.blocks || !pasteData.field || pasteData.field !== field

    // Test to see if pasting/cloning would exceed the parent's max child blocks
    const parentBlock = this.getParent(blocks)
    if ((!pasteDisabled || !cloneDisabled) && parentBlock) {
      const maxChildBlocks = parentBlock.getBlockType().getMaxChildBlocks()

      if (maxChildBlocks > 0) {
        const childBlockCount = parentBlock.getChildren(blocks).length
        const pasteBlockCount = pasteData.blocks?.length ?? 0
        pasteDisabled ||= childBlockCount + pasteBlockCount > maxChildBlocks
        cloneDisabled ||= childBlockCount >= maxChildBlocks
      }
    }

    // Test to see if pasting would exceed this block's max sibling blocks
    if (!(pasteDisabled && cloneDisabled)) {
      const maxSiblingBlocks = this.getBlockType().getMaxSiblingBlocks()

      if (maxSiblingBlocks > 0) {
        const hasSameBlockType = block => {
          if (Object.prototype.hasOwnProperty.call(block, 'type')) {
            return block.type === this.getBlockType().getId()
          } else if (typeof block.getBlockType === 'function') {
            return block.getBlockType().getHandle() === this.getBlockType().getHandle()
          }

          return false
        }

        const siblingBlockCount = siblingBlocks.filter(hasSameBlockType, this).length
        const pasteSiblingBlockCount = pasteData.blocks ? pasteData.blocks.filter(hasSameBlockType, this).length : 0
        pasteDisabled ||= siblingBlockCount + pasteSiblingBlockCount > maxSiblingBlocks
        cloneDisabled ||= siblingBlockCount >= maxSiblingBlocks
      }
    }

    if (!pasteDisabled) {
      const currentBlockTypesById = blocks.reduce((m, b) => {
        const bt = b.getBlockType()
        const id = bt.getId()
        const v = m[id] || { blockType: bt, count: 0 }

        v.count++
        m[id] = v

        return m
      })

      for (const pasteBlock of pasteData.blocks) {
        const pasteBlockTypeObj = currentBlockTypesById[pasteBlock.type]

        // Test to see if any max block types properties will be violated
        if (pasteBlockTypeObj) {
          const pasteBlockType = pasteBlockTypeObj.blockType
          const currentBlocksOfTypeCount = pasteBlockTypeObj.count
          const maxPasteBlockTypes = pasteBlockType.getMaxBlocks()
          const pasteTypeDisabled = maxPasteBlockTypes > 0 && currentBlocksOfTypeCount >= maxPasteBlockTypes

          pasteDisabled ||= pasteTypeDisabled
        }

        // Test to see if the top level paste blocks have a block type that is allowed to be pasted here
        if (pasteBlock.level === 1) {
          pasteDisabled ||= !allowedBlockTypes.find(bt => bt.getId() === pasteBlock.type)
        }
      }
    }

    const siblingIndex = siblingBlocks.indexOf(this)
    const disableMoveUp = siblingIndex <= 0
    const disableMoveDown = [-1, siblingBlocks.length - 1].includes(siblingIndex)

    this.$menuContainer.find('[data-action="moveUp"]').parent().toggleClass('hidden', disableMoveUp)
    this.$menuContainer.find('[data-action="moveDown"]').parent().toggleClass('hidden', disableMoveDown)
    this.$menuContainer.find('[data-action="add"]').toggleClass('disabled', allDisabled)
    this.$menuContainer.find('[data-action="duplicate"]').toggleClass('disabled', cloneDisabled)
    this.$menuContainer.find('[data-action="paste"]').toggleClass('disabled', pasteDisabled)
  },

  toggleSettingsMenu (toggle) {
    toggle ??= !this._settingsMenu.isExpanded()
    if (toggle) {
      this._settingsMenu.show()
    } else {
      this._settingsMenu.hide()
    }
  },

  _handleActionClick (e) {
    e.preventDefault()
    this['@settingSelect'](e)
  },

  _handleActionKeydown (e) {
    if (e.keyCode === Garnish.SPACE_KEY) {
      e.preventDefault()
      this['@settingSelect'](e)
    }
  },

  _handleTabKeydown (e) {
    if (e.keyCode === Garnish.SPACE_KEY) {
      this['@setTab'](e)
    }
  },

  _detectChange () {
    // When editing a draft and autosave is enabled, we need to force modified to be set, or
    // returning the block to its original values will cause it not to be resaved.
    const elementEditor = this.$form.data('elementEditor')

    if (elementEditor?.enableAutosave && elementEditor.settings.draftId) {
      this.setModified(true)
      this._forceModified = true
    }

    if (!this._forceModified) {
      const initial = this._initialState
      const content = this._getPostData()

      const modified = !Craft.compare(content, initial.content, false) ||
        initial.enabled !== this._enabled ||
        initial.level !== this._level

      if (modified !== this._modified) {
        this.setModified(modified)
      }
    }

    this.trigger('change')
  },

  _getPostData () {
    const content = Garnish.getPostData(this.$contentContainer)
    // Remove keys associated with child block subfields (occurs when using child blocks UI element)
    const badKeys = Object.keys(content)
      .filter((key) => !key.startsWith(`fields[${this._field.getName()}][blocks][${this._id}]`))

    for (const key of badKeys) {
      delete content[key]
    }

    return content
  },

  '@settingSelect' (e) {
    this._settingsMenu.hide()
    const $option = $(e.target)

    if (!$option.hasClass('disabled')) {
      switch ($option.attr('data-action')) {
        case 'collapse':
          this.collapse()
          break
        case 'expand':
          this.expand()
          break
        case 'disable':
          this.disable()
          this.collapse()
          break
        case 'enable':
          this.enable()
          this.expand()
          break
        case 'moveUp':
          this.trigger('moveUpBlock', { block: this })
          break
        case 'moveDown':
          this.trigger('moveDownBlock', { block: this })
          break
        case 'delete':
          this.trigger('removeBlock', { block: this })
          break
        case 'add':
          this.trigger('addBlockAbove', { block: this })
          break
        case 'copy':
          this.trigger('copyBlock', { block: this })
          break
        case 'paste':
          this.trigger('pasteBlock', { block: this })
          break
        case 'duplicate':
          this.trigger('duplicateBlock', { block: this })
          break
      }
    }
  },

  '@doubleClickTitle' (e) {
    e.preventDefault()

    const $target = $(e.target)
    const $checkFrom = $target.parent()
    const isLeft = $checkFrom.closest(this.$topbarLeftContainer).length > 0
    const isRight = $checkFrom.closest(this.$topbarRightContainer).length > 0

    if (!isLeft && !isRight) {
      this.$form.data('elementEditor')?.pause()
      this.toggleExpansion()
      this.$form.data('elementEditor')?.resume()
    }
  },

  '@setTab' (e) {
    e.preventDefault()
    this._tabsMenu.hide()

    const $tab = $(e.currentTarget)
    const tabName = $tab.attr('data-neo-b-info')

    this.selectTab(tabName)
  }
},
{
  _totalNewBlocks: 0,

  getNewId () {
    return `new${this._totalNewBlocks++}`
  }
})
