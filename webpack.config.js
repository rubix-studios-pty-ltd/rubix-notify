const defaultConfig = require('@wordpress/scripts/config/webpack.config')
const path = require('node:path')
const CopyPlugin = require('copy-webpack-plugin')

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
  plugins: [
    ...defaultConfig.plugins,
    new CopyPlugin({
      patterns: [
        {
          from: '*.css',
          context: path.resolve(process.cwd(), 'src/admin'),
          to: '[name][ext]',
          noErrorOnMissing: true,
        },
      ],
    }),
  ],
}
