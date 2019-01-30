#!/bin/bash -e

WORKSPACE="/tmp/PHPloyTestWorkspace"

docker stop sftp_testserver
docker rm sftp_testserver

docker stop ftp_testserver
docker rm ftp_testserver

rm "$WORKSPACE" -rf
