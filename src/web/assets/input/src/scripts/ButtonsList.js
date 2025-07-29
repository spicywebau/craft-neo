import $ from 'jquery'
import { v4 as uuidv4 } from 'uuid'
import { NewBlockMenu, GarnishNewBlockMenu } from './NewBlockMenu'

class ButtonsList extends NewBlockMenu {
  /**
   * @inheritdoc
   */
  renderButtons () {
    const field = this.getField()
    const ownerBlockType = this.$ownerContainer?.hasClass('ni_block')
      ? this.$ownerContainer.attr('class').match(/ni_block--([^\s]+)/)[1]
      : null
    const ungroupChildBlockTypes = ownerBlockType !== null &&
      !field.getBlockTypeByHandle(ownerBlockType).getGroupChildBlockTypes()
    const buttonsHtml = []
    const menuId = uuidv4()
    let currentGroup = null

    buttonsHtml.push(`
      <div class="ni_buttons">
        <button aria-controls="${menuId}" class="btn dashed add icon menubtn" data-disclosure-trigger="true" data-neo-bn="container.menu">
          ${field.getNewBlockButtonLabel()}
        </button>`)

    let lastGroupHadBlockTypes = false
    buttonsHtml.push(`
        <div id="${menuId}" class="menu menu--disclosure ni_newblocklist" data-neo-bn="container.buttons">`)

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
        const blockTypeIconId = `fields-ni-icon-${field.getName()}-${item.getHandle()}`
        const hasBlockTypeIcon = this._field?.$container.closest('form').find(`#${blockTypeIconId}`).length > 0 ?? false
        buttonsHtml.push(`
              <li>
                <button${titleAttr} class="menu-item" aria-label="${item.getName()}" data-neo-bn="button.addBlock" ${NewBlockMenu.BUTTON_INFO}="${item.getHandle()}">`)

        if (hasBlockTypeIcon) {
          buttonsHtml.push(`
                  <svg class="ni_newblocklist_icon">
                    <use href="#${blockTypeIconId}"></use>
                  </svg>`)
        } else {
          buttonsHtml.push(`
                  <div class="ni_newblocklist_icon defaulticon">
                  </div>`)
        }

        buttonsHtml.push(`
                  <span>${item.getName()}</span>
                </button>
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

export default GarnishNewBlockMenu.extend({
  init (settings = {}) {
    this.base(new ButtonsList(settings))
  }
})
