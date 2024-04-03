@echo off
mkdir tmp
curl -o tmp\wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
php tmp\wp-cli.phar i18n make-pot . tmp\woo-voucherly.pot --slug=woo-voucherly --exclude=tmp-svn,tmp-plugin
copy tmp\woo-voucherly.pot .
rmdir /s /q tmp
