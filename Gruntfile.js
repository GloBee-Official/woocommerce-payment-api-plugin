'use strict';
module.exports = function(grunt) {
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        clean: {
            build: [
                'build'
            ],
            dev: {
                src: [
                    '~/www/woocommerce/wp-content/plugins/globee-woocommerce-payment-api/'
                ],
                options: {
                    force: true
                }
            }
        },
        compress: {
            build: {
                options: {
                    archive: 'dist/globee-woocommerce-payment-api-2.0.0.zip'
                },
                files: [
                    {
                        expand: true,
                        cwd: 'build',
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
                        dest: 'build/globee-woocommerce-payment-api'
                    },
                    {
                        expand: true,
                        cwd: 'vendor/globee/payment-api/src/',
                        src: [
                            '**/**.*'
                        ],
                        dest: 'build/globee-woocommerce-payment-api/lib'
                    },
                    {
                        src: 'LICENSE',
                        dest: 'build/globee-woocommerce-payment-api/license.txt'
                    }
                ]
            },
            dev: {
                files: [
                    {
                        expand: true,
                        cwd: 'build/globee-woocommerce-payment-api',
                        src: [
                            '**/**'
                        ],
                        dest: '~/www/woocommerce/wp-content/plugins/globee-woocommerce-payment-api/'
                    }
                ]
            }
        },
        cssmin: {
            build: {
                files: {
                    'build/globee-woocommerce-payment-api/assets/css/style.css': [
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
        'compress:build',
        'clean:build',
    ]);
    grunt.registerTask('dev', [
        'build',
        'clean:dev',
        'copy:dev'
    ]);
    grunt.registerTask('default', 'build');
};

