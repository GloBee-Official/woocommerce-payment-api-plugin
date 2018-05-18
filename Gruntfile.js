'use strict';
module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        clean: {
            build: [
                'dist'
            ],
            dev: {
                src: [
                    '~/www/woocommerce/wp-content/plugins/globee-woocommerce-1.0.0/'
                ],
                options: {
                    force: true
                }
            }
        },
        compress: {
            build: {
                options: {
                    archive: 'dist/globee-woocommerce-1.0.0.zip'
                },
                files: [
                    {
                        expand: true,
                        cwd: 'dist',
                        src: [
                            '**'
                        ]
                    }
                ]
            }
        },
        copy: {
            build: {
                files: [
                    {
                        expand: true,
                        cwd: 'src/',
                        src: [
                            '**/**.php',
                            'assets/js/**/**.*',
                            'assets/images/**/**.*',
                            'templates/**/**.*'
                        ],
                        dest: 'dist/globee-woocommerce-1.0.0'
                    },
                    {
                        expand: true,
                        cwd: 'vendor/paybee/php-client/src/',
                        src: [
                            '**/**.*'
                        ],
                        dest: 'dist/globee-woocommerce-1.0.0/lib'
                    },
                    {
                        src: 'LICENSE',
                        dest: 'dist/globee-woocommerce-1.0.0/license.txt'
                    }
                ]
            },
            dev: {
                files: [
                    {
                        expand: true,
                        cwd: 'dist/globee-woocommerce-1.0.0',
                        src: [
                            '**/**'
                        ],
                        dest: '~/www/woocommerce/wp-content/plugins/globee-woocommerce-1.0.0/'
                    }
                ]
            }
        },
        cssmin: {
            build: {
                files: {
                    'dist/globee-woocommerce-1.0.0/assets/css/style.css': [
                        'src/assets/css/**.css'
                    ]
                }
            }
        },
        phpcsfixer: {
            build: {
                dir: 'src/'
            },
            options: {
                bin: 'vendor/bin/php-cs-fixer',
                diff: true,
                ignoreExitCode: true,
                level: 'all',
                quiet: true
            }
        },
        watch: {
            scripts: {
                files: [
                    'src/**/**.*'
                ],
                tasks: [
                    'dev'
                ],
                options: {
                    spawn: false,
                    atBegin: true
                },
            },
        },
    });

    // Load the plugins
    grunt.loadNpmTasks('grunt-contrib-clean');
    grunt.loadNpmTasks('grunt-contrib-compress');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-php-cs-fixer');

    // Default task(s).
    grunt.registerTask('build', [
        'phpcsfixer',
        'clean:build',
        'cssmin:build',
        'copy:build',
        'compress:build'
    ]);
    grunt.registerTask('dev', [
        'build',
        'clean:dev',
        'copy:dev'
    ]);
    grunt.registerTask('default', 'build');
};

