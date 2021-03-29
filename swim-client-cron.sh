php -r "copy('https://raw.githubusercontent.com/sedwebagency/swim-client/master/swim-client-installer.php', 'swim-client-setup.php');"
php swim-client-setup.php
php -r "unlink('swim-client-setup.php');"
