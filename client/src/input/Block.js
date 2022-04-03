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
  level: 0,
  buttons: null,
  enabled: true,
  collapsed: false,
  modified: true,
  showButtons: true,
  hasOldChildBlocksContainer: true
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
    this._modified = settings.modified
    this._showButtons = settings.showButtons
    this._hasOldChildBlocksContainer = settings.hasOldChildBlocksContainer
    this.$container = generateElement ? this._generateElement() : $(`[data-neo-b-id=${this._id}]`)

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
    this.$tabsContainer = $neo.filter(`[data-neo-b="${this._id}.container.tabs"]`)
    this.$tabContainer = $neo.filter(`[data-neo-b="${this._id}.container.tab"]`)
    this.$menuContainer = $neo.filter(`[data-neo-b="${this._id}.container.menu"]`)
    this.$previewContainer = $neo.filter(`[data-neo-b="${this._id}.container.preview"]`)
    this.$tabButton = $neo.filter(`[data-neo-b="${this._id}.button.tab"]`)
    this.$settingsButton = $neo.filter(`[data-neo-b="${this._id}.button.actions"]`)
    this.$togglerButton = $neo.filter(`[data-neo-b="${this._id}.button.toggler"]`)
    this.$tabsButton = $neo.filter(`[data-neo-b="${this._id}.button.tabs"]`)
    this.$enabledInput = $neo.filter(`[data-neo-b="${this._id}.input.enabled"]`)
    this.$levelInput = $neo.filter(`[data-neo-b="${this._id}.input.level"]`)
    this.$status = $neo.filter(`[data-neo-b="${this._id}.status"]`)
    this.$sortOrder = $neo.filter(`[data-neo-b="${this._id}.sortOrder"]`)

    if (this._buttons) {
      this._buttons.on('newBlock', e => this.trigger('newBlock', Object.assign(e, { level: this.getLevel() + 1 })))
      this.$buttonsContainer.append(this._buttons.$container)
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
    this.toggleEnabled(settings.enabled)
    this.toggleExpansion(hasErrors ? true : !settings.collapsed, false, false)
    this.toggleShowButtons(this._showButtons)

    this.addListener(this.$topbarContainer, 'dblclick', '@doubleClickTitle')
    this.addListener(this.$tabButton, 'click', '@setTab')
  },

  _generateElement () {
    NS.enter(this._templateNs)
    const baseInputName = NS.toFieldName()
    NS.leave()

    const type = this._blockType
    const typeTabs = type.getTabs()
    const hasTabs = typeTabs.length > 0
    const isParent = type.isParent()
    const actionBtnLabel = `${type.getName()} ${Craft.t('neo', 'Actions')}`
    const actionMenuId = `neoblock-action-menu-${this._id}`
    const elementHtml = []
    elementHtml.push(`
      <div class="ni_block ni_block--${type.getHandle()} is-${this._collapsed ? 'collapsed' : 'expanded'} ${!hasTabs && !isParent ? 'is-empty' : ''} ${isParent ? 'is-parent' : ''}" data-neo-b-id="${this._id}">
        <input type="hidden" name="${baseInputName}[type]" value="${type.getHandle()}">
        <input type="hidden" name="${baseInputName}[enabled]" value="${this._enabled ? '1' : '0'}" data-neo-b="${this._id}.input.enabled">
        <input type="hidden" name="${baseInputName}[level]" value="${this._level}" data-neo-b="${this._id}.input.level">
        <input type="hidden" name="${this._templateNs[0]}[${this._templateNs[1]}][sortOrder][]" value="${this._id}" data-neo-b="${this._id}.input.sortOrder">

        <div class="ni_block_topbar" data-neo-b="${this._id}.container.topbar">
          <div class="ni_block_topbar_left" data-neo-b="${this._id}.container.topbarLeft">
            <div class="ni_block_topbar_item" data-neo-b="${this._id}.select">
              <div class="checkbox block-checkbox" title="${Craft.t('neo', 'Select')} aria-label="${Craft.t('neo', 'Select')}"></div>
            </div>
            <div class="ni_block_topbar_item title clip-text">
              <span class="blocktype" data-neo-b="${this._id}.select">${type.getName()}</span><span class="preview" data-neo-b="${this._id}.container.preview">&nbsp;</span>
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
                <a class="tab${!i ? 'is-selected' : ''}" data-neo-b="${this._id}.button.tab" data-neo-b-info="${tabName}">${tabName}</a>`)
      }

      elementHtml.push(`
              </div>
              <div class="tabs_btn menubtn" data-neo-b="${this._id}.button.tabs">
                ${typeTabs[0].getName()}
              </div>
              <div class="neo_block_tabs-menu menu">
                <ul>`)

      for (let i = 0; i < typeTabs.length; i++) {
        const tabName = typeTabs[i].getName()
        elementHtml.push(`
                  <li>
                    <a${!i ? ' class="is-selected"' : ''} data-neo-b="${this._id}.button.tab" data-neo-b-info="${tabName}">${tabName}</a>
                  </li>`)
      }

      elementHtml.push(`
                </ul>
              </div>`)
    }

    elementHtml.push(`
            </div>
            <div class="ni_block_topbar_item hidden" data-neo-b="${this._id}.status">
              <div class="status off" title="${Craft.t('neo', 'Disabled')}"></div>
            </div>
            <div class="ni_block_topbar_item block-settings">
              <div data-wrapper>
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
                  </ul>
                  <hr>
                  <ul class="padded">
                    <li><a class="error" data-icon="remove" data-action="delete" href="#" type="button" role="button" aria-label="${Craft.t('neo', 'Delete')}">${Craft.t('neo', 'Delete')}</a></li>
                  </ul>
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
          <div class="ni_block_content" data-neo-b="${this._id}.container.content">`)

        for (let i = 0; i < typeTabs.length; i++) {
          const tabName = typeTabs[i].getName()
          elementHtml.push(`
            <div class="ni_block_content_tab flex-fields${!i ? ' is-selected' : ''}" data-neo-b="${this._id}.container.tab" data-neo-b-info="${tabName}">
              ${typeTabs[i].getBodyHtml(this._id)}
            </div>`)
          Garnish.$bod.append(typeTabs[i].getFootHtml(this._id))
        }

        elementHtml.push(`
          </div>`)
      }

      if (isParent && this._hasOldChildBlocksContainer) {
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

    return $(elementHtml.join(''))
  },

  initUi (callInitUiElements = true) {
    if (this._initialised) {
      // Nothing to do here
      return
    }

    const tabs = this._blockType.getTabs()
    const footList = tabs.map(tab => tab.getFootHtml(this._id))
    this.$foot = $(footList.join('')).filter(_resourceFilter)

    Garnish.$bod.append(this.$foot)

    if (callInitUiElements) {
      Craft.initUiElements(this.$contentContainer)
    }

    this.$tabsButton.menubtn()

    this._settingsMenu = new Garnish.DisclosureMenu(this.$settingsButton)
    this._settingsMenu.on('show', () => this.$container.addClass('active'))
    this._settingsMenu.on('hide', () => this.$container.removeClass('active'))
    this.addListener(this.$menuContainer.find('[data-action]'), 'click', this._handleActionClick)
    this.addListener(this.$menuContainer.find('[data-action]'), 'keydown', this._handleActionKeydown)

    this._initialised = true

    if (this._buttons) {
      this._buttons.initUi()
    }

    Garnish.requestAnimationFrame(() => this.updateResponsiveness())

    // For Matrix blocks inside a Neo block, this listener adds a class name to the block for Neo to style.
    // Neo applies its own styles to Matrix blocks in an effort to improve the visibility of them, however
    // when dragging a Matrix block these styles get lost (since a dragged Matrix block loses its context of
    // being inside a Neo block). Adding this class name to blocks before they are dragged means that the
    // dragged Matrix block can still have the Neo-specific styles.
    this.$container.on('mousedown', '.matrixblock', function (e) {
      $(this).addClass('neo-matrixblock')
    })

    // Setting up field and block property watching
    if (!this._modified && !this.isNew()) {
      this._initialState = {
        enabled: this._enabled,
        level: this._level,
        content: Garnish.getPostData(this.$contentContainer)
      }

      const detectChange = () => this._detectChange()
      const observer = new window.MutationObserver(() => setTimeout(detectChange, 200))

      observer.observe(this.$container[0], {
        attributes: true,
        childList: true,
        characterData: true,
        subtree: true
      })

      this.$contentContainer.on('propertychange change click', 'input, textarea, select, div.redactor-in', detectChange)
      this.$contentContainer.on('paste input keyup', 'input:not([type="hidden"]), textarea, div.redactor-in', detectChange)

      this._detectChangeObserver = observer

      // If there's a Super Table field with a static row or min rows set, we need to check for new
      // rows and force this block's modified state so it saves the new rows
      if (this.$contentContainer.length > 0 && this.$contentContainer.html().match(/\[blocks\]\[new/)) {
        this._forceModified = true
        this.setModified(true)
        const fieldInputName = this._templateNs[0] + '[' + this._templateNs[1] + ']'

        if (!Craft.modifiedDeltaNames.includes(fieldInputName)) {
          Craft.modifiedDeltaNames.push(fieldInputName)
        }
      }
    }

    addFieldLinks(this.$contentContainer)

    this.trigger('initUi')
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

  getLocale () {
    if (!this._locale) {
      const $form = this.$container.closest('form')
      const $locale = $form.find('input[name="siteId"]')

      this._locale = $locale.val()
    }

    return this._locale
  },

  getContent () {
    const rawContent = Garnish.getPostData(this.$contentContainer)
    const content = {}

    const setValue = (keys, value) => {
      let currentSet = content

      for (let i = 0; i < keys.length - 1; i++) {
        const key = keys[i]

        if (!$.isPlainObject(currentSet[key]) && !$.isArray(currentSet[key])) {
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

  getParent (blocks) {
    const level = this.getLevel()
    let index = blocks.indexOf(this)
    let blockParent = null

    if (index >= 0 && level > 0) {
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

  getChildren (blocks, descendants = null) {
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

  getSiblings (blocks) {
    if (this._level === 0) {
      return blocks.filter(b => b.getLevel() === 0)
    }

    return this.getParent(blocks).getChildren(blocks)
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
            const $elements = $input.find('.element')

            $elements.each(function () {
              const $element = $(this)
              const title = $element.find('.title, .label').eq(0).text()

              values.push(_escapeHTML(_limit(title)))
            })

            value = values.join(', ')
          }
          break
        case 'craft\\fields\\Checkboxes':
          {
            const values = []
            const $checkboxes = $input.find('input[type="checkbox"]')

            $checkboxes.each(function () {
              if (this.checked) {
                const $checkbox = $(this)
                const id = $checkbox.prop('id')
                const $label = $input.find(`label[for="${id}"]`)
                const label = $label.text()

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
          value = _escapeHTML(_limit($input.children('input[type="text"]').val()))
          break
        case 'craft\\fields\\RadioButtons':
          {
            const $checked = $input.find('input[type="radio"]:checked')
            const $label = $checked.closest('label')
            const label = $label.text()

            value = _escapeHTML(_limit(label))
          }
          break
        case 'craft\\redactor\\Field':
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
        case 'fruitstudios\\linkit\\fields\\LinkitField':
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
    expand = (typeof expand === 'boolean' ? expand : !this._expanded)
    save = (typeof save === 'boolean' ? save : true)
    animate = (typeof animate === 'boolean' ? animate : true)

    if (expand !== this._expanded) {
      this._expanded = expand

      const expandContainer = this.$menuContainer.find('[data-action="expand"]').parent()
      const collapseContainer = this.$menuContainer.find('[data-action="collapse"]').parent()

      this.$container
        .toggleClass('is-expanded', this._expanded)
        .toggleClass('is-collapsed', !this._expanded)

      expandContainer.toggleClass('hidden', this._expanded)
      collapseContainer.toggleClass('hidden', !this._expanded)

      if (!this._expanded) {
        this.updatePreview()
      }

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
      const thisBlockId = this.getId()
      const elementEditor = $('#main-form').data('elementEditor')
      const duplicatedBlockId = elementEditor.duplicatedElements[thisBlockId]
      const sentBlockId = elementEditor.settings.isProvisionalDraft && typeof duplicatedBlockId !== 'undefined'
        ? duplicatedBlockId
        : thisBlockId
      const data = {
        expanded: this.isExpanded() ? 1 : 0,
        blockId: sentBlockId,
        locale: this.getLocale()
      }

      Craft.queueActionRequest(() => Craft.postActionRequest('neo/input/save-expansion', data))
    }
  },

  disable () {
    this.toggleEnabled(false)
  },

  enable () {
    this.toggleEnabled(true)
  },

  toggleEnabled (enable = !this._enabled) {
    if (enable !== this._enabled) {
      this._enabled = enable

      const enableContainer = this.$menuContainer.find('[data-action="enable"]').parent()
      const disableContainer = this.$menuContainer.find('[data-action="disable"]').parent()

      this.$container
        .toggleClass('is-enabled', this._enabled)
        .toggleClass('is-disabled', !this._enabled)

      this.$status.toggleClass('hidden', this._enabled)

      enableContainer.toggleClass('hidden', this._enabled)
      disableContainer.toggleClass('hidden', !this._enabled)

      this.$enabledInput.val(this._enabled ? 1 : 0)

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

  selectTab (name) {
    const $tabs = $()
      .add(this.$tabButton)
      .add(this.$tabContainer)

    $tabs.removeClass('is-selected')
    const $tab = $tabs.filter(`[data-neo-b-info="${name}"]`).addClass('is-selected')
    this.$tabsButton.text(name)
    Craft.ElementThumbLoader.retryAll()

    this.trigger('selectTab', {
      tabName: name,
      $tabButton: $tab.filter('[data-neo-b="button.tab"]'),
      $tabContainer: $tab.filter('[data-neo-b="container.tab"]')
    })
  },

  updateResponsiveness () {
    if (typeof this._topbarLeftWidth === 'undefined') {
      const previewWidth = this._expanded ? 0 : this.$previewContainer.width()
      this._topbarLeftWidth = this.$topbarLeftContainer.width() - previewWidth
    }

    this._topbarRightWidth = this._topbarRightWidth || this.$topbarRightContainer.width()
    const isMobile = (this.$topbarContainer.width() < this._topbarLeftWidth + this._topbarRightWidth)

    this.$tabsContainer.toggleClass('invisible', isMobile)
    this.$tabsButton.toggleClass('invisible', !isMobile)
  },

  updateMenuStates (field, blocks = [], maxBlocks = 0, additionalCheck = null, allowedBlockTypes = false, maxTopBlocks = 0) {
    additionalCheck = (typeof additionalCheck === 'boolean') ? additionalCheck : true

    const blockType = this.getBlockType()
    const blocksOfType = blocks.filter(b => b.getBlockType().getHandle() === blockType.getHandle())
    const maxBlockTypes = blockType.getMaxBlocks()
    const siblingBlocks = this.getSiblings(blocks)

    const totalTopBlocks = blocks.filter(block => block.getLevel() === 0).length

    const maxBlocksMet = maxBlocks > 0 && blocks.length >= maxBlocks
    const maxTopBlocksMet = maxTopBlocks > 0 && totalTopBlocks >= maxTopBlocks

    const allDisabled = maxBlocksMet || maxTopBlocksMet || !additionalCheck
    const typeDisabled = (maxBlockTypes > 0 && blocksOfType.length >= maxBlockTypes)
    let cloneDisabled = allDisabled || typeDisabled

    const pasteData = JSON.parse(window.localStorage.getItem('neo:copy') || '{}')
    let pasteDisabled = allDisabled || !pasteData.blocks || !pasteData.field || pasteData.field !== field

    // Test to see if pasting would exceed the parent's max child blocks
    const parentBlock = this.getParent(blocks)
    if (!pasteDisabled && parentBlock) {
      const maxChildBlocks = parentBlock.getBlockType().getMaxChildBlocks()

      if (maxChildBlocks > 0) {
        const childBlockCount = parentBlock.getChildren(blocks).length
        const pasteBlockCount = pasteData.blocks.length
        pasteDisabled = pasteDisabled || childBlockCount + pasteBlockCount > maxChildBlocks
      }
    }

    // Test to see if pasting would exceed this block's max sibling blocks
    if (!(pasteDisabled && cloneDisabled)) {
      const maxSiblingBlocks = this.getBlockType().getMaxSiblingBlocks()

      if (maxSiblingBlocks > 0) {
        const hasSameBlockType = (block) => {
          if (Object.prototype.hasOwnProperty.call(block, 'type')) {
            return block.type === this.getBlockType().getId()
          } else if (typeof block.getBlockType === 'function') {
            return block.getBlockType().getHandle() === this.getBlockType().getHandle()
          }

          return false
        }

        const siblingBlockCount = siblingBlocks.filter(hasSameBlockType, this).length
        const pasteSiblingBlockCount = pasteData.blocks ? pasteData.blocks.filter(hasSameBlockType, this).length : 0
        pasteDisabled = pasteDisabled || siblingBlockCount + pasteSiblingBlockCount > maxSiblingBlocks
        cloneDisabled = cloneDisabled || siblingBlockCount >= maxSiblingBlocks
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
          const pasteTypeDisabled = (maxPasteBlockTypes > 0 && currentBlocksOfTypeCount >= maxPasteBlockTypes)

          pasteDisabled = pasteDisabled || pasteTypeDisabled
        }

        // Test to see if the top level paste blocks have a block type that is allowed to be pasted here
        if (pasteBlock.level === 0) {
          const allowedBlockType = allowedBlockTypes.find(bt => bt.getId() === pasteBlock.type)

          pasteDisabled = pasteDisabled || !allowedBlockType
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

  _detectChange () {
    // When editing a draft and autosave is enabled, we need to force modified to be set, or
    // returning the block to its original values will cause it not to be resaved.
    if (
      window.draftEditor &&
      window.draftEditor.enableAutosave &&
      window.draftEditor.settings.draftId
    ) {
      this.setModified(true)
      this._forceModified = true
    }

    if (this._forceModified) {
      return
    }

    const initial = this._initialState
    const content = Garnish.getPostData(this.$contentContainer)

    const modified = !Craft.compare(content, initial.content, false) ||
      initial.enabled !== this._enabled ||
      initial.level !== this._level

    if (modified !== this._modified) {
      this.setModified(modified)
    }
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
    const isLeft = ($checkFrom.closest(this.$topbarLeftContainer).length > 0)
    const isRight = ($checkFrom.closest(this.$topbarRightContainer).length > 0)

    if (!isLeft && !isRight) {
      if (window.draftEditor) {
        window.draftEditor.pause()
      }

      this.toggleExpansion()

      if (window.draftEditor) {
        window.draftEditor.resume()
      }
    }
  },

  '@setTab' (e) {
    e.preventDefault()

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
