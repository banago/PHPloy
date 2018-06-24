#!/bin/bash
docker pull atmoz/sftp
if [ $? != 0 ]; then
    tmp="/tmp/atmoz-sftp"
    mkdir -p ${tmp}
    wget -O "${tmp}/master.zip" https://github.com/atmoz/sftp/archive/master.zip
    (cd "${tmp}" && unzip master.zip)
    (cd "${tmp}" && docker build .)
fi
