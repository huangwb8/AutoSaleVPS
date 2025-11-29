module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true
  },
  parser: '@typescript-eslint/parser',
  extends: ['eslint:recommended', 'plugin:@typescript-eslint/recommended'],
  parserOptions: {
    ecmaVersion: 'latest',
    sourceType: 'module'
  },
  ignorePatterns: ['assets'],
  plugins: ['import', 'promise', 'jsdoc'],
  rules: {
    'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    'import/order': ['warn', { 'newlines-between': 'always' }],
    'promise/always-return': 'off',
    'jsdoc/check-alignment': 'warn'
  }
};
