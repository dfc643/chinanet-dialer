#!/bin/bash

# ChinaNet Pinger Script 
# 3 / 16 / 2015 by Norckon

WatchDog() {
	echo -e "\033[5m$(date '+%D %T') Pinger is working\033[0m"
	while [ 1 ]
	do
		# If cannot pingback dial server, change IP address!
		if [ $(ping -c10 -W1 118.249.248.1|grep -c '100% packet loss') -eq 1 ]
		then
			echo -e "(date '+%D %T') Current IP Address is unavailable, Renew IP Address ..."
			sshpass -p 'dfc220HAM_' ssh root@192.168.16.254 'killall -SIGUSR2 udhcpc;sleep 1;killall -SIGUSR1 udhcpc;'
		fi
	done
}

WatchDog;
