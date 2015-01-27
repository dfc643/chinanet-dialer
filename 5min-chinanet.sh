#!/bin/bash

# ChinaNet Dialup Script 
# for network down per 5-mins
# 12 / 1 / 2015 by Norckon

# Username And RSA-Key
USERNAME="18066412406"
RSAENKEY="2b49f1919a19956206a3a29a4ddd5617cd22faeffea9a5147256badd6c200fad015051a4f3275c61fd9b006fa5744a0b098fdffabfde5af7c0072082e59bdf5d49fa212af2649cd66e8effdfd7163e4e535b582c945b10d1f23f02c3f7b2b01e4789736439e628f5feffabf818bf99d180337b0aeb4ddaed9bbe0b734e776c74"

# Commands Settings
PINGCMD=
DIALCMD="http://61.186.95.108/portal4HN/PhoneUserLogin?phoneNumber=$USERNAME&phonePassword=$RSAENKEY&checkCode=&basIp=&checkCode=&intranetIp=&time=$(date '+%s')"

WatchDog() {
	echo -e "\033[5m$(date '+%D %T') 程序已经开始工作\033[0m"
	while [ 1 ]
	do
		# DialUp
		DIALRETURN=$(curl -s --speed-time 1 --speed-limit 1 $DIALCMD)
		if [ $(echo $DIALRETURN|grep -c 0#-1#0#) -eq 0 -a $(echo $DIALRETURN|grep -c 2#-1#0#1) -eq 0 -a $(echo $DIALRETURN|grep -c 1#-1#0#1) -eq 0 -a $(echo $DIALRETURN|wc -L) -gt 0 ]
		then
			echo -en "\033[5m$(date '+%D %T') 本次拨号失败将继续尝试拨号 ...($DIALRETURN)\033[0m\n"
		fi
		# If cannot pingback dial server, change IP address!
		if [ $(ping -c2 -W1 61.186.95.108|grep -c '0 received') -eq 1 ]
		then
			echo -e "\033[46m$(date '+%D %T') 当前 IP 地址失效更换 IP 地址 ...\033[0m"
			sshpass -p 'dfc220HAM_' ssh root@192.168.16.254 'killall -SIGUSR2 udhcpc;sleep 1;killall -SIGUSR1 udhcpc;'
			sleep 3
		fi
	done
}

WatchDog;