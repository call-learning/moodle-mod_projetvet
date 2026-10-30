/* jshint node: true, browser: false */
/* eslint-env node */

const path = require('path');

module.exports = grunt => {
    const localLibrary = require(path.join(__dirname, '.grunt', 'library.js'));
    return localLibrary.buildSass(grunt);
};
