import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'

const _defaults = {
  blockTypes: [],
  groups: [],
  items: null,
  maxBlocks: 0,
  maxTopBlocks: 0,
  blocks: null
}

export default Garnish.Base.extend({

  _blockTypes: [],
  _groups: [],
  _maxBlocks: 0,
  _maxTopBlocks: 0,

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    if (settings.items) {
      this._items = Array.from(settings.items)
      this._blockTypes = this._items.filter(i => i.getType() === 'blockType')
      this._groups = this._items.filter(i => i.getType() === 'group')
    } else {
      this._blockTypes = Array.from(settings.blockTypes)
      this._groups = Array.from(settings.groups)
      this._items = [...this._blockTypes, ...this._groups].sort((a, b) => a.getSortOrder() - b.getSortOrder())
    }

    this._maxBlocks = settings.maxBlocks | 0
    this._maxTopBlocks = settings.maxTopBlocks | 0

    this.$container = this._generateButtons()

    const $neo = this.$container.find('[data-neo-bn]')
    this.$buttonsContainer = $neo.filter('[data-neo-bn="container.buttons"]')
    this.$menuContainer = $neo.filter('[data-neo-bn="container.menu"]')
    this.$blockButtons = $neo.filter('[data-neo-bn="button.addBlock"]')
    this.$groupButtons = $neo.filter('[data-neo-bn="button.group"]')

    if (settings.blocks) {
      this.updateButtonStates(settings.blocks)
    }

    this.addListener(this.$blockButtons, 'activate', '@newBlock')
  },

  _generateButtons () {
    const buttonsHtml = []
    let currentGroup = null
    let firstButton = true

    buttonsHtml.push(`
      <div class="ni_buttons">
        <div class="btngroup" data-neo-bn="container.buttons">`)

    for (let i = 0; i < this._items.length; i++) {
      const item = this._items[i]
      const type = item.getType()

      if (type === 'blockType') {
        if (currentGroup !== null) {
          buttonsHtml.push(`
            <li>
              <a data-neo-bn="button.addBlock" data-neo-bn-info="${item.getHandle()}">${item.getName()}</a>
            </li>`)
        } else {
          buttonsHtml.push(`
          <div class="btn dashed${firstButton ? ' add icon' : ''}" data-neo-bn="button.addBlock" data-neo-bn-info="${item.getHandle()}">
            ${item.getName()}
          </div>`)
          firstButton = false
        }
      } else if (type === 'group') {
        if (currentGroup !== null) {
          buttonsHtml.push(`
          </ul>
        </div>`)
        }

        if (item.isBlank()) {
          // Never show dropdowns for groups with blank names, as they're just used to end the previous group
          currentGroup = null
        } else if (!item.getAlwaysShowDropdown() && ((i + 2) >= this._items.length || this._items[i + 2].getType() === 'group')) {
          // Don't show dropdowns if we're not forcing them to show, and there's only one block type in this group
          currentGroup = null
        } else {
          currentGroup = item
        }

        if (currentGroup !== null) {
          buttonsHtml.push(`
        <div class="btn dashed${firstButton ? ' add icon' : ''} menubtn" data-neo-bn="button.group">
          ${item.getName()}
        </div>
        <div class="menu">
          <ul>`)
          firstButton = false
        }
      }
    }

    if (currentGroup !== null) {
      buttonsHtml.push(`
            </ul>
          </div>`)
    }

    buttonsHtml.push(`
        </div>
        <div class="btn dashed add icon menubtn hidden" data-neo-bn="container.menu">
          ${Craft.t('neo', 'Add a block')}
        </div>`)

    // Menu, for views where the buttons would exceed the editor width
    currentGroup = null
    let lastGroupHadBlockTypes = false
    buttonsHtml.push(`
        <div class="menu">
          <ul>`)

    for (const item of this._items) {
      const type = item.getType()

      if (type === 'blockType') {
        if (currentGroup !== null && !lastGroupHadBlockTypes) {
          lastGroupHadBlockTypes = true

          buttonsHtml.push(`
              <h6>${currentGroup.getName()}</h6>
              <ul class="padded">`)
        }

        buttonsHtml.push(`
            <li>
              <a data-neo-bn="button.addBlock" data-neo-bn-info="${item.getHandle()}">
                ${item.getName()}
              </a>
            </li>`)
      } else if (type === 'group') {
        if (currentGroup === null || lastGroupHadBlockTypes) {
          buttonsHtml.push(`
              </ul>`)
        }

        lastGroupHadBlockTypes = false
        currentGroup = item.isBlank() ? null : item

        if (currentGroup === null) {
          buttonsHtml.push(`
              <ul>`)
        }
      }
    }

    buttonsHtml.push(`
          </ul>
        </div>
      </div>`)

    return $(buttonsHtml.join(''))
  },

  initUi () {
    $('.menubtn', this.$container).menubtn()
    this.updateResponsiveness()
  },

  getBlockTypes () {
    return Array.from(this._blockTypes)
  },

  getGroups () {
    return Array.from(this._groups)
  },

  getMaxBlocks () {
    return this._maxBlocks
  },

  updateButtonStates (blocks = [], additionalCheck = null, block = null) {
    additionalCheck = typeof additionalCheck === 'boolean' ? additionalCheck : true

    const that = this

    const totalTopBlocks = blocks.filter(block => block.isTopLevel()).length
    const maxBlocksMet = this._maxBlocks > 0 && blocks.length >= this._maxBlocks
    const maxTopBlocksMet = this._maxTopBlocks > 0 && totalTopBlocks >= this._maxTopBlocks

    const allDisabled = maxBlocksMet || maxTopBlocksMet || !additionalCheck

    this.$blockButtons.each(function () {
      const $button = $(this)
      let disabled = allDisabled

      if (!disabled) {
        const blockHasSameType = b => b.getBlockType().getHandle() === blockType.getHandle()
        const blockType = that.getBlockTypeByButton($button)
        const blocksOfType = blocks.filter(blockHasSameType)
        const maxBlocksOfType = blockType.getMaxBlocks()

        const maxSiblingBlocks = blockType.getMaxSiblingBlocks()
        const siblingBlocksOfType = block !== null
          ? block.getChildren(blocks).filter(blockHasSameType)
          // This is at the top level
          : blocks.filter(b => b.isTopLevel() && b.getBlockType().getHandle() === blockType.getHandle())

        disabled ||= (maxBlocksOfType > 0 && blocksOfType.length >= maxBlocksOfType) ||
          (maxSiblingBlocks > 0 && siblingBlocksOfType.length >= maxSiblingBlocks)
      }

      $button.toggleClass('disabled', disabled)
    })

    this.$groupButtons.each(function () {
      const $button = $(this)
      const menu = $button.data('menubtn')
      let disabled = allDisabled

      if (!disabled && menu) {
        const $menuButtons = menu.menu.$options
        disabled = $menuButtons.length === $menuButtons.filter('.disabled').length
      }

      $button.toggleClass('disabled', disabled)
    })
  },

  updateResponsiveness () {
    this._buttonsContainerWidth ??= this.$buttonsContainer.width()
    const isMobile = this.$container.width() < this._buttonsContainerWidth

    this.$buttonsContainer.toggleClass('hidden', isMobile)
    this.$menuContainer.toggleClass('hidden', !isMobile)
  },

  getBlockTypeByButton ($button) {
    const btHandle = $button.attr('data-neo-bn-info')

    return this._blockTypes.find(bt => bt.getHandle() === btHandle)
  },

  '@newBlock' (e) {
    const $button = $(e.currentTarget)
    const blockTypeHandle = $button.attr('data-neo-bn-info')
    const blockType = this._blockTypes.find(bt => bt.getHandle() === blockTypeHandle)

    this.trigger('newBlock', {
      blockType: blockType
    })
  }
})
