import $ from 'jquery'
import Garnish from 'garnish'

export default Garnish.Base.extend({

  $container: new $(),

  /**
   * @deprecated in 3.8.0
   */
  getSortOrder (_) {
    console.warn('Settings.getSortOrder() is deprecated. Use Item.getSortOrder() instead.')
  },

  /**
   * @deprecated in 3.8.0
   */
  setSortOrder (_) {
    console.warn('Settings.setSortOrder() is deprecated and no longer used.')
  },

  getFocusElement () {
    return new $()
  },

  destroy () {
    this.$foot?.remove()
    this.trigger('destroy')
  },

  _refreshSetting ($container, showSetting, animate) {
    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)

    if (animate) {
      if (showSetting) {
        if ($container.hasClass('hidden')) {
          $container
            .removeClass('hidden')
            .css({
              opacity: 0,
              marginBottom: -($container.outerHeight())
            })
            .velocity({
              opacity: 1,
              marginBottom: 24
            }, 'fast')
        }
      } else if (!$container.hasClass('hidden')) {
        $container
          .css({
            opacity: 1,
            marginBottom: 24
          })
          .velocity({
            opacity: 0,
            marginBottom: -($container.outerHeight())
          }, 'fast', () => {
            $container.addClass('hidden')
          })
      }
    } else {
      $container
        .toggleClass('hidden', !showSetting)
        .css('margin-bottom', showSetting ? 24 : '')
    }
  }
})
