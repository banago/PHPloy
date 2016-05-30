#!/bin/bash -e

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

mkdir -p "/tmp/PHPloyTestWorkspace/share"
docker run \
	--name sftp_testserver \
	-d \
	-v $DIR/workspace/share:/home/sftpuser/share \
	-p 2222:22 atmoz/sftp \
	sftpuser:password:1000
