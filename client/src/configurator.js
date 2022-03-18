import Configurator from './configurator/Configurator'

const context = typeof window !== 'undefined' ? window : this
const configurators = []

context.Neo = {
  Configurator,
  configurators,

  createConfigurator (settings = {}) {
    const configurator = new Configurator(settings)
    configurators.push(configurator)

    return configurator
  }
}
