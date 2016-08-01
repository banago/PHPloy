#!/bin/bash
(echo password; echo password) | pure-pw useradd ftpuser -d /home/ftpusers/ftpuser -u ftpuser
pure-pw mkdb
/usr/sbin/pure-ftpd -c 50 -C 10 -l puredb:/etc/pure-ftpd/pureftpd.pdb -E -j -R -P $PUBLICHOST -p 30000:30009
