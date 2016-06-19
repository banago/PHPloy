#!/bin/bash -e

docker stop sftp_testserver
docker rm sftp_testserver

docker stop ftp_testserver
docker rm ftp_testserver
