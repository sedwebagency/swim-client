# php from shell is /usr/local/bin/php which supports -r
# php from cron job is /usr/bin/php which does not support -r
/usr/local/bin/php -d allow_url_fopen=1 -r "copy('https://raw.githubusercontent.com/sedwebagency/swim-client/master/swim-client-installer.php', 'swim-client-setup.php');";
/usr/local/bin/php -d allow_url_fopen=1 swim-client-setup.php;
/usr/local/bin/php -d allow_url_fopen=1 -r "unlink('swim-client-setup.php');";
