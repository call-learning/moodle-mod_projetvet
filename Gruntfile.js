/* eslint-env node */
/* jshint node: true */
/* jshint esversion: 6 */

module.exports = grunt => {
    const path = require('path');
    const originalCwd = process.cwd();
    const moodleRoot = path.resolve(__dirname, '../../');

    try {
        // Se déplacer vers la racine de Moodle
        process.chdir(moodleRoot);

        // Charger le Gruntfile racine depuis le contexte de la racine
        const rootGruntfile = path.join(moodleRoot, 'Gruntfile.js');
        if (grunt.file.exists(rootGruntfile)) {
            require(rootGruntfile)(grunt);
        }

        // Étendre la configuration existante avec des chemins absolus
        grunt.config.merge({
            sass: {
                projetvet: {
                    files: {
                        [path.join(originalCwd, "styles.css")]: path.join(originalCwd, "scss/styles.scss")
                    },
                    options: {
                        implementation: require('sass'),
                        includePaths: [path.join(originalCwd, "scss/")],
                        outputStyle: 'expanded',
                        indentWidth: 4,
                        linefeed: 'lf'
                    }
                }
            }
        });

        // Custom task to run stylelint
        grunt.registerTask('stylelint:projetvet', 'Run stylelint on projetvet CSS', function() {
            const done = this.async();
            const { exec } = require('child_process');

            exec('npx stylelint mod/projetvet/styles.css --fix', { cwd: moodleRoot }, (error, stdout, stderr) => {
                if (stdout) {
                    grunt.log.writeln(stdout);
                }
                if (stderr) {
                    grunt.log.error(stderr);
                }
                if (error && error.code !== 0 && error.code !== 2) {
                    // Code 2 means warnings were found but fixed
                    grunt.log.error('Stylelint failed:', error);
                    done(false);
                } else {
                    grunt.log.ok('Stylelint completed');
                    done();
                }
            });
        });

        // Créer une tâche qui utilise directement la configuration sass
        grunt.registerTask('build:projetvet', ['sass:projetvet', 'stylelint:projetvet']);

    } catch (error) {
        grunt.log.error('Erreur lors du chargement du Gruntfile racine:', error.message);

        // Revenir au répertoire original pour la configuration de base
        process.chdir(originalCwd);

        grunt.loadNpmTasks('grunt-sass');
        grunt.initConfig({
            sass: {
                projetvet: {
                    files: {
                        "styles.css": "scss/styles.scss"
                    },
                    options: {
                        implementation: require('sass'),
                        includePaths: ["scss/"],
                        outputStyle: 'expanded',
                        indentWidth: 4,
                        linefeed: 'lf'
                    }
                }
            }
        });

        grunt.registerTask('default', ['sass:projetvet']);
    }
};
