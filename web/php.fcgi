#!/bin/bash
DEFAULTPHPINI=/home/espr1tn/public_html/action/php72-fcgi.ini
exec /usr/local/php7.2/bin/php-cgi -c ${DEFAULTPHPINI}
