import Configurator from './Configurator'

const context = window ?? this
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
