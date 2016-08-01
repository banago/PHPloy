#!/bin/bash -e

mkdir -p "/tmp/PHPloyTestWorkspace/sftp_share/share"
mkdir -p "/tmp/PHPloyTestWorkspace/ftp_share/share"

docker run \
	--name sftp_testserver \
	-d \
	-v /tmp/PHPloyTestWorkspace/sftp_share:/home/sftpuser \
	-p 2222:22 atmoz/sftp \
	sftpuser:password:$(id -u)


docker run \
	--name ftp_testserver \
	-d \
	-v /tmp/PHPloyTestWorkspace/ftp_share:/home/ftpusers/ftpuser \
	-v $PWD/ftp_setup.sh:/root/ftp_setup.sh \
	-p 21:21 -p 30000-30009:30000-30009 \
	-e "PUBLICHOST=localhost"  \
	stilliard/pure-ftpd:hardened \
	/root/ftp_setup.sh
