#!/bin/sh
cd /home/tmf/x_5001_fz_fs_fun_01
su -s /bin/bash -c "php7.3 -d safe_mode=0 -d allow_url_fopen=on /home/tmf/x_5001_fz_fs_fun_01/aseco.php TMF </dev/null >aseco.log 2>&1 & echo $!" xaseco