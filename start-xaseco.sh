#! /bin/sh
#########################################################################
#
# Autorestarter for XAseco
# ~~~~~~~~~~~~~~~~~~~~~~~~
# To respond as quickly as possible on any necessary changes,
# please use the installation instructions from the website:
#
# http://labs.undef.de/Tools/Aseco-Autorestarter.php
#
# -----------------------------------------------------------------------
# Author:		undef.de
# Version:		1.0.3
# Date:			2013-02-20
# Copyright:		2009 - 2013 by undef.de
# System:		XAseco (all versions)
# -----------------------------------------------------------------------
#
# LICENSE: This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################
#
# BEGIN: CONFIGURE
#
#------------------------------------------------------------------------

USER="xaseco"
BASEPATH="/home/tmf/x_5001_fz_fs_fun_01"
ASECO="x_5001_fz_fs_fun_01"

#
# END: CONFIGURE
#
#########################################################################


# Add $BASEPATH to $PATH
PATH=$BASEPATH:$PATH


# Return the PID from THIS Bash-Script
echo $$


# Check for existing "Logs"
if [ ! -d $BASEPATH/Logs/ ]; then
	mkdir -m 755 $BASEPATH/Logs/
fi


# The main loop
while [ true ]
        do
		if [ -f $BASEPATH/Logs/xaseco.log ]; then
				DATE=`date +"%Y-%m-%d_%H-%M-%S"`
				mv $BASEPATH/Logs/xaseco.log $BASEPATH/Logs/xaseco-$DATE.log
		fi

		if [ -f $BASEPATH/logfile.txt ]; then
				rm $BASEPATH/logfile.txt
		fi

		sleep 5s;

		su -s /bin/bash -c "cd $BASEPATH/; php7.3 -d safe_mode=0 -d allow_url_fopen=on $BASEPATH/aseco.php TMF </dev/null >$BASEPATH/Logs/xaseco.log 2>&1" $USER
	done
