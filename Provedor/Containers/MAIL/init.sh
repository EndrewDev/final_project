#!/bin/sh

# Verifica se os Maildirs já existem no volume, se não, copia do backup interno
if [ ! -d "/home/usuario/Maildir" ]; then
    cp -r /maildata/usuario /home/
fi

if [ ! -d "/home/usuario2/Maildir" ]; then
    cp -r /maildata/usuario2 /home/
fi

chown -R usuario:usuario /home/usuario
chown -R usuario2:usuario2 /home/usuario2

# Iniciar serviços
service syslog-ng start
service dovecot start
service postfix start

# Manter container rodando
tail -F /var/log/mail.log
