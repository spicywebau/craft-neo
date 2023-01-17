import $ from 'jquery'
import Craft from 'craft'
import { BlockSelector, GarnishBlockSelector } from './BlockSelector'

class ButtonsList extends BlockSelector {
  /**
   * @inheritdoc
   */
  renderButtons () {
    const ownerBlockType = this.$ownerContainer?.hasClass('ni_block')
      ? this.$ownerContainer.attr('class').match(/ni_block--([^\s]+)/)[1]
      : null
    const ungroupChildBlockTypes = ownerBlockType !== null &&
      !this.getField().getBlockTypeByHandle(ownerBlockType).getGroupChildBlockTypes()
    const buttonsHtml = []
    let currentGroup = null

    buttonsHtml.push(`
      <div class="ni_buttons">
        <div class="btn dashed add icon menubtn" data-neo-bn="container.menu">
          ${Craft.t('neo', 'Add a block')}
        </div>`)

    let lastGroupHadBlockTypes = false
    buttonsHtml.push(`
        <div class="menu ni_newblocklist" data-neo-bn="container.buttons">`)

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
        const defaultIconId = 'ni-icon'
        const blockTypeIconId = `fields-${defaultIconId}-${this.getField().getName()}-${item.getHandle()}`
        const hasBlockTypeIcon = this.$ownerContainer?.closest('form').find(`#${blockTypeIconId}`).length > 0 ?? false
        buttonsHtml.push(`
              <li>
                <a${titleAttr} class="flex" aria-label="${item.getName()}" data-neo-bn="button.addBlock" ${BlockSelector.BUTTON_INFO}="${item.getHandle()}">
                  <svg class="ni_newblocklist_icon">
                    <use href="#${hasBlockTypeIcon ? blockTypeIconId : defaultIconId}"></use>
                  </svg>
                  <span>${item.getName()}</span>
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
}

export default GarnishBlockSelector.extend({
  init (settings = {}) {
    this.base(new ButtonsList(settings))
  }
})
