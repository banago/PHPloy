#!/bin/bash -e

BASE_DIR=$(cd "$(dirname "$0")" && pwd)
WORKSPACE="/tmp/PHPloyTestWorkspace"

mkdir -p "$WORKSPACE/sftp_share/share"
mkdir -p "$WORKSPACE/ftp_share/share"

docker run \
	--name sftp_testserver \
	-d \
	-v "$WORKSPACE/sftp_share":/home/sftpuser \
	-p 2222:22 atmoz/sftp \
	"sftpuser:password:$(id -u)"

chmod +x "$BASE_DIR/ftp_setup.sh"
docker run \
	--name ftp_testserver \
	-d \
	-v "$WORKSPACE/ftp_share":/home/ftpusers/ftpuser \
	-v "$BASE_DIR/ftp_setup.sh:/root/ftp_setup.sh" \
	-p 21:21 -p 30000-30009:30000-30009 \
	-e "PUBLICHOST=localhost"  \
	stilliard/pure-ftpd:hardened \
	bash /root/ftp_setup.sh
