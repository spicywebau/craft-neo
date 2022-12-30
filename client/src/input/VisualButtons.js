import $ from 'jquery'
import Craft from 'craft'
import { BlockSelector, GarnishBlockSelector } from './BlockSelector'

const _defaults = {
  maxBlocks: 0,
  maxTopBlocks: 0,
  blocks: null
}

class VisualButtons extends BlockSelector {
  constructor (settings = {}) {
    settings = Object.assign({}, _defaults, settings)
    super(settings)

    this._maxBlocks = settings.maxBlocks | 0
    this._maxTopBlocks = settings.maxTopBlocks | 0

    this.$container = this._generateButtons()

    const $neo = this.$container.find('[data-neo-bn]')
    this.$buttonsContainer = $neo.filter('[data-neo-bn="container.buttons"]')
    this.$menuContainer = $neo.filter('[data-neo-bn="container.menu"]')
    this.$blockButtons = $neo.filter('[data-neo-bn="button.addBlock"]')
    this.$groupButtons = $neo.filter('[data-neo-bn="button.group"]')

    if (settings.blocks) {
      this.updateState(settings.blocks)
    }
  }

  _generateButtons () {
    const ownerBlockType = this.$ownerContainer?.hasClass('ni_block')
      ? this.$ownerContainer.attr('class').match(/ni_block--([^\s]+)/)[1]
      : null
    const ungroupChildBlockTypes = ownerBlockType !== null &&
      !this._field.getBlockTypeByHandle(ownerBlockType).getGroupChildBlockTypes()
    const buttonsHtml = []
    let currentGroup = null

    buttonsHtml.push(`
        <div class="ni_visualbuttons">
          <div class="btn dashed add icon menubtn" data-neo-bn="container.menu">
            ${Craft.t('neo', 'Add a block')}
          </div>`)

    currentGroup = null
    let lastGroupHadBlockTypes = false
    buttonsHtml.push(`
          <div class="menu ni_visualbuttons_menu" data-neo-bn="container.buttons">`)

    for (const item of this._items) {
      const type = item.getType()

      if (type === 'blockType') {
        // Ignore disabled block types, or block types for which the current user isn't allowed to create blocks
        if (!item.getEnabled() || !item.isCreatableByUser()) {
          continue
        }

        if (!lastGroupHadBlockTypes) {
          lastGroupHadBlockTypes = true

          if (currentGroup !== null) {
            buttonsHtml.push(`
            <h6>${currentGroup.getName()}</h6>`)
          }

          buttonsHtml.push(`
            <ul class="padded">`)
        }

        const titleAttr = item.getDescription() ? ` title="${item.getDescription()}"` : ''
        buttonsHtml.push(`
              <li>
                <a${titleAttr} aria-label="${item.getName()}" data-neo-bn="button.addBlock" ${BlockSelector.BUTTON_INFO}="${item.getHandle()}">
                  ${item.getName()}
                </a>
              </li>`)
      } else if (type === 'group') {
        if (lastGroupHadBlockTypes) {
          buttonsHtml.push(`
            </ul>`)
        }

        lastGroupHadBlockTypes = false
        currentGroup = item.isBlank() || ungroupChildBlockTypes ? null : item
      }
    }

    buttonsHtml.push(`
          </ul>
        </div>
      </div>
    </div>`)

    return $(buttonsHtml.join(''))
  }

  initUi () {
    $('.menubtn', this.$container).menubtn()
    this.updateResponsiveness()

    // If no buttons were rendered (e.g. if all valid block types are disabled for the user), hide the button container
    if (this.$buttonsContainer.find('[data-neo-bn="button.addBlock"]').length === 0) {
      const parent = this.$container.parent()
      const grandParent = parent.parent()
      const childrenContainer = grandParent.children('.ni_blocks')

      if (childrenContainer.length === 0 || childrenContainer.children().length === 0) {
        grandParent.addClass('hidden')
      } else {
        parent.addClass('hidden')
      }
    }
  }

  updateState (blocks = [], additionalCheck = null, block = null) {
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
  }
}

export default GarnishBlockSelector.extend({
  init (settings = {}) {
    this.base(new VisualButtons(settings))
  }
})
