import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'
import Tab from './BlockTypeTab'

import { addFieldLinks } from '../plugins/cpfieldinspect/main'

const _defaults = {
  namespace: [],
  blockType: null,
  tabs: null,
  id: null,
  level: 1,
  buttons: null,
  enabled: true,
  collapsed: false,
  modified: true,
  showButtons: true,
  showBlockTypeHandle: false
}

const _escapeMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;'
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
  _tabs: null,
  _html: null,
  _js: null,

  init (settings = {}, generateElement = false) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._field = settings.field
    this._blockType = settings.blockType
    if (settings.tabs !== null) {
      this._tabs = settings.tabs.tabNames?.map(
        tab => tab instanceof Tab
          ? tab
          : new Tab({
            name: tab,
            uid: settings.tabs.tabUids[tab]
          })
      ) ?? []
    } else {
      this._tabs = null
    }
    this._blockHtml = settings.blockHtml
    this._bodyHtml = settings.bodyHtml
    this._headHtml = settings.headHtml
    this._id = settings.id
    this._enabled = settings.enabled && this._blockType.getEnabled()
    this._initialEnabled = settings.enabled
    this._modified = settings.modified
    this._showButtons = settings.showButtons
    this._renderOldChildBlocksContainer = !settings.blockType.hasChildBlocksUiElement()
    this.$container = this._blockHtml
      ? $(this._blockHtml)
      : this._field.$container.find(`[data-neo-b-id=${this._id}]`)
    this._uuid = settings.uuid ?? this.$container.data('neo-b-uuid')

    const $neo = this.$container.find('[data-neo-b]')
    this.$bodyContainer = $neo.filter(`[data-neo-b="${this._id}.container.body"]`)
    this.$contentContainer = $neo.filter(`[data-neo-b="${this._id}.container.content"]`)
    this.$topbarContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbar"]`)
    this.$topbarLeftContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbarLeft"]`)
    this.$topbarRightContainer = $neo.filter(`[data-neo-b="${this._id}.container.topbarRight"]`)
    this.$handleContainer = $neo.filter(`[data-neo-b="${this._id}.container.handle"]`)
    this.$tabContainer = this.$contentContainer.children('[data-layout-tab]')
    this.$menuContainer = $neo.filter(`[data-neo-b="${this._id}.container.menu"]`)
    this.$previewContainer = $neo.filter(`[data-neo-b="${this._id}.container.preview"]`)
    this.$settingsButton = $neo.filter(`[data-neo-b="${this._id}.button.actions"]`)
    this.$togglerButton = $neo.filter(`[data-neo-b="${this._id}.button.toggler"]`)
    this.$enabledInput = $neo.filter(`[data-neo-b="${this._id}.input.enabled"]`)
    this.$levelInput = $neo.filter(`[data-neo-b="${this._id}.input.level"]`)
    this.$collapsedInput = $neo.filter(`[data-neo-b="${this._id}.input.collapsed"]`)
    this.$status = $neo.filter(`[data-neo-b="${this._id}.status"]`)
    this.$sortOrder = $neo.filter(`[data-neo-b="${this._id}.sortOrder"]`)
    this.$form = this.$container.closest('form')
    this.resetButtons(settings.buttons)

    let hasErrors = false
    if (this._blockType) {
      for (const tabName of this._blockType.getTabNames()) {
        const selector = `[data-neo-b-info="${tabName}"]`

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

  initUi (callInitUiElements = true) {
    if (this._initialised) {
      // Nothing to do here
      return
    }

    if (callInitUiElements) {
      Craft.appendBodyHtml(this._bodyHtml)
      Craft.appendHeadHtml(this._headHtml)
      Craft.initUiElements(this.$contentContainer)
    }

    this.$form = this.$container.closest('form')
    this.initTabs()

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
  initTabs () {
    const $neo = this.$container.find('[data-neo-b]')
    this.$tabsButton = $neo.filter(`[data-neo-b="${this._id}.button.tabs"]`)
    this.$tabsContainer = $neo.filter(`[data-neo-b="${this._id}.container.tabs"]`)
    this.$tabButton = $neo.filter(`[data-neo-b="${this._id}.button.tab"]`)
    this.$tabContainer = this.$contentContainer.children('[data-layout-tab]')

    this._tabsMenu = this.$tabsButton.data('trigger') || new Garnish.DisclosureMenu(this.$tabsButton)
    this._tabsMenu.on('show', () => this.$container.addClass('active'))
    this._tabsMenu.on('hide', () => this.$container.removeClass('active'))

    this.$tabButton = this.$tabButton.add(this._tabsMenu.$container.find(`[data-neo-b="${this._id}.button.tab"]`))
    this.addListener(this.$tabButton, 'click', this['@setTab'])
    this.addListener(this.$tabButton, 'keydown', this._handleTabKeydown)
  },

  /**
   * @since 3.9.0
   */
  getHtml () {
    return this._blockHtml.replace(/__NEOBLOCK__/g, this._id)
  },

  /**
   * @since 3.9.0
   */
  getJs () {
    return this._bodyHtml.replace(/__NEOBLOCK__/g, this._id)
  },

  destroy () {
    if (this._initialised) {
      this.$foot?.remove()

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
   * @returns the block UUID
   * @since 4.2.0
   */
  getUuid () {
    return this._uuid
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
    const tabUid = $tabButton.attr('data-neo-b-tabuid')
    const $tabContainer = this.$tabContainer
      .filter(`[data-layout-tab="${tabUid}"]`)
      .removeClass('hidden')
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

    // Finally, only allow block types that are allowed to be created by the current user
    // This is safe since allowedBlockTypes is only used to check if paste/add block actions should be disabled
    allowedBlockTypes = allowedBlockTypes.filter((bt) => bt.isCreatableByUser())

    const field = this._field.getName()
    const maxBlocks = this._field.getMaxBlocks()
    const additionalCheck = true
    const maxTopBlocks = this._level === 1 ? this._field.getMaxTopBlocks() : 0
    const noAllowedBlockTypes = !allowedBlockTypes || allowedBlockTypes.length === 0

    const blockType = this.getBlockType()
    const blocksOfType = blocks.filter(b => b.getBlockType().getHandle() === blockType.getHandle())
    const maxBlockTypes = blockType.getMaxBlocks()
    const siblingBlocks = this.getSiblings(blocks)

    const totalTopBlocks = blocks.filter(block => block.isTopLevel()).length

    const maxBlocksMet = maxBlocks > 0 && blocks.length >= maxBlocks
    const maxTopBlocksMet = maxTopBlocks > 0 && totalTopBlocks >= maxTopBlocks

    const allDisabled = maxBlocksMet || maxTopBlocksMet || !additionalCheck
    const addDisabled = allDisabled || noAllowedBlockTypes
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
    this.$menuContainer.find('[data-action="duplicate"]').toggleClass('disabled', cloneDisabled)

    // Paste/add actions should be hidden if there is no chance of them being enabled later
    if (noAllowedBlockTypes) {
      this.$menuContainer.find('[data-action="add"]').parent().toggleClass('hidden', addDisabled)
      this.$menuContainer.find('[data-action="paste"]').parent().toggleClass('hidden', pasteDisabled)
    } else {
      this.$menuContainer.find('[data-action="add"]').toggleClass('disabled', addDisabled)
      this.$menuContainer.find('[data-action="paste"]').toggleClass('disabled', pasteDisabled)
    }

    // If there are no visible items in the second list, hide the separator as well
    this.$menuContainer.children('hr').toggleClass(
      'hidden',
      this.$menuContainer.children('ul:last-child').children('li:not(.hidden)').length === 0
    )
  },

  resetButtons (settings) {
    this.$blocksContainer = this.$container.find(`[data-neo-b="${this._id}.container.blocks"]`)
    this.$buttonsContainer = this.$container.find(`[data-neo-b="${this._id}.container.buttons"]`)
    this.$childrenContainer = this.$container.find(`[data-neo-b="${this._id}.container.children"]`)
    this.$childrenWarningsContainer = this.$container.find(`[data-neo-b="${this._id}.container.childrenWarnings"]`)
    this.$collapsedChildrenContainer = this.$container.find(`[data-neo-b="${this._id}.container.collapsedChildren"]`)

    if (typeof settings !== 'undefined' && settings !== null) {
      this._buttons = settings
    } else {
      this._buttons = new this._field.ButtonClass({
        $ownerContainer: this.$container,
        field: this._field,
        items: this._blockType.getChildBlockItems(this._field.getItems()),
        maxBlocks: this._field.getMaxBlocks()
      })
    }

    if (this._buttons) {
      this._buttons.on('newBlock', e => this.trigger('newBlock', Object.assign(e, { level: this.getLevel() + 1 })))
      this.$buttonsContainer.append(this._buttons.$container)

      if (this._buttons.$ownerContainer === null) {
        this._buttons.$ownerContainer = this.$container
      }

      if (this._initialised) {
        this._buttons.initUi()
      }
    }
  },

  namespaceId (id) {
    NS.enter(this._templateNs)
    const namespacedId = `${NS.toString('-')}-${Craft.formatInputId(id)}`
    NS.leave()
    return namespacedId
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
})
