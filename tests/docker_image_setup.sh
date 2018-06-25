#!/bin/bash

# explicitly pull docker images to prevent some proxying issues which might rise during CI
docker pull atmoz/sftp
docker pull stilliard/pure-ftpd:hardened
