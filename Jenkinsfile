pipeline {
  agent any
  stages {
    stage('Prepare') {
      steps {
        sh 'rm -rf vendor'
        sh 'composer install'
      }
    }
    stage('PHP Syntax check') {
      steps {
        sh 'vendor/bin/parallel-lint --exclude vendor/ .'
      }
    }
    stage('Checkstyle') {
      steps {
        sh 'vendor/bin/phpcs --standard=codesniffer/ruleset.xml --extensions=php --ignore=lib/php-imap-client --ignore=vendor/ .'
      }
    }
  }
}
