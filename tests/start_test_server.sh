#!/bin/bash -e

mkdir -p "/tmp/PHPloyTestWorkspace/share"
docker run \
	--name sftp_testserver \
	-d \
	-v /tmp/PHPloyTestWorkspace/share:/home/sftpuser/share \
	-p 2222:22 atmoz/sftp \
	sftpuser:password:$(id -u)
