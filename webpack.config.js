const defaultConfig = require('@wordpress/scripts/config/webpack.config')
const path = require('path')

module.exports = {
  ...defaultConfig,
  entry: {
    index: path.resolve(process.cwd(), 'src/admin/index.tsx'),
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(process.cwd(), 'admin'),
    filename: '[name].js',
  },
}
